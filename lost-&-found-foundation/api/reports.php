<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetReports($pdo);
        break;
    case 'POST':
        handleCreateReport($pdo);
        break;
    case 'PUT':
    case 'PATCH':
        requireAdmin();
        handleUpdateReport($pdo);
        break;
    case 'DELETE':
        requireAdmin();
        handleDeleteReport($pdo);
        break;
    default:
        jsonResponse(['error' => 'Method Not Allowed'], 405);
}

function handleGetReports(PDO|FileDbAdapter $pdo): void {
    $status   = strtolower(trim($_GET['status'] ?? 'all'));
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $type     = strtolower(trim($_GET['type'] ?? ''));
    $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $mine     = !empty($_GET['mine']);
    $sessUser = $mine ? requireLogin() : null;

    $whereClause = ['1=1'];
    $params = [];

    if (!isAdmin()) {
        $whereClause[] = "status IN ('confirmed', 'claimed', 'returned')";
    }

    if ($status !== 'all' && !empty($status)) {
        if ($status === 'dashboard') {
            $whereClause[] = "status IN ('confirmed', 'claimed', 'returned')";
        } elseif ($status === 'lost' || $status === 'found') {
            $whereClause[] = "report_type = :status_type AND status IN ('confirmed', 'claimed', 'returned')";
            $params[':status_type'] = $status;
        } else {
            $whereClause[] = "status = :status";
            $params[':status'] = $status;
        }
    }

    if (!empty($category)) {
        $whereClause[] = "category = :category";
        $params[':category'] = $category;
    }

    if (!empty($type)) {
        $whereClause[] = "report_type = :type";
        $params[':type'] = $type;
    }

    if (!empty($search)) {
        $whereClause[] = "(item_name LIKE :s1 OR description LIKE :s2 OR location LIKE :s3 OR report_id LIKE :s4)";
        $like = '%' . $search . '%';
        $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = $like;
    }

    $sql = "SELECT id, report_id, item_name, category, description, date_event, location, photo_url, status, report_type, created_at, user_id
            FROM reports
            WHERE " . implode(' AND ', $whereClause) . "
            ORDER BY id DESC";

    if ($limit > 0 && !$mine) {
        $sql .= " LIMIT " . $limit;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll();

        if ($mine) {
            $reports = array_values(array_filter($reports, fn($r) => (int)($r['user_id'] ?? 0) === (int)$sessUser['id']));
            if ($limit > 0) $reports = array_slice($reports, 0, $limit);
        }
        foreach ($reports as &$r) { unset($r['user_id']); }
        unset($r);

        jsonResponse(['success' => true, 'data' => $reports]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage(), 'data' => []], 500);
    }
}

