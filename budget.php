<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$messageType = 'info';

$month = isset($_POST['month']) ? (int)$_POST['month'] : (isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n'));
$year = isset($_POST['year']) ? (int)$_POST['year'] : (isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y'));
$categories = getDefaultCategories();

if ($month === (int)date('n') && $year === (int)date('Y')) {
    applyRecurringExpensesForMonth($userId, $month, $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? 'monthly_budget');
    if ($action === 'category_budget') {
        $categoryName = sanitize($_POST['category_name'] ?? '');
        $categoryAmount = validateAmount($_POST['category_amount'] ?? '');
        if ($categoryAmount === false || $categoryName === '') {
            $message = 'Please enter valid category budget details.';
            $messageType = 'danger';
        } else {
            $result = setCategoryBudget($userId, $month, $year, $categoryName, $categoryAmount);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    } else {
        $amount = validateAmount($_POST['amount'] ?? '');
        if ($amount === false) {
            $message = 'Please enter a valid budget amount greater than 0.';
            $messageType = 'danger';
        } else {
            $result = setBudget($userId, $month, $year, $amount);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    }
}

$budget = getBudget($userId, $month, $year);
$spent = getTotalExpenses($userId, $month, $year);
$remaining = $budget - $spent;
$expenseCount = getMonthlyExpenseCount($userId, $month, $year);
$usage = $budget > 0 ? min(100, round(($spent / $budget) * 100, 2)) : 0;
$categoryBudgetStatus = getCategoryBudgetStatus($userId, $month, $year);
$categoryBudgets = getCategoryBudgets($userId, $month, $year);

include 'navbar.php';
?>

<div class="card topbar-card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="section-title">Budget Planner</h2>
            <div class="muted-small">Manage monthly budgets and compare them with actual expenses.</div>
        </div>
        <a href="add_expense.php" class="btn btn-primary">Add Expense</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Budget</div>
                <div class="stat-value text-primary">₹<?= number_format($budget, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Spent</div>
                <div class="stat-value text-danger">₹<?= number_format($spent, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Remaining</div>
                <div class="stat-value <?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                    ₹<?= number_format($remaining, 2) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Entries</div>
                <div class="stat-value text-dark"><?= (int)$expenseCount ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Set Monthly Budget</h4>

                <form method="POST" action="budget.php">
                    <input type="hidden" name="action" value="monthly_budget">
                    <div class="mb-3">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= esc($year) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="<?= $budget > 0 ? esc(number_format($budget, 2, '.', '')) : '' ?>" required>
                    </div>

                    <button type="submit" class="btn btn-success">Save Budget</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3"><?= date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year ?> Overview</h4>

                <?php if ($budget > 0): ?>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Usage</span>
                        <strong><?= number_format($usage, 2) ?>%</strong>
                    </div>

                    <div class="progress mb-4" style="height: 22px;">
                        <div
                            class="progress-bar <?= $usage >= 100 ? 'bg-danger' : ($usage >= 75 ? 'bg-warning' : 'bg-success') ?>"
                            role="progressbar"
                            style="width: <?= $usage ?>%;"
                        >
                            <?= number_format($usage, 0) ?>%
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <div class="muted-small">Average per expense</div>
                                <div class="h5 mb-0">
                                    ₹<?= $expenseCount > 0 ? number_format($spent / $expenseCount, 2) : '0.00' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded">
                                <div class="muted-small">Status</div>
                                <div class="h5 mb-0 <?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $remaining < 0 ? 'Over Budget' : 'On Track' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No budget set for this selected month.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Set Category Budget</h4>
                <form method="POST" action="budget.php">
                    <input type="hidden" name="action" value="category_budget">
                    <input type="hidden" name="month" value="<?= esc($month) ?>">
                    <input type="hidden" name="year" value="<?= esc($year) ?>">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_name" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $item): ?>
                                <option value="<?= esc($item) ?>"><?= esc($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Budget amount</label>
                        <input type="number" name="category_amount" class="form-control" min="0.01" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Save Category Budget</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Category Budget Status</h4>
                <?php if (empty($categoryBudgetStatus)): ?>
                    <div class="text-muted">No category data for this period.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Spent</th>
                                    <th>Budget</th>
                                    <th>Remaining</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryBudgetStatus as $row): ?>
                                    <tr>
                                        <td><?= esc($row['category']) ?></td>
                                        <td>₹<?= number_format($row['spent'], 2) ?></td>
                                        <td><?= isset($categoryBudgets[$row['category']]) ? '₹' . number_format($row['budget'], 2) : '—' ?></td>
                                        <td class="<?= $row['remaining'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= isset($categoryBudgets[$row['category']]) ? '₹' . number_format($row['remaining'], 2) : '—' ?>
                                        </td>
                                        <td><?= $row['budget'] > 0 ? number_format($row['usage'], 1) . '%' : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
