<?php
/**
 * Database Connection & Hybrid Storage Adapter
 */

class DbStatement {
    private array $data;
    private int $position = 0;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function execute(?array $params = null): bool {
        return true;
    }

    public function fetchAll(): array {
        return $this->data;
    }

    public function fetch(): array|false {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return false;
    }

    public function fetchColumn(): mixed {
        $row = $this->fetch();
        if ($row && is_array($row)) {
            return reset($row);
        }
        return false;
    }

    public function rowCount(): int {
        return count($this->data);
    }
}

class FileDbAdapter {
    private string $filePath;
    private array $data = [];
    private int $lastId = 0;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        $this->load();
    }

    private function load(): void {
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            $this->data = json_decode($content, true) ?: [];
        }

        if (empty($this->data['reports'])) {
            $this->seed();
        }
    }

    private function seed(): void {
        $this->data = [
            'users' => [
                [
                    'id' => 1,
                    'full_name' => 'Sample User',
                    'email' => 'user@school.edu',
                    'school_id' => '000000',
                    'role' => 'Student',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ],
            'reports' => [
                [
                    'id' => 1,
                    'report_id' => 'R-10102026-#001',
                    'item_name' => 'Black Leather Wallet',
                    'category' => 'personal-belongings',
                    'description' => 'Leather wallet with school ID and cards.',
                    'date_event' => '2026-10-10',
                    'location' => 'Main Library 2nd Floor',
                    'photo_url' => '/assets/category/personal-belongings.png',
                    'status' => 'confirmed',
                    'report_type' => 'lost',
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'report_id' => 'R-10082026-#002',
                    'item_name' => 'Blue Grid Notebook',
                    'category' => 'school-supplies',
                    'description' => 'Math notes inside with name tag.',
                    'date_event' => '2026-10-08',
                    'location' => 'Cafeteria Table 4',
                    'photo_url' => '/assets/category/school-supplies.png',
                    'status' => 'confirmed',
                    'report_type' => 'found',
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 3,
                    'report_id' => 'R-10052026-#003',
                    'item_name' => 'Wireless Earbuds',
                    'category' => 'electronics',
                    'description' => 'White charging case, brand sound core.',
                    'date_event' => '2026-10-05',
                    'location' => 'Gymnasium Bleachers',
                    'photo_url' => '/assets/category/electronics.png',
                    'status' => 'returned',
                    'report_type' => 'found',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ],
            'claims' => [
                [
                    'id' => 1,
                    'claim_id' => 'CLM-001',
                    'report_id' => 1,
                    'report_code' => 'R-10102026-#001',
                    'item_name' => 'Black Leather Wallet',
                    'full_name' => 'Sample User',
                    'email' => 'user@school.edu',
                    'school_id' => '000000',
                    'status' => 'pending',
                    'staff_feedback' => 'Waiting for verification.',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ],
            'inquiries' => []
        ];
        $this->save();
    }

    private function save(): void {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function lastInsertId(): int {
        return $this->lastId;
    }

    public function prepare(string $sql): DbStatement {
        return $this->executeSql($sql, []);
    }

    public function query(string $sql): DbStatement {
        return $this->executeSql($sql, []);
    }

    public function executeSql(string $sql, array $params = []): DbStatement {
        $sqlTrim = trim($sql);
        $upper = strtoupper($sqlTrim);

        if (str_starts_with($upper, 'SELECT')) {
            return new DbStatement($this->handleSelect($sqlTrim, $params));
        } elseif (str_starts_with($upper, 'INSERT')) {
            return new DbStatement($this->handleInsert($sqlTrim, $params));
        } elseif (str_starts_with($upper, 'UPDATE')) {
            return new DbStatement($this->handleUpdate($sqlTrim, $params));
        } elseif (str_starts_with($upper, 'DELETE')) {
            return new DbStatement($this->handleDelete($sqlTrim, $params));
        }

        return new DbStatement([]);
    }

    private function handleSelect(string $sql, array $params): array {
        $table = 'reports';
        if (stripos($sql, 'FROM claims') !== false) {
            $table = 'claims';
        } elseif (stripos($sql, 'FROM inquiries') !== false) {
            $table = 'inquiries';
        } elseif (stripos($sql, 'FROM users') !== false) {
            $table = 'users';
        }

        $items = $this->data[$table] ?? [];

        // Join reports if claims requested
        if ($table === 'claims') {
            $reportsMap = [];
            foreach ($this->data['reports'] ?? [] as $r) {
                $reportsMap[$r['id']] = $r;
                $reportsMap[$r['report_id']] = $r;
            }
            foreach ($items as &$c) {
                $matched = $reportsMap[$c['report_id'] ?? ''] ?? $reportsMap[$c['report_code'] ?? ''] ?? null;
                $c['photo_url'] = $matched['photo_url'] ?? '/assets/category/others.png';
                $c['category'] = $matched['category'] ?? 'others';
                $c['description'] = $matched['description'] ?? '';
            }
        }

        // Filtering
        $search = $params[':search'] ?? null;
        $status = $params[':status'] ?? $params[':status_type'] ?? null;
        $category = $params[':category'] ?? null;
        $email = $params[':email'] ?? null;

        $filtered = array_filter($items, function($item) use ($sql, $search, $status, $category, $email) {
            if (stripos($sql, "status IN ('confirmed', 'pending', 'claimed', 'returned')") !== false) {
                if (($item['status'] ?? '') === 'rejected') return false;
            } elseif ($status && isset($item['status']) && strtolower($item['status']) !== strtolower($status) && strtolower($item['report_type'] ?? '') !== strtolower($status)) {
                return false;
            }

            if ($category && isset($item['category']) && strtolower($item['category']) !== strtolower($category)) {
                return false;
            }

            if ($email && isset($item['email']) && strtolower($item['email']) !== strtolower($email)) {
                return false;
            }

            if ($search) {
                $cleanSearch = strtolower(trim($search, '%'));
                $match = false;
                foreach (['item_name', 'description', 'location', 'report_id', 'claim_id'] as $field) {
                    if (isset($item[$field]) && str_contains(strtolower($item[$field]), $cleanSearch)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) return false;
            }

            return true;
        });

        // Sorting
        $filtered = array_reverse(array_values($filtered));

        // Limit
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $m)) {
            $limit = (int)$m[1];
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    private function handleInsert(string $sql, array $params): array {
        $table = 'reports';
        if (stripos($sql, 'INTO claims') !== false) {
            $table = 'claims';
        } elseif (stripos($sql, 'INTO inquiries') !== false) {
            $table = 'inquiries';
        } elseif (stripos($sql, 'INTO users') !== false) {
            $table = 'users';
        }

        $maxId = 0;
        foreach ($this->data[$table] ?? [] as $row) {
            if (($row['id'] ?? 0) > $maxId) $maxId = $row['id'];
        }
        $newId = $maxId + 1;
        $this->lastId = $newId;

        $record = ['id' => $newId, 'created_at' => date('Y-m-d H:i:s')];
        foreach ($params as $k => $v) {
            $fieldName = ltrim($k, ':');
            $record[$fieldName] = $v;
        }

        $this->data[$table][] = $record;
        $this->save();

        return [$record];
    }

    private function handleUpdate(string $sql, array $params): array {
        $table = 'reports';
        if (stripos($sql, 'claims') !== false) {
            $table = 'claims';
        } elseif (stripos($sql, 'inquiries') !== false) {
            $table = 'inquiries';
        } elseif (stripos($sql, 'users') !== false) {
            $table = 'users';
        }

        $id = $params[':id'] ?? $params[':rid'] ?? null;
        $code = $params[':report_id'] ?? $params[':claim_id'] ?? $params[':rcode'] ?? $params[':code'] ?? null;
        $status = $params[':status'] ?? $params[':st'] ?? null;

        if (isset($this->data[$table])) {
            foreach ($this->data[$table] as &$item) {
                if (($id && ($item['id'] ?? null) == $id) || ($code && (($item['report_id'] ?? '') == $code || ($item['claim_id'] ?? '') == $code))) {
                    if ($status) $item['status'] = $status;
                    if (isset($params[':feedback'])) $item['staff_feedback'] = $params[':feedback'];
                    if (isset($params[':name'])) $item['full_name'] = $params[':name'];
                }
            }
            $this->save();
        }

        return [];
    }

    private function handleDelete(string $sql, array $params): array {
        $table = 'reports';
        if (stripos($sql, 'FROM claims') !== false) {
            $table = 'claims';
        } elseif (stripos($sql, 'FROM inquiries') !== false) {
            $table = 'inquiries';
        }

        $id = $params[':id'] ?? null;
        $code = $params[':claim_id'] ?? $params[':report_id'] ?? null;

        if (isset($this->data[$table])) {
            $this->data[$table] = array_values(array_filter($this->data[$table], function($item) use ($id, $code) {
                if ($id && ($item['id'] ?? null) == $id) return false;
                if ($code && (($item['claim_id'] ?? '') == $code || ($item['report_id'] ?? '') == $code)) return false;
                return true;
            }));
            $this->save();
        }

        return [];
    }
}

function getDbConnection(): PDO|FileDbAdapter {
    static $conn = null;

    if ($conn !== null) {
        return $conn;
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
        $conn = new PDO($dsn, $user, $pass, $options);
        ensureSchemaInitialized($conn);
        return $conn;
    } catch (PDOException $e) {
        try {
            $fallbackDsn = "mysql:host=127.0.0.1;port={$port};dbname={$dbname};charset={$charset}";
            $conn = new PDO($fallbackDsn, $user, $pass, $options);
            ensureSchemaInitialized($conn);
            return $conn;
        } catch (PDOException $ex) {
            // Fall back to JSON file database adapter
            $storePath = __DIR__ . '/../db/store.json';
            $conn = new FileDbAdapter($storePath);
            return $conn;
        }
    }
}

function ensureSchemaInitialized($pdo): void {
    if (!($pdo instanceof PDO)) return;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'reports'");
        if ($check->rowCount() === 0) {
            $sqlFile = __DIR__ . '/../db/schema.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }
    } catch (Exception $e) {}
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
