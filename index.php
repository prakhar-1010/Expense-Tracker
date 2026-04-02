<?php
/**
 * index.php – Dashboard
 * ============================================================
 * Main landing page after login.  Shows:
 *   - Current month summary (total spent, budget, remaining)
 *   - Budget usage progress bar with colour coding
 *   - Over-budget warning (animated red alert)
 *   - Last 5 expenses table
 *   - Category breakdown for the current month
 * ============================================================
 */
require_once 'config.php';
requireLogin();   // Redirect to login.php if not authenticated

$userId   = getCurrentUserId();
$username = getCurrentUsername();

// ── Current month / year ─────────────────────────────────────
$month = (int)date('n');  // 1–12
$year  = (int)date('Y');

// ── Financial summary ────────────────────────────────────────
$totalExpenses = getTotalExpenses($userId, $month, $year);
$budget        = getBudget($userId, $month, $year);
$remaining     = $budget - $totalExpenses;
$overBudget    = ($budget > 0 && $totalExpenses > $budget);

// Usage percentage for progress bar (capped at 100 for display)
$usagePercent = ($budget > 0) ? min(100, (int)round(($totalExpenses / $budget) * 100)) : 0;

// Progress bar Bootstrap colour
$barColor = 'bg-success';
if ($usagePercent >= 75) $barColor = 'bg-warning';
if ($usagePercent >= 100) $barColor = 'bg-danger';

// ── Data for widgets ─────────────────────────────────────────
$recentExpenses   = getRecentExpenses($userId, 5);
$monthBreakdown = getCategoryTotals($userId, $month, $year);
$monthExpenseCount = count(getExpenses($userId, '', sprintf('%04d-%02d', $year, $month)));
$trendRows = getMonthlyTotalsForLastMonths($userId, 6);
$trendLabels = array_map(function ($row) { return $row['label']; }, $trendRows);
$trendValues = array_map(function ($row) { return (float)$row['total']; }, $trendRows);
$categoryLabels = array_keys($monthBreakdown);
$categoryValues = array_values($monthBreakdown);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- ── Navigation Bar ──────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-wallet2 me-2"></i>Expense Tracker
        </a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-house me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_expense.php">
                        <i class="bi bi-plus-circle me-1"></i>Add Expense
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="view_expense.php">
                        <i class="bi bi-table me-1"></i>View Expenses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="budget.php">
                        <i class="bi bi-piggy-bank me-1"></i>Budget
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php">
                        <i class="bi bi-bar-chart-line me-1"></i>Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
            <span class="navbar-text ms-3 d-none d-lg-inline text-white-50">
                <i class="bi bi-person-circle me-1"></i><?= esc($username) ?>
            </span>
        </div>
    </div>
</nav>
<!-- ── End Navbar ───────────────────────────────────────────── -->

