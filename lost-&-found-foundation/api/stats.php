<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

// Regular users only ever see their own numbers. Admins get site-wide totals
// (used by the admin pages) unless they ask for ?scope=mine.
$user  = currentUser();
$mine  = !isAdmin($user) || ($_GET['scope'] ?? '') === 'mine';

if ($mine && $user === null) {
    jsonResponse(['success' => true, 'signed_in' => false, 'data' => [
        'lost_items' => 0, 'found_items' => 0, 'returned_items' => 0,
        'pending_reports' => 0, 'pending_claims' => 0, 'total_claims' => 0, 'categories' => []
    ]]);
}

$reports = $pdo->query("SELECT id, status, report_type, category, user_id FROM reports")->fetchAll();
$claims  = $pdo->query("SELECT id, status, email FROM claims")->fetchAll();

if ($mine) {
    $reports = array_values(array_filter($reports, fn($r) => (int)($r['user_id'] ?? 0) === (int)$user['id']));
    $claims  = array_values(array_filter($claims, fn($c) => strcasecmp((string)($c['email'] ?? ''), $user['email']) === 0));
}

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
    'signed_in' => $user !== null,
    'scope' => $mine ? 'mine' : 'all',
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
