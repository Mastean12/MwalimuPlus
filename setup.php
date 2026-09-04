<?php
/**
 * First-run setup: creates the first teacher account for the school.
 *
 * Only reachable while the users table is empty. After that it redirects to
 * login.php so no one can overwrite or duplicate the first account.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();

// Already signed in: nothing to set up.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// An account already exists: this page's job is done.
if (app_has_users()) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    try {
        if ($name === '') {
            throw new RuntimeException('Enter the teacher name for this account.');
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
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        $_SESSION['user_name'] = $name;

        header('Location: dashboard.php');
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($e->errorInfo[1] === 1062) {
            $error = 'An account for this email already exists. Sign in instead.';
        } else {
            $error = 'Could not create the account right now. Please try again.';
        }
    }
}

$pageTitle = 'Set up your account';
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
        <h1>Set up your school account</h1>
        <p class="auth-sub">This first account belongs to the school. Other teachers sign in with accounts created here.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="setup.php" class="auth-form">
            <label for="name">Teacher name</label>
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

            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
    </main>

</div>
<script src="assets/js/auth.js"></script>
</body>
</html>
