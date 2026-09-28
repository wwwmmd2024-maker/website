<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrCommerce;

use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Commerce (Part 3 §8): product CPT + fields, storefront, session cart,
 * checkout with stock control, order management and reports — implemented
 * entirely on core extension points.
 */
final class Plugin extends PluginServiceProvider
{
    private const ORDER_STATUSES = ['pending' => 'در انتظار', 'processing' => 'در حال پردازش', 'shipped' => 'ارسال‌شده', 'completed' => 'تکمیل‌شده', 'cancelled' => 'لغوشده'];
    private const PAYMENT_STATUSES = ['unpaid' => 'پرداخت‌نشده', 'paid' => 'پرداخت‌شده', 'refunded' => 'مستردشده'];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-commerce');

        // 1. Product CPT (content + slug live in core posts).
        $sdk->registerPostType('product', [
            'name' => 'محصول',
            'icon' => '🛒',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'seo', 'fields'],
            'settings' => ['archive' => true, 'single' => true, 'rest_api' => true],
        ]);

        // 2. Product taxonomy.
        $sdk->registerTaxonomy('product-category', [
            'name' => 'دسته‌بندی محصولات',
            'post_types' => ['product'],
            'hierarchical' => true,
        ]);

        // 3. Commerce fields on the product form.
        foreach ([
            ['key' => 'price', 'label' => 'قیمت (تومان)', 'type' => 'number', 'settings' => ['required' => true, 'min' => 0]],
            ['key' => 'sale_price', 'label' => 'قیمت با تخفیف (اختیاری)', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0]],
            ['key' => 'sku', 'label' => 'کد کالا (SKU)', 'type' => 'text', 'settings' => ['required' => false]],
            ['key' => 'stock', 'label' => 'موجودی', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0]],
        ] as $field) {
            $sdk->registerField($field + ['location' => [['param' => 'post_type', 'value' => 'product']]]);
        }

        // 4. Store settings.
        $sdk->registerSettings([
            ['key' => 'store_name', 'type' => 'text', 'label' => 'نام فروشگاه', 'default' => 'فروشگاه'],
            ['key' => 'tax_percent', 'type' => 'number', 'label' => 'مالیات (٪)', 'default' => '0'],
            ['key' => 'shipping_flat', 'type' => 'number', 'label' => 'هزینه ارسال (تومان)', 'default' => '0'],
            ['key' => 'auto_cancel_days', 'type' => 'number', 'label' => 'لغو خودکار سفارش‌های در انتظار (روز)', 'default' => '14'],
        ]);

        // 5. Builder block: product grid.
        $sdk->registerBlock([
            'slug' => 'ir/products',
            'title' => 'ویترین محصولات',
            'category' => 'commerce',
            'icon' => '🛒',
            'description' => 'نمایش آخرین محصولات به‌صورت شبکه‌ای با قیمت و دکمه خرید.',
            'schema' => [
                ['key' => 'count', 'type' => 'number', 'label' => 'تعداد'],
                ['key' => 'columns', 'type' => 'number', 'label' => 'ستون‌ها'],
                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان بخش'],
            ],
            'defaults' => ['count' => 6, 'columns' => 3, 'title' => 'محصولات'],
            'render' => fn (array $data, RenderContext $ctx): string => $this->productGrid(
                max(1, min(24, (int) ($data['count'] ?? 6))),
                max(1, min(4, (int) ($data['columns'] ?? 3))),
                (string) ($data['title'] ?? 'محصولات'),
            ),
        ]);

        // 6. Sidebar widget: mini cart.
        $sdk->registerWidget([
            'slug' => 'ir/cart-summary',
            'title' => 'سبد خرید',
            'description' => 'خلاصه سبد خرید بازدیدکننده.',
            'icon' => '🧺',
            'schema' => [['key' => 'title', 'type' => 'text', 'label' => 'عنوان']],
            'defaults' => ['title' => 'سبد خرید شما'],
            'render' => function (array $data, RenderContext $ctx): string {
                $summary = (new Cart())->summary();

                return '<div class="irj-cart-widget" style="border:1px solid #e3e7f2;border-radius:12px;padding:14px">'
                    . '<strong>' . htmlspecialchars((string) ($data['title'] ?? 'سبد خرید شما'), ENT_QUOTES, 'UTF-8') . '</strong>'
                    . '<p style="margin:8px 0">' . ($summary['count'] > 0
                        ? $summary['count'] . ' کالا — ' . $this->money($summary['subtotal'])
                        : 'سبد خرید خالی است.') . '</p>'
                    . ($summary['count'] > 0 ? '<a href="/checkout" style="color:#0d9488">ادامه خرید ←</a>' : '<a href="/shop" style="color:#0d9488">مشاهده فروشگاه ←</a>')
                    . '</div>';
            },
        ]);

        // 7. Admin: order management.
        $sdk->registerAdminPage('orders', 'سفارش‌ها', fn (Request $request): string => $this->adminOrders($request), '📦');

        // 8. Storefront routes.
        $sdk->registerRoute(['GET'], '/shop', fn (Request $request): Response => $this->shopPage($request), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/cart', fn (Request $request): Response => $this->cartPage(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/cart/add', fn (Request $request): Response => $this->cartAdd($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['POST'], '/cart/update', fn (Request $request): Response => $this->cartUpdate($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/checkout', fn (Request $request): Response => $this->checkoutPage(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/checkout', fn (Request $request): Response => $this->checkoutSubmit($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/order/{order_no}', fn (Request $request): Response => $this->orderPage($request), [StartSession::class]);

        // 9. REST API.
        $sdk->registerApiEndpoint('GET', 'commerce/products', function (): Response {
            $db = Application::get()->make(Database::class);

            return Response::json(['ok' => true, 'data' => $this->products($db, 50)]);
        });
        $sdk->registerApiEndpoint('GET', 'commerce/orders', function (): Response {
            $db = Application::get()->make(Database::class);

            return Response::json(['ok' => true, 'data' => $db->select('SELECT * FROM ir_commerce_orders ORDER BY id DESC LIMIT 100')]);
        });

        // 10. Auto-cancel stale pending orders.
        $sdk->cron('auto_cancel', 'لغو خودکار سفارش‌های قدیمی', 'daily', function (): void {
            try {
                $days = max(1, (int) IRJalali::for('ir-commerce')->setting('auto_cancel_days', '14'));
                Application::get()->make(Database::class)->table('ir_commerce_orders')
                    ->where('order_status', 'pending')
                    ->where('created_at', date('Y-m-d H:i:s', strtotime("-{$days} days")), '<')
                    ->update(['order_status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
            } catch (\Throwable) {
            }
        });

        // 11. CLI report.
        $sdk->command('commerce:orders', 'گزارش سفارش‌ها بر اساس وضعیت', function (array $args, array $options, $out): int {
            $rows = Application::get()->make(Database::class)->select(
                'SELECT order_status, COUNT(*) AS c, SUM(grand_total) AS total FROM ir_commerce_orders GROUP BY order_status'
            );
            foreach ($rows as $row) {
                $out->writeln("{$row['order_status']}: {$row['c']} سفارش — مجموع {$row['total']} تومان");
            }

            return 0;
        });
    }

    // ── Storefront ───────────────────────────────────────────────

    private function shopPage(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;
        $all = $this->products($db, $perPage, ($page - 1) * $perPage);
        $total = (int) $db->value("SELECT COUNT(*) FROM posts WHERE post_type = 'product' AND status = 'published' AND deleted_at IS NULL");

        $cards = '';
        foreach ($all as $p) {
            $cards .= $this->productCard($p);
        }
        $pager = '';
        $pages = max(1, (int) ceil($total / $perPage));
        for ($i = 1; $i <= $pages; $i++) {
            $pager .= $i === $page
                ? "<span style='padding:4px 10px;background:#0d9488;color:#fff;border-radius:8px'>{$i}</span> "
                : "<a href='/shop?page={$i}' style='padding:4px 10px'>{$i}</a> ";
        }

        $body = '<h1 style="margin:0 0 16px">' . e($this->storeName()) . '</h1>'
            . ($all === [] ? '<p>هنوز محصولی منتشر نشده است.</p>'
                : "<div class='grid' style='display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px'>{$cards}</div>"
                  . ($pages > 1 ? "<nav style='margin-top:20px;text-align:center'>{$pager}</nav>" : ''));

        return Response::html($this->shell('فروشگاه', $body));
    }

    private function cartPage(): Response
    {
        $sdk = IRJalali::for('ir-commerce');
        $cart = new Cart();
        $summary = $cart->summary();
        $csrf = Application::get()->make(Csrf::class);

        $rows = '';
        foreach ($summary['items'] as $id => $item) {
            $rows .= '<tr>'
                . '<td>' . e($item['title']) . '</td>'
                . '<td dir="ltr">' . $this->money((int) $item['price']) . '</td>'
                . '<td><form method="post" action="/cart/update" style="display:flex;gap:6px">'
                . $csrf->field() . "<input type='hidden' name='product_id' value='" . (int) $id . "'>"
                . "<input type='number' name='qty' min='0' max='999' value='" . (int) $item['qty'] . "' style='width:70px'>"
                . '<button class="btn">به‌روزرسانی</button></form></td>'
                . '<td dir="ltr">' . $this->money((int) $item['price'] * (int) $item['qty']) . '</td>'
                . '</tr>';
        }

        $tax = (int) round($summary['subtotal'] * max(0, (float) $sdk->setting('tax_percent', '0')) / 100);
        $shipping = $summary['count'] > 0 ? max(0, (int) $sdk->setting('shipping_flat', '0')) : 0;
        $grand = $summary['subtotal'] + $tax + $shipping;

        $body = '<h1>🧺 سبد خرید</h1>';
        if ($summary['count'] === 0) {
            $body .= '<p>سبد خرید شما خالی است. <a href="/shop">بازگشت به فروشگاه</a></p>';
        } else {
            $body .= '<table class="tbl" style="width:100%;border-collapse:collapse">'
                . '<tr><th>کالا</th><th>قیمت</th><th>تعداد</th><th>جمع</th></tr>' . $rows . '</table>'
                . '<div style="margin-top:16px;max-width:360px">'
                . '<p>جمع اقلام: ' . $this->money($summary['subtotal']) . '</p>'
                . '<p>مالیات: ' . $this->money($tax) . '</p>'
                . '<p>هزینه ارسال: ' . $this->money($shipping) . '</p>'
                . '<h3>مبلغ قابل پرداخت: ' . $this->money($grand) . '</h3>'
                . '<a href="/checkout" class="btn" style="display:inline-block;background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none">ادامه فرآیند خرید</a> '
                . '<a href="/shop">بازگشت به فروشگاه</a></div>';
        }

        return Response::html($this->shell('سبد خرید', $body));
    }

    private function cartAdd(Request $request): Response
    {
        $productId = (int) $request->input('product_id', 0);
        $qty = max(1, min(99, (int) $request->input('qty', 1)));
        $db = Application::get()->make(Database::class);
        $product = $this->findProduct($db, $productId);

        if ($product === null) {
            return Response::redirect('/shop');
        }
        $stock = $product['stock'];
        if ($stock !== null && $stock < $qty) {
            return Response::redirect('/shop?error=stock');
        }
        (new Cart())->add($productId, $product['title'], $product['price'], $qty);

        return Response::redirect('/cart');
    }

    private function cartUpdate(Request $request): Response
    {
        $cart = new Cart();
        $productId = (int) $request->input('product_id', 0);
        $qty = (int) $request->input('qty', 0);
        $cart->setQty($productId, $qty);

        return Response::redirect('/cart');
    }

    private function checkoutPage(): Response
    {
        $cart = (new Cart())->summary();
        if ($cart['count'] === 0) {
            return Response::redirect('/shop');
        }
        $sdk = IRJalali::for('ir-commerce');
        $csrf = Application::get()->make(Csrf::class)->field();
        $tax = (int) round($cart['subtotal'] * max(0, (float) $sdk->setting('tax_percent', '0')) / 100);
        $shipping = max(0, (int) $sdk->setting('shipping_flat', '0'));
        $grand = $cart['subtotal'] + $tax + $shipping;

        $items = '';
        foreach ($cart['items'] as $item) {
            $items .= '<li>' . e($item['title']) . ' × ' . (int) $item['qty'] . '</li>';
        }

        $body = '<h1>📦 تکمیل سفارش</h1>'
            . "<ul>{$items}</ul>"
            . '<p>جمع: ' . $this->money($cart['subtotal']) . ' | مالیات: ' . $this->money($tax)
            . ' | ارسال: ' . $this->money($shipping) . ' | <b>قابل پرداخت: ' . $this->money($grand) . '</b></p>'
            . '<form method="post" action="/checkout" style="max-width:480px;display:grid;gap:10px">'
            . $csrf
            . '<label>نام و نام خانوادگی *<input type="text" name="name" required maxlength="120"></label>'
            . '<label>شماره موبایل *<input type="text" name="phone" required dir="ltr" pattern="09[0-9]{9}" placeholder="09xxxxxxxxx"></label>'
            . '<label>ایمیل (اختیاری)<input type="email" name="email" dir="ltr"></label>'
            . '<label>آدرس کامل *<textarea name="address" required rows="3" maxlength="500"></textarea></label>'
            . '<label>یادداشت (اختیاری)<input type="text" name="note" maxlength="500"></label>'
            . '<button class="btn" style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;border:none">ثبت نهایی سفارش</button>'
            . '</form>';

        return Response::html($this->shell('پرداخت', $body));
    }

    private function checkoutSubmit(Request $request): Response
    {
        $cart = new Cart();
        $summary = $cart->summary();
        if ($summary['count'] === 0) {
            return Response::redirect('/shop');
        }

        $name = trim($request->str('name'));
        $phone = trim($request->str('phone'));
        $email = trim($request->str('email'));
        $address = trim($request->str('address'));
        $note = trim($request->str('note'));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120
            || !preg_match('/^09[0-9]{9}$/', $phone)
            || mb_strlen($address) < 10
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        ) {
            return Response::redirect('/checkout?error=validation');
        }

        $db = Application::get()->make(Database::class);
        $sdk = IRJalali::for('ir-commerce');

        // Re-validate stock right before creating the order.
        foreach ($summary['items'] as $productId => $item) {
            $product = $this->findProduct($db, $productId);
            if ($product === null) {
                $cart->remove($productId);

                return Response::redirect('/cart');
            }
            if ($product['stock'] !== null && $product['stock'] < (int) $item['qty']) {
                return Response::redirect('/cart?error=stock');
            }
        }

        $tax = (int) round($summary['subtotal'] * max(0, (float) $sdk->setting('tax_percent', '0')) / 100);
        $shipping = max(0, (int) $sdk->setting('shipping_flat', '0'));
        $grand = $summary['subtotal'] + $tax + $shipping;
        $orderNo = 'IRJ-' . strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');

        $orderId = (int) $db->insert('ir_commerce_orders', [
            'order_no' => $orderNo,
            'customer_name' => mb_substr($name, 0, 120),
            'customer_phone' => mb_substr($phone, 0, 40),
            'customer_email' => $email !== '' ? mb_substr($email, 0, 191) : null,
            'address' => mb_substr($address, 0, 500),
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
            'payment_method' => 'manual',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'items_total' => $summary['subtotal'],
            'shipping_total' => $shipping,
            'grand_total' => $grand,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($summary['items'] as $productId => $item) {
            $db->insert('ir_commerce_order_items', [
                'order_id' => $orderId,
                'product_id' => $productId,
                'title' => mb_substr($item['title'], 0, 255),
                'price' => (int) $item['price'],
                'qty' => (int) $item['qty'],
                'line_total' => (int) $item['price'] * (int) $item['qty'],
            ]);
            $this->decrementStock($db, $productId, (int) $item['qty']);
        }

        $cart->clear();

        // Extension point: notify listeners (e.g. IR-SMTP email) about the new order.
        Application::get()->make(Hooks::class)->doAction('ir_commerce.order_created', $orderId, $orderNo);
        $this->notifyNewOrder($orderNo, $email, $name, $grand);

        return Response::redirect('/order/' . $orderNo);
    }

    private function orderPage(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $order = $db->first('SELECT * FROM ir_commerce_orders WHERE order_no = ?', [$request->route('order_no')]);
        if ($order === null) {
            return Response::html($this->shell('سفارش', '<h1>سفارش پیدا نشد</h1>'), 404);
        }
        $items = $db->select('SELECT * FROM ir_commerce_order_items WHERE order_id = ?', [(int) $order['id']]);
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td>' . e($item['title']) . '</td><td>' . (int) $item['qty'] . '</td><td dir="ltr">' . $this->money((int) $item['line_total']) . '</td></tr>';
        }
        $body = '<h1>✅ سفارش ' . e((string) $order['order_no']) . ' ثبت شد</h1>'
            . '<p>وضعیت: <b>' . e(self::ORDER_STATUSES[$order['order_status']] ?? (string) $order['order_status']) . '</b> — پرداخت: <b>'
            . e(self::PAYMENT_STATUSES[$order['payment_status']] ?? (string) $order['payment_status']) . '</b></p>'
            . '<p>برای هماهنگی پرداخت با شما تماس گرفته خواهد شد.</p>'
            . '<table class="tbl" style="width:100%;border-collapse:collapse"><tr><th>کالا</th><th>تعداد</th><th>جمع</th></tr>' . $rows . '</table>'
            . '<h3 style="margin-top:14px">مبلغ کل: ' . $this->money((int) $order['grand_total']) . '</h3>'
            . '<p><a href="/shop">بازگشت به فروشگاه</a></p>';

        return Response::html($this->shell('سفارش ' . $order['order_no'], $body));
    }

    // ── Admin ────────────────────────────────────────────────────

    private function adminOrders(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST')) {
            $orderId = (int) $request->input('order_id', 0);
            $order = $orderId > 0 ? $db->first('SELECT * FROM ir_commerce_orders WHERE id = ?', [$orderId]) : null;
            if ($order !== null) {
                $newStatus = (string) $request->input('order_status', '');
                $newPayment = (string) $request->input('payment_status', '');
                if (isset(self::ORDER_STATUSES[$newStatus]) || isset(self::PAYMENT_STATUSES[$newPayment])) {
                    $update = ['updated_at' => date('Y-m-d H:i:s')];
                    if (isset(self::ORDER_STATUSES[$newStatus])) {
                        $update['order_status'] = $newStatus;
                    }
                    if (isset(self::PAYMENT_STATUSES[$newPayment])) {
                        $update['payment_status'] = $newPayment;
                    }
                    $db->table('ir_commerce_orders')->where('id', $orderId)->update($update);
                    $message = 'سفارش به‌روزرسانی شد.';
                }
            }
        }

        $statusFilter = $request->str('status');
        $orders = $statusFilter !== '' && isset(self::ORDER_STATUSES[$statusFilter])
            ? $db->select('SELECT * FROM ir_commerce_orders WHERE order_status = ? ORDER BY id DESC LIMIT 100', [$statusFilter])
            : $db->select('SELECT * FROM ir_commerce_orders ORDER BY id DESC LIMIT 100');

        $revenue = (int) $db->value("SELECT COALESCE(SUM(grand_total), 0) FROM ir_commerce_orders WHERE payment_status = 'paid'");
        $pending = (int) $db->value("SELECT COUNT(*) FROM ir_commerce_orders WHERE order_status = 'pending'");

        ob_start(); ?>
        <h2 style="margin-top:0">📦 سفارش‌ها</h2>
        <div class="toolbar" style="margin-bottom:12px">
          <span class="badge green">درآمد پرداخت‌شده: <?= $this->money($revenue) ?></span>
          <span class="badge">در انتظار: <?= $pending ?></span>
          <a class="btn small" href="/admin/plugin/ir-commerce--orders">همه</a>
          <?php foreach (self::ORDER_STATUSES as $key => $label): ?>
            <a class="btn small" href="/admin/plugin/ir-commerce--orders?status=<?= e($key) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>
        <?php if ($orders === []): ?>
          <p class="muted">سفارشی ثبت نشده است.</p>
        <?php else: ?>
          <table class="tbl">
            <tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>وضعیت سفارش</th><th>پرداخت</th><th>تاریخ</th><th>به‌روزرسانی</th></tr>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td dir="ltr"><?= e($o['order_no']) ?><br><a href="/order/<?= e($o['order_no']) ?>" target="_blank" style="font-size:11px">مشاهده</a></td>
                <td><?= e($o['customer_name']) ?><br><span class="muted" dir="ltr" style="font-size:11px"><?= e($o['customer_phone']) ?></span></td>
                <td dir="ltr"><?= $this->money((int) $o['grand_total']) ?></td>
                <td><span class="badge <?= $o['order_status'] === 'completed' ? 'green' : ($o['order_status'] === 'cancelled' ? 'red' : '') ?>"><?= e(self::ORDER_STATUSES[$o['order_status']] ?? (string) $o['order_status']) ?></span></td>
                <td><span class="badge <?= $o['payment_status'] === 'paid' ? 'green' : '' ?>"><?= e(self::PAYMENT_STATUSES[$o['payment_status']] ?? (string) $o['payment_status']) ?></span></td>
                <td class="muted" style="font-size:11px"><?= e($o['created_at']) ?></td>
                <td>
                  <form method="post" action="/admin/plugin/ir-commerce--orders" style="display:flex;gap:4px;flex-wrap:wrap">
                    <?= $csrf ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                    <select name="order_status">
                      <?php foreach (self::ORDER_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $o['order_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="payment_status">
                      <?php foreach (self::PAYMENT_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $o['payment_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn small" type="submit">ثبت</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif;
        return (string) ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** @return list<array{id:int,title:string,slug:string,price:int,sale_price:int,stock:?int,excerpt:string}> */
    private function products(Database $db, int $limit = 12, int $offset = 0): array
    {
        $rows = $db->select(
            'SELECT id, title, slug, excerpt FROM posts
             WHERE post_type = \'product\' AND status = \'published\' AND deleted_at IS NULL
             ORDER BY published_at DESC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $out = [];
        foreach ($rows as $row) {
            $meta = $this->meta($db, (int) $row['id']);
            $price = (int) ($meta['price'] ?? 0);
            $sale = isset($meta['sale_price']) && $meta['sale_price'] !== '' ? (int) $meta['sale_price'] : 0;
            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'price' => $price,
                'sale_price' => $sale > 0 && $sale < $price ? $sale : 0,
                'stock' => isset($meta['stock']) && $meta['stock'] !== '' ? (int) $meta['stock'] : null,
                'excerpt' => (string) ($row['excerpt'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return array{id:int,title:string,price:int,stock:?int}|null */
    private function findProduct(Database $db, int $id): ?array
    {
        $row = $db->first(
            "SELECT id, title FROM posts WHERE id = ? AND post_type = 'product' AND status = 'published' AND deleted_at IS NULL",
            [$id]
        );
        if ($row === null) {
            return null;
        }
        $meta = $this->meta($db, $id);
        $price = (int) ($meta['price'] ?? 0);
        $sale = isset($meta['sale_price']) && $meta['sale_price'] !== '' ? (int) $meta['sale_price'] : 0;

        return [
            'id' => $id,
            'title' => (string) $row['title'],
            'price' => $sale > 0 && $sale < $price ? $sale : $price,
            'stock' => isset($meta['stock']) && $meta['stock'] !== '' ? (int) $meta['stock'] : null,
        ];
    }

    /** @return array<string, string> */
    private function meta(Database $db, int $postId): array
    {
        $rows = $db->select('SELECT `key`, `value` FROM post_meta WHERE post_id = ?', [$postId]);
        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row['key'];
            if (str_starts_with($key, 'field:')) {
                $out[substr($key, 6)] = (string) $row['value'];
            }
        }

        return $out;
    }

    private function decrementStock(Database $db, int $productId, int $qty): void
    {
        $meta = $db->first("SELECT id, `value` FROM post_meta WHERE post_id = ? AND `key` = 'field:stock'", [$productId]);
        if ($meta === null || !is_numeric($meta['value'])) {
            return;
        }
        $new = max(0, (int) $meta['value'] - $qty);
        $db->table('post_meta')->where('id', (int) $meta['id'])->update(['value' => (string) $new]);
    }

    private function productGrid(int $count, int $columns, string $title): string
    {
        $db = Application::get()->make(Database::class);
        $products = $this->products($db, $count);
        if ($products === []) {
            return '';
        }
        $cards = '';
        foreach ($products as $p) {
            $cards .= $this->productCard($p);
        }

        return '<section class="irj-products">'
            . ($title !== '' ? '<h2 style="margin:8px 0 14px">' . e($title) . '</h2>' : '')
            . '<div style="display:grid;grid-template-columns:repeat(' . max(1, $columns) . ',1fr);gap:16px">' . $cards . '</div>'
            . '</section>';
    }

    /** @param array{id:int,title:string,slug:string,price:int,sale_price:int,stock:?int,excerpt:string} $p */
    private function productCard(array $p): string
    {
        $csrf = '';
        try {
            $csrf = Application::get()->make(Csrf::class)->field();
        } catch (\Throwable) {
        }
        $outOfStock = $p['stock'] !== null && $p['stock'] <= 0;
        $priceHtml = $p['sale_price'] > 0
            ? '<del style="color:#999">' . $this->money($p['price']) . '</del> <b>' . $this->money($p['sale_price']) . '</b>'
            : '<b>' . $this->money($p['price']) . '</b>';

        return '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:8px">'
            . '<h3 style="margin:0"><a href="/' . e($p['slug']) . '" style="text-decoration:none;color:inherit">' . e($p['title']) . '</a></h3>'
            . ($p['excerpt'] !== '' ? '<p style="margin:0;color:#7a84a6;font-size:13px">' . e(mb_substr($p['excerpt'], 0, 90)) . '</p>' : '')
            . '<div>' . $priceHtml . '</div>'
            . ($outOfStock
                ? '<span class="badge red">ناموجود</span>'
                : '<form method="post" action="/cart/add" style="display:flex;gap:6px">'
                  . $csrf . '<input type="hidden" name="product_id" value="' . (int) $p['id'] . '">'
                  . '<input type="number" name="qty" value="1" min="1" max="99" style="width:60px">'
                  . '<button class="btn small" type="submit" style="background:#0d9488;color:#fff;border:none;border-radius:8px;padding:6px 14px;cursor:pointer">افزودن به سبد</button></form>')
            . '</article>';
    }

    private function notifyNewOrder(string $orderNo, string $email, string $name, int $grand): void
    {
        try {
            // Only when IR-SMTP is installed and active; never fatal otherwise.
            $db = Application::get()->make(Database::class);
            $active = $db->first("SELECT slug FROM plugins WHERE slug = 'ir-smtp' AND status = 'active'");
            if ($active === null || $email === '') {
                return;
            }
            \IRJalali\Plugins\IrSmtp\Plugin::mailer()->queueTemplate(
                $email,
                'ثبت سفارش ' . $orderNo,
                "<p>سلام {{name}} عزیز،</p><p>سفارش شما با شماره <b>{{order}}</b> به مبلغ {{total}} تومان ثبت شد و در انتظار پرداخت است.</p>",
                ['name' => $name, 'order' => $orderNo, 'total' => number_format($grand)]
            );
        } catch (\Throwable) {
        }
    }

    private function money(int $amount): string
    {
        return number_format($amount) . ' تومان';
    }

    private function storeName(): string
    {
        try {
            return (string) IRJalali::for('ir-commerce')->setting('store_name', 'فروشگاه');
        } catch (\Throwable) {
            return 'فروشگاه';
        }
    }

    private function shell(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($title) . ' — ' . e($this->storeName()) . '</title>'
            . '<style>body{font-family:Tahoma,"Segoe UI",sans-serif;margin:0;background:#f6f8fc;color:#1f2540}'
            . 'main{max-width:1000px;margin:0 auto;padding:24px}'
            . 'a{color:#0d9488}input,textarea,select{width:100%;padding:8px;border:1px solid #d6dcea;border-radius:8px;box-sizing:border-box}'
            . 'label{font-size:13px;display:grid;gap:4px}.btn{border:none;border-radius:8px;padding:6px 14px;cursor:pointer;background:#e6ebf7}'
            . '.tbl td,.tbl th{border:1px solid #e3e7f2;padding:8px;text-align:right}.badge{display:inline-block;padding:2px 10px;border-radius:99px;background:#e6ebf7;font-size:12px}'
            . '.badge.green{background:#d1fae5;color:#065f46}.badge.red{background:#fee2e2;color:#991b1b}.muted{color:#7a84a6}'
            . 'header{background:#fff;border-bottom:1px solid #e3e7f2;padding:12px 24px;display:flex;gap:16px;align-items:center}'
            . 'header a{text-decoration:none;font-weight:700}</style></head><body>'
            . '<header><a href="/shop">🛒 ' . e($this->storeName()) . '</a><a href="/cart">سبد خرید</a><a href="/">بازگشت به سایت</a></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
