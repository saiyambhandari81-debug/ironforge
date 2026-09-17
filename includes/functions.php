<?php
function formatMoney($amount): string
{
    return 'Rs. ' . number_format((float) $amount, 2);
}

function formatDate($date): string
{
    return date('M j, Y', strtotime($date));
}

function calculateRevenue(PDO $pdo, string $startDate, string $endDate): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(amount), 0) AS total,
            COALESCE(SUM(CASE WHEN membership_id IS NOT NULL THEN amount ELSE 0 END), 0) AS membership_revenue,
            COALESCE(SUM(CASE WHEN membership_id IS NULL THEN amount ELSE 0 END), 0) AS other_revenue,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) AS cash_total,
            COALESCE(SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END), 0) AS card_total,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount ELSE 0 END), 0) AS bank_transfer_total,
            COALESCE(SUM(CASE WHEN payment_method = 'esewa' THEN amount ELSE 0 END), 0) AS esewa_total,
            COALESCE(SUM(CASE WHEN payment_method = 'khalti' THEN amount ELSE 0 END), 0) AS khalti_total,
            COALESCE(SUM(CASE WHEN payment_method = 'other' THEN amount ELSE 0 END), 0) AS other_method_total
         FROM payments
         WHERE payment_status = 'completed'
           AND DATE(payment_date) BETWEEN ? AND ?"
    );
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetch();
}

function calculateExpenses(PDO $pdo, string $startDate, string $endDate): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?"
    );
    $stmt->execute([$startDate, $endDate]);
    return (float) $stmt->fetchColumn();
}

function expenseCategoryLabels(): array
{
    return [
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
}

function resolveDateRange(): array
{
    $range = $_GET['range'] ?? 'month';

    switch ($range) {
        case 'today':
            $start = date('Y-m-d');
            $end   = date('Y-m-d');
            break;
        case 'week':
            $daysSinceMonday = (int) date('N') - 1;
            $start = date('Y-m-d', strtotime("-$daysSinceMonday days"));
            $end   = date('Y-m-d');
            break;
        case 'year':
            $start = date('Y-01-01');
            $end   = date('Y-m-d');
            break;
        case 'custom':
            $start = trim($_GET['start_date'] ?? date('Y-m-01'));
            $end   = trim($_GET['end_date'] ?? date('Y-m-d'));
            if (!DateTime::createFromFormat('Y-m-d', $start)) {
                $start = date('Y-m-01');
            }
            if (!DateTime::createFromFormat('Y-m-d', $end)) {
                $end = date('Y-m-d');
            }
            break;
        case 'month':
        default:
            $range = 'month';
            $start = date('Y-m-01');
            $end   = date('Y-m-d');
            break;
    }

    return ['range' => $range, 'start' => $start, 'end' => $end];
}

/**
 * Automatically reactivates trainers whose approved leave end date has passed.
 * Sets status back to 'active' if leave_end < CURDATE() and status was 'on_leave'.
 */
function autoReactivateTrainers(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare("
            UPDATE trainers 
            SET status = 'active' 
            WHERE status = 'on_leave' 
              AND leave_end IS NOT NULL 
              AND leave_end < CURDATE()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        // Table or columns might not exist if migration hasn't been run yet
        return 0;
    }
}

function plansHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = \'plans\'
           AND COLUMN_NAME = ?'
    );
    $stmt->execute([$column]);
    $cache[$column] = ((int) $stmt->fetchColumn()) > 0;

    return $cache[$column];
}

function trainerBookingUpgradeMessage(): string
{
    return 'Your plan does not include personal training. Upgrade to Premium (Gym + Cardio + Personal trainer) to book a trainer.';
}

function trainerBookingAdminUpgradeMessage(): string
{
    return 'This member\'s plan does not include personal training. Upgrade to Premium (Gym + Cardio + Personal trainer) to book a trainer.';
}

/**
 * Active unexpired membership plus a plan with includes_trainer = 1.
 *
 * @return array{ok:bool, code:?string, error:?string}
 */
function memberCanBookTrainer(PDO $pdo, int $memberId, bool $forAdmin = false): array
{
    $hasTrainerCol = plansHasColumn($pdo, 'includes_trainer');
    $trainerExpr = $hasTrainerCol ? 'p.includes_trainer' : '0';

    $stmt = $pdo->prepare("
        SELECT ms.membership_id, {$trainerExpr} AS includes_trainer
        FROM memberships ms
        INNER JOIN plans p ON p.plan_id = ms.plan_id
        WHERE ms.member_id = ?
          AND ms.status = 'active'
          AND ms.expiry_date >= CURDATE()
        ORDER BY ms.expiry_date DESC
        LIMIT 1
    ");
    $stmt->execute([$memberId]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'ok'    => false,
            'code'  => 'no_membership',
            'error' => $forAdmin
                ? 'Member has no active membership. Cannot book.'
                : 'You need an active membership to book a trainer.',
        ];
    }

    if ((int) $row['includes_trainer'] !== 1) {
        return [
            'ok'    => false,
            'code'  => 'no_trainer',
            'error' => $forAdmin ? trainerBookingAdminUpgradeMessage() : trainerBookingUpgradeMessage(),
        ];
    }

    return ['ok' => true, 'code' => null, 'error' => null];
}
