<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$reports = $pdo->query("SELECT id, status, report_type, category FROM reports")->fetchAll();
$claims  = $pdo->query("SELECT id, status FROM claims")->fetchAll();

$lostCount = $foundCount = $returnedCount = $pendingReports = 0;
$categories = [];

foreach ($reports as $r) {
    $status = $r['status'] ?? '';
    $type   = $r['report_type'] ?? '';

    if ($status === 'confirmed') {
        if ($type === 'lost')  $lostCount++;
        if ($type === 'found') $foundCount++;
    }
    if ($status === 'returned' || $status === 'claimed') $returnedCount++;
    if ($status === 'pending') $pendingReports++;

    if (in_array($status, ['confirmed', 'claimed', 'returned'], true)) {
        $cat = $r['category'] ?? 'others';
        $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    }
}

$pendingClaims = 0;
foreach ($claims as $c) {
    if (($c['status'] ?? '') === 'pending') $pendingClaims++;
}

$categoryBreakdown = [];
foreach ($categories as $cat => $total) {
    $categoryBreakdown[] = ['category' => $cat, 'total' => $total];
}

jsonResponse([
    'success' => true,
    'data' => [
        'lost_items'      => $lostCount,
        'found_items'     => $foundCount,
        'returned_items'  => $returnedCount,
        'pending_reports' => $pendingReports,
        'pending_claims'  => $pendingClaims,
        'total_claims'    => count($claims),
        'categories'      => $categoryBreakdown
    ]
]);
