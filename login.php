<?php
/**
 * Sign in page.
 *
 * When no teacher account exists yet, the page redirects straight to
 * setup.php so the first teacher can create the school account. Once an
 * account exists, the branded sign-in view is shown.
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

// No teachers yet: creation happens on setup.php, not here.
if (!app_has_users()) {
    header('Location: setup.php');
    exit;
}

$error = '';

// CSRF token for the login form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Reject cross-site form posts.
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // rotate after login
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: dashboard.php');
                exit;
            }

            $error = 'The email or password does not match.';
        } catch (PDOException $e) {
            $error = 'Could not sign in right now. Please try again.';
        }
    }
}

$emailValue = htmlspecialchars($_POST['email'] ?? '');
$pageTitle = 'Sign in';
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
<div class="auth-shell">

    <!-- Brand / message panel -->
    <aside class="auth-panel">
        <a class="brand auth-brand" href="login.php">Mwalimu<span>Plus</span></a>
        <p class="auth-panel-lead">Lesson plans, grounded in the KICD curriculum your school teaches.</p>

        <ul class="auth-points">
            <li>Every plan cites the strand design it came from</li>
            <li>Built for the 40-minute classroom period</li>
            <li>Works offline once a lesson pack is saved</li>
        </ul>

        <p class="auth-panel-note">Mwalimu AI is a preparation assistant. It can be wrong, and you make the final call in your classroom.</p>
    </aside>

    <!-- Form panel -->
    <main class="auth-form-panel">
        <h1>Sign in</h1>
        <p class="auth-sub">Welcome back. Pick up where you left off.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $emailValue ?>" required autocomplete="email" autofocus>

            <div class="password-field">
                <label for="password">Password</label>
                <div class="password-input-wrap">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="password">Show</button>
                </div>
            </div>

            <p class="auth-forgot"><a href="forgot.php">Forgot your password?</a></p>

            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </main>

</div>
<script src="assets/js/auth.js"></script>
</body>
</html>
