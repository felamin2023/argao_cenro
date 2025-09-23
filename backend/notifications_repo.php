<?php
declare(strict_types=1);

/**
 * Notifications repository (Supabase PG via PDO)
 * Requires: backend/connection.php exposing $pdo
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/connection.php';
}

/**
 * Get recent notifications for a user.
 * $onlyUnread = true returns only unread items.
 */
function fetch_user_notifications(PDO $pdo, string $userId, int $limit = 10, int $offset = 0, bool $onlyUnread = false): array {
    $sql = <<<SQL
        SELECT
            n.notif_id,
            n.message,
            n.status,
            n.is_read,
            n.created_at,
            a.approval_id,
            a.request_type,
            a.approval_status,
            a.permit_type,
            a.application_id
        FROM public.notifications n
        LEFT JOIN public.approval a
               ON a.approval_id = n.approval_id
        LEFT JOIN public.client c1
               ON c1.client_id = a.client_id
        LEFT JOIN public.application_form af
               ON af.application_id = a.application_id
        LEFT JOIN public.client c2
               ON c2.client_id = af.client_id
        WHERE (c1.user_id = :user_id OR c2.user_id = :user_id)
    SQL;

    if ($onlyUnread) {
        $sql .= " AND n.is_read = FALSE";
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";

    $st = $pdo->prepare($sql);
    $st->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Count unread notifications for a user */
function count_unread_notifications(PDO $pdo, string $userId): int {
    $sql = <<<SQL
        SELECT COUNT(*)
        FROM public.notifications n
        LEFT JOIN public.approval a
               ON a.approval_id = n.approval_id
        LEFT JOIN public.client c1
               ON c1.client_id = a.client_id
        LEFT JOIN public.application_form af
               ON af.application_id = a.application_id
        LEFT JOIN public.client c2
               ON c2.client_id = af.client_id
        WHERE (c1.user_id = :user_id OR c2.user_id = :user_id)
          AND n.is_read = FALSE
    SQL;

    $st = $pdo->prepare($sql);
    $st->execute([':user_id' => $userId]);
    return (int)$st->fetchColumn();
}

/** Mark ALL notifications for this user as read; returns affected rows */
function mark_all_read(PDO $pdo, string $userId): int {
    $sql = <<<SQL
        UPDATE public.notifications n
           SET is_read = TRUE
        FROM public.approval a
        LEFT JOIN public.client c1
               ON c1.client_id = a.client_id
        LEFT JOIN public.application_form af
               ON af.application_id = a.application_id
        LEFT JOIN public.client c2
               ON c2.client_id = af.client_id
        WHERE n.approval_id = a.approval_id
          AND (c1.user_id = :user_id OR c2.user_id = :user_id)
          AND n.is_read = FALSE
    SQL;

    $st = $pdo->prepare($sql);
    $st->execute([':user_id' => $userId]);
    return $st->rowCount();
}

/** Mark a single notification (by notif_id) as read, but only if it belongs to this user */
function mark_read(PDO $pdo, string $userId, string $notifId): bool {
    $sql = <<<SQL
        UPDATE public.notifications n
           SET is_read = TRUE
        FROM public.approval a
        LEFT JOIN public.client c1
               ON c1.client_id = a.client_id
        LEFT JOIN public.application_form af
               ON af.application_id = a.application_id
        LEFT JOIN public.client c2
               ON c2.client_id = af.client_id
        WHERE n.approval_id = a.approval_id
          AND (c1.user_id = :user_id OR c2.user_id = :user_id)
          AND n.notif_id = :notif_id
    SQL;

    $st = $pdo->prepare($sql);
    return $st->execute([':user_id' => $userId, ':notif_id' => $notifId]);
}

/** Simple "time ago" utility for display */
function time_ago_string(string $ts): string {
    $dt = new DateTime($ts);
    $now = new DateTime('now', $dt->getTimezone());
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) return $diff . 's ago';
    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . 'm ago';
    $hours = intdiv($mins, 60);
    if ($hours < 24) return $hours . 'h ago';
    $days = intdiv($hours, 24);
    if ($days < 7) return $days . 'd ago';
    return $dt->format('M j, Y');
}
