<?php
require_once 'config.php';
requireLogin();

$userId = getCurrentUserId();
$id = $_GET['id'] ?? '';

if ($id !== '') {
    deleteExpense($userId, $id);
}

header('Location: view_expense.php');
exit();
