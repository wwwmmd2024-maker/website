<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrCommerce;

/**
 * Session-backed shopping cart. Item shape:
 *   [productId => ['qty' => int, 'price' => int]]
 * Prices are captured at add-time so later edits don't change a cart.
 */
final class Cart
{
    private const SESSION_KEY = 'irj_cart';

    /** @return array<int, array{qty: int, price: int, title: string}> */
    public function items(): array
    {
        $raw = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($raw) ? $raw : [];
    }

    public function add(int $productId, string $title, int $price, int $qty = 1): void
    {
        $items = $this->items();
        $qty = max(1, $qty);
        if (isset($items[$productId])) {
            $items[$productId]['qty'] = min(999, $items[$productId]['qty'] + $qty);
            $items[$productId]['price'] = $price;
            $items[$productId]['title'] = $title;
        } else {
            $items[$productId] = ['qty' => min(999, $qty), 'price' => max(0, $price), 'title' => $title];
        }
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function setQty(int $productId, int $qty): void
    {
        $items = $this->items();
        if ($qty <= 0) {
            unset($items[$productId]);
        } elseif (isset($items[$productId])) {
            $items[$productId]['qty'] = min(999, $qty);
        }
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function remove(int $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $_SESSION[self::SESSION_KEY] = $items;
    }

    public function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }

    /** @return array{items: array, subtotal: int, count: int} */
    public function summary(): array
    {
        $items = $this->items();
        $subtotal = 0;
        $count = 0;
        foreach ($items as $item) {
            $subtotal += (int) $item['price'] * (int) $item['qty'];
            $count += (int) $item['qty'];
        }

        return ['items' => $items, 'subtotal' => $subtotal, 'count' => $count];
    }
}
