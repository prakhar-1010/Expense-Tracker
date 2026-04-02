<?php
require_once 'config.php';
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid app-shell">
    <div class="row">
        <aside class="col-lg-2 col-md-3 sidebar p-4">
            <div class="brand-box mb-4">Expense <span class="brand-dim">Tracker</span></div>

            <div class="user-info">
                Signed in as<br><strong><?= esc(getCurrentUsername()) ?></strong>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
                <a class="nav-link <?= $currentPage === 'add_expense.php' ? 'active' : '' ?>" href="add_expense.php">Add Expense</a>
                <a class="nav-link <?= $currentPage === 'view_expense.php' ? 'active' : '' ?>" href="view_expense.php">View Expenses</a>
                <a class="nav-link <?= $currentPage === 'budget.php' ? 'active' : '' ?>" href="budget.php">Budget</a>
                <a class="nav-link <?= $currentPage === 'report.php' ? 'active' : '' ?>" href="report.php">Reports</a>
                <a class="nav-link nav-logout" href="logout.php">Logout</a>
            </nav>
        </aside>
        <main class="col-lg-10 col-md-9 p-4">
