<?php
/**
 * Teacher profile: view/update account details and change password.
 *
 * Requests with an X-Requested-With: XMLHttpRequest header (sent by the
 * profile modal in assets/js/app.js) get just the content fragment back, so
 * the modal can inject it without a full navigation. Without that header —
 * i.e. a plain link click or a no-JS form submit — this behaves as a normal
 * full page, with the usual redirect-after-POST.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Your session expired. Please try again.');
        if (!$isAjax) {
            header('Location: profile.php');
            exit;
        }
    } else {
        $formAction = $_POST['form_action'] ?? '';

        if ($formAction === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            try {
                if ($name === '') {
                    throw new RuntimeException('Enter your name.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address.');
                }

                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $userId]);

                $_SESSION['user_name'] = $name;
                set_flash('success', 'Your profile has been updated.');
            } catch (RuntimeException $e) {
                set_flash('error', $e->getMessage());
            } catch (PDOException $e) {
                if ($e->errorInfo[1] === 1062) {
                    set_flash('error', 'An account for this email already exists.');
                } else {
                    set_flash('error', 'Could not update your profile right now. Please try again.');
                }
            }
        } elseif ($formAction === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm'] ?? '';

            try {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();

                if ($hash === false || !password_verify($currentPassword, $hash)) {
                    throw new RuntimeException('Your current password is incorrect.');
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('Use a new password of at least 8 characters.');
                }
                if ($password !== $confirm) {
                    throw new RuntimeException('The two new passwords do not match.');
                }

                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

                set_flash('success', 'Your password has been changed.');
            } catch (RuntimeException $e) {
                set_flash('error', $e->getMessage());
            } catch (PDOException $e) {
                set_flash('error', 'Could not change your password right now. Please try again.');
            }
        }

        if (!$isAjax) {
            header('Location: profile.php');
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT name, email, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$lessonCountStmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE user_id = ?');
$lessonCountStmt->execute([$userId]);
$lessonCount = (int) $lessonCountStmt->fetchColumn();

if ($isAjax) {
    header('Content-Type: text/html; charset=utf-8');
    render_flash();
    require __DIR__ . '/includes/profile-fragment.php';
    exit;
}

$pageTitle    = 'Profile';
$activeNav    = 'profile';
$showHeader   = true;
$pageIcon     = '👤';
$pageHeading  = 'Your profile';
$pageSubtitle = 'Manage your account details and password.';
$breadcrumbs  = [['label' => 'Profile']];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/profile-fragment.php';
?>
<script src="assets/js/auth.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
