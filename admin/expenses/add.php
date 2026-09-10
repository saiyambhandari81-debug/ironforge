<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$categories = [
    'equipment'      => 'Equipment',
    'electricity'    => 'Electricity',
    'water'          => 'Water',
    'rent'           => 'Rent',
    'trainer_salary' => 'Trainer Salary',
    'maintenance'    => 'Maintenance',
    'cleaning'       => 'Cleaning',
    'internet'       => 'Internet',
    'other'          => 'Other',
];

$errors = [];
$old = [
    'category'     => '',
    'description'  => '',
    'amount'       => '',
    'expense_date' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['category']     = trim($_POST['category'] ?? '');
    $old['description']  = trim($_POST['description'] ?? '');
    $old['amount']       = trim($_POST['amount'] ?? '');
    $old['expense_date'] = trim($_POST['expense_date'] ?? '');

    // Category
    if (!array_key_exists($old['category'], $categories)) {
        $errors[] = 'Please choose a valid category.';
    }

    // Description
    if ($old['description'] === '') {
        $errors[] = 'Please add a short description.';
    } elseif (mb_strlen($old['description']) < 3) {
        $errors[] = 'Description must be at least 3 characters.';
    } elseif (mb_strlen($old['description']) > 255) {
        $errors[] = 'Description cannot be longer than 255 characters.';
    }

    // Amount
    if ($old['amount'] === '' || !is_numeric($old['amount'])) {
        $errors[] = 'Amount must be a valid number.';
    } elseif ((float) $old['amount'] <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    } elseif ((float) $old['amount'] > 1000000) {
        $errors[] = 'Amount cannot be more than Rs. 1,000,000.';
    }

    // Expense date
    // Today and past = allowed
    // Future = not allowed
    if ($old['expense_date'] === '') {
        $errors[] = 'Expense date is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $old['expense_date'])) {
        $errors[] = 'Please enter a valid date.';
    } elseif ($old['expense_date'] > date('Y-m-d')) {
        $errors[] = 'Expense date cannot be in the future.';
    }

    if (!$errors) {
        $insert = $pdo->prepare(
            "INSERT INTO expenses (category, description, amount, expense_date, created_by)
             VALUES (?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $old['category'],
            $old['description'],
            $old['amount'],
            $old['expense_date'],
            $_SESSION['admin_id'],
        ]);

        header('Location: ' . BASE_URL . '/admin/expenses/?msg=added');
        exit;
    }
}

$pageTitle = 'Add Expense';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $old['category'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Description *</label>
                <input type="text" name="description" class="form-control"
                       value="<?= htmlspecialchars($old['description']) ?>"
                       placeholder="e.g. Electricity bill" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Amount (Rs.) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                           value="<?= htmlspecialchars($old['amount']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expense Date *</label>
                    <input type="date" name="expense_date" class="form-control"
                           value="<?= htmlspecialchars($old['expense_date']) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Expense</button>
            <a href="<?= BASE_URL ?>/admin/expenses/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>