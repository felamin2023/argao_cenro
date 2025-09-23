<?php
// backend/admins/marine/delete_incident_report.php
declare(strict_types=1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../backend/connection.php'; // must set $pdo (PDO)
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id      = isset($data['id']) ? (int)$data['id'] : 0;
$user_id = isset($data['user_id']) ? trim((string)$data['user_id']) : '';

if ($id <= 0 || $user_id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

/** Helper: normalize stored photo entries to plain paths */
function photo_paths_from_json($json): array
{
    $out = [];
    $arr = is_array($json) ? $json : json_decode((string)$json, true);
    if (is_array($arr)) {
        foreach ($arr as $item) {
            $p = is_string($item) ? $item
                : (is_array($item) ? ($item['path'] ?? $item['name'] ?? $item['key'] ?? '') : '');
            $p = trim((string)$p);
            if ($p !== '' && !preg_match('~^https?://~i', $p)) {
                // only keep storage paths (not absolute URLs)
                $out[] = ltrim($p, '/');
            }
        }
    }
    return array_values(array_unique($out));
}

/** Attempt to delete objects from Supabase Storage (PUBLIC or PRIVATE)
 *  Requires SUPABASE_SERVICE_ROLE_KEY (server side).
 *  Returns ['ok'=>bool, 'code'=>int, 'response'=>mixed] */
function storage_delete_objects(array $paths): array
{
    $base   = rtrim((string)getenv('SUPABASE_URL'), '/');
    $bucket = getenv('INCIDENT_BUCKET') ?: 'incident_report'; // default used in your app
    $svc    = (string)getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (!$base || !$bucket || !$svc || empty($paths)) {
        return ['ok' => false, 'code' => 0, 'response' => 'missing_config_or_empty'];
    }

    // REST: POST /storage/v1/object/{bucket}/remove  body: { "prefixes": ["path1","path2"] }
    $endpoint = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/remove';
    $body = json_encode(['prefixes' => array_values($paths)], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $svc,
            'apikey: ' . $svc,
        ],
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed = json_decode((string)$resp, true);
    return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'response' => $parsed ?: $resp];
}

try {
    // 1) Fetch photos first
    $st = $pdo->prepare('SELECT photos FROM public.incident_report WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }

    $paths = photo_paths_from_json($row['photos']);

    // 2) Delete DB row (commit independently of storage cleanup)
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM public.incident_report WHERE id = :id');
    $del->execute([':id' => $id]);
    $deleted_rows = $del->rowCount();
    $pdo->commit();

    $storage_result = ['ok' => false, 'code' => 0, 'response' => null];
    $storage_attempted = false;

    // 3) Try to delete Storage objects (best-effort)
    if (!empty($paths)) {
        $storage_attempted = true;
        $storage_result = storage_delete_objects($paths);
    }

    echo json_encode([
        'success'             => $deleted_rows > 0,
        'deleted_rows'        => $deleted_rows,
        'storage_attempted'   => $storage_attempted,
        'storage_deleted'     => $storage_result['ok'],
        'storage_http_code'   => $storage_result['code'],
        'storage_response'    => $storage_result['ok'] ? null : $storage_result['response'],
        'deleted_photo_paths' => $paths,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
