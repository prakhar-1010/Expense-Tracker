<?php
/**
 * register.php
 * ============================================================
 * User Registration Page
 *
 * - Shows a registration form
 * - Server-side validation: username format, email, password
 * - Calls registerUser() which hashes the password and stores
 *   user data in data.xml
 * - On success: stores a flash message in session and redirects
 *   to login.php (Post-Redirect-Get pattern prevents re-submission
 *   on browser refresh)
 * ============================================================
 */
require_once 'config.php';
startSession();

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error    = '';
$formData = ['username' => '', 'email' => ''];  // Preserve values on error

// ── Process registration form ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username        = sanitize($_POST['username']         ?? '');
    $email           = sanitize($_POST['email']            ?? '');
    $password        = $_POST['password']                  ?? '';
    $confirmPassword = $_POST['confirm_password']          ?? '';

    // ── Server-side validation ───────────────────────────────
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'All fields are required.';

    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3–20 characters (letters, digits, underscore only).';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';

    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';

    } else {
        $result = registerUser($username, $password, $email);

        if ($result['success']) {
            // Store success message and redirect (PRG)
            $_SESSION['reg_success'] = $result['message'];
            header('Location: login.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }

    // Keep form values populated on validation error
    $formData = ['username' => $username, 'email' => $email];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-sm-10 col-md-7 col-lg-5">

            <div class="card shadow-lg border-0 auth-card">
                <div class="card-body p-5">

                    <!-- Heading -->
                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus-fill display-3 text-primary"></i>
                        <h2 class="mt-2 fw-bold">Create Account</h2>
                        <p class="text-muted mb-0">Track your expenses for free</p>
                    </div>

                    <!-- Server error -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger flash-alert alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?= esc($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Registration form — client validation handled by script.js -->
                    <form method="POST" action="register.php" id="registerForm" novalidate>

                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                Username <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       placeholder="3–20 chars, letters/digits/_"
                                       maxlength="20"
                                       value="<?= esc($formData['username']) ?>"
                                       required>
                                <div class="invalid-feedback">
                                    3–20 characters (letters, digits, underscore).
                                </div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-envelope text-muted"></i>
                                </span>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       placeholder="you@example.com"
                                       value="<?= esc($formData['email']) ?>"
                                       required>
                                <div class="invalid-feedback">Please enter a valid email.</div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="Minimum 6 characters"
                                       autocomplete="new-password"
                                       required>
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button"
                                        data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">At least 6 characters required.</div>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-lock-fill text-muted"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="confirm_password"
                                       name="confirm_password"
                                       placeholder="Repeat your password"
                                       autocomplete="new-password"
                                       required>
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button"
                                        data-target="confirm_password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                                <i class="bi bi-person-check me-2"></i>Create Account
                            </button>
                        </div>

                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-0 text-muted">Already have an account?
                            <a href="login.php" class="fw-semibold text-decoration-none">Sign in here</a>
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>
