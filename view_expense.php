<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$messageType = 'info';
applyRecurringExpensesForMonth($userId, (int)date('n'), (int)date('Y'));

if (isset($_GET['delete']) && $_GET['delete'] !== '') {
    $result = deleteExpense($userId, $_GET['delete']);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'danger';
}
$searchQuery = sanitize($_GET['q'] ?? '');
$quickFilter = sanitize($_GET['quick'] ?? '');
$sortBy = sanitize($_GET['sort'] ?? 'date_desc');
$allExpenses = getExpenses($userId);
$expenses = [];

foreach ($allExpenses as $expense) {

    $expenseTs = strtotime($expense['date']);
    if ($quickFilter === 'this_month') {
        if (date('Y-m', $expenseTs) !== date('Y-m')) {
            continue;
        }
    } elseif ($quickFilter === 'prev_month') {
        if (date('Y-m', $expenseTs) !== date('Y-m', strtotime('-1 month'))) {
            continue;
        }
    } elseif ($quickFilter === 'last_7') {
        if ($expenseTs < strtotime('-6 days') || $expenseTs > strtotime('today 23:59:59')) {
            continue;
        }
    } elseif ($quickFilter === 'last_30') {
        if ($expenseTs < strtotime('-29 days') || $expenseTs > strtotime('today 23:59:59')) {
            continue;
        }
    }
    $matchesSearch = true;
    if ($searchQuery !== '') {
        if (preg_match('/^\d{4}-\d{2}$/', $searchQuery)) {
            $matchesSearch = strpos($expense['date'], $searchQuery . '-') === 0;
        } elseif (validateDate($searchQuery)) {
            $matchesSearch = $expense['date'] === $searchQuery;
        } else {
            $q = strtolower($searchQuery);
            $haystack = strtolower(
                $expense['title'] . ' ' .
                $expense['category'] . ' ' .
                $expense['description'] . ' ' .
                $expense['date']
            );
            $matchesSearch = strpos($haystack, $q) !== false;
        }
    }

    if ($matchesSearch) {
        $expenses[] = $expense;
        continue;
    }
}
usort($expenses, function ($a, $b) use ($sortBy) {
    if ($sortBy === 'date_asc') {
        return strcmp($a['date'], $b['date']);
    }
    if ($sortBy === 'amount_desc') {
        return ((float)$b['amount']) <=> ((float)$a['amount']);
    }
    if ($sortBy === 'amount_asc') {
        return ((float)$a['amount']) <=> ((float)$b['amount']);
    }
    if ($sortBy === 'title_asc') {
        return strcmp(strtolower($a['title']), strtolower($b['title']));
    }
    if ($sortBy === 'title_desc') {
        return strcmp(strtolower($b['title']), strtolower($a['title']));
    }
    return strcmp($b['date'], $a['date']);
});

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$totalSpent = getTotalExpenses($userId, $currentMonth, $currentYear);
$totalCount = getMonthlyExpenseCount($userId, $currentMonth, $currentYear);
$categoryTotals = [];
foreach ($expenses as $expense) {
    $cat = $expense['category'];
    if (!isset($categoryTotals[$cat])) {
        $categoryTotals[$cat] = 0;
    }
    $categoryTotals[$cat] += (float)$expense['amount'];
}
arsort($categoryTotals);

include 'navbar.php';
?>

<div class="card topbar-card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="section-title">View Expenses</h2>
            <div class="muted-small">Browse, search, and delete your saved expenses.</div>
        </div>
        <a href="add_expense.php" class="btn btn-primary">Add Expense</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">This Month Total</div>
                <div class="stat-value text-danger">₹<?= number_format($totalSpent, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">This Month Entries</div>
                <div class="stat-value text-primary"><?= (int)$totalCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Filtered Results</div>
                <div class="stat-value text-success"><?= count($expenses) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Search</h4>

                <form method="GET" action="view_expense.php">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label">Search by title/date/category/description</label>
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                value="<?= esc($searchQuery) ?>"
                                placeholder="Examples: Park, Entertainment, 2026-04, 2026-04-01"
                            >
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sort</label>
                            <select name="sort" class="form-select">
                                <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Date (newest)</option>
                                <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>Date (oldest)</option>
                                <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Amount (high to low)</option>
                                <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>Amount (low to high)</option>
                                <option value="title_asc" <?= $sortBy === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                                <option value="title_desc" <?= $sortBy === 'title_desc' ? 'selected' : '' ?>>Title (Z-A)</option>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3 d-grid">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                    <input type="hidden" name="quick" value="<?= esc($quickFilter) ?>">
                </form>
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <?php
                    $chips = [
                        '' => 'All',
                        'this_month' => 'This Month',
                        'prev_month' => 'Prev Month',
                        'last_7' => 'Last 7 Days',
                        'last_30' => 'Last 30 Days'
                    ];
                    foreach ($chips as $chipKey => $chipLabel):
                        $isActive = $quickFilter === $chipKey;
                        $chipClass = $isActive ? 'btn-primary' : 'btn-outline-secondary';
                    ?>
                        <a
                            class="btn btn-sm <?= $chipClass ?>"
                            href="view_expense.php?q=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sortBy) ?>&quick=<?= urlencode($chipKey) ?>"
                        ><?= esc($chipLabel) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Expense List</h4>

                <?php if (empty($expenses)): ?>
                    <div class="text-muted">No expenses found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?= esc($expense['title']) ?></td>
                                        <td><?= esc($expense['category']) ?></td>
                                        <td>₹<?= number_format($expense['amount'], 2) ?></td>
                                        <td><?= esc($expense['date']) ?></td>
                                        <td><?= esc($expense['description']) ?></td>
                                        <td>
                                            <a
                                                href="view_expense.php?delete=<?= urlencode($expense['id']) ?>&q=<?= urlencode($searchQuery) ?>&sort=<?= urlencode($sortBy) ?>&quick=<?= urlencode($quickFilter) ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this expense?');"
                                            >
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Category Summary</h4>

                <?php if (empty($categoryTotals)): ?>
                    <div class="text-muted">No category data for this month.</div>
                <?php else: ?>
                    <?php foreach ($categoryTotals as $category => $amount): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?= esc($category) ?></span>
                            <strong>₹<?= number_format($amount, 2) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
