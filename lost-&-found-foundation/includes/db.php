<?php
/**
 * Database Connection & Helper Utilities
 */

function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host     = getenv('DB_HOST')     ?: 'db';
    $port     = getenv('DB_PORT')     ?: '3306';
    $dbname   = getenv('DB_NAME')     ?: 'db';
    $user     = getenv('DB_USER')     ?: 'db';
    $pass     = getenv('DB_PASS')     ?: 'db';
    $charset  = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if ($host === 'db') {
            $fallbackDsn = "mysql:host=127.0.0.1;port={$port};dbname={$dbname};charset={$charset}";
            try {
                $pdo = new PDO($fallbackDsn, $user, $pass, $options);
            } catch (PDOException $ex) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Database Connection Failed',
                    'message' => $ex->getMessage()
                ]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Database Connection Failed',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
    ensureSchemaInitialized($pdo);

    return $pdo;
}

function ensureSchemaInitialized(PDO $pdo): void {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'reports'");
        if ($check->rowCount() === 0) {
            $sqlFile = __DIR__ . '/../db/schema.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }
    } catch (Exception $e) {
    }
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function getJsonInput(): array {
    $rawInput = file_get_contents('php://input');
    if (!$rawInput) {
        return $_POST ?: [];
    }
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
}
