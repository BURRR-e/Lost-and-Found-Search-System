<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

$lostCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE report_type = 'lost' AND status IN ('pending', 'confirmed')")->fetchColumn();
$foundCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE report_type = 'found' AND status IN ('pending', 'confirmed')")->fetchColumn();
$returnedCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('returned', 'claimed')")->fetchColumn();

$pendingReports = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$pendingClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'")->fetchColumn();
$totalClaims = (int)$pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();

$catStmt = $pdo->query("SELECT category, COUNT(*) as total FROM reports GROUP BY category");
$categoryBreakdown = $catStmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'lost_items'      => $lostCount,
        'found_items'     => $foundCount,
        'returned_items'  => $returnedCount,
        'pending_reports' => $pendingReports,
        'pending_claims'  => $pendingClaims,
        'total_claims'    => $totalClaims,
        'categories'      => $categoryBreakdown
    ]
]);
