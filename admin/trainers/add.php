<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/mailer.php';

requireLogin();

$errors = [];

$old = [
    'full_name'        => '',
    'email'            => '',
    'phone'            => '',
    'specialization'   => '',
    'experience_years' => '',
    'salary'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $old['full_name']        = trim($_POST['full_name'] ?? '');
    $old['email']            = trim($_POST['email'] ?? '');
    $old['phone']            = trim($_POST['phone'] ?? '');
    $old['specialization']   = trim($_POST['specialization'] ?? '');
    $old['experience_years'] = trim($_POST['experience_years'] ?? '');
    $old['salary']           = trim($_POST['salary'] ?? '');

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    // -----------------------------
    // Full name validation
    // -----------------------------
    if ($old['full_name'] === '') {

        $errors[] = 'Full name is required.';

    } elseif (mb_strlen($old['full_name']) < 2) {

        $errors[] = 'Full name must be at least 2 characters.';

    } elseif (mb_strlen($old['full_name']) > 100) {

        $errors[] = 'Full name cannot be longer than 100 characters.';

    } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $old['full_name'])) {

        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }


    // -----------------------------
    // Email validation
    // -----------------------------
    if ($old['email'] === '') {

        $errors[] = 'Email is required.';

    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {

        $errors[] = 'Please enter a valid email address.';

    } else {

        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM trainers WHERE email = ?'
        );

        $check->execute([$old['email']]);

        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'A trainer with that email already exists.';
        }
    }


    // -----------------------------
    // Phone validation
    // -----------------------------
    if ($old['phone'] === '') {

        $errors[] = 'Phone number is required.';

    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {

        $errors[] = 'Phone must be 10 digits starting with 97, 98, 96, or 94.';
    }


    // -----------------------------
    // Specialization
    // -----------------------------
    if (
        $old['specialization'] !== '' &&
        mb_strlen($old['specialization']) > 100
    ) {
        $errors[] = 'Specialization cannot be longer than 100 characters.';
    }


    // -----------------------------
    // Experience
    // -----------------------------
    if (
        $old['experience_years'] === '' ||
        !ctype_digit($old['experience_years'])
    ) {
        $errors[] = 'Experience must be a whole number (0 or more).';
    }


    // -----------------------------
    // Salary
    // -----------------------------
    if (
        $old['salary'] === '' ||
        !is_numeric($old['salary']) ||
        (float) $old['salary'] < 0
    ) {
        $errors[] = 'Salary must be a valid amount (0 or more).';
    }


    // -----------------------------
    // Password
    // -----------------------------
    if ($password === '') {

        $errors[] = 'Password is required so the trainer can log in.';

    } elseif (strlen($password) < 6) {

        $errors[] = 'Password must be at least 6 characters.';
    }


    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }


    // =========================================================
    // CREATE TRAINER + OTP
    // =========================================================
    if (!$errors) {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Hash OTP before storing it
        $otpHash = hash('sha256', $otp);

        try {

            $pdo->beginTransaction();

            // -------------------------------------------------
            // Create trainer as UNVERIFIED
            // -------------------------------------------------
            $stmt = $pdo->prepare(
                "INSERT INTO trainers
                (
                    full_name,
                    email,
                    phone,
                    specialization,
                    experience_years,
                    salary,
                    status,
                    password_hash,
                    email_verified
                )
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, 0)"
            );

            $stmt->execute([
                $old['full_name'],
                $old['email'],
                $old['phone'],
                $old['specialization'] !== ''
                    ? $old['specialization']
                    : null,
                (int) $old['experience_years'],
                (float) $old['salary'],
                $hash,
            ]);


            // -------------------------------------------------
            // Invalidate old registration OTPs
            // -------------------------------------------------
            $invalidate = $pdo->prepare(
                "UPDATE email_otps
                 SET used_at = NOW()
                 WHERE email = ?
                   AND purpose = 'register'
                   AND used_at IS NULL"
            );

            $invalidate->execute([
                $old['email']
            ]);


            // -------------------------------------------------
            // Store new OTP
            // -------------------------------------------------
            $otpStmt = $pdo->prepare(
                "INSERT INTO email_otps
                (
                    email,
                    purpose,
                    otp_hash,
                    expires_at,
                    attempts
                )
                VALUES
                (
                    ?,
                    'register',
                    ?,
                    DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                    0
                )"
            );

            $otpStmt->execute([
                $old['email'],
                $otpHash
            ]);


            // Commit database changes
            $pdo->commit();


            // =================================================
            // SEND OTP EMAIL
            // =================================================

            $body =
                "Hello {$old['full_name']},\n\n" .
                "Welcome to IronForge Gym.\n\n" .
                "Your trainer account has been created by the gym administrator.\n\n" .
                "Your email verification code is:\n\n" .
                "{$otp}\n\n" .
                "This code is valid for 15 minutes.\n\n" .
                "You must verify your email before you can log in.\n\n" .
                "If you did not expect this account, please contact IronForge Gym.\n\n" .
                "IronForge Gym";


            $mail = sendGymEmail(
                $old['email'],
                'IronForge Gym - Trainer Email Verification',
                $body
            );


            // -------------------------------------------------
            // If email fails
            // -------------------------------------------------
            if (!$mail['ok']) {

                /*
                 * During development, stop here and show the
                 * actual SMTP error.
                 *
                 * This helps us diagnose Gmail configuration.
                 */
                die(
                    '<div style="font-family:Arial;padding:30px;">' .
                    '<h2>Trainer created, but email was not sent.</h2>' .
                    '<p><strong>Email:</strong> ' .
                    htmlspecialchars($old['email']) .
                    '</p>' .
                    '<p><strong>Error:</strong> ' .
                    htmlspecialchars($mail['error']) .
                    '</p>' .
                    '<p>Please fix the email configuration and try again.</p>' .
                    '</div>'
                );
            }


            // -------------------------------------------------
            // Email successfully sent
            // -------------------------------------------------
            $_SESSION['flash_success'] =
                'Trainer created successfully. A verification code has been sent to the trainer email.';


            header(
                'Location: ' .
                BASE_URL .
                '/verify-email.php?email=' .
                urlencode($old['email'])
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Trainer creation failed: ' .
                $e->getMessage()
            );

            $errors[] =
                'Could not create trainer account. Please try again.';
        }
    }
}

