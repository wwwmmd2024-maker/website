<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Repositories\FormRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\RateLimiter;
use IRJalali\Core\View\View;

/**
 * Public form endpoints rendered by the builder (contact preset, custom forms, newsletter).
 */
final class PublicFormController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly FormRepository $forms,
        private readonly RateLimiter $limiter,
    ) {
        parent::__construct($view, $auth);
    }

    public function submit(Request $request): Response
    {
        $key = 'form:' . $request->ip();
        if ($this->limiter->tooManyAttempts($key, 10)) {
            $this->withFlash('error', 'تعداد تلاش‌ها بیش از حد مجاز است؛ لطفاً کمی بعد تلاش کنید.');

            return $this->backTo($request);
        }
        $this->limiter->hit($key, 300);

        $formId = (int) $request->input('form_id', 0);
        if ($formId > 0) {
            $form = $this->forms->findForm($formId);
            if ($form === null || empty($form['is_active'])) {
                return $this->fail($request, 'فرم موردنظر یافت نشد یا غیرفعال است.');
            }
        } elseif ($request->str('preset') === 'contact') {
            $form = $this->forms->ensureContactForm();
        } else {
            return $this->fail($request, 'فرم نامعتبر است.');
        }

        $fields = $this->forms->fields((int) $form['id']);
        $input = $request->input('fields', []);
        if (!is_array($input)) {
            $input = [];
        }
        // Contact preset posts flat fields (name/email/message); normalize them.
        if ($formId === 0) {
            $input = [
                'name' => $request->str('name'),
                'email' => $request->str('email'),
                'message' => $request->str('message'),
            ];
        }
        [$data, $error] = $this->validateSubmission($fields, $input);
        if ($error !== null) {
            return $this->fail($request, $error);
        }

        $this->forms->storeSubmission((int) $form['id'], $data, $request->ip(), $request->userAgent());
        $this->withFlash('success', 'پیام شما با موفقیت ثبت شد. سپاس!');

        return $this->backTo($request);
    }

    public function newsletter(Request $request): Response
    {
        $key = 'newsletter:' . $request->ip();
        if ($this->limiter->tooManyAttempts($key, 10)) {
            $this->withFlash('error', 'تعداد تلاش‌ها بیش از حد مجاز است؛ لطفاً کمی بعد تلاش کنید.');

            return $this->backTo($request);
        }
        $this->limiter->hit($key, 300);

        $email = mb_substr(trim($request->str('email')), 0, 190);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($request, 'نشانی ایمیل معتبر نیست.');
        }
        $name = mb_substr(trim($request->str('name')), 0, 120) ?: null;
        $this->forms->subscribeNewsletter($email, $name, $request->ip());
        $this->withFlash('success', 'عضویت شما در خبرنامه ثبت شد.');

        return $this->backTo($request);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $input
     * @return array{0: array<string, string>, 1: string|null}
     */
    private function validateSubmission(array $fields, array $input): array
    {
        $data = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $label = (string) ($field['label'] ?? $key);
            $type = (string) $field['type'];
            $settings = json_decode((string) ($field['settings'] ?? '{}'), true) ?: [];
            $required = !empty($settings['required']);
            $raw = $input[$key] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($type === 'checkbox') {
                $data[$key] = $value !== '' ? '1' : '0';
                if ($required && $value === '') {
                    return [[], 'فیلد «' . $label . '» الزامی است.'];
                }
                continue;
            }
            if ($value === '') {
                if ($required) {
                    return [[], 'فیلد «' . $label . '» الزامی است.'];
                }
                $data[$key] = '';

                continue;
            }
            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return [[], 'فیلد «' . $label . '» ایمیل معتبر نیست.'];
            }
            if ($type === 'number' && !is_numeric($value)) {
                return [[], 'فیلد «' . $label . '» باید عدد باشد.'];
            }
            if ($type === 'select') {
                $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
                if (!in_array($value, array_map('strval', $options), true)) {
                    return [[], 'گزینه فیلد «' . $label . '» معتبر نیست.'];
                }
            }
            $data[$key] = mb_substr($value, 0, $type === 'textarea' ? 2000 : 500);
        }

        return [$data, null];
    }

    private function fail(Request $request, string $message): Response
    {
        $this->withFlash('error', $message);

        return $this->backTo($request);
    }

    private function backTo(Request $request): Response
    {
        $referer = $request->header('Referer');

        return Response::redirect($referer !== '' ? $referer : '/');
    }
}
