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

function handleGetClaims(PDO|FileDbAdapter $pdo): void {
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

function handleCreateClaim(PDO|FileDbAdapter $pdo): void {
    $input = getJsonInput();

    $reportCode  = trim($input['report_code'] ?? $input['reportCode'] ?? '');
    $itemName    = trim($input['item_name']   ?? $input['itemName']   ?? '');
    $fullName    = trim($input['full_name']   ?? $input['fullName']   ?? 'Sample User');
    $email       = trim($input['email']       ?? 'user@school.edu');
    $schoolId    = trim($input['school_id']   ?? $input['schoolId']   ?? '000000');

    if (empty($reportCode) && empty($itemName)) {
        jsonResponse(['error' => 'Item information or Report Code is required.'], 400);
    }
    $maxNum = 0;
    foreach ($pdo->query("SELECT claim_id FROM claims")->fetchAll() as $row) {
        if (preg_match('/^CLM-(\d+)$/', $row['claim_id'], $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $claimId = sprintf("CLM-%03d", $maxNum + 1);

    $reportDbId = null;
    if (!empty($reportCode)) {
        $rStmt = $pdo->prepare("SELECT id, item_name, status FROM reports WHERE report_id = :code");
        $rStmt->execute([':code' => $reportCode]);
        $reportRow = $rStmt->fetch();
        if (!$reportRow) {
            jsonResponse(['error' => 'Report not found.'], 404);
        }
        if (($reportRow['status'] ?? '') !== 'confirmed') {
            jsonResponse(['error' => 'This item is no longer available to claim.'], 409);
        }
        $reportDbId = $reportRow['id'];
        if (empty($itemName)) {
            $itemName = $reportRow['item_name'];
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

function handleUpdateClaim(PDO|FileDbAdapter $pdo): void {
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

    $cStmt = $pdo->prepare("SELECT report_id, report_code FROM claims WHERE " . ($id ? "id = :id" : "claim_id = :claim_id"));
    $cStmt->execute($id ? [':id' => $id] : [':claim_id' => $claimId]);
    $cRow = $cStmt->fetch();
    if ($cRow) {
        if ($status === 'approved') {
            setReportStatusForClaim($pdo, $cRow, 'claimed');
        } elseif ($status === 'completed') {
            setReportStatusForClaim($pdo, $cRow, 'returned');
        } elseif ($status === 'rejected') {
            setReportStatusForClaim($pdo, $cRow, 'confirmed', 'claimed');
        }
    }

    jsonResponse(['success' => true, 'message' => 'Claim request updated successfully.']);
}

function handleDeleteClaim(PDO|FileDbAdapter $pdo): void {
    $input = getJsonInput();
    $id      = $_GET['id'] ?? $input['id'] ?? null;
    $claimId = $_GET['claim_id'] ?? $input['claim_id'] ?? null;

    if (!$id && !$claimId) {
        jsonResponse(['error' => 'Claim ID is required.'], 400);
    }

    $cStmt = $pdo->prepare("SELECT report_id, report_code FROM claims WHERE " . ($id ? "id = :id" : "claim_id = :claim_id"));
    $cStmt->execute($id ? [':id' => $id] : [':claim_id' => $claimId]);
    $cRow = $cStmt->fetch();

    if ($cRow) {
        setReportStatusForClaim($pdo, $cRow, 'confirmed', 'claimed');
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

/**
 * Set the status of the report linked to a claim row (by numeric id, else by report code).
 * $onlyIfCurrent restricts the change to reports currently in that status.
 */
function setReportStatusForClaim(PDO|FileDbAdapter $pdo, array $cRow, string $newStatus, ?string $onlyIfCurrent = null): void {
    $cond = $onlyIfCurrent !== null ? " AND status = '" . $onlyIfCurrent . "'" : '';

    if (!empty($cRow['report_id'])) {
        $st = $pdo->prepare("UPDATE reports SET status = :st WHERE id = :rid" . $cond);
        $st->execute([':st' => $newStatus, ':rid' => $cRow['report_id']]);
    } elseif (!empty($cRow['report_code'])) {
        $st = $pdo->prepare("UPDATE reports SET status = :st WHERE report_id = :rcode" . $cond);
        $st->execute([':st' => $newStatus, ':rcode' => $cRow['report_code']]);
    }
}
