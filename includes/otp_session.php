<?php
/**
 * Session-based OTP helpers (supervisor: do not store OTP in the database).
 * Only a SHA-256 hash + metadata is kept in $_SESSION.
 */

const OTP_SESSION_KEY = 'email_otp';
const OTP_MAX_ATTEMPTS = 5;
const OTP_TTL_SECONDS = 60; // 1 minute
const OTP_MAX_PER_EMAIL_HOUR = 3;

/**
 * Create / replace OTP in session.
 * Returns the plain 6-digit code (for sending by email only).
 */
function otpSessionCreate(string $email, string $purpose, ?string $userType = null): string
{
    $email = strtolower(trim($email));
    $otp = (string) random_int(100000, 999999);

    $_SESSION[OTP_SESSION_KEY] = [
        'email'     => $email,
        'purpose'   => $purpose, // 'register' | 'reset'
        'user_type' => $userType, // admin|trainer|member|null
        'hash'      => hash('sha256', $otp),
        'expires'   => time() + OTP_TTL_SECONDS,
        'attempts'  => 0,
        'created'   => time(),
    ];

    return $otp;
}

function otpSessionGet(): ?array
{
    if (empty($_SESSION[OTP_SESSION_KEY]) || !is_array($_SESSION[OTP_SESSION_KEY])) {
        return null;
    }
    return $_SESSION[OTP_SESSION_KEY];
}

function otpSessionClear(): void
{
    unset($_SESSION[OTP_SESSION_KEY], $_SESSION['demo_otp']);
}

/**
 * Rate limit: max OTP creates per email+purpose per hour (session-based).
 */
function otpSessionTooManyRequests(string $email, string $purpose): bool
{
    $email = strtolower(trim($email));
    if (!isset($_SESSION['otp_request_log']) || !is_array($_SESSION['otp_request_log'])) {
        $_SESSION['otp_request_log'] = [];
    }

    $now = time();
    $window = 3600;

    $_SESSION['otp_request_log'] = array_values(array_filter(
        $_SESSION['otp_request_log'],
        static function ($row) use ($now, $window) {
            return is_array($row)
                && isset($row['t'])
                && ($now - (int) $row['t']) < $window;
        }
    ));

    $count = 0;
    foreach ($_SESSION['otp_request_log'] as $row) {
        if (
            ($row['email'] ?? '') === $email
            && ($row['purpose'] ?? '') === $purpose
        ) {
            $count++;
        }
    }

    return $count >= OTP_MAX_PER_EMAIL_HOUR;
}

function otpSessionLogRequest(string $email, string $purpose): void
{
    if (!isset($_SESSION['otp_request_log']) || !is_array($_SESSION['otp_request_log'])) {
        $_SESSION['otp_request_log'] = [];
    }
    $_SESSION['otp_request_log'][] = [
        'email'   => strtolower(trim($email)),
        'purpose' => $purpose,
        't'       => time(),
    ];
}

/**
 * Verify submitted code against session OTP.
 * @return array{ok:bool,error:string,data?:array}
 */
function otpSessionVerify(string $email, string $purpose, string $code): array
{
    $email = strtolower(trim($email));
    $code = preg_replace('/\D+/', '', $code);
    $data = otpSessionGet();

    if (!$data) {
        return ['ok' => false, 'error' => 'No active verification code. Please request a new code.'];
    }
    if (($data['purpose'] ?? '') !== $purpose) {
        return ['ok' => false, 'error' => 'No active verification code for this action. Please request a new code.'];
    }
    if (strtolower((string) ($data['email'] ?? '')) !== $email) {
        return ['ok' => false, 'error' => 'Email does not match the verification request. Use the same email.'];
    }
    if ((int) ($data['expires'] ?? 0) < time()) {
        otpSessionClear();
        return ['ok' => false, 'error' => 'That code has expired. Please request a new code.'];
    }
    if ((int) ($data['attempts'] ?? 0) >= OTP_MAX_ATTEMPTS) {
        otpSessionClear();
        return ['ok' => false, 'error' => 'Too many attempts. This code is locked. Please request a new code.'];
    }
    if (strlen($code) !== 6) {
        return ['ok' => false, 'error' => 'Enter the 6-digit verification code.'];
    }

    if (!hash_equals((string) $data['hash'], hash('sha256', $code))) {
        $_SESSION[OTP_SESSION_KEY]['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
        $left = OTP_MAX_ATTEMPTS - (int) $_SESSION[OTP_SESSION_KEY]['attempts'];
        if ($left <= 0) {
            otpSessionClear();
            return ['ok' => false, 'error' => 'Incorrect code. Maximum attempts reached. Request a new code.'];
        }
        return [
            'ok' => false,
            'error' => 'Incorrect code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.',
        ];
    }

    return ['ok' => true, 'error' => '', 'data' => $data];
}