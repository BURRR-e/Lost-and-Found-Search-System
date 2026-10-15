<?php
require_once __DIR__ . '/../includes/db.php';

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
        handleUpdateReport($pdo);
        break;
    case 'DELETE':
        handleDeleteReport($pdo);
        break;
    default:
        jsonResponse(['error' => 'Method Not Allowed'], 405);
}

function handleGetReports(PDO $pdo): void {
    $status   = $_GET['status'] ?? 'all';
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $type     = trim($_GET['type'] ?? '');
    $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;

    $whereClause = ['1=1'];
    $params = [];

    if ($status !== 'all') {
        if ($status === 'dashboard') {
            $whereClause[] = "status IN ('confirmed', 'claimed', 'returned')";
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
        $whereClause[] = "(item_name LIKE :search OR description LIKE :search OR location LIKE :search OR report_id LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql = "SELECT id, report_id, item_name, category, description, date_event, location, photo_url, status, report_type, created_at
            FROM reports
            WHERE " . implode(' AND ', $whereClause) . "
            ORDER BY id DESC";

    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $reports]);
}

function handleCreateReport(PDO $pdo): void {
    $itemName    = trim($_POST['item-name'] ?? $_POST['itemName'] ?? '');
    $category    = trim($_POST['item-category'] ?? $_POST['category'] ?? 'others');
    $description = trim($_POST['description'] ?? '');
    $dateEvent   = trim($_POST['date'] ?? date('Y-m-d'));
    $location    = trim($_POST['location'] ?? '');
    $reportType  = trim($_POST['report_type'] ?? $_POST['type'] ?? 'lost');

    if (empty($itemName)) {
        jsonResponse(['error' => 'Item name is required.'], 400);
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

    if (empty($photoUrl) && !empty($_POST['photo_url'])) {
        $photoUrl = trim($_POST['photo_url']);
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

    $todayStr = date('mdY');
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE DATE(created_at) = CURDATE()");
    $countStmt->execute();
    $todayCount = (int)$countStmt->fetchColumn() + 1;
    $reportId = sprintf("R-%s-#%03d", $todayStr, $todayCount);

    $sql = "INSERT INTO reports (report_id, item_name, category, description, date_event, location, photo_url, status, report_type)
            VALUES (:report_id, :item_name, :category, :description, :date_event, :location, :photo_url, 'pending', :report_type)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':report_id'   => $reportId,
        ':item_name'   => $itemName,
        ':category'    => $category,
        ':description' => $description,
        ':date_event'  => $dateEvent,
        ':location'    => $location,
        ':photo_url'   => $photoUrl,
        ':report_type' => $reportType
    ]);

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
            'status'      => 'pending',
            'report_type' => $reportType
        ]
    ], 201);
}

function handleUpdateReport(PDO $pdo): void {
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

function handleDeleteReport(PDO $pdo): void {
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