<div class="container py-4">

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Welcome back, <?= esc($username) ?>!</h4>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar3 me-1"></i><?= date('F Y') ?> Overview
            </p>
        </div>
        <a href="add_expense.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Add Expense
        </a>
    </div>

    <!-- Over-budget warning (pulsing) -->
    <?php if ($overBudget): ?>
    <div class="alert alert-danger budget-exceeded d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-3 me-3 flex-shrink-0"></i>
        <div>
            <strong>Budget Exceeded!</strong>
            You spent <strong>₹<?= number_format($totalExpenses, 2) ?></strong> against a budget
            of <strong>₹<?= number_format($budget, 2) ?></strong> this month.
            <a href="budget.php" class="alert-link ms-2">Update budget →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Summary stat cards ─────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Total Spent -->
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100 border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Spent</div>
                        <div class="fs-5 fw-bold text-danger">
                            ₹<?= number_format($totalExpenses, 2) ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;"><?= date('M Y') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Budget -->
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100 border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Monthly Budget</div>
                        <div class="fs-5 fw-bold text-primary">
                            <?= ($budget > 0) ? '₹' . number_format($budget, 2) : '—' ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            <?php if (!$budget): ?>
                                <a href="budget.php">Set budget</a>
                            <?php else: ?>
                                <?= $usagePercent ?>% used
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remaining / Overspent -->
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100 border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon <?= $overBudget
                        ? 'bg-danger bg-opacity-10 text-danger'
                        : 'bg-success bg-opacity-10 text-success' ?>">
                        <i class="bi bi-<?= $overBudget ? 'graph-down-arrow' : 'graph-up-arrow' ?>"></i>
                    </div>
                    <div>
                        <div class="text-muted small">
                            <?= $overBudget ? 'Over Budget' : 'Remaining' ?>
                        </div>
                        <div class="fs-5 fw-bold <?= $overBudget ? 'text-danger' : 'text-success' ?>">
                            <?= ($budget > 0) ? '₹' . number_format(abs($remaining), 2) : '—' ?>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            <?php if ($budget > 0): ?>
                                <?= $overBudget ? 'over limit' : 'left to spend' ?>
                            <?php else: ?>
                                no budget set
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Count -->
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100 border-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Transactions</div>
                        <div class="fs-5 fw-bold text-info"><?= $monthExpenseCount ?></div>
                        <div class="text-muted" style="font-size:.75rem;">This month</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /stat cards -->

    <!-- ── Budget progress bar ────────────────────────────── -->
    <?php if ($budget > 0): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold small">
                    Budget usage — <?= date('F Y') ?>
                </span>
                <span class="text-muted small">
                    ₹<?= number_format($totalExpenses, 2) ?> /
                    ₹<?= number_format($budget, 2) ?>
                </span>
            </div>
            <div class="progress" style="height:20px; border-radius:10px;">
                <div class="progress-bar <?= $barColor ?> progress-bar-striped progress-bar-animated"
                     role="progressbar"
                     style="width:<?= $usagePercent ?>%"
                     aria-valuenow="<?= $usagePercent ?>"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    <?= $usagePercent ?>%
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Bottom two-column section ─────────────────────── -->
    <div class="row g-4">

        <!-- Recent expenses table -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                    <h6 class="section-title mb-0">Recent Expenses</h6>
                    <a href="view_expense.php" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentExpenses)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            No expenses yet.
                            <a href="add_expense.php">Add your first one!</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover expense-table mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentExpenses as $exp): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= esc($exp['title']) ?></td>
                                        <td>
                                            <span class="badge <?= getCategoryBadgeClass($exp['category']) ?>">
                                                <?= esc($exp['category']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-danger fw-semibold">
                                            ₹<?= number_format((float)$exp['amount'], 2) ?>
                                        </td>
                                        <td class="text-muted small"><?= esc($exp['date']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Category breakdown (this month) -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="section-title mb-0">
                        <i class="bi bi-pie-chart me-1 text-primary"></i>
                        Spending by Category
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($monthBreakdown)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                            No data for <?= date('F') ?>.
                        </div>
                    <?php else: ?>
                        <div class="category-progress">
                            <?php foreach ($monthBreakdown as $cat => $amt):
                                $pct = ($totalExpenses > 0)
                                    ? (int)round(($amt / $totalExpenses) * 100)
                                    : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-semibold"><?= esc($cat) ?></span>
                                    <span class="small text-muted">
                                        ₹<?= number_format($amt, 2) ?>
                                        <span class="text-secondary">(<?= $pct ?>%)</span>
                                    </span>
                                </div>
                                <div class="progress" style="height:10px; border-radius:5px;">
                                    <div class="progress-bar <?= getCategoryProgressColor($cat) ?>"
                                         style="width:<?= $pct ?>%"
                                         role="progressbar"
                                         aria-valuenow="<?= $pct ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->
    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="section-title mb-0">Monthly Spend Trend (Last 6 Months)</h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="section-title mb-0">Category Distribution (This Month)</h6>
                </div>
                <div class="card-body">
                    <canvas id="categoryPieChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <p class="text-center text-muted small mt-5">
        &copy; <?= date('Y') ?> Expense Tracker &mdash; Academic Project
    </p>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>
<script>
const trendCtx = document.getElementById('monthlyTrendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Spent (₹)',
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#e2b714',
                backgroundColor: 'rgba(226,183,20,0.12)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#d1d0c5' }, grid: { color: 'rgba(255,255,255,0.06)' } },
                y: { ticks: { color: '#d1d0c5' }, grid: { color: 'rgba(255,255,255,0.06)' } }
            }
        }
    });
}

const categoryCtx = document.getElementById('categoryPieChart');
if (categoryCtx) {
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($categoryLabels) ?>,
            datasets: [{
                data: <?= json_encode($categoryValues) ?>,
                backgroundColor: ['#e2b714', '#6a9b5e', '#ca4754', '#4fa3ff', '#8b5cf6', '#22c55e', '#f97316', '#64748b']
            }]
        },
        options: {
            plugins: {
                legend: { labels: { color: '#d1d0c5' } }
            }
        }
    });
}
</script>
</body>
</html>
