<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'me') {
    $user = currentUser();
    jsonResponse(['success' => true, 'data' => $user]); // data is null when signed out
}

if ($method !== 'POST') {
    jsonResponse(['error' => 'Method Not Allowed'], 405);
}

switch ($action) {
    case 'register':       handleRegister($pdo, false); break;
    case 'register_admin': handleRegister($pdo, true);  break;
    case 'login':          handleLogin($pdo);           break;
    case 'logout':         logoutSession(); jsonResponse(['success' => true]); break;
    case 'change_password': handleChangePassword($pdo); break;
    default:               jsonResponse(['error' => 'Unknown action.'], 400);
}

function normalizeRole(string $role): string {
    // The users.role column only allows Student / Staff / Admin.
    return in_array(strtolower(trim($role)), ['staff', 'professor'], true) ? 'Staff' : 'Student';
}

function validatePassword(string $password): ?string {
    if (strlen($password) < 8)  return 'Password must be at least 8 characters.';
    if (strlen($password) > 72) return 'Password must be 72 characters or fewer.';
    return null;
}

function handleRegister(PDO|FileDbAdapter $pdo, bool $admin): void {
    $in       = getJsonInput();
    $name     = trim((string)($in['name'] ?? $in['full_name'] ?? ''));
    $email    = strtolower(trim((string)($in['email'] ?? '')));
    $schoolId = trim((string)($in['school_id'] ?? $in['schoolid'] ?? ''));
    $password = (string)($in['password'] ?? '');

    if ($admin) {
        // Admin accounts need a secret invite code set on the server (env var).
        $expected = (string)getenv('ADMIN_INVITE_CODE');
        if ($expected === '') {
            jsonResponse(['error' => 'Admin sign-up is disabled: ADMIN_INVITE_CODE is not set on the server.'], 403);
        }
        if (!hash_equals($expected, (string)($in['invite_code'] ?? ''))) {
            jsonResponse(['error' => 'Invalid admin invite code.'], 403);
        }
        $role = 'Admin';
    } else {
        $role = normalizeRole((string)($in['role'] ?? 'student'));
        if ($schoolId === '') {
            jsonResponse(['error' => 'School ID is required.'], 400);
        }
    }

    if ($name === '' || mb_strlen($name) > 100) {
        jsonResponse(['error' => 'Please enter your name (max 100 characters).'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        jsonResponse(['error' => 'Please enter a valid email address.'], 400);
    }
    if (mb_strlen($schoolId) > 50) {
        jsonResponse(['error' => 'School ID is too long.'], 400);
    }
    if (($err = validatePassword($password)) !== null) {
        jsonResponse(['error' => $err], 400);
    }

    $dup = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $dup->execute([':email' => $email]);
    if ($dup->fetch()) {
        jsonResponse(['error' => 'An account with this email already exists.'], 409);
    }
    if ($schoolId !== '') {
        foreach ($pdo->query("SELECT id, school_id FROM users")->fetchAll() as $row) {
            if (strcasecmp((string)($row['school_id'] ?? ''), $schoolId) === 0) {
                jsonResponse(['error' => 'This School ID is already registered.'], 409);
            }
        }
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, school_id, role, password_hash)
             VALUES (:full_name, :email, :school_id, :role, :password_hash)"
        );
        $stmt->execute([
            ':full_name'     => $name,
            ':email'         => $email,
            ':school_id'     => $schoolId !== '' ? $schoolId : null,
            ':role'          => $role,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // unique violation (race)
            jsonResponse(['error' => 'An account with this email already exists.'], 409);
        }
        error_log('Register failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Could not create the account.'], 500);
    }

    jsonResponse(['success' => true, 'message' => 'Account created successfully.', 'role' => $role], 201);
}

function handleLogin(PDO|FileDbAdapter $pdo): void {
    $in        = getJsonInput();
    $email     = strtolower(trim((string)($in['email'] ?? '')));
    $password  = (string)($in['password'] ?? '');
    $adminOnly = !empty($in['admin']);

    if ($email === '' || $password === '') {
        jsonResponse(['error' => 'Email and password are required.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, full_name, email, school_id, role, password_hash, created_at FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Always run a hash check so unknown emails take about as long as wrong passwords.
    $hash = ($user && !empty($user['password_hash']))
        ? $user['password_hash']
        : password_hash('not-a-real-password', PASSWORD_DEFAULT);

    if (!password_verify($password, $hash) || !$user || empty($user['password_hash'])) {
        jsonResponse(['error' => 'Invalid email or password.'], 401);
    }

    if ($adminOnly && ($user['role'] ?? '') !== 'Admin') {
        jsonResponse(['error' => 'This account does not have admin access.'], 403);
    }

    loginSession($user);
    jsonResponse(['success' => true, 'data' => publicUser($user)]);
}

function handleChangePassword(PDO|FileDbAdapter $pdo): void {
    $sess    = requireLogin();
    $in      = getJsonInput();
    $current = (string)($in['current_password'] ?? '');
    $new     = (string)($in['new_password'] ?? '');

    if (($err = validatePassword($new)) !== null) {
        jsonResponse(['error' => $err], 400);
    }

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email");
    $stmt->execute([':email' => $sess['email']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password_hash']) || !password_verify($current, $user['password_hash'])) {
        jsonResponse(['error' => 'Current password is incorrect.'], 403);
    }

    $up = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $up->execute([':hash' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);

    jsonResponse(['success' => true, 'message' => 'Password updated.']);
}