$pageTitle = 'Add Trainer';

require_once ROOT_PATH . '/includes/header.php';

?>

<div class="card" style="max-width: 700px;">

    <div class="card-body">

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $e): ?>

                        <li>
                            <?= htmlspecialchars($e) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form method="POST">

            <?= csrfField() ?>


            <!-- Full Name -->

            <div class="mb-3">

                <label class="form-label">
                    Full Name *
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?= htmlspecialchars($old['full_name']) ?>"
                    required
                >

            </div>


            <!-- Email + Phone -->

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Email *
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($old['email']) ?>"
                        required
                    >

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Phone *
                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?= htmlspecialchars($old['phone']) ?>"
                        required
                    >

                </div>

            </div>


            <!-- Specialization -->

            <div class="mb-3">

                <label class="form-label">
                    Specialization
                </label>

                <input
                    type="text"
                    name="specialization"
                    class="form-control"
                    value="<?= htmlspecialchars($old['specialization']) ?>"
                >

            </div>


            <!-- Experience + Salary -->

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Experience (years) *
                    </label>

                    <input
                        type="number"
                        step="1"
                        min="0"
                        name="experience_years"
                        class="form-control"
                        value="<?= htmlspecialchars($old['experience_years']) ?>"
                        required
                    >

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Salary (Rs./month) *
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="salary"
                        class="form-control"
                        value="<?= htmlspecialchars($old['salary']) ?>"
                        required
                    >

                </div>

            </div>


            <hr>


            <p class="text-muted small">

                Portal password — used on the main login page
                for the trainer account.

                The trainer must verify their email before logging in.

            </p>


            <!-- Password -->

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Password *
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        required
                        minlength="6"
                    >

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Confirm password *
                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        required
                        minlength="6"
                    >

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-dark"
            >
                Save Trainer
            </button>


            <a
                href="<?= BASE_URL ?>/admin/trainers/"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>


<?php require_once ROOT_PATH . '/includes/footer.php'; ?>