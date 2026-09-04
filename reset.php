<?php
/**
 * Password reset: consumes a single-use token from the reset email and lets
 * the teacher choose a new password.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();

// Already signed in.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenHash = $token === '' ? '' : hash('sha256', $token);

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Use a password of at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        try {
            $pdo = db();

            // Find the unused, unexpired token.
            $stmt = $pdo->prepare(
                'SELECT id, user_id FROM password_resets
                  WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
                  LIMIT 1'
            );
            $stmt->execute([$tokenHash]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $error = 'This reset link is invalid or has already been used. Request a new one below.';
            } else {
                $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);

                // Mark this token used and revoke any other outstanding resets.
                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ?')
                    ->execute([(int) $reset['user_id']]);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
                    ->execute([(int) $reset['user_id']]);

                // Sign the teacher in.
                session_regenerate_id(true);
                $name = $pdo->query('SELECT name FROM users WHERE id = ' . (int) $reset['user_id'])->fetchColumn();
                $_SESSION['user_id'] = (int) $reset['user_id'];
                $_SESSION['user_name'] = $name !== false ? (string) $name : 'Teacher';

                set_flash('success', 'Your password has been updated.');
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Could not reset the password right now. Please try again.';
        }
    }
}

// Pre-validate the token on GET so the form is only shown for a live link.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $token !== '') {
    try {
        $stmt = db()->prepare(
            'SELECT id FROM password_resets
              WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        if (!$stmt->fetch()) {
            $error = 'This reset link is invalid, expired, or has already been used.';
            $token = '';
        }
    } catch (PDOException $e) {
        $error = 'Could not check the reset link right now. Please try again.';
        $token = '';
    }
}

$pageTitle = 'Choose a new password';
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

        <?php if ($token === ''): ?>
            <h1>Reset link not valid</h1>
            <p class="auth-sub"><?= htmlspecialchars($error !== '' ? $error : 'Request a new reset link to continue.') ?></p>
            <p class="auth-switch"><a href="forgot.php">Request a new link</a></p>
        <?php else: ?>
            <h1>Choose a new password</h1>
            <p class="auth-sub">Make it at least 8 characters.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="reset.php" class="auth-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>

                <label for="confirm">Confirm new password</label>
                <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">

                <button type="submit" class="btn btn-primary btn-block">Update password</button>
            </form>
        <?php endif; ?>
    </main>

</div>
</body>
</html>
