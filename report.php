<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();

$month = (int)sanitize($_GET['month'] ?? date('n'));
$year = (int)sanitize($_GET['year'] ?? date('Y'));
$category = sanitize($_GET['category'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');
$export = sanitize($_GET['export'] ?? '');

if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$monthKey = sprintf('%04d-%02d', $year, $month);
$monthlyExpenses = getExpenses($userId, '', $monthKey);
$filteredExpenses = [];

foreach ($monthlyExpenses as $expense) {
    if ($category !== '' && strcasecmp($expense['category'], $category) !== 0) {
        continue;
    }

    if ($dateFrom !== '' && validateDate($dateFrom) && $expense['date'] < $dateFrom) {
        continue;
    }

    if ($dateTo !== '' && validateDate($dateTo) && $expense['date'] > $dateTo) {
        continue;
    }

    $filteredExpenses[] = $expense;
}

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="expense_report_' . $monthKey . '.csv"');

    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Title', 'Category', 'Amount', 'Date', 'Description']);
    foreach ($filteredExpenses as $expense) {
        fputcsv($fp, [
            $expense['title'],
            $expense['category'],
            number_format((float)$expense['amount'], 2, '.', ''),
            $expense['date'],
            $expense['description']
        ]);
    }
    fclose($fp);
    exit();
}

$budget = getBudget($userId, $month, $year);
$totalSpent = 0;
$categoryTotals = [];
$dailyTotals = [];
$topExpense = null;

foreach ($filteredExpenses as $expense) {
    $amount = (float)$expense['amount'];
    $totalSpent += $amount;

    if (!isset($categoryTotals[$expense['category']])) {
        $categoryTotals[$expense['category']] = 0;
    }
    $categoryTotals[$expense['category']] += $amount;

    if (!isset($dailyTotals[$expense['date']])) {
        $dailyTotals[$expense['date']] = 0;
    }
    $dailyTotals[$expense['date']] += $amount;

    if ($topExpense === null || $amount > (float)$topExpense['amount']) {
        $topExpense = $expense;
    }
}

arsort($categoryTotals);
ksort($dailyTotals);

$entryCount = count($filteredExpenses);
$averageExpense = $entryCount > 0 ? round($totalSpent / $entryCount, 2) : 0;
$remaining = $budget - $totalSpent;
$usagePercent = $budget > 0 ? round(($totalSpent / $budget) * 100, 2) : 0;
$usagePercentForBar = (int)min(100, round($usagePercent));
$usageBarClass = $usagePercent >= 100 ? 'bg-danger' : ($usagePercent >= 75 ? 'bg-warning' : 'bg-success');

$prevMonthTs = strtotime('-1 month', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$prevMonth = (int)date('n', $prevMonthTs);
$prevYear = (int)date('Y', $prevMonthTs);
$previousMonthSpent = getTotalExpenses($userId, $prevMonth, $prevYear);
$changeValue = $totalSpent - $previousMonthSpent;
$changePercent = $previousMonthSpent > 0 ? round(($changeValue / $previousMonthSpent) * 100, 2) : null;

$insights = [];
if ($entryCount === 0) {
    $insights[] = 'No expenses found for the selected filters. Try removing category/date filters.';
}
if ($budget <= 0) {
    $insights[] = 'No budget set for this month. Add a monthly budget to track overspending.';
} elseif ($totalSpent > $budget) {
    $insights[] = 'You are over budget for this period. Consider reducing high-value categories first.';
} elseif ($usagePercent >= 80) {
    $insights[] = 'Budget usage is above 80%. Keep upcoming expenses limited to avoid crossing budget.';
}
if (!empty($categoryTotals)) {
    $largestCategory = array_key_first($categoryTotals);
    $largestAmount = $categoryTotals[$largestCategory];
    $largestPercent = $totalSpent > 0 ? round(($largestAmount / $totalSpent) * 100, 2) : 0;
    if ($largestPercent >= 50) {
        $insights[] = $largestCategory . ' contributes ' . $largestPercent . '% of current spend; this is the biggest optimization lever.';
    }
}

$topByAmount = $filteredExpenses;
usort($topByAmount, function ($a, $b) {
    return ((float)$b['amount']) <=> ((float)$a['amount']);
});
$topByAmount = array_slice($topByAmount, 0, 5);

include 'navbar.php';
?>

<div class="card topbar-card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="section-title">Reports</h2>
            <div class="muted-small">Analyze spending trends, category split, and monthly budget performance.</div>
        </div>
        <a href="add_expense.php" class="btn btn-primary">Add Expense</a>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <h4 class="mb-3">Filter Report</h4>
        <form method="GET" action="report.php">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= esc($year) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category (optional)</label>
                    <select name="category" class="form-select">
                        <option value="">All categories</option>
                        <?php
                        $allCategories = ['Food', 'Transport', 'Shopping', 'Bills', 'Entertainment', 'Health', 'Education', 'Other'];
                        foreach ($allCategories as $item):
                        ?>
                            <option value="<?= esc($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= esc($item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= esc($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= esc($dateTo) ?>">
                </div>
                <div class="col-md-1 d-grid">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a
                    class="btn btn-outline-primary btn-sm"
                    href="report.php?month=<?= urlencode((string)$month) ?>&year=<?= urlencode((string)$year) ?>&category=<?= urlencode($category) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&export=csv"
                >Export CSV</a>
                <a class="btn btn-outline-secondary btn-sm" href="report.php">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Total Spent</div>
                <div class="stat-value text-danger">₹<?= number_format($totalSpent, 2) ?></div>
                <div class="muted-small"><?= date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Entries</div>
                <div class="stat-value text-primary"><?= $entryCount ?></div>
                <div class="muted-small">Filtered transactions</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Average Expense</div>
                <div class="stat-value text-success">₹<?= number_format($averageExpense, 2) ?></div>
                <div class="muted-small">Per transaction</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Top Expense</div>
                <div class="stat-value text-warning">
                    <?= $topExpense ? '₹' . number_format((float)$topExpense['amount'], 2) : '—' ?>
                </div>
                <div class="muted-small"><?= $topExpense ? esc($topExpense['title']) : 'No data' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h4 class="mb-3">Budget Performance</h4>
                <?php if ($budget > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Spent vs Budget</span>
                        <span class="muted-small">₹<?= number_format($totalSpent, 2) ?> / ₹<?= number_format($budget, 2) ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 22px;">
                        <div class="progress-bar <?= $usageBarClass ?>" role="progressbar" style="width: <?= $usagePercentForBar ?>%;">
                            <?= number_format($usagePercent, 1) ?>%
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <div class="muted-small">Remaining</div>
                                <div class="h5 mb-0 <?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                                    ₹<?= number_format(abs($remaining), 2) ?> <?= $remaining < 0 ? 'over' : 'left' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <div class="muted-small">Vs Previous Month</div>
                                <div class="h5 mb-0 <?= $changeValue > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $changeValue > 0 ? '+' : '' ?>₹<?= number_format($changeValue, 2) ?>
                                </div>
                                <div class="muted-small">
                                    <?php if ($changePercent !== null): ?>
                                        <?= $changePercent > 0 ? '+' : '' ?><?= $changePercent ?>%
                                    <?php else: ?>
                                        No baseline
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No budget set for this month. Use the Budget page to define a monthly limit.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h4 class="mb-3">Insights</h4>
                <?php if (empty($insights)): ?>
                    <div class="text-muted">No special alerts for this period. Spending pattern looks stable.</div>
                <?php else: ?>
                    <?php foreach ($insights as $insight): ?>
                        <div class="alert alert-info mb-2"><?= esc($insight) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h4 class="mb-3">Category Breakdown</h4>
                <?php if (empty($categoryTotals)): ?>
                    <div class="text-muted">No category data for selected filters.</div>
                <?php else: ?>
                    <?php foreach ($categoryTotals as $cat => $amt): ?>
                        <?php $pct = $totalSpent > 0 ? round(($amt / $totalSpent) * 100, 1) : 0; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= esc($cat) ?></span>
                                <span class="muted-small">₹<?= number_format($amt, 2) ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?= getCategoryProgressColor($cat) ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h4 class="mb-3">Daily Spend Trend</h4>
                <?php if (empty($dailyTotals)): ?>
                    <div class="text-muted">No daily trend data for selected filters.</div>
                <?php else: ?>
                    <?php $maxDaily = max($dailyTotals); ?>
                    <?php foreach ($dailyTotals as $date => $amt): ?>
                        <?php $w = $maxDaily > 0 ? round(($amt / $maxDaily) * 100, 1) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="muted-small"><?= esc($date) ?></span>
                                <span class="muted-small">₹<?= number_format($amt, 2) ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?= $w ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <h4 class="mb-3">Top 5 Expenses (by amount)</h4>
        <?php if (empty($topByAmount)): ?>
            <div class="text-muted">No expenses available for selected filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topByAmount as $expense): ?>
                            <tr>
                                <td><?= esc($expense['title']) ?></td>
                                <td><?= esc($expense['category']) ?></td>
                                <td class="text-danger fw-semibold">₹<?= number_format((float)$expense['amount'], 2) ?></td>
                                <td><?= esc($expense['date']) ?></td>
                                <td><?= esc($expense['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
