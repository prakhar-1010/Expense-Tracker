<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();
$message = '';
$messageType = 'info';
$importErrors = [];

$title = '';
$amount = '';
$category = '';
$date = date('Y-m-d');
$description = '';

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$recurringApply = applyRecurringExpensesForMonth($userId, $currentMonth, $currentYear);
$currentBudget = getBudget($userId, $currentMonth, $currentYear);
$currentSpent = getTotalExpenses($userId, $currentMonth, $currentYear);
$currentRemaining = $currentBudget - $currentSpent;
$recentExpenses = getRecentExpenses($userId, 5);
$recurringExpenses = getRecurringExpenses($userId);
$categories = getDefaultCategories();

if (isset($_GET['download_template']) && $_GET['download_template'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="expense_import_template.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['title', 'amount', 'category', 'date', 'description']);
    fputcsv($fp, ['Groceries', '1200.00', 'Food', date('Y-m-d'), 'Weekly shopping']);
    fputcsv($fp, ['Bus Pass', '850.00', 'Transport', date('Y-m-d'), 'Monthly pass']);
    fclose($fp);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recurring_submit'])) {
    $result = addRecurringExpense(
        $userId,
        sanitize($_POST['recurring_title'] ?? ''),
        sanitize($_POST['recurring_amount'] ?? ''),
        sanitize($_POST['recurring_category'] ?? ''),
        sanitize($_POST['recurring_start_date'] ?? date('Y-m-d')),
        sanitize($_POST['recurring_description'] ?? ''),
        'monthly'
    );
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'danger';
    $recurringExpenses = getRecurringExpenses($userId);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv_submit'])) {
    if (!isset($_FILES['expense_csv']) || $_FILES['expense_csv']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please choose a valid CSV file to import.';
        $messageType = 'danger';
    } else {
        $fileName = $_FILES['expense_csv']['name'] ?? '';
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $message = 'Only .csv files are allowed for import.';
            $messageType = 'danger';
        } else {
            $handle = fopen($_FILES['expense_csv']['tmp_name'], 'r');

            if ($handle === false) {
                $message = 'Unable to read uploaded CSV file.';
                $messageType = 'danger';
            } else {
                $added = 0;
                $failed = 0;
                $rowNum = 0;

                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;

                    if ($row === [null] || count(array_filter($row, function ($value) { return trim((string)$value) !== ''; })) === 0) {
                        continue;
                    }

                    if (
                        $rowNum === 1 &&
                        isset($row[0]) &&
                        strcasecmp(trim((string)$row[0]), 'title') === 0
                    ) {
                        continue;
                    }

                    $rowTitle = sanitize($row[0] ?? '');
                    $rowAmount = sanitize($row[1] ?? '');
                    $rowCategory = sanitize($row[2] ?? '');
                    $rowDate = sanitize($row[3] ?? '');
                    $rowDescription = sanitize($row[4] ?? '');
                    $validAmount = validateAmount($rowAmount);

                    if ($rowTitle === '' || $rowCategory === '' || $validAmount === false || !validateDate($rowDate)) {
                        $failed++;
                        $importErrors[] = 'Row ' . $rowNum . ': invalid data (expected title, amount>0, category, valid date).';
                        continue;
                    }

                    $result = addExpense($userId, $rowTitle, $validAmount, $rowCategory, $rowDate, $rowDescription);
                    if ($result['success']) {
                        $added++;
                    } else {
                        $failed++;
                        $importErrors[] = 'Row ' . $rowNum . ': ' . $result['message'];
                    }
                }

                fclose($handle);

                if ($added > 0 && $failed === 0) {
                    $message = 'CSV import successful. Added ' . $added . ' expense(s).';
                    $messageType = 'success';
                } elseif ($added > 0) {
                    $message = 'CSV import completed with partial success. Added ' . $added . ', failed ' . $failed . '.';
                    $messageType = 'warning';
                } else {
                    $message = 'CSV import failed. Please fix file data and try again.';
                    $messageType = 'danger';
                }

                if ($added > 0) {
                    $currentBudget = getBudget($userId, $currentMonth, $currentYear);
                    $currentSpent = getTotalExpenses($userId, $currentMonth, $currentYear);
                    $currentRemaining = $currentBudget - $currentSpent;
                    $recentExpenses = getRecentExpenses($userId, 5);
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $amount = sanitize($_POST['amount'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $date = sanitize($_POST['date'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    $validAmount = validateAmount($amount);

    if ($title === '' || $category === '' || !$validAmount || !validateDate($date)) {
        $message = 'Please enter valid expense details. Amount must be greater than 0.';
        $messageType = 'danger';
    } else {
        $result = addExpense($userId, $title, $validAmount, $category, $date, $description);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';

        if ($result['success']) {
            $title = '';
            $amount = '';
            $category = '';
            $date = date('Y-m-d');
            $description = '';

            $currentBudget = getBudget($userId, $currentMonth, $currentYear);
            $currentSpent = getTotalExpenses($userId, $currentMonth, $currentYear);
            $currentRemaining = $currentBudget - $currentSpent;
            $recentExpenses = getRecentExpenses($userId, 5);
        }
    }
}

if ($message === '' && $recurringApply['success'] && (int)$recurringApply['added'] > 0) {
    $message = 'Auto-added ' . (int)$recurringApply['added'] . ' recurring expense(s) for this month.';
    $messageType = 'success';
    $currentSpent = getTotalExpenses($userId, $currentMonth, $currentYear);
    $currentRemaining = $currentBudget - $currentSpent;
    $recentExpenses = getRecentExpenses($userId, 5);
}
include 'navbar.php';
?>

<div class="card topbar-card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="section-title">Add Expense</h2>
            <div class="muted-small">Add a new expense and track it instantly on your dashboard.</div>
        </div>
        <a href="view_expense.php" class="btn btn-primary">View All Expenses</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">This Month Budget</div>
                <div class="stat-value text-primary">₹<?= number_format($currentBudget, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">This Month Spent</div>
                <div class="stat-value text-danger">₹<?= number_format($currentSpent, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="muted-small">Remaining</div>
                <div class="stat-value <?= $currentRemaining < 0 ? 'text-danger' : 'text-success' ?>">
                    ₹<?= number_format($currentRemaining, 2) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>
<?php if (!empty($importErrors)): ?>
    <div class="alert alert-danger">
        <strong>Import row errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($importErrors as $importError): ?>
                <li><?= esc($importError) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Expense Details</h4>

                <form method="POST" action="add_expense.php">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" value="<?= esc($title) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="<?= esc($amount) ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?= esc($date) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $item): ?>
                                <option value="<?= esc($item) ?>" <?= $category === $item ? 'selected' : '' ?>>
                                    <?= esc($item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= esc($description) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Import Expenses (CSV)</h4>
                <div class="muted-small mb-3">
                    CSV columns: <strong>title, amount, category, date, description</strong><br>
                    Date format: <strong>YYYY-MM-DD</strong> (header row is optional).
                </div>
                <div class="mb-3">
                    <a href="add_expense.php?download_template=1" class="btn btn-outline-secondary btn-sm">Download Sample CSV</a>
                </div>

                <form method="POST" action="add_expense.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Choose CSV file</label>
                        <input type="file" name="expense_csv" class="form-control" accept=".csv,text/csv" required>
                    </div>
                    <button type="submit" name="import_csv_submit" value="1" class="btn btn-outline-primary">
                        Import CSV
                    </button>
                </form>
            </div>
        </div>
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h4 class="mb-3">Add Recurring Expense</h4>
                <form method="POST" action="add_expense.php">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="recurring_title" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" name="recurring_amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start date</label>
                            <input type="date" name="recurring_start_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Category</label>
                        <select name="recurring_category" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $item): ?>
                                <option value="<?= esc($item) ?>"><?= esc($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="recurring_description" class="form-control">
                    </div>
                    <button type="submit" name="add_recurring_submit" value="1" class="btn btn-outline-primary">Save Recurring</button>
                </form>
                <hr>
                <div class="muted-small mb-2">Active recurring rules: <?= count($recurringExpenses) ?></div>
                <?php if (!empty($recurringExpenses)): ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr><th>Title</th><th>Amount</th><th>Start</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recurringExpenses, 0, 5) as $r): ?>
                                    <tr>
                                        <td><?= esc($r['title']) ?></td>
                                        <td>₹<?= number_format((float)$r['amount'], 2) ?></td>
                                        <td><?= esc($r['start_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card content-card">
            <div class="card-body p-4">
                <h4 class="mb-3">Recent Expenses</h4>

                <?php if (empty($recentExpenses)): ?>
                    <div class="text-muted">No expenses added yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExpenses as $expense): ?>
                                    <tr>
                                        <td><?= esc($expense['title']) ?></td>
                                        <td>₹<?= number_format($expense['amount'], 2) ?></td>
                                        <td><?= esc($expense['date']) ?></td>
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
