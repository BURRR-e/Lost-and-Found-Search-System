<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetClaims($pdo);
        break;
    case 'POST':
        handleCreateClaim($pdo);
        break;
    case 'PUT':
    case 'PATCH':
        handleUpdateClaim($pdo);
        break;
    case 'DELETE':
        handleDeleteClaim($pdo);
        break;
    default:
        jsonResponse(['error' => 'Method Not Allowed'], 405);
}

function handleGetClaims(PDO $pdo): void {
    $status = $_GET['status'] ?? 'all';
    $email  = trim($_GET['email'] ?? '');

    $whereClause = ['1=1'];
    $params = [];

    if ($status !== 'all' && !empty($status)) {
        $whereClause[] = "c.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($email)) {
        $whereClause[] = "c.email = :email";
        $params[':email'] = $email;
    }

    $sql = "SELECT c.id, c.claim_id, c.report_id, c.report_code, c.item_name, c.full_name, c.email, c.school_id, c.status, c.staff_feedback, c.created_at,
                   COALESCE(r.photo_url, '/assets/category/others.png') as photo_url,
                   COALESCE(r.category, 'others') as category,
                   COALESCE(r.description, '') as description
            FROM claims c
            LEFT JOIN reports r ON (c.report_id = r.id OR (c.report_code IS NOT NULL AND c.report_code = r.report_id))
            WHERE " . implode(' AND ', $whereClause) . "
            ORDER BY c.id DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $claims = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $claims]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage(), 'data' => []], 500);
    }
}

function handleCreateClaim(PDO $pdo): void {
    $input = getJsonInput();

    $reportCode  = trim($input['report_code'] ?? $input['reportCode'] ?? '');
    $itemName    = trim($input['item_name']   ?? $input['itemName']   ?? '');
    $fullName    = trim($input['full_name']   ?? $input['fullName']   ?? 'Sample User');
    $email       = trim($input['email']       ?? 'user@school.edu');
    $schoolId    = trim($input['school_id']   ?? $input['schoolId']   ?? '000000');

    if (empty($reportCode) && empty($itemName)) {
        jsonResponse(['error' => 'Item information or Report Code is required.'], 400);
    }
    $countStmt = $pdo->query("SELECT COUNT(*) FROM claims");
    $nextNum = (int)$countStmt->fetchColumn() + 1;
    $claimId = sprintf("CLM-%03d", $nextNum);
    $reportDbId = null;
    if (!empty($reportCode)) {
        $rStmt = $pdo->prepare("SELECT id, item_name FROM reports WHERE report_id = :code");
        $rStmt->execute([':code' => $reportCode]);
        $reportRow = $rStmt->fetch();
        if ($reportRow) {
            $reportDbId = $reportRow['id'];
            if (empty($itemName)) {
                $itemName = $reportRow['item_name'];
            }
        }
    }

    $sql = "INSERT INTO claims (claim_id, report_id, report_code, item_name, full_name, email, school_id, status, staff_feedback)
            VALUES (:claim_id, :report_id, :report_code, :item_name, :full_name, :email, :school_id, 'pending', 'Waiting for verification.')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':claim_id'    => $claimId,
        ':report_id'   => $reportDbId,
        ':report_code' => $reportCode,
        ':item_name'   => $itemName,
        ':full_name'   => $fullName,
        ':email'       => $email,
        ':school_id'   => $schoolId
    ]);

    // Mark report status as 'claimed' when a claim is requested
    if ($reportDbId) {
        $upStmt = $pdo->prepare("UPDATE reports SET status = 'claimed' WHERE id = :id AND status = 'confirmed'");
        $upStmt->execute([':id' => $reportDbId]);
    } elseif ($reportCode) {
        $upStmt = $pdo->prepare("UPDATE reports SET status = 'claimed' WHERE report_id = :code AND status = 'confirmed'");
        $upStmt->execute([':code' => $reportCode]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Claim request submitted successfully.',
        'data'    => [
            'claim_id'    => $claimId,
            'report_code' => $reportCode,
            'item_name'   => $itemName,
            'status'      => 'pending'
        ]
    ], 201);
}

