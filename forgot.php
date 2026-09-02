<?php
/**
 * Forgot password: requests a password reset email.
 *
 * Always shows the same confirmation whether or not the email exists, so the
 * page cannot be used to discover registered accounts. Requests are rate
 * limited per session.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mail.php';
secure_session_start();

// Already signed in? No reset needed.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// No teachers yet: nothing to reset, go create the first account.
if (!app_has_users()) {
    header('Location: setup.php');
    exit;
}

$confirmed = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limit: up to 3 requests per 15 minutes per session.
    $now = time();
    $window = $_SESSION['reset_attempts'] ?? ['count' => 0, 'first' => $now];

    if ($now - $window['first'] > 900) {
        $window = ['count' => 0, 'first' => $now];
    }

    if ($window['count'] >= 3) {
        $error = 'Too many requests. Wait about 15 minutes, then try again.';
    } else {
        $window['count']++;
        $_SESSION['reset_attempts'] = $window;

        $email = trim($_POST['email'] ?? '');
        $found = false;

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $pdo = db();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $found = true;

                    // One active token per user: revoke any earlier unused ones.
                    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
                        ->execute([(int) $user['id']]);

                    $token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                    );
                    $stmt->execute([(int) $user['id'], hash('sha256', $token)]);

                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $resetUrl = $scheme . '://' . $host . '/reset.php?token=' . urlencode($token);

                    send_mail(
                        $email,
                        'Reset your password',
                        password_reset_email_html($resetUrl),
                        password_reset_email_text($resetUrl)
                    );
                }
            } catch (PDOException $e) {
                $error = 'Could not process the request right now. Please try again.';
            }
        }

        // Same neutral message for known, unknown, and malformed emails.
        if ($error === '') {
            $confirmed = true;
        }
    }
}

$pageTitle = 'Reset your password';
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
        <a class="brand auth-brand" href="login.php">Mwalimu<span>Plus</span></a>

        <?php if ($confirmed): ?>
            <h1>Check your email</h1>
            <p class="auth-sub">If an account exists for that address, a reset link is on its way. It works once and expires within an hour.</p>
            <p class="auth-sub"><a href="login.php">Back to sign in</a></p>
        <?php else: ?>
            <h1>Reset your password</h1>
            <p class="auth-sub">Enter the email you use to sign in and we will send a reset link.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="forgot.php" class="auth-form">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" autofocus>

                <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
            </form>

            <p class="auth-switch"><a href="login.php">Back to sign in</a></p>
        <?php endif; ?>
    </main>

</div>
</body>
</html>
