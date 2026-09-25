<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSmtp;

/**
 * Minimal, dependency-free SMTP client (RFC 5321 subset) with PLAIN/LOGIN
 * auth, STARTTLS and SSL support. Errors throw SmtpException with the last
 * server transcript so failures are diagnosable from the admin log.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    /** @var list<string> */
    private array $transcript = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly string $encryption = 'tls', // tls | ssl | none
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly int $timeout = 20,
    ) {
    }

    /** @param array<string, string> $headers extra headers (e.g. Reply-To) */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, array $headers = []): void
    {
        $this->connect();
        try {
            $this->expect([220]);
            $this->ehlo();

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                $this->enableTls();
                $this->ehlo();
            }

            if ($this->username !== '') {
                $this->authenticate();
            }

            $this->command("MAIL FROM:<{$fromEmail}>", [250]);
            $this->command("RCPT TO:<{$toEmail}>", [250, 251]);
            $this->command('DATA', [354]);

            $message = $this->buildMessage($fromEmail, $fromName, $toEmail, $subject, $htmlBody, $headers);
            fwrite($this->socket, $message . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    /** @return list<string> */
    public function transcript(): array
    {
        return $this->transcript;
    }

    private function connect(): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new SmtpException("Cannot connect to {$this->host}:{$this->port} ({$errstr})");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function enableTls(): void
    {
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new SmtpException('STARTTLS negotiation failed.');
        }
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->command("EHLO {$hostname}", [250]);
    }

    private function authenticate(): void
    {
        // AUTH LOGIN (widely supported).
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->username), [334]);
        $this->command(base64_encode($this->password), [235]);
    }

    /** @param list<int> $expected */
    private function command(string $line, array $expected): void
    {
        if ($this->socket === null) {
            throw new SmtpException('Not connected.');
        }
        fwrite($this->socket, $line . "\r\n");
        $this->transcript[] = '> ' . $this->mask($line);
        $this->expect($expected);
    }

    /** @param list<int> $expected */
    private function expect(array $expected): string
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new SmtpException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }

    private function readResponse(): string
    {
        $data = '';
        while (($line = fgets($this->socket, 2048)) !== false) {
            $data .= $line;
            $this->transcript[] = '< ' . rtrim($line);
            // Multi-line responses continue while char 4 is '-'.
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }

        return $data;
    }

    /** @param array<string, string> $headers */
    private function buildMessage(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, array $headers): string
    {
        $boundary = 'irj-' . bin2hex(random_bytes(12));
        $fromHeader = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>' : $fromEmail;
        $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $out = 'Date: ' . date('r') . "\r\n";
        $out .= 'From: ' . $fromHeader . "\r\n";
        $out .= 'To: <' . $toEmail . ">\r\n";
        $out .= 'Subject: ' . $subjectHeader . "\r\n";
        $out .= 'Message-ID: <' . bin2hex(random_bytes(16)) . '@ir-jalali>' . "\r\n";
        $out .= "MIME-Version: 1.0\r\n";
        $out .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
        foreach ($headers as $name => $value) {
            $safeName = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
            $out .= $safeName . ': ' . str_replace(["\r", "\n"], '', $value) . "\r\n";
        }
        $out .= "\r\n";
        $plain = trim(strip_tags($htmlBody));
        $out .= '--' . $boundary . "\r\n";
        $out .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= chunk_split(base64_encode($plain)) . "\r\n";
        $out .= '--' . $boundary . "\r\n";
        $out .= "Content-Type: text/html; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $out .= '--' . $boundary . '--';

        // Dot-stuffing for DATA.
        return str_replace("\r\n.", "\r\n..", $out);
    }

    private function mask(string $line): string
    {
        // Never write credentials into the transcript.
        if (str_starts_with($line, 'AUTH') || preg_match('/^[A-Za-z0-9+\/=]+$/', $line)) {
            return '********';
        }

        return $line;
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
