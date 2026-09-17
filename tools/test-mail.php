<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once ROOT_PATH . '/includes/mailer.php';

header('Content-Type: text/html; charset=utf-8');

$remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
$isCli = (php_sapi_name() === 'cli');
if ((!isLocalHost() || !in_array($remoteAddr, ['127.0.0.1', '::1'], true)) && !$isCli) {
    http_response_code(403);
    echo 'Mail test is only allowed on localhost.';
    exit;
}

$to = trim($_GET['to'] ?? (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : ''));
$result = null;

if (isset($_GET['send'])) {
    $result = sendGymEmail(
        $to,
        'IronForge test email',
        "If you received this, Gmail SMTP is working for IronForge.\n\nTime: " . date('Y-m-d H:i:s')
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mail test - IronForge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:520px">
    <h1 class="h4 mb-3">Gmail SMTP test</h1>
    <form method="get" class="card card-body shadow-sm">
        <label class="form-label">Send test to</label>
        <input type="email" name="to" class="form-control mb-3" value="<?= htmlspecialchars($to) ?>" required>
        <button class="btn btn-dark" name="send" value="1">Send test email</button>
    </form>
    <?php if ($result !== null): ?>
        <div class="alert mt-3 <?= $result['ok'] ? 'alert-success' : 'alert-danger' ?>">
            <?= $result['ok'] ? 'Sent OK. Check inbox and Spam.' : htmlspecialchars($result['error']) ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>