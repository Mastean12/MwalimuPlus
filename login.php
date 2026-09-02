<?php
/** Login page + a demo/register shortcut (creates a local demo teacher on first use). */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already signed in.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $pdo = db();

    try {
        if ($action === 'register') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new RuntimeException('Enter your name, a valid email, and a password of at least 8 characters.');
            }

            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            header('Location: dashboard.php');
            exit;
        }

        // action === 'login'
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email or password.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = 'Registration failed. Is the database set up? Run database/schema.sql first.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · MwalimuPlus</title>
    <link rel="manifest" href="offline/manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
    <meta name="theme-color" content="#1f6f43">
</head>
<body class="auth-body">
<main class="auth-card">
    <a class="brand auth-brand" href="login.php">Mwalimu<span>Plus</span></a>
    <p class="auth-tagline">Curriculum-grounded AI lesson prep for Kenyan Grade 10 teachers.</p>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" class="auth-form" id="login-form">
        <input type="hidden" name="action" value="login">
        <h1>Sign in</h1>
        <label>Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <details class="auth-register">
        <summary>New teacher? Create an account</summary>
        <form method="post" class="auth-form">
            <input type="hidden" name="action" value="register">
            <label>Full name
                <input type="text" name="name" required autocomplete="name">
            </label>
            <label>Email
                <input type="email" name="email" required autocomplete="email">
            </label>
            <label>Password (min. 8 characters)
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
    </details>
</main>
</body>
</html>
