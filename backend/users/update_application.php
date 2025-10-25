<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/connection.php';

const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) {
        return REQUIREMENTS_BUCKET;
    }
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) {
        return SUPABASE_BUCKET;
    }
    return DEFAULT_REQUIREMENTS_BUCKET;
}

function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    $segments = explode('/', $path);
    $segments = array_map('rawurlencode', $segments);
    return implode('/', $segments);
}

function supa_public_url(string $bucket, string $path): string
{
    $encoded = encode_path_segments($path);
    return rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/{$bucket}/{$encoded}";
}

function supa_upload(string $bucket, string $path, string $tmpPath, string $mime): string
{
    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";
    $data = @file_get_contents($tmpPath);
    if ($data === false) {
        throw new Exception('Failed to read uploaded file.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $code >= 300) {
        $err = $resp ?: curl_error($ch);
        curl_close($ch);
        throw new Exception("Storage upload failed ({$code}): {$err}");
    }
    curl_close($ch);

    return supa_public_url($bucket, $path);
}

function clean_field_name(string $field): string
{
    return preg_replace('/[^a-z0-9_]+/i', '_', trim($field));
}

function generate_storage_path(string $requestType, string $approvalId, string $originalName): string
{
    $base = $requestType !== '' ? $requestType : 'requests';
    $base = preg_replace('/[^a-z0-9_-]+/i', '-', $base);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = $ext ? '.' . $ext : '';
    $stamp = date('Ymd_His');
    $rand = substr(bin2hex(random_bytes(4)), 0, 8);
    return "{$base}/updates/{$approvalId}/{$stamp}_{$rand}{$ext}";
}

try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Not authenticated.');
    }

    $approvalId = trim((string)($_POST['approval_id'] ?? ''));
    if ($approvalId === '') {
        throw new Exception('Missing approval_id.');
    }

    $fields = $_POST['fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }

    $requestType = strtolower(trim((string)($_POST['request_type'] ?? '')));
    $permitType = strtolower(trim((string)($_POST['permit_type'] ?? '')));

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        select
            a.application_id,
            a.requirement_id,
            lower(coalesce(a.approval_status,'')) as status,
            lower(coalesce(a.request_type,''))     as request_type,
            lower(coalesce(a.permit_type,''))      as permit_type
        from public.approval a
        join public.client c on c.client_id = a.client_id
        where a.approval_id = :aid
          and c.user_id = :uid
        limit 1
    ");
    $stmt->execute([
        ':aid' => $approvalId,
        ':uid' => $_SESSION['user_id'],
    ]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$approval) {
        throw new Exception('Approval not found.');
    }

    if (($approval['status'] ?? '') !== 'pending') {
        throw new Exception('Only pending requests can be edited.');
    }

    $applicationId = $approval['application_id'] ?? null;
    $requirementId = $approval['requirement_id'] ?? null;
    $dbRequestType = strtolower((string)($approval['request_type'] ?? '')) ?: $requestType;
    $dbPermitType  = strtolower((string)($approval['permit_type'] ?? '')) ?: $permitType;

    $appRow = [];
    if ($applicationId) {
        $stmt = $pdo->prepare("select * from public.application_form where application_id = :id limit 1");
        $stmt->execute([':id' => $applicationId]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        // allow case-insensitive matching
        $appRow = array_change_key_case($appRow, CASE_LOWER);
    }


    $reqRow = [];
    if ($requirementId) {
        $stmt = $pdo->prepare("select * from public.requirements where requirement_id = :id limit 1");
        $stmt->execute([':id' => $requirementId]);
        $reqRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Never allow these app_form columns to be updated
    $blockedAppCols = [
        'application_id',       // generated
        'application_for',      // read-only in edit mode
        'type_of_permit',       // read-only in edit mode
        // common protected/system fields:
        'request_type',
        'permit_type',
        'approval_status',
        'status',
        'created_at',
        'updated_at',
        'submitted_at'
    ];

    $appUpdates = [];
    foreach ($fields as $field => $value) {
        if (!is_string($field)) continue;
        $fieldName = strtolower(clean_field_name($field)); // normalize
        if ($fieldName === '' || !array_key_exists($fieldName, $appRow)) continue;
        if (in_array($fieldName, $blockedAppCols, true)) continue; // still block system columns
        $appUpdates[$fieldName] = trim((string)$value);
    }



    $fileOrigins = $_POST['file_origins'] ?? [];
    if (!is_array($fileOrigins)) {
        $fileOrigins = [];
    }

    $appFileUpdates = [];
    $reqFileUpdates = [];

    if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $bucket = bucket_name();
        foreach ($_FILES['files']['name'] as $field => $name) {
            $fieldName = strtolower(clean_field_name((string)$field));
            if ($fieldName === '') {
                continue;
            }

            $tmpName = $_FILES['files']['tmp_name'][$field] ?? '';
            $error = $_FILES['files']['error'][$field] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE || !is_uploaded_file($tmpName)) {
                continue;
            }

            $origin = strtolower((string)($fileOrigins[$field] ?? 'requirements'));
            $origin = in_array($origin, ['application_form', 'requirements'], true) ? $origin : 'requirements';

            if ($origin === 'application_form' && !array_key_exists($fieldName, $appRow)) {
                continue;
            }
            if ($origin === 'requirements' && !array_key_exists($fieldName, $reqRow)) {
                continue;
            }

            $mime = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $tmpName);
                    if ($detected) {
                        $mime = $detected;
                    }
                    finfo_close($finfo);
                }
            }

            $storagePath = generate_storage_path($dbRequestType ?: $requestType, (string)$approvalId, (string)$name);
            $publicUrl = supa_upload($bucket, $storagePath, $tmpName, $mime);

            if ($origin === 'application_form') {
                $appFileUpdates[$fieldName] = $publicUrl;
            } else {
                $reqFileUpdates[$fieldName] = $publicUrl;
            }
        }
    }

    foreach ($appFileUpdates as $field => $url) {
        $appUpdates[$field] = $url;
    }

    if ($applicationId && $appUpdates) {
        $setParts = [];
        $params = [':id' => $applicationId];
        foreach ($appUpdates as $field => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', $field)) continue;
            if (in_array(strtolower($field), $blockedAppCols, true)) continue; // double guard
            $setParts[] = "\"{$field}\" = :app_{$field}";
            $params[":app_{$field}"] = $value;
        }
        if ($setParts) {
            $sql = 'update public.application_form set ' . implode(', ', $setParts) . ' where application_id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }


    if ($requirementId && $reqFileUpdates) {
        $setParts = [];
        $params = [':id' => $requirementId];
        foreach ($reqFileUpdates as $field => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', $field)) {
                continue;
            }
            $setParts[] = "\"{$field}\" = :req_{$field}";
            $params[":req_{$field}"] = $value;
        }
        if ($setParts) {
            $sql = 'update public.requirements set ' . implode(', ', $setParts) . ' where requirement_id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    $pdo->commit();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
