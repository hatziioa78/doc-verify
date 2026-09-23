<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function configFromSettings(): array
    {
        return self::normalize([
            'host' => Settings::get('smtp_host'),
            'port' => (int) Settings::get('smtp_port', '587'),
            'username' => Settings::get('smtp_username'),
            'password' => Settings::secret('smtp_password'),
            'from_name' => Settings::get('smtp_from_name'),
            'from_email' => Settings::get('smtp_from_email'),
            'security' => Settings::get('smtp_security', 'tls'),
        ]);
    }

    public static function normalize(array $config): array
    {
        $security = (string) ($config['security'] ?? 'tls');
        if (!in_array($security, ['tls', 'ssl', 'none'], true)) {
            $security = 'tls';
        }
        return [
            'host' => trim((string) ($config['host'] ?? '')),
            'port' => (int) ($config['port'] ?? 587),
            'username' => trim((string) ($config['username'] ?? '')),
            'password' => (string) ($config['password'] ?? ''),
            'from_name' => trim((string) ($config['from_name'] ?? '')),
            'from_email' => normalize_email((string) ($config['from_email'] ?? '')),
            'security' => $security,
        ];
    }

    public static function ready(array $config): bool
    {
        return $config['host'] !== '' && valid_email($config['from_email']) && $config['port'] > 0;
    }

    public static function send(array $config, string $to, string $subject, string $html, string $text): void
    {
        $config = self::normalize($config);
        if (!self::ready($config) || !valid_email($to)) {
            throw new RuntimeException('Οι ρυθμίσεις email δεν είναι πλήρεις.');
        }
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->Timeout = 15;
        $mail->SMTPAuth = $config['username'] !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
        }
        if ($config['security'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['security'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        $fromName = $config['from_name'] !== '' ? $config['from_name'] : Settings::headerName();
        $mail->setFrom($config['from_email'], $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        try {
            $mail->send();
        } catch (Throwable $e) {
            log_exception($e);
            throw new RuntimeException('Η αποστολή email απέτυχε. Ελέγξτε τον διακομιστή SMTP, τη θύρα και τα στοιχεία σύνδεσης.');
        }
    }
}
