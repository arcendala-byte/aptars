<?php
declare(strict_types=1);

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function baseUrl(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function post(string $key, $default = null) {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}
function get(string $key, $default = null) {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $t) { /* table may not exist yet */ }
    }
    return $cache[$key] ?? $default;
}

function gradeForPercentage(float $percentage): array {
    static $scales = null;
    if ($scales === null) {
        try {
            $scales = db()->query('SELECT * FROM grading_scales ORDER BY min_percentage DESC')->fetchAll();
        } catch (Throwable $t) { $scales = []; }
    }
    foreach ($scales as $s) {
        if ($percentage >= (float)$s['min_percentage'] && $percentage <= (float)$s['max_percentage']) {
            return $s;
        }
    }
    return ['grade' => 'E', 'grade_point' => 0.0, 'description' => 'Fail'];
}

function calculateGrade(float $percentage): string {
    return gradeForPercentage($percentage)['grade'];
}
function calculateGradePoint(string $grade): float {
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db()->query('SELECT grade, grade_point FROM grading_scales') as $r) {
                $map[$r['grade']] = (float)$r['grade_point'];
            }
        } catch (Throwable $t) {}
    }
    return $map[$grade] ?? 0.0;
}

function logActivity(?int $userId, string $action, string $description = ''): void {
    try {
        $stmt = db()->prepare('INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $t) { /* non-fatal */ }
}

function fullName(array $p): string {
    return trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
}

function pct(?float $v, int $dp = 2): string {
    return $v === null ? '-' : number_format($v, $dp) . '%';
}

function asset(string $path): string {
    $full = __DIR__ . '/../' . ltrim($path, '/');
    $v    = @filemtime($full) ?: time();
    return baseUrl($path) . '?v=' . $v;
}