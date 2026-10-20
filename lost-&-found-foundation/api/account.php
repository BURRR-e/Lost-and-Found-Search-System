<?php
require_once __DIR__ . '/../includes/db.php';

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
    $email = $_GET['email'] ?? 'user@school.edu';

    $stmt = $pdo->prepare("SELECT id, full_name, email, school_id, role, created_at FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $user = [
            'full_name' => 'Sample User',
            'email'     => 'user@school.edu',
            'school_id' => '000000',
            'role'      => 'Student'
        ];
    }

    $lostStmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE report_type = 'lost' AND (user_id = :uid OR status != '')");
    $lostStmt->execute([':uid' => $user['id'] ?? 0]);
    $lostCount = (int)$lostStmt->fetchColumn();

    $foundStmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE report_type = 'found' AND (user_id = :uid OR status != '')");
    $foundStmt->execute([':uid' => $user['id'] ?? 0]);
    $foundCount = (int)$foundStmt->fetchColumn();

    $claimStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE email = :email");
    $claimStmt->execute([':email' => $user['email']]);
    $claimCount = (int)$claimStmt->fetchColumn();

    $user['activity'] = [
        'lost_reports'  => $lostCount,
        'found_reports' => $foundCount,
        'claim_requests'=> $claimCount
    ];

    jsonResponse(['success' => true, 'data' => $user]);
}

function handleUpdateAccount(PDO|FileDbAdapter $pdo): void {
    $input = getJsonInput();

    $fullName = trim($input['full-name'] ?? $input['full_name'] ?? '');
    $email    = trim($input['account-email'] ?? $input['email'] ?? '');
    $schoolId = isset($input['school_id']) ? trim($input['school_id']) : '';

    if (empty($fullName) || empty($email)) {
        jsonResponse(['error' => 'Full Name and Email Address are required.'], 400);
    }

    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $checkStmt->execute([':email' => $email]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if ($schoolId !== '') {
            $stmt = $pdo->prepare("UPDATE users SET full_name = :name, school_id = :school_id WHERE id = :id");
            $stmt->execute([':name' => $fullName, ':school_id' => $schoolId, ':id' => $existing['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = :name WHERE id = :id");
            $stmt->execute([':name' => $fullName, ':id' => $existing['id']]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, school_id, role) VALUES (:name, :email, :school_id, 'Student')");
        $stmt->execute([':name' => $fullName, ':email' => $email, ':school_id' => $schoolId !== '' ? $schoolId : '000000']);
    }

    jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
}
