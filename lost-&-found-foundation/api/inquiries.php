<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetInquiries($pdo);
        break;
    case 'POST':
        handleCreateInquiry($pdo);
        break;
    case 'PUT':
    case 'PATCH':
        handleUpdateInquiry($pdo);
        break;
    default:
        jsonResponse(['error' => 'Method Not Allowed'], 405);
}

function handleGetInquiries(PDO $pdo): void {
    $sql = "SELECT id, full_name, email, subject, message, status, created_at FROM inquiries ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $inquiries = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $inquiries]);
}

function handleCreateInquiry(PDO $pdo): void {
    $input = getJsonInput();

    $fullName = trim($input['name'] ?? $input['full_name'] ?? '');
    $email    = trim($input['email'] ?? '');
    $subject  = trim($input['subject'] ?? '');
    $message  = trim($input['message'] ?? '');

    if (empty($fullName) || empty($email) || empty($subject) || empty($message)) {
        jsonResponse(['error' => 'All fields (name, email, subject, message) are required.'], 400);
    }

    $sql = "INSERT INTO inquiries (full_name, email, subject, message, status)
            VALUES (:full_name, :email, :subject, :message, 'unread')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name' => $fullName,
        ':email'     => $email,
        ':subject'   => $subject,
        ':message'   => $message
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Thank you! Your inquiry has been submitted.'
    ], 201);
}

function handleUpdateInquiry(PDO $pdo): void {
    $input = getJsonInput();
    $id     = $input['id'] ?? null;
    $status = $input['status'] ?? null;

    if (!$id || !$status) {
        jsonResponse(['error' => 'Inquiry ID and status are required.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE inquiries SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Inquiry status updated.']);
}
