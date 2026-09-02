<?php
/**
 * Auth API (JSON).
 *
 * POST /api/auth.php { action: "me" }          -> current session
 * POST /api/auth.php { action: "login", ... }  -> sign in
 * POST /api/auth.php { action: "logout" }      -> sign out
 * POST /api/auth.php { action: "register", ... } -> create account
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? 'me';

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
                'user' => ['id' => (int) $_SESSION['user_id'], 'name' => $_SESSION['user_name'] ?? ''],
            ]);
            break;

        case 'login':
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
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

        case 'register':
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Name, valid email, and a password of at least 8 characters are required.']);
                break;
            }

            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;

            echo json_encode([
                'success' => true,
                'user' => ['id' => (int) $pdo->lastInsertId(), 'name' => $name],
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
