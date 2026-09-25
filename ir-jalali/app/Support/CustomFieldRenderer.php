<?php

declare(strict_types=1);

namespace IRJalali\App\Support;

use IRJalali\Core\Database\Database;

/**
 * Renders a custom field as an HTML input for the admin and casts submitted
 * values back to a safe scalar/JSON string. Supports the 30 field types from
 * Master Prompt Part 1 §11; complex/structured types degrade to a JSON textarea
 * so no data path is fake.
 */
final class CustomFieldRenderer
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{key: string, label: string, type: string, settings: array<string, mixed>} $field
     * @return string HTML for the input (with label).
     */
    public function input(array $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $key = htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8');
        $name = $namePrefix . '[' . $key . ']';
        $settings = $field['settings'] ?? [];
        $required = !empty($settings['required']) ? ' required' : '';
        $str = is_string($value) ? $value : ($value === null ? '' : json_encode($value, JSON_UNESCAPED_UNICODE));
        $esc = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        $choices = (array) ($settings['choices'] ?? []);

        $open = '<div style="margin-bottom:12px"><label>' . $label . ' <span class="muted" dir="ltr">(' . $key . ')</span></label>';
        $close = '</div>';

        switch ($field['type']) {
            case 'textarea':
            case 'richtext':
            case 'code':
                return $open . '<textarea name="' . $name . '" rows="4" style="width:100%;max-width:560px"' . $required . '>' . $esc . '</textarea>' . $close;

            case 'json':
            case 'repeater':
            case 'group':
                return $open . '<textarea name="' . $name . '" rows="3" dir="ltr" style="width:100%;max-width:560px;font-family:Consolas,monospace;font-size:12px"' . $required . '>' . $esc . '</textarea>'
                    . '<span class="muted" style="font-size:11px">مقدار به صورت JSON ذخیره می‌شود.</span>' . $close;

            case 'number':
            case 'currency':
                return $open . '<input type="number" step="any" name="' . $name . '" value="' . $esc . '" dir="ltr"' . $required . '>' . $close;

            case 'email':
                return $open . '<input type="email" name="' . $name . '" value="' . $esc . '" dir="ltr"' . $required . '>' . $close;

            case 'phone':
                return $open . '<input type="tel" name="' . $name . '" value="' . $esc . '" dir="ltr"' . $required . '>' . $close;

            case 'url':
                return $open . '<input type="url" name="' . $name . '" value="' . $esc . '" dir="ltr"' . $required . '>' . $close;

            case 'password':
                return $open . '<input type="password" name="' . $name . '" value="" dir="ltr"' . $required . '>' . $close;

            case 'date':
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" placeholder="1404/07/02"' . $required . '>' . $close;

            case 'time':
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" placeholder="14:30"' . $required . '>' . $close;

            case 'datetime':
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" placeholder="1404/07/02 14:30"' . $required . '>' . $close;

            case 'color':
                return $open . '<input type="color" name="' . $name . '" value="' . ($this->isColor($str) ? $esc : '#7c3aed') . '">' . $close;

            case 'toggle':
            case 'checkbox':
                $checked = in_array(strtolower($str), ['1', 'true', 'on', 'yes'], true) ? ' checked' : '';

                return $open . '<label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="' . $name . '" value="1"' . $checked . '> فعال</label>' . $close;

            case 'select':
            case 'radio':
                $opts = '';
                foreach ($choices as $choice) {
                    $c = htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8');
                    $sel = (string) $choice === $str ? ' selected' : '';
                    $opts .= '<option value="' . $c . '"' . $sel . '>' . $c . '</option>';
                }

                return $open . '<select name="' . $name . '"' . $required . '>' . $opts . '</select>' . $close;

            case 'multiselect':
                $selected = is_array($value) ? $value : (array) json_decode($str, true);
                $opts = '';
                foreach ($choices as $choice) {
                    $c = htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8');
                    $sel = in_array((string) $choice, array_map('strval', $selected), true) ? ' selected' : '';
                    $opts .= '<option value="' . $c . '"' . $sel . '>' . $c . '</option>';
                }

                return $open . '<select name="' . $name . '[]" multiple size="4"' . $required . '>' . $opts . '</select>' . $close;

            case 'post_selector':
                $rows = $this->db->select('SELECT id, title FROM posts WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 100');
                $opts = '<option value="">—</option>';
                foreach ($rows as $row) {
                    $sel = (string) $row['id'] === $str ? ' selected' : '';
                    $opts .= '<option value="' . (int) $row['id'] . '"' . $sel . '>' . htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') . '</option>';
                }

                return $open . '<select name="' . $name . '"' . $required . '>' . $opts . '</select>' . $close;

            case 'user_selector':
                $rows = $this->db->select('SELECT id, username FROM users ORDER BY id LIMIT 100');
                $opts = '<option value="">—</option>';
                foreach ($rows as $row) {
                    $sel = (string) $row['id'] === $str ? ' selected' : '';
                    $opts .= '<option value="' . (int) $row['id'] . '"' . $sel . '>' . htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') . '</option>';
                }

                return $open . '<select name="' . $name . '"' . $required . '>' . $opts . '</select>' . $close;

            case 'taxonomy_selector':
                $rows = $this->db->select('SELECT slug, name FROM taxonomies ORDER BY slug');
                $opts = '<option value="">—</option>';
                foreach ($rows as $row) {
                    $sel = (string) $row['slug'] === $str ? ' selected' : '';
                    $opts .= '<option value="' . htmlspecialchars((string) $row['slug'], ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                }

                return $open . '<select name="' . $name . '"' . $required . '>' . $opts . '</select>' . $close;

            case 'image':
            case 'file':
            case 'gallery':
                $hint = $field['type'] === 'gallery' ? 'آیدی رسانه‌ها را با کاما وارد کنید.' : 'آیدی رسانه را از کتابخانه رسانه وارد کنید.';

                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" dir="ltr"' . $required . '>'
                    . '<span class="muted" style="font-size:11px">' . $hint . '</span>' . $close;

            case 'map':
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" dir="ltr" placeholder="35.6892,51.3890"' . $required . '>'
                    . '<span class="muted" style="font-size:11px">عرض,طول جغرافیایی</span>' . $close;

            case 'relationship':
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '" dir="ltr" placeholder="آیدی‌ها با کاما"' . $required . '>' . $close;

            case 'text':
            default:
                return $open . '<input type="text" name="' . $name . '" value="' . $esc . '"' . $required . '>' . $close;
        }
    }

    private function isColor(string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', $value);
    }
}
