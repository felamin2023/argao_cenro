<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../backend/connection.php'; // provides $pdo (PDO connected to Supabase Postgres)

    // Fetch all seedlings with current stock
    $stmt = $pdo->query("
        SELECT seedlings_id, seedling_name, stock
        FROM public.seedlings
        ORDER BY seedling_name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'seedlings' => array_map(static function (array $r): array {
            return [
                'seedlings_id' => $r['seedlings_id'],
                'seedling_name' => $r['seedling_name'],
                'stock'        => (int)($r['stock'] ?? 0),
            ];
        }, $rows),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
