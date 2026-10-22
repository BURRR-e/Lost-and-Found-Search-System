<?php
/**
 * Session helpers shared by every API endpoint.
 * Sessions are server-side; the browser only holds an HttpOnly cookie.
 */
require_once __DIR__ . '/db.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('lf_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Only these fields may ever be sent to the browser (never password_hash). */
function publicUser(array $u): array {
    return [
        'id'         => (int)($u['id'] ?? 0),
        'full_name'  => $u['full_name'] ?? '',
        'email'      => $u['email'] ?? '',
        'school_id'  => $u['school_id'] ?? '',
        'role'       => $u['role'] ?? 'Student',
        'created_at' => $u['created_at'] ?? null,
    ];
}

function currentUser(): ?array {
    startSecureSession();
    return $_SESSION['user'] ?? null;
}

function isAdmin(?array $user = null): bool {
    $user = $user ?? currentUser();
    return $user !== null && ($user['role'] ?? '') === 'Admin';
}

function requireLogin(): array {
    $user = currentUser();
    if ($user === null) {
        jsonResponse(['success' => false, 'error' => 'Please sign in to continue.'], 401);
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireLogin();
    if (!isAdmin($user)) {
        jsonResponse(['success' => false, 'error' => 'Admin access required.'], 403);
    }
    return $user;
}

function loginSession(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user'] = publicUser($user);
}

function logoutSession(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
