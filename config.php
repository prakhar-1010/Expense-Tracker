<?php
define('XML_FILE', __DIR__ . '/data.xml');

function startSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getDefaultCategories()
{
    return ['Food', 'Transport', 'Shopping', 'Bills', 'Entertainment', 'Health', 'Education', 'Other'];
}

function setCategoryBudget($userId, $month, $year, $category, $amount)
{
    $month = (int)$month;
    $year = (int)$year;
    $category = sanitize($category);
    $amount = validateAmount($amount);

    if ($userId === '' || $month < 1 || $month > 12 || $year < 2000 || $year > 2100 || $category === '' || $amount === false) {
        return ['success' => false, 'message' => 'Invalid category budget data.'];
    }

    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    foreach ($xml->category_budgets->category_budget as $row) {
        if (
            (string)$row->userid === (string)$userId &&
            (int)$row->month === $month &&
            (int)$row->year === $year &&
            strcasecmp((string)$row->category, $category) === 0
        ) {
            $row->amount = number_format($amount, 2, '.', '');
            if (saveXML($xml)) {
                return ['success' => true, 'message' => 'Category budget updated.'];
            }
            return ['success' => false, 'message' => 'Failed to update category budget.'];
        }
    }

    $id = generateNextId($xml, 'category_budgets', 'category_budget', 'cb');
    $budget = $xml->category_budgets->addChild('category_budget');
    $budget->addAttribute('id', $id);
    $budget->addChild('userid', $userId);
    $budget->addChild('month', (string)$month);
    $budget->addChild('year', (string)$year);
    $budget->addChild('category', $category);
    $budget->addChild('amount', number_format($amount, 2, '.', ''));

    if (saveXML($xml)) {
        return ['success' => true, 'message' => 'Category budget saved.'];
    }

    return ['success' => false, 'message' => 'Failed to save category budget.'];
}

function getCategoryBudgets($userId, $month, $year)
{
    $xml = loadXML();
    $result = [];
    if (!$xml) {
        return $result;
    }

    foreach ($xml->category_budgets->category_budget as $row) {
        if (
            (string)$row->userid === (string)$userId &&
            (int)$row->month === (int)$month &&
            (int)$row->year === (int)$year
        ) {
            $result[(string)$row->category] = (float)$row->amount;
        }
    }

    ksort($result);
    return $result;
}

function getCategoryBudgetStatus($userId, $month, $year)
{
    $totals = getCategoryTotals($userId, $month, $year);
    $budgets = getCategoryBudgets($userId, $month, $year);
    $categories = array_values(array_unique(array_merge(array_keys($totals), array_keys($budgets), getDefaultCategories())));
    sort($categories);

    $rows = [];
    foreach ($categories as $category) {
        $spent = (float)($totals[$category] ?? 0);
        $budget = (float)($budgets[$category] ?? 0);
        $usage = $budget > 0 ? round(($spent / $budget) * 100, 2) : 0;
        $rows[] = [
            'category' => $category,
            'spent' => $spent,
            'budget' => $budget,
            'remaining' => $budget - $spent,
            'usage' => $usage
        ];
    }

    return $rows;
}

function getRecurringExpenses($userId, $activeOnly = true)
{
    $xml = loadXML();
    $result = [];
    if (!$xml) {
        return $result;
    }

    foreach ($xml->recurring_expenses->recurring as $row) {
        if ((string)$row->userid !== (string)$userId) {
            continue;
        }
        if ($activeOnly && (string)$row->active !== '1') {
            continue;
        }

        $result[] = [
            'id' => (string)$row['id'],
            'title' => (string)$row->title,
            'amount' => (float)$row->amount,
            'category' => (string)$row->category,
            'description' => (string)$row->description,
            'start_date' => (string)$row->start_date,
            'frequency' => (string)$row->frequency,
            'last_applied_month' => (string)$row->last_applied_month,
            'active' => (string)$row->active === '1',
            'userid' => (string)$row->userid
        ];
    }

    return $result;
}

