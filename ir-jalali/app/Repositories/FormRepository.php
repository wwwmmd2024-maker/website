<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\Core\Database\Database;

/**
 * Forms, form fields, submissions and newsletter subscribers.
 */
final class FormRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listForms(): array
    {
        return $this->db->table('forms')->orderBy('id', 'DESC')->get();
    }

    /** @return array<string, mixed>|null */
    public function findForm(int $id): ?array
    {
        return $this->db->table('forms')->where('id', $id)->first();
    }

    public function findFormBySlug(string $slug): ?array
    {
        return $this->db->table('forms')->where('slug', $slug)->first();
    }

    /** @param array<string, mixed> $data */
    public function createForm(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('forms', [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateForm(int $id, array $data): bool
    {
        $allowed = ['title', 'slug', 'description', 'settings', 'is_active'];
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $update[$column] = $column === 'is_active' ? (!empty($data[$column]) ? 1 : 0) : $data[$column];
            }
        }

        return $this->db->table('forms')->where('id', $id)->update($update) > 0;
    }

    public function deleteForm(int $id): bool
    {
        $this->db->table('form_submissions')->where('form_id', $id)->delete();
        $this->db->table('form_fields')->where('form_id', $id)->delete();

        return $this->db->table('forms')->where('id', $id)->delete() > 0;
    }

    public function slugTaken(string $slug, ?int $ignoreId = null): bool
    {
        foreach ($this->db->table('forms')->where('slug', $slug)->get() as $row) {
            if ($ignoreId === null || (int) $row['id'] !== $ignoreId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function fields(int $formId): array
    {
        return $this->db->table('form_fields')
            ->where('form_id', $formId)
            ->orderBy('ordering')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed>|null */
    public function findField(int $id): ?array
    {
        return $this->db->table('form_fields')->where('id', $id)->first();
    }

    /** @param array<string, mixed> $data */
    public function createField(int $formId, array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('form_fields', [
            'form_id' => $formId,
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'settings' => $data['settings'] ?? null,
            'ordering' => $data['ordering'] ?? $this->nextFieldOrdering($formId),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateField(int $id, array $data): bool
    {
        $allowed = ['key', 'label', 'type', 'settings', 'ordering'];
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $update[$column] = $data[$column];
            }
        }

        return $this->db->table('form_fields')->where('id', $id)->update($update) > 0;
    }

    public function deleteField(int $id): bool
    {
        return $this->db->table('form_fields')->where('id', $id)->delete() > 0;
    }

    public function nextFieldOrdering(int $formId): int
    {
        $row = $this->db->select(
            'SELECT COALESCE(MAX(ordering), 0) AS m FROM form_fields WHERE form_id = :id',
            ['id' => $formId]
        );

        return ((int) ($row[0]['m'] ?? 0)) + 1;
    }

    /** @param array<string, mixed> $data */
    public function storeSubmission(int $formId, array $data, ?string $ip, ?string $userAgent): int
    {
        return (int) $this->db->insert('form_submissions', [
            'form_id' => $formId,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function submissions(int $formId, int $limit, int $offset): array
    {
        return $this->db->table('form_submissions')
            ->where('form_id', $formId)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countSubmissions(int $formId): int
    {
        return $this->db->table('form_submissions')->where('form_id', $formId)->count();
    }

    public function countUnread(int $formId): int
    {
        return $this->db->table('form_submissions')->where('form_id', $formId)->where('is_read', 0)->count();
    }

    /** @return array<string, mixed>|null */
    public function findSubmission(int $id): ?array
    {
        return $this->db->table('form_submissions')->where('id', $id)->first();
    }

    public function markSubmissionRead(int $id): void
    {
        $this->db->table('form_submissions')->where('id', $id)->update(['is_read' => 1]);
    }

    public function deleteSubmission(int $id): bool
    {
        return $this->db->table('form_submissions')->where('id', $id)->delete() > 0;
    }

    /**
     * Ensures the built-in contact form (used by the contact preset) exists.
     *
     * @return array<string, mixed>
     */
    public function ensureContactForm(): array
    {
        $form = $this->findFormBySlug('contact');
        if ($form !== null) {
            return $form;
        }
        $id = $this->createForm([
            'title' => 'فرم تماس',
            'slug' => 'contact',
            'description' => 'فرم تماس پیش‌فرض سایت',
            'is_active' => 1,
        ]);
        foreach ([
            ['key' => 'name', 'label' => 'نام', 'type' => 'text', 'settings' => ['required' => true]],
            ['key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'settings' => ['required' => true]],
            ['key' => 'message', 'label' => 'پیام', 'type' => 'textarea', 'settings' => ['required' => true]],
        ] as $index => $field) {
            $this->createField($id, [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'settings' => json_encode($field['settings'], JSON_UNESCAPED_UNICODE),
                'ordering' => $index + 1,
            ]);
        }
        $created = $this->findForm($id);
        if ($created === null) {
            throw new \RuntimeException('Contact form creation failed.');
        }

        return $created;
    }

    public function subscribeNewsletter(string $email, ?string $name, ?string $ip): bool
    {
        $existing = $this->db->table('newsletter_subscribers')->where('email', $email)->first();
        if ($existing !== null) {
            if (($existing['status'] ?? '') === 'subscribed') {
                return true;
            }
            $this->db->table('newsletter_subscribers')->where('id', $existing['id'])->update([
                'status' => 'subscribed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('newsletter_subscribers', [
            'email' => $email,
            'name' => $name,
            'status' => 'subscribed',
            'token' => bin2hex(random_bytes(24)),
            'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }
}
