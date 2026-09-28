<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSmtp;

use IRJalali\Core\Database\Database;

/**
 * Email queue + sender. Emails are persisted first (never lost on crash),
 * then flushed by the scheduler or `sendNow()`. Supports simple templates
 * with {{placeholders}}.
 */
final class Mailer
{
    public function __construct(
        private readonly Database $db,
        private readonly \Closure $settings, // fn(string $key, mixed $default): mixed
    ) {
    }

    /** Queue an email for background delivery. */
    public function queue(string $to, string $subject, string $htmlBody, array $headers = []): int
    {
        return (int) $this->db->insert('ir_smtp_queue', [
            'to_email' => mb_substr($to, 0, 191),
            'subject' => mb_substr($subject, 0, 255),
            'body' => $htmlBody,
            'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Queue using a named template with {{placeholders}}. */
    public function queueTemplate(string $to, string $subject, string $template, array $vars = [], array $headers = []): int
    {
        return $this->queue($to, $subject, $this->renderTemplate($template, $vars), $headers);
    }

    /** Render a simple template string with {{key}} placeholders. */
    public function renderTemplate(string $template, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($vars) {
            $key = $m[1];

            return isset($vars[$key]) ? htmlspecialchars((string) $vars[$key], ENT_QUOTES, 'UTF-8') : '';
        }, $template);
    }

    /**
     * Send up to $limit queued emails now. Returns [sent, failed].
     * @return array{0: int, 1: int}
     */
    public function flush(int $limit = 20): array
    {
        $rows = $this->db->select(
            "SELECT * FROM ir_smtp_queue WHERE status = 'pending' ORDER BY id LIMIT " . max(1, $limit)
        );
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                $this->deliver($row);
                $this->db->table('ir_smtp_queue')->where('id', $row['id'])->update([
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'attempts' => (int) $row['attempts'] + 1,
                ]);
                $this->log($row, 'sent', '');
                $sent++;
            } catch (\Throwable $e) {
                $attempts = (int) $row['attempts'] + 1;
                $this->db->table('ir_smtp_queue')->where('id', $row['id'])->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
                $this->log($row, $attempts >= 5 ? 'failed' : 'retry', $e->getMessage());
                $failed++;
            }
        }

        return [$sent, $failed];
    }

    /** Send a single email immediately (bypasses queue). Throws on failure. */
    public function sendNow(string $to, string $subject, string $htmlBody, array $headers = []): void
    {
        $this->deliver([
            'to_email' => $to,
            'subject' => $subject,
            'body' => $htmlBody,
            'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function deliver(array $row): void
    {
        $driver = (string) call_user_func($this->settings, 'driver', 'smtp');
        $headers = json_decode((string) ($row['headers'] ?? '[]'), true) ?: [];

        if ($driver === 'log') {
            // Development driver: write to the plugin log channel only.
            $this->log($row, 'logged', 'driver=log — no network send.');

            return;
        }

        $client = new SmtpClient(
            (string) call_user_func($this->settings, 'host', 'localhost'),
            (int) call_user_func($this->settings, 'port', 587),
            (string) call_user_func($this->settings, 'encryption', 'tls'),
            (string) call_user_func($this->settings, 'username', ''),
            (string) call_user_func($this->settings, 'password', ''),
        );
        $client->send(
            (string) call_user_func($this->settings, 'from_email', 'noreply@localhost'),
            (string) call_user_func($this->settings, 'from_name', 'IR-Jalali'),
            (string) $row['to_email'],
            (string) $row['subject'],
            (string) $row['body'],
            is_array($headers) ? array_map('strval', $headers) : []
        );
    }

    /** @param array<string, mixed> $row */
    private function log(array $row, string $status, string $detail): void
    {
        try {
            $this->db->insert('ir_smtp_log', [
                'to_email' => mb_substr((string) $row['to_email'], 0, 191),
                'subject' => mb_substr((string) $row['subject'], 0, 255),
                'status' => mb_substr($status, 0, 20),
                'detail' => mb_substr($detail, 0, 2000) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
