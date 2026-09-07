<?php
/**
 * Teacher self-registration: creates a 'pending' account that cannot sign
 * in until a superadmin approves it on superadmin-users.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    try {
        if (maintenance_mode_enabled()) {
            throw new RuntimeException('MwalimuPlus is down for maintenance right now. Please check back shortly.');
        }
        if ($name === '') {
            throw new RuntimeException('Enter your name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Use a password of at least 8 characters.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('The two passwords do not match.');
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'teacher', 'pending')"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        audit_log(null, 'register_pending', 'user', (int) $pdo->lastInsertId());

        $submitted = true;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            $error = 'An account for this email already exists.';
        } else {
            $error = 'Could not submit the request right now. Please try again.';
        }
    }
}

$pageTitle = 'Request an account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · MwalimuPlus</title>
    <link rel="manifest" href="offline/manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
    <meta name="theme-color" content="#1f6f43">
</head>
<body class="auth-body">
<div class="auth-shell auth-shell-single">

    <main class="auth-form-panel">
        <a class="brand auth-brand" href="login.php">
            <span class="brand-mark" aria-hidden="true">M+</span>
            <span class="brand-name">Mwalimu<span>Plus</span></span>
        </a>
        <h1>Request a teacher account</h1>
        <p class="auth-sub">A super admin reviews every request before you can sign in.</p>

        <?php if ($submitted): ?>
            <div class="alert alert-success" role="status">Thanks — your request has been submitted. You'll be able to sign in once a super admin approves your account.</div>
            <p class="auth-switch"><a href="login.php">Back to sign in</a></p>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="register.php" class="auth-form">
                <label for="name">Your name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autocomplete="name" autofocus>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">

                <div class="password-field">
                    <label for="password">Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="password">Show</button>
                    </div>
                    <div class="strength-meter" aria-hidden="true">
                        <span class="strength-bar" data-strength-bar></span>
                    </div>
                    <p class="field-hint" data-strength-label>At least 8 characters.</p>
                </div>

                <div class="password-field">
                    <label for="confirm">Confirm password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="confirm">Show</button>
                    </div>
                    <p class="field-hint match-hint" data-match-hint role="status"></p>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Request account</button>
            </form>
            <p class="auth-switch"><a href="login.php">Already have an account? Sign in</a></p>
        <?php endif; ?>
    </main>

</div>
<script src="assets/js/auth.js"></script>
</body>
</html>
