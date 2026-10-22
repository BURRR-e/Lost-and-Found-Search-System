<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetAccount($pdo);
        break;
    case 'POST':
    case 'PUT':
        handleUpdateAccount($pdo);
        break;
    default:
        jsonResponse(['error' => 'Method Not Allowed'], 405);
}

function handleGetAccount(PDO|FileDbAdapter $pdo): void {
    $sess = requireLogin();

    $stmt = $pdo->prepare("SELECT id, full_name, email, school_id, role, created_at FROM users WHERE email = :email");
    $stmt->execute([':email' => $sess['email']]);
    $row = $stmt->fetch();

    if (!$row) {
        logoutSession(); // account was deleted
        jsonResponse(['success' => false, 'error' => 'Account not found.'], 401);
    }
    $user = publicUser($row);

    $lost = $found = $returned = 0;
    foreach ($pdo->query("SELECT id, report_type, status, user_id FROM reports")->fetchAll() as $r) {
        if ((int)($r['user_id'] ?? 0) !== $user['id']) continue;
        if (($r['report_type'] ?? '') === 'lost')  $lost++;
        if (($r['report_type'] ?? '') === 'found') $found++;
        if (in_array($r['status'] ?? '', ['claimed', 'returned'], true)) $returned++;
    }

    $claimStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE email = :email");
    $claimStmt->execute([':email' => $user['email']]);

    $user['activity'] = [
        'lost_reports'   => $lost,
        'found_reports'  => $found,
        'returned_items' => $returned,
        'claim_requests' => (int)$claimStmt->fetchColumn(),
    ];

    jsonResponse(['success' => true, 'data' => $user]);
}

function handleUpdateAccount(PDO|FileDbAdapter $pdo): void {
    $sess  = requireLogin();
    $input = getJsonInput();

    // Email and role are fixed: email is the login identity, role is admin-controlled.
    $fullName = trim($input['full-name'] ?? $input['full_name'] ?? '');
    $schoolId = isset($input['school_id']) ? trim($input['school_id']) : '';

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        jsonResponse(['error' => 'Full Name is required (max 100 characters).'], 400);
    }

    if ($schoolId !== '') {
        $stmt = $pdo->prepare("UPDATE users SET full_name = :name, school_id = :school_id WHERE id = :id");
        $stmt->execute([':name' => $fullName, ':school_id' => $schoolId, ':id' => $sess['id']]);
        $_SESSION['user']['school_id'] = $schoolId;
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = :name WHERE id = :id");
        $stmt->execute([':name' => $fullName, ':id' => $sess['id']]);
    }
    $_SESSION['user']['full_name'] = $fullName;

    jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
}
