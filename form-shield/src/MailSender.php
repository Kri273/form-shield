<?php

declare(strict_types=1);

/** Minimal SMTP sender for plain-text contact-form notifications. */
class MailSender
{
    public function send(array $config, array $message): void
    {
        foreach (['from_address', 'to_address', 'smtp_host'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new RuntimeException('SMTP configuration is incomplete.');
            }
        }
        if (
            !filter_var($config['from_address'], FILTER_VALIDATE_EMAIL)
            || !filter_var($config['to_address'], FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('SMTP sender/recipient email address is invalid.');
        }

        $host = (string) $config['smtp_host'];
        $port = (int) ($config['smtp_port'] ?? 587);
        $encryption = strtolower((string) ($config['smtp_encryption'] ?? 'tls'));
        $connectHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $connectHost . ':' . $port,
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not connect to the SMTP server.');
        }

        stream_set_timeout($socket, 8);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('Could not establish SMTP TLS encryption.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            } elseif (!in_array($encryption, ['ssl', 'none'], true)) {
                throw new RuntimeException('SMTP_ENCRYPTION must be tls, ssl, or none.');
            }

            $username = (string) ($config['smtp_username'] ?? '');
            $password = (string) ($config['smtp_password'] ?? '');
            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $from = (string) $config['from_address'];
            $to = (string) $config['to_address'];
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $fromName = (string) ($config['from_name'] ?? 'Contact Form');
            $subject = (string) ($message['subject'] ?? 'New contact form message');
            $body = (string) ($message['body'] ?? '');
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n";
            $body = str_replace(["\r\n", "\r"], "\n", $body);
            $body = str_replace("\n", "\r\n", $body);
            $data .= $body;
            // SMTP requires lines beginning with a dot to be dot-stuffed.
            $data = preg_replace('/(^|\r\n)\./', '$1..', $data) ?? $data;
            fwrite($socket, $data . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expected);
    }

    private function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // A space after the SMTP code marks the final response line.
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        if (
            $response === '' || !preg_match('/^(\d{3})/', $response, $match)
            || !in_array((int) $match[1], $expected, true)
        ) {
            throw new RuntimeException('SMTP server rejected a command (response code ' . ($match[1] ?? 'unknown') . ').');
        }
        return $response;
    }
}
