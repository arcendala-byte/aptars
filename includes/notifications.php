<?php
declare(strict_types=1);

/**
 * Create a notification for a user.
 * $type = 'info' | 'success' | 'warning' | 'danger'
 */
function createNotification(int $userId, string $title, string $message, string $type = 'info'): void {
    if ($userId <= 0) return;
    $validTypes = ['info','success','warning','danger'];
    if (!in_array($type, $validTypes, true)) $type = 'info';
    try {
        db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)')
            ->execute([$userId, $title, $message, $type]);
    } catch (Throwable $t) {
        error_log('[APTARS notif] ' . $t->getMessage());
    }
}

/**
 * Create the same notification for multiple users.
 */
function createNotificationBulk(array $userIds, string $title, string $message, string $type = 'info'): int {
    $created = 0;
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        if ($uid <= 0) continue;
        createNotification($uid, $title, $message, $type);
        $created++;
    }
    return $created;
}

function unreadNotificationCount(int $userId): int {
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $t) { return 0; }
}

function markNotificationRead(int $notificationId, int $userId): void {
    db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
        ->execute([$notificationId, $userId]);
}

function markAllNotificationsRead(int $userId): void {
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$userId]);
}

/**
 * Get the users linked to a set of students (for notifications).
 * Only students with a linked user account receive notifications.
 */
function usersForStudents(array $studentIds): array {
    if (!$studentIds) return [];
    $ids = array_map('intval', $studentIds);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT user_id FROM students WHERE id IN ($ph) AND user_id IS NOT NULL");
    $stmt->execute($ids);
    return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
}

/** Human-friendly relative time (e.g. "3 hours ago"). */
function timeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    if ($ts === false) return $datetime;
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hr ago';
    if ($diff < 604800) return floor($diff/86400) . ' day(s) ago';
    return date('d M Y', $ts);
}

/** Badge class for a notification type. */
function notificationIcon(string $type): string {
    $map = ['info'=>'bi-info-circle','success'=>'bi-check-circle','warning'=>'bi-exclamation-triangle','danger'=>'bi-x-circle'];
    return $map[$type] ?? 'bi-bell';
}