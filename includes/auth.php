<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'         => (int)$_SESSION['user_id'],
        'username'   => $_SESSION['username']   ?? '',
        'email'      => $_SESSION['email']      ?? '',
        'role'       => $_SESSION['role'],
        'name'       => $_SESSION['full_name']  ?? ($_SESSION['username'] ?? ''),
        'profile_id' => $_SESSION['profile_id'] ?? null,
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirect(baseUrl('login.php'));
    }
}

function requireRole($roles): void {
    requireLogin();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        setFlash('danger', 'You are not authorized to access that page.');
        redirect(baseUrl('index.php'));
    }
}

function loginUser(array $user, ?int $profileId = null, string $fullName = ''): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['profile_id'] = $profileId;
    $_SESSION['full_name']  = $fullName !== '' ? $fullName : $user['username'];
}

function currentProfile(): ?array {
    $u = currentUser();
    if (!$u) return null;
    try {
        if ($u['role'] === 'student') {
            $stmt = db()->prepare('SELECT * FROM students WHERE user_id = ? LIMIT 1');
        } elseif ($u['role'] === 'lecturer') {
            $stmt = db()->prepare('SELECT * FROM lecturers WHERE user_id = ? LIMIT 1');
        } else {
            return null;
        }
        $stmt->execute([$u['id']]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $t) {
        return null;
    }
}