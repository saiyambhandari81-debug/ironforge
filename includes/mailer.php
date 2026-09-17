<?php
/**
 * Minimal Gmail SMTP mailer (no Composer).
 */

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config/database.php';
}
require_once ROOT_PATH . '/config/mail.php';

function isLocalHost(): bool
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $hostname = preg_replace('/:\d+$/', '', $host);
    return in_array($hostname, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * @return array{ok:bool,error:string}
 */
function sendGymEmail(string $to, string $subject, string $bodyText): array
{
    if (!MAIL_ENABLED) {
        return ['ok' => false, 'error' => 'Mail is disabled in config/mail.php'];
    }
    if (SMTP_USER === '' || SMTP_PASS === '' || SMTP_PASS === 'paste_app_password_here_no_spaces') {
        return ['ok' => false, 'error' => 'Set SMTP_USER and SMTP_PASS in config/mail.php'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $fromEmail = MAIL_FROM_EMAIL;
    $fromName = MAIL_FROM_NAME;

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$fp) {
        return ['ok' => false, 'error' => "Connection failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($fp, 30);

    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = function (string $data, string $code) {
        return strpos($data, $code) === 0;
    };

    try {
        $greeting = $read();
        if (!$expect($greeting, '220')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Bad greeting: ' . trim($greeting)];
        }

        $write('EHLO localhost');
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            $write('HELO localhost');
            $ehlo = $read();
            if (!$expect($ehlo, '250')) {
                fclose($fp);
                return ['ok' => false, 'error' => 'EHLO/HELO failed'];
            }
        }

        $write('STARTTLS');
        $tls = $read();
        if (!$expect($tls, '220')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'STARTTLS failed: ' . trim($tls)];
        }

        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'TLS negotiation failed'];
        }

        $write('EHLO localhost');
        $ehlo2 = $read();
        if (!$expect($ehlo2, '250')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'EHLO after TLS failed'];
        }

        $write('AUTH LOGIN');
        $auth = $read();
        if (!$expect($auth, '334')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'AUTH LOGIN not accepted'];
        }

        $write(base64_encode($user));
        $u = $read();
        if (!$expect($u, '334')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Username rejected'];
        }

        $write(base64_encode($pass));
        $p = $read();
        if (!$expect($p, '235')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Password rejected (check App Password)'];
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        $mf = $read();
        if (!$expect($mf, '250')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'MAIL FROM failed'];
        }

        $write('RCPT TO:<' . $to . '>');
        $rt = $read();
        if (!$expect($rt, '250') && !$expect($rt, '251')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'RCPT TO failed'];
        }

        $write('DATA');
        $data = $read();
        if (!$expect($data, '354')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'DATA not accepted'];
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date('r'),
        ];

        $bodyText = str_replace(["\r\n", "\r"], "\n", $bodyText);
        $bodyText = str_replace("\n", "\r\n", $bodyText);
        $bodyText = preg_replace('/^\./m', '..', $bodyText);

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyText . "\r\n.";
        $write($message);
        $end = $read();
        if (!$expect($end, '250')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Message not accepted: ' . trim($end)];
        }

        $write('QUIT');
        fclose($fp);

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        if (is_resource($fp)) {
            fclose($fp);
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @return array{ok:bool,error:string}
 */
function sendPasswordResetOtp(string $toEmail, string $otpCode): array
{
    $subject = 'IronForge password verification code';
    $body = "Your IronForge verification code is: {$otpCode}\r\n\r\n"
        . "This code is valid for 15 minutes.\r\n"
        . "If you did not request this, ignore this email.\r\n\r\n"
        . "— IronForge Gym";
    return sendGymEmail($toEmail, $subject, $body);
}