function handleCreateReport(PDO|FileDbAdapter $pdo): void {
    $sessionUser = requireLogin();
    $input = getJsonInput();

    $itemName    = trim($input['item-name'] ?? $input['itemName'] ?? $input['item_name'] ?? $_POST['item-name'] ?? $_POST['item_name'] ?? '');
    $category    = trim($input['item-category'] ?? $input['category'] ?? $input['item_category'] ?? $_POST['item-category'] ?? 'others');
    $description = trim($input['description'] ?? $_POST['description'] ?? '');
    $dateEvent   = trim($input['date'] ?? $input['date_event'] ?? $_POST['date'] ?? date('Y-m-d'));
    $location    = trim($input['location'] ?? $_POST['location'] ?? '');
    $reportType  = trim($input['report_type'] ?? $input['report-type'] ?? $input['type'] ?? $_POST['report_type'] ?? 'lost');

    if (empty($itemName)) {
        jsonResponse(['error' => 'Item name is required.'], 400);
    }

    $reportType = strtolower($reportType);
    if (!in_array($reportType, ['lost', 'found'], true)) {
        $reportType = 'lost';
    }
    $validCategories = ['school-supplies', 'electronics', 'personal-belongings', 'clothing', 'others'];
    if (!in_array($category, $validCategories, true)) {
        $category = 'others';
    }

    $photoUrl = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $photoUrl = '/uploads/' . $newFileName;
            }
        }
    }

    if (empty($photoUrl) && (!empty($input['photo_url']) || !empty($_POST['photo_url']))) {
        $photoUrl = trim($input['photo_url'] ?? $_POST['photo_url'] ?? '');
    }

    if (empty($photoUrl)) {
        $categoryImages = [
            'school-supplies'    => '/assets/category/school-supplies.png',
            'electronics'        => '/assets/category/electronics.png',
            'personal-belongings'=> '/assets/category/personal-belongings.png',
            'clothing'           => '/assets/category/clothes.png',
            'others'             => '/assets/category/others.png'
        ];
        $photoUrl = $categoryImages[$category] ?? '/assets/category/others.png';
    }

    $prefix = 'R-' . date('mdY') . '-#';
    $maxSeq = 0;
    foreach ($pdo->query("SELECT report_id FROM reports")->fetchAll() as $row) {
        if (str_starts_with($row['report_id'], $prefix)) {
            $maxSeq = max($maxSeq, (int)substr($row['report_id'], strlen($prefix)));
        }
    }
    $reportId = sprintf("%s%03d", $prefix, $maxSeq + 1);

    $sql = "INSERT INTO reports (report_id, item_name, category, description, date_event, location, photo_url, status, report_type, user_id)
            VALUES (:report_id, :item_name, :category, :description, :date_event, :location, :photo_url, 'confirmed', :report_type, :user_id)";

    try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':report_id'   => $reportId,
        ':item_name'   => $itemName,
        ':category'    => $category,
        ':description' => $description,
        ':date_event'  => $dateEvent,
        ':location'    => $location,
        ':photo_url'   => $photoUrl,
        ':report_type' => $reportType,
        ':user_id'     => $sessionUser['id']
    ]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Could not save report: ' . $e->getMessage()], 500);
    }

    $insertedId = $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Report submitted successfully.',
        'data'    => [
            'id'          => $insertedId,
            'report_id'   => $reportId,
            'item_name'   => $itemName,
            'category'    => $category,
            'description' => $description,
            'date_event'  => $dateEvent,
            'location'    => $location,
            'photo_url'   => $photoUrl,
            'status'      => 'confirmed',
            'report_type' => $reportType
        ]
    ], 201);
}

function handleUpdateReport(PDO|FileDbAdapter $pdo): void {
    $input = getJsonInput();

    $id       = $input['id'] ?? null;
    $reportId = $input['report_id'] ?? null;
    $status   = $input['status'] ?? null;

    if (!$id && !$reportId) {
        jsonResponse(['error' => 'Report ID is required.'], 400);
    }

    if (!$status) {
        jsonResponse(['error' => 'Status is required.'], 400);
    }

    $allowedStatuses = ['pending', 'confirmed', 'rejected', 'claimed', 'returned'];
    if (!in_array($status, $allowedStatuses)) {
        jsonResponse(['error' => 'Invalid status value.'], 400);
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE reports SET status = :status WHERE report_id = :report_id");
        $stmt->execute([':status' => $status, ':report_id' => $reportId]);
    }

    jsonResponse(['success' => true, 'message' => 'Report updated successfully.']);
}

function handleDeleteReport(PDO|FileDbAdapter $pdo): void {
    $input = getJsonInput();
    $id       = $_GET['id'] ?? $input['id'] ?? null;
    $reportId = $_GET['report_id'] ?? $input['report_id'] ?? null;

    if (!$id && !$reportId) {
        jsonResponse(['error' => 'Report ID is required for deletion.'], 400);
    }

    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE report_id = :report_id");
        $stmt->execute([':report_id' => $reportId]);
    }

    jsonResponse(['success' => true, 'message' => 'Report deleted successfully.']);
}
