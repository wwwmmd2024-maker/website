<?php

declare(strict_types=1);

namespace IRJalali\Core\Validation;

/**
 * Rule-based validator. Rules: required|email|min:3|max:50|url|numeric|
 * integer|in:a,b|same:field|regex:/.../|mobile|slug
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {
    }

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $this->apply($field, $value, trim($rule));
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    private function apply(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $str = is_scalar($value) ? (string) $value : '';

        $fail = function (string $message) use ($field): void {
            $this->errors[$field][] = $message;
        };

        switch ($name) {
            case 'required':
                if ($value === null || $str === '') {
                    $fail("فیلد {$field} الزامی است.");
                }
                break;
            case 'email':
                if ($str !== '' && filter_var($str, FILTER_VALIDATE_EMAIL) === false) {
                    $fail("فیلد {$field} باید ایمیل معتبر باشد.");
                }
                break;
            case 'url':
                if ($str !== '' && filter_var($str, FILTER_VALIDATE_URL) === false) {
                    $fail("فیلد {$field} باید آدرس معتبر باشد.");
                }
                break;
            case 'numeric':
                if ($str !== '' && !is_numeric($str)) {
                    $fail("فیلد {$field} باید عدد باشد.");
                }
                break;
            case 'integer':
                if ($str !== '' && filter_var($str, FILTER_VALIDATE_INT) === false) {
                    $fail("فیلد {$field} باید عدد صحیح باشد.");
                }
                break;
            case 'min':
                if (mb_strlen($str) < (int) $param) {
                    $fail("فیلد {$field} حداقل {$param} کاراکتر باشد.");
                }
                break;
            case 'max':
                if (mb_strlen($str) > (int) $param) {
                    $fail("فیلد {$field} حداکثر {$param} کاراکتر باشد.");
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array($str, $allowed, true)) {
                    $fail("مقدار فیلد {$field} نامعتبر است.");
                }
                break;
            case 'same':
                if ($str !== (string) ($this->data[$param ?? ''] ?? '')) {
                    $fail("تکرار فیلد {$field} مطابقت ندارد.");
                }
                break;
            case 'regex':
                if ($str !== '' && @preg_match((string) $param, $str) !== 1) {
                    $fail("قالب فیلد {$field} نامعتبر است.");
                }
                break;
            case 'mobile':
                $normalized = str_replace([' ', '-', '+'], '', $str);
                if ($str !== '' && !preg_match('/^(98|0)?9\d{9}$/', $normalized)) {
                    $fail("شماره موبایل نامعتبر است.");
                }
                break;
            case 'slug':
                if ($str !== '' && !preg_match('/^[\p{L}\p{N}\-]+$/u', $str)) {
                    $fail("نامک (slug) نامعتبر است.");
                }
                break;
        }
    }
}
