<?php
/**
 * Database Connection & Hybrid Storage Adapter
 */

class DbStatement {
    private array $data;
    private int $position = 0;
    /** @var callable|null */
    private $runner;

    public function __construct(array $data = [], ?callable $runner = null) {
        $this->data = $data;
        $this->runner = $runner;
    }

    public function execute(?array $params = null): bool {
        if ($this->runner !== null) {
            $this->data = ($this->runner)($params ?? []);
            $this->position = 0;
        }
        return true;
    }

    public function fetchAll(): array {
        $rest = array_slice($this->data, $this->position);
        $this->position = count($this->data);
        return $rest;
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
    private const TABLES = ['users', 'reports', 'claims', 'inquiries'];

    private string $filePath;
    private array $data = [];
    private int $lastId = 0;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        $this->load();
    }

    private function load(): void {
        $loaded = null;
        if (file_exists($this->filePath)) {
            $loaded = json_decode((string)file_get_contents($this->filePath), true);
        }

        if (!is_array($loaded) || !isset($loaded['reports'])) {
            $this->seed();
            return;
        }

        $this->data = $loaded;
        foreach (self::TABLES as $t) {
            if (!isset($this->data[$t])) {
                $this->data[$t] = [];
            }
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
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    public function lastInsertId(): int {
        return $this->lastId;
    }

    public function prepare(string $sql): DbStatement {
        return new DbStatement([], fn(array $p) => $this->runSql($sql, $p));
    }

    public function query(string $sql): DbStatement {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function executeSql(string $sql, array $params = []): DbStatement {
        return new DbStatement($this->runSql($sql, $params));
    }

    private function runSql(string $sql, array $params): array {
        $sqlTrim = trim($sql);
        $upper = strtoupper($sqlTrim);

        if (str_starts_with($upper, 'SELECT')) {
            return $this->handleSelect($sqlTrim, $params);
        } elseif (str_starts_with($upper, 'INSERT')) {
            return $this->handleInsert($sqlTrim, $params);
        } elseif (str_starts_with($upper, 'UPDATE')) {
            return $this->handleUpdate($sqlTrim, $params);
        } elseif (str_starts_with($upper, 'DELETE')) {
            return $this->handleDelete($sqlTrim, $params);
        }

        return [];
    }

    // ---- small SQL helpers -------------------------------------------------

    private function tableFrom(string $sql, string $pattern): string {
        if (preg_match($pattern, $sql, $m) && in_array(strtolower($m[1]), self::TABLES, true)) {
            return strtolower($m[1]);
        }
        return 'reports';
    }

    private function resolveToken(string $tok, array $params): mixed {
        if ($tok[0] === ':') return $params[$tok] ?? null;
        if ($tok[0] === "'") return substr($tok, 1, -1);
        return strtoupper($tok) === 'NULL' ? null : $tok;
    }

    private function parseConditions(string $where, array $params): array {
        preg_match_all("/(\w+)\s*(!=|=)\s*(:\w+|'[^']*')/", $where, $mm, PREG_SET_ORDER);
        $conds = [];
        foreach ($mm as $m) {
            $conds[] = [$m[1], $m[2], $this->resolveToken($m[3], $params)];
        }
        return $conds;
    }

    private function matchesAll(array $item, array $conds): bool {
        foreach ($conds as [$col, $op, $val]) {
            $equal = (string)($item[$col] ?? '') === (string)($val ?? '');
            if ($op === '=' && !$equal) return false;
            if ($op === '!=' && $equal) return false;
        }
        return true;
    }

    // ---- statements --------------------------------------------------------

    private function handleSelect(string $sql, array $params): array {
        $table = $this->tableFrom($sql, '/\bFROM\s+(\w+)/i');
        $items = $this->data[$table] ?? [];

        if ($table === 'claims') {
            $reportsMap = [];
            foreach ($this->data['reports'] ?? [] as $r) {
                $reportsMap['id:' . $r['id']] = $r;
                $reportsMap['code:' . $r['report_id']] = $r;
            }
            $items = array_map(function ($c) use ($reportsMap) {
                $matched = null;
                if (!empty($c['report_id'])) {
                    $matched = $reportsMap['id:' . $c['report_id']] ?? null;
                }
                if (!$matched && !empty($c['report_code'])) {
                    $matched = $reportsMap['code:' . $c['report_code']] ?? null;
                }
                $c['photo_url']   = $matched['photo_url'] ?? '/assets/category/others.png';
                $c['category']    = $matched['category'] ?? 'others';
                $c['description'] = $matched['description'] ?? '';
                return $c;
            }, $items);
        }

        $statusIn = $statusNotIn = null;
        if (preg_match('/\bstatus\s+NOT\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            preg_match_all("/'([^']*)'/", $m[1], $v);
            $statusNotIn = $v[1];
        }
        if (preg_match('/\bstatus\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            preg_match_all("/'([^']*)'/", $m[1], $v);
            $statusIn = $v[1];
        }

        $search     = $params[':s1'] ?? $params[':search'] ?? null;
        $status     = $params[':status'] ?? null;
        $reportType = $params[':status_type'] ?? $params[':type'] ?? null;
        $category   = $params[':category'] ?? null;
        $email      = $params[':email'] ?? null;
        $id         = $params[':id'] ?? null;
        $claimId    = $params[':claim_id'] ?? null;
        $code       = $params[':code'] ?? null;
        $cleanSearch = $search !== null ? strtolower(trim($search, '%')) : '';

        $filtered = [];
        foreach ($items as $item) {
            $itemStatus = strtolower((string)($item['status'] ?? ''));

            if ($statusIn !== null && !in_array($itemStatus, $statusIn, true)) continue;
            if ($statusNotIn !== null && in_array($itemStatus, $statusNotIn, true)) continue;
            if ($status !== null && $itemStatus !== strtolower($status)) continue;
            if ($reportType !== null && strtolower((string)($item['report_type'] ?? '')) !== strtolower($reportType)) continue;
            if ($category !== null && strtolower((string)($item['category'] ?? '')) !== strtolower($category)) continue;
            if ($email !== null && strtolower((string)($item['email'] ?? '')) !== strtolower($email)) continue;
            if ($id !== null && (string)($item['id'] ?? '') !== (string)$id) continue;
            if ($claimId !== null && (string)($item['claim_id'] ?? '') !== (string)$claimId) continue;
            if ($code !== null && (string)($item['report_id'] ?? '') !== (string)$code) continue;

            if ($cleanSearch !== '') {
                $match = false;
                foreach (['item_name', 'description', 'location', 'report_id', 'claim_id'] as $field) {
                    if (isset($item[$field]) && str_contains(strtolower((string)$item[$field]), $cleanSearch)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) continue;
            }

            $filtered[] = $item;
        }

        if (preg_match('/^SELECT\s+COUNT\(\*\)/i', $sql)) {
            return [['count' => count($filtered)]];
        }

        $filtered = array_reverse($filtered); // ORDER BY id DESC

        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $m)) {
            $filtered = array_slice($filtered, 0, (int)$m[1]);
        }

        return $filtered;
    }

    private function handleInsert(string $sql, array $params): array {
        $table = $this->tableFrom($sql, '/\bINTO\s+(\w+)/i');

        $maxId = 0;
        foreach ($this->data[$table] ?? [] as $row) {
            if (($row['id'] ?? 0) > $maxId) $maxId = $row['id'];
        }
        $newId = $maxId + 1;
        $this->lastId = $newId;

        $values = [];
        if (preg_match('/\(([^)]*)\)\s*VALUES\s*\((.*)\)\s*$/is', $sql, $m)) {
            $cols = array_map('trim', explode(',', $m[1]));
            preg_match_all("/:\w+|'[^']*'|[^\s,]+/", $m[2], $vm);
            foreach ($cols as $i => $col) {
                if (isset($vm[0][$i])) {
                    $values[$col] = $this->resolveToken($vm[0][$i], $params);
                }
            }
        } else {
            foreach ($params as $k => $v) {
                $values[ltrim($k, ':')] = $v;
            }
        }

        $record = array_merge(['id' => $newId, 'created_at' => date('Y-m-d H:i:s')], $values);

        $this->data[$table][] = $record;
        $this->save();

        return [$record];
    }

    private function handleUpdate(string $sql, array $params): array {
        $table = $this->tableFrom($sql, '/^UPDATE\s+(\w+)/i');

        if (!preg_match('/\bSET\s+(.*?)\s+WHERE\s+(.*)$/is', $sql, $m)) {
            return [];
        }

        preg_match_all("/(\w+)\s*=\s*(:\w+|'[^']*')/", $m[1], $am, PREG_SET_ORDER);
        $assign = [];
        foreach ($am as $a) {
            $assign[$a[1]] = $this->resolveToken($a[2], $params);
        }

        $conds = $this->parseConditions($m[2], $params);
        if (empty($conds) || empty($assign)) {
            return [];
        }

        foreach ($this->data[$table] as $i => $item) {
            if ($this->matchesAll($item, $conds)) {
                foreach ($assign as $col => $val) {
                    $this->data[$table][$i][$col] = $val;
                }
            }
        }
        $this->save();

        return [];
    }

    private function handleDelete(string $sql, array $params): array {
        $table = $this->tableFrom($sql, '/\bFROM\s+(\w+)/i');

        if (!preg_match('/\bWHERE\s+(.*)$/is', $sql, $m)) {
            return [];
        }

        $conds = $this->parseConditions($m[1], $params);
        if (empty($conds)) {
            return [];
        }

        $this->data[$table] = array_values(array_filter(
            $this->data[$table],
            fn($item) => !$this->matchesAll($item, $conds)
        ));
        $this->save();

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