function addRecurringExpense($userId, $title, $amount, $category, $startDate, $description = '', $frequency = 'monthly')
{
    $title = sanitize($title);
    $category = sanitize($category);
    $description = sanitize($description);
    $amount = validateAmount($amount);
    $startDate = sanitize($startDate);
    $frequency = sanitize($frequency);

    if ($frequency !== 'monthly') {
        return ['success' => false, 'message' => 'Only monthly recurring frequency is currently supported.'];
    }
    if ($userId === '' || $title === '' || $category === '' || $amount === false || !validateDate($startDate)) {
        return ['success' => false, 'message' => 'Invalid recurring expense data.'];
    }

    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    $id = generateNextId($xml, 'recurring_expenses', 'recurring', 'r');
    $row = $xml->recurring_expenses->addChild('recurring');
    $row->addAttribute('id', $id);
    $row->addChild('userid', $userId);
    $row->addChild('title', $title);
    $row->addChild('amount', number_format($amount, 2, '.', ''));
    $row->addChild('category', $category);
    $row->addChild('description', $description);
    $row->addChild('start_date', $startDate);
    $row->addChild('frequency', 'monthly');
    $row->addChild('last_applied_month', '');
    $row->addChild('active', '1');

    if (saveXML($xml)) {
        return ['success' => true, 'message' => 'Recurring expense saved successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to save recurring expense.'];
}

function applyRecurringExpensesForMonth($userId, $month, $year)
{
    $month = (int)$month;
    $year = (int)$year;
    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'added' => 0, 'message' => 'Unable to load data file.'];
    }

    $added = 0;
    $targetMonthKey = sprintf('%04d-%02d', $year, $month);

    foreach ($xml->recurring_expenses->recurring as $recurring) {
        if ((string)$recurring->userid !== (string)$userId || (string)$recurring->active !== '1') {
            continue;
        }

        $startDate = (string)$recurring->start_date;
        if (!validateDate($startDate)) {
            continue;
        }
        $startTs = strtotime($startDate);
        $targetTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
        if ($startTs > $targetTs) {
            continue;
        }

        if ((string)$recurring->last_applied_month === $targetMonthKey) {
            continue;
        }

        $day = (int)date('j', $startTs);
        $lastDay = (int)date('t', $targetTs);
        $useDay = min($day, $lastDay);
        $date = sprintf('%04d-%02d-%02d', $year, $month, $useDay);
        $recurringId = (string)$recurring['id'];

        $alreadyExists = false;
        foreach ($xml->expenses->expense as $expense) {
            if (
                (string)$expense->userid === (string)$userId &&
                (string)$expense->recurringid === $recurringId &&
                (string)$expense->date === $date
            ) {
                $alreadyExists = true;
                break;
            }
        }
        if ($alreadyExists) {
            $recurring->last_applied_month = $targetMonthKey;
            continue;
        }

        $id = generateNextId($xml, 'expenses', 'expense', 'e');
        $expense = $xml->expenses->addChild('expense');
        $expense->addAttribute('id', $id);
        $expense->addChild('title', sanitize((string)$recurring->title));
        $expense->addChild('amount', number_format((float)$recurring->amount, 2, '.', ''));
        $expense->addChild('category', sanitize((string)$recurring->category));
        $expense->addChild('date', $date);
        $expense->addChild('description', sanitize((string)$recurring->description));
        $expense->addChild('userid', $userId);
        $expense->addChild('recurringid', $recurringId);
        $recurring->last_applied_month = $targetMonthKey;
        $added++;
    }

    if (saveXML($xml)) {
        return ['success' => true, 'added' => $added, 'message' => 'Recurring expenses applied.'];
    }

    return ['success' => false, 'added' => 0, 'message' => 'Failed to apply recurring expenses.'];
}

function getMonthlyTotalsForLastMonths($userId, $months = 6)
{
    $months = max(1, (int)$months);
    $result = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} month");
        $m = (int)date('n', $ts);
        $y = (int)date('Y', $ts);
        $result[] = [
            'month' => $m,
            'year' => $y,
            'label' => date('M Y', $ts),
            'total' => getTotalExpenses($userId, $m, $y)
        ];
    }
    return $result;
}

startSession();

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sanitize($value)
{
    return trim((string)$value);
}

function validateAmount($amount)
{
    if ($amount === null || $amount === '') {
        return false;
    }

    if (!is_numeric($amount)) {
        return false;
    }

    $amount = (float)$amount;

    if ($amount <= 0) {
        return false;
    }

    return round($amount, 2);
}