function handleUpdateClaim(PDO $pdo): void {
    $input = getJsonInput();

    $id            = $input['id'] ?? null;
    $claimId       = $input['claim_id'] ?? null;
    $status        = $input['status'] ?? null;
    $staffFeedback = $input['staff_feedback'] ?? $input['feedback'] ?? null;

    if (!$id && !$claimId) {
        jsonResponse(['error' => 'Claim ID is required.'], 400);
    }

    if (!$status) {
        jsonResponse(['error' => 'Status is required.'], 400);
    }

    $allowedStatuses = ['pending', 'approved', 'rejected', 'completed'];
    if (!in_array($status, $allowedStatuses)) {
        jsonResponse(['error' => 'Invalid claim status.'], 400);
    }

    $sql = "UPDATE claims SET status = :status";
    $params = [':status' => $status];

    if ($staffFeedback !== null) {
        $sql .= ", staff_feedback = :feedback";
        $params[':feedback'] = $staffFeedback;
    }

    if ($id) {
        $sql .= " WHERE id = :id";
        $params[':id'] = $id;
    } else {
        $sql .= " WHERE claim_id = :claim_id";
        $params[':claim_id'] = $claimId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($status === 'approved' || $status === 'completed') {
        $targetReportStatus = ($status === 'completed') ? 'returned' : 'claimed';
        if ($claimId || $id) {
            $cStmt = $pdo->prepare("SELECT report_id, report_code FROM claims WHERE " . ($id ? "id = :id" : "claim_id = :claim_id"));
            $cStmt->execute($id ? [':id' => $id] : [':claim_id' => $claimId]);
            $cRow = $cStmt->fetch();
            if ($cRow) {
                if ($cRow['report_id']) {
                    $upStmt = $pdo->prepare("UPDATE reports SET status = :st WHERE id = :rid");
                    $upStmt->execute([':st' => $targetReportStatus, ':rid' => $cRow['report_id']]);
                } elseif ($cRow['report_code']) {
                    $upStmt = $pdo->prepare("UPDATE reports SET status = :st WHERE report_id = :rcode");
                    $upStmt->execute([':st' => $targetReportStatus, ':rcode' => $cRow['report_code']]);
                }
            }
        }
    }

    jsonResponse(['success' => true, 'message' => 'Claim request updated successfully.']);
}

function handleDeleteClaim(PDO $pdo): void {
    $input = getJsonInput();
    $id      = $_GET['id'] ?? $input['id'] ?? null;
    $claimId = $_GET['claim_id'] ?? $input['claim_id'] ?? null;

    if (!$id && !$claimId) {
        jsonResponse(['error' => 'Claim ID is required.'], 400);
    }

    // Restore report status back to 'confirmed' if claim is cancelled
    $cStmt = $pdo->prepare("SELECT report_id, report_code FROM claims WHERE " . ($id ? "id = :id" : "claim_id = :claim_id"));
    $cStmt->execute($id ? [':id' => $id] : [':claim_id' => $claimId]);
    $cRow = $cStmt->fetch();

    if ($cRow) {
        if ($cRow['report_id']) {
            $upStmt = $pdo->prepare("UPDATE reports SET status = 'confirmed' WHERE id = :rid AND status = 'claimed'");
            $upStmt->execute([':rid' => $cRow['report_id']]);
        } elseif ($cRow['report_code']) {
            $upStmt = $pdo->prepare("UPDATE reports SET status = 'confirmed' WHERE report_id = :rcode AND status = 'claimed'");
            $upStmt->execute([':rcode' => $cRow['report_code']]);
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM claims WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM claims WHERE claim_id = :claim_id");
        $stmt->execute([':claim_id' => $claimId]);
    }

    jsonResponse(['success' => true, 'message' => 'Claim request cancelled/removed successfully.']);
}
