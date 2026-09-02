<?php
/**
 * MwalimuPlus database connection.
 * Laragon defaults: root with no password on 127.0.0.1:3306.
 * Override via environment variables if your setup differs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../database/schema.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('MWALIMU_DB_HOST') ?: '127.0.0.1';
        $port = getenv('MWALIMU_DB_PORT') ?: '3306';
        $name = getenv('MWALIMU_DB_NAME') ?: 'mwalimu_plus';
        $user = getenv('MWALIMU_DB_USER') ?: 'root';
        $pass = getenv('MWALIMU_DB_PASS') ?: '';

        // Make sure the database itself exists before connecting to it.
        ensure_database();

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Every connect guarantees the full table set exists.
            ensure_schema($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed. Check that MySQL is running.',
            ]);
            exit;
        }
    }

    return $pdo;
}