function validateDate($date)
{
    $date = sanitize($date);
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function loadXML()
{
    if (!file_exists(XML_FILE)) {
        return false;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file(XML_FILE);
    if ($xml) {
        ensureXMLSections($xml);
    }

    return $xml ?: false;
}

function ensureXMLSections($xml)
{
    $requiredSections = ['users', 'expenses', 'budgets', 'recurring_expenses', 'category_budgets'];
    foreach ($requiredSections as $section) {
        if (!isset($xml->$section)) {
            $xml->addChild($section);
        }
    }
}

function saveXML($xml)
{
    return $xml->asXML(XML_FILE);
}

function requireLogin()
{
    startSession();

    if (!isset($_SESSION['userid'])) {
        header('Location: login.php');
        exit();
    }
}

function isLoggedIn()
{
    startSession();
    return isset($_SESSION['userid']) && $_SESSION['userid'] !== '';
}

function getCurrentUserId()
{
    startSession();
    return $_SESSION['userid'] ?? '';
}

function getCurrentUsername()
{
    startSession();
    return $_SESSION['username'] ?? '';
}

function generateNextId($xml, $section, $node, $prefix)
{
    $max = 0;

    if (isset($xml->$section->$node)) {
        foreach ($xml->$section->$node as $item) {
            $id = (string)$item['id'];
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $id, $matches)) {
                $num = (int)$matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }
    }

    return $prefix . ($max + 1);
}

function registerUser($username, $password, $email)
{
    $username = sanitize($username);
    $email = sanitize($email);

    if ($username === '' || $password === '' || $email === '') {
        return ['success' => false, 'message' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    foreach ($xml->users->user as $user) {
        if (strcasecmp((string)$user->username, $username) === 0) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        if (strcasecmp((string)$user->email, $email) === 0) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }
    }

    $id = generateNextId($xml, 'users', 'user', 'u');

    $user = $xml->users->addChild('user');
    $user->addAttribute('id', $id);
    $user->addChild('username', $username);
    $user->addChild('password', password_hash($password, PASSWORD_DEFAULT));
    $user->addChild('email', $email);
    $user->addChild('createdat', date('Y-m-d'));

    if (saveXML($xml)) {
        return ['success' => true, 'message' => 'Registration successful.'];
    }

    return ['success' => false, 'message' => 'Registration failed.'];
}

function loginUser($username, $password)
{
    $username = sanitize($username);

    if ($username === '' || $password === '') {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    foreach ($xml->users->user as $user) {
        if ((string)$user->username === $username && password_verify($password, (string)$user->password)) {
            startSession();
            $_SESSION['userid'] = (string)$user['id'];
            $_SESSION['username'] = (string)$user->username;

            return ['success' => true, 'message' => 'Login successful.'];
        }
    }

    return ['success' => false, 'message' => 'Invalid username or password.'];
}

function addExpense($userId, $title, $amount, $category, $date, $description = '', $recurringId = '')
{
    $title = sanitize($title);
    $category = sanitize($category);
    $description = sanitize($description);
    $amount = validateAmount($amount);

    if ($userId === '' || $title === '' || $category === '' || $amount === false || !validateDate($date)) {
        return ['success' => false, 'message' => 'Invalid expense data.'];
    }

    $xml = loadXML();
    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    $id = generateNextId($xml, 'expenses', 'expense', 'e');

    $expense = $xml->expenses->addChild('expense');
    $expense->addAttribute('id', $id);
    $expense->addChild('title', $title);
    $expense->addChild('amount', number_format($amount, 2, '.', ''));
    $expense->addChild('category', $category);
    $expense->addChild('date', $date);
    $expense->addChild('description', $description);
    $expense->addChild('userid', $userId);
    $expense->addChild('recurringid', sanitize($recurringId));

    if (saveXML($xml)) {
        return ['success' => true, 'message' => 'Expense added successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to save expense.'];
}

function getExpenses($userId, $category = '', $date = '')
{
    $xml = loadXML();
    $result = [];

    if (!$xml) {
        return $result;
    }

    $category = sanitize($category);
    $date = sanitize($date);

    foreach ($xml->expenses->expense as $expense) {
        if ((string)$expense->userid !== (string)$userId) {
            continue;
        }

        if ($category !== '' && stripos((string)$expense->category, $category) === false) {
            continue;
        }

        if ($date !== '') {
            $expenseDate = (string)$expense->date;

            if (preg_match('/^\d{4}-\d{2}$/', $date)) {
                if (strpos($expenseDate, $date . '-') !== 0) {
                    continue;
                }
            } elseif ($expenseDate !== $date) {
                continue;
            }
        }

        $result[] = [
            'id' => (string)$expense['id'],
            'title' => (string)$expense->title,
            'amount' => (float)$expense->amount,
            'category' => (string)$expense->category,
            'date' => (string)$expense->date,
            'description' => (string)$expense->description,
            'userid' => (string)$expense->userid,
            'recurringid' => (string)$expense->recurringid
        ];
    }

    usort($result, function ($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    return $result;
}

function deleteExpense($userId, $id)
{
    $xml = loadXML();

    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    foreach ($xml->expenses->expense as $index => $expense) {
        if ((string)$expense['id'] === (string)$id && (string)$expense->userid === (string)$userId) {
            unset($xml->expenses->expense[$index]);

            if (saveXML($xml)) {
                return ['success' => true, 'message' => 'Expense deleted successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to delete expense.'];
        }
    }

    return ['success' => false, 'message' => 'Expense not found.'];
}

function setBudget($userId, $month, $year, $amount)
{
    $month = (int)$month;
    $year = (int)$year;
    $amount = validateAmount($amount);

    if ($userId === '' || $month < 1 || $month > 12 || $year < 2000 || $amount === false) {
        return ['success' => false, 'message' => 'Invalid budget data.'];
    }

    $xml = loadXML();

    if (!$xml) {
        return ['success' => false, 'message' => 'Unable to load data file.'];
    }

    foreach ($xml->budgets->budget as $budget) {
        if (
            (string)$budget->userid === (string)$userId &&
            (int)$budget->month === $month &&
            (int)$budget->year === $year
        ) {
            $budget->amount = number_format($amount, 2, '.', '');

            if (saveXML($xml)) {
                return ['success' => true, 'message' => 'Budget updated successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to update budget.'];
        }
    }

    $id = generateNextId($xml, 'budgets', 'budget', 'b');

    $budget = $xml->budgets->addChild('budget');
    $budget->addAttribute('id', $id);
    $budget->addChild('userid', $userId);
    $budget->addChild('month', (string)$month);
    $budget->addChild('year', (string)$year);
    $budget->addChild('amount', number_format($amount, 2, '.', ''));

    if (saveXML($xml)) {
        return ['success' => true, 'message' => 'Budget saved successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to save budget.'];
}

function getBudget($userId, $month, $year)
{
    $xml = loadXML();

    if (!$xml) {
        return 0;
    }

    foreach ($xml->budgets->budget as $budget) {
        if (
            (string)$budget->userid === (string)$userId &&
            (int)$budget->month === (int)$month &&
            (int)$budget->year === (int)$year
        ) {
            return (float)$budget->amount;
        }
    }

    return 0;
}

function getTotalExpenses($userId, $month = null, $year = null)
{
    $expenses = getExpenses($userId);
    $total = 0;

    foreach ($expenses as $expense) {
        if ($month !== null && $year !== null) {
            $expenseMonth = (int)date('n', strtotime($expense['date']));
            $expenseYear = (int)date('Y', strtotime($expense['date']));

            if ($expenseMonth !== (int)$month || $expenseYear !== (int)$year) {
                continue;
            }
        }

        $total += (float)$expense['amount'];
    }

    return round($total, 2);
}

function getRecentExpenses($userId, $limit = 5)
{
    return array_slice(getExpenses($userId), 0, $limit);
}

function getMonthlyExpenseCount($userId, $month, $year)
{
    $count = 0;
    $expenses = getExpenses($userId);

    foreach ($expenses as $expense) {
        $expenseMonth = (int)date('n', strtotime($expense['date']));
        $expenseYear = (int)date('Y', strtotime($expense['date']));

        if ($expenseMonth === (int)$month && $expenseYear === (int)$year) {
            $count++;
        }
    }

    return $count;
}

function getCategoryTotals($userId, $month = null, $year = null)
{
    $totals = [];
    $expenses = getExpenses($userId);

    foreach ($expenses as $expense) {
        if ($month !== null && $year !== null) {
            $expenseMonth = (int)date('n', strtotime($expense['date']));
            $expenseYear = (int)date('Y', strtotime($expense['date']));

            if ($expenseMonth !== (int)$month || $expenseYear !== (int)$year) {
                continue;
            }
        }

        $category = $expense['category'];

        if (!isset($totals[$category])) {
            $totals[$category] = 0;
        }

        $totals[$category] += (float)$expense['amount'];
    }

    arsort($totals);

    return $totals;
}
function getCategoryBadgeClass($category)
{
    $map = [
        'Food'          => 'bg-success',
        'Transport'     => 'bg-info text-dark',
        'Shopping'      => 'bg-warning text-dark',
        'Bills'         => 'bg-danger',
        'Entertainment' => 'bg-purple text-white',
        'Health'        => 'bg-pink text-white',
        'Education'     => 'bg-primary',
        'Other'         => 'bg-secondary',
    ];

    return $map[$category] ?? 'bg-secondary';
}

function getCategoryProgressColor($category)
{
    $map = [
        'Food'          => 'bg-success',
        'Transport'     => 'bg-info',
        'Shopping'      => 'bg-warning',
        'Bills'         => 'bg-danger',
        'Entertainment' => 'bg-primary',
        'Health'        => 'bg-danger',
        'Education'     => 'bg-primary',
        'Other'         => 'bg-secondary',
    ];

    return $map[$category] ?? 'bg-secondary';
}
?>
