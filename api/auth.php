<?php
/**
 * Auth API (JSON).
 *
 * POST /api/auth.php
 *   { action: "me" }                  -> current session
 *   { action: "login", email, password }
 *   { action: "request_reset", email }
 *   { action: "logout" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mail.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? 'me';

try {
    $pdo = db();

    switch ($action) {
        case 'me':
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not signed in.']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => (int) $_SESSION['user_id'],
                    'name' => $_SESSION['user_name'] ?? '',
                ],
            ]);
            break;

        case 'login':
            $email = trim((string) ($input['email'] ?? ''));
            $password = (string) ($input['password'] ?? '');

            $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'The email or password does not match.']);
                break;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];

            echo json_encode([
                'success' => true,
                'user' => ['id' => (int) $user['id'], 'name' => $user['name']],
            ]);
            break;

        case 'request_reset':
            $email = trim((string) ($input['email'] ?? ''));

            // Rate limit: up to 3 requests per 15 minutes per session.
            $now = time();
            $window = $_SESSION['reset_attempts'] ?? ['count' => 0, 'first' => $now];
            if ($now - $window['first'] > 900) {
                $window = ['count' => 0, 'first' => $now];
            }
            $window['count']++;
            $_SESSION['reset_attempts'] = $window;

            if ($window['count'] > 3) {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Too many requests. Wait about 15 minutes, then try again.']);
                break;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
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

                    send_mail($email, 'Reset your password', password_reset_email_body($resetUrl));
                }
            }

            // Neutral response either way: never reveal whether the email exists.
            echo json_encode([
                'success' => true,
                'message' => 'If an account exists for that email, a reset link is on its way.',
            ]);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
