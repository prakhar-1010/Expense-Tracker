<?php
/**
 * login.php
 * ============================================================
 * User Login Page
 *
 * - Displays the login form
 * - Processes POST: validates credentials, creates session
 * - Supports "Remember me" cookie (30-day lifetime)
 * - Shows success message if redirected from register.php
 *
 * Session handling notes:
 *   On successful login, two session variables are set:
 *     $_SESSION['user_id']  – the user's XML id (e.g. "u3")
 *     $_SESSION['username'] – the user's plain username
 *   These persist for the browser session (or until logout.php
 *   destroys them).
 * ============================================================
 */
require_once 'config.php';
startSession();

// If already authenticated, skip the login page
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error   = '';
// Pick up success message stored by register.php (PRG pattern)
$success = $_SESSION['reg_success'] ?? '';
unset($_SESSION['reg_success']);

// ── Process form submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password']           ?? '';  // NOT sanitized before verify
    $remember = isset($_POST['remember_me']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = loginUser($username, $password);

        if ($result['success']) {
            // ── Remember me cookie ───────────────────────────
            // Stores the username in a browser cookie for 30 days.
            // On the next visit, the username field is pre-filled.
            if ($remember) {
                setcookie('remember_user', $username, time() + (30 * 24 * 60 * 60), '/');
            } else {
                // Clear any existing cookie if "remember me" is unchecked
                setcookie('remember_user', '', time() - 3600, '/');
            }

            header('Location: index.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Pre-fill username from "remember me" cookie
$rememberedUser = sanitize($_COOKIE['remember_user'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Expense Tracker</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="style.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-sm-10 col-md-6 col-lg-4">

            <div class="card shadow-lg border-0 auth-card">
                <div class="card-body p-5">

                    <!-- Logo / Heading -->
                    <div class="text-center mb-4">
                        <i class="bi bi-wallet2 display-3 text-primary"></i>
                        <h2 class="mt-2 fw-bold">Expense Tracker</h2>
                        <p class="text-muted mb-0">Sign in to continue</p>
                    </div>

                    <!-- Success message from registration -->
                    <?php if ($success): ?>
                        <div class="alert alert-success flash-alert alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= esc($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Error message -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger flash-alert alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?= esc($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form (POST to same page) -->
                    <form method="POST" action="login.php" id="loginForm">

                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       placeholder="Your username"
                                       value="<?= esc($rememberedUser) ?>"
                                       autocomplete="username"
                                       required>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="Your password"
                                       autocomplete="current-password"
                                       required>
                                <!-- Toggle password visibility -->
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button"
                                        data-target="password"
                                        title="Show/hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember me -->
                        <div class="mb-4 form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="remember_me"
                                   name="remember_me"
                                <?= $rememberedUser ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="remember_me">
                                Remember me for 30 days
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </div>

                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-0 text-muted">Don't have an account?
                            <a href="register.php" class="fw-semibold text-decoration-none">Register here</a>
                        </p>
                    </div>

                </div><!-- /card-body -->
            </div><!-- /card -->

        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="script.js"></script>
</body>
</html>
