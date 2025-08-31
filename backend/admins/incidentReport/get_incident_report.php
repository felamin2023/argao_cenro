<?php
// backend/admins/marine/get_incident_report.php
declare(strict_types=1);
header('Content-Type: application/json');

/*
 * Uses a PUBLIC bucket:
 *   - Bucket name: incident_report  (confirmed)
 *   - SUPABASE_URL read from backend/.env (with a PHP 7–safe fallback)
 */

// ---------- paths ----------
$BACKEND_DIR = dirname(__DIR__, 2); // .../backend

// ---------- DB connection ----------
try {
    require_once $BACKEND_DIR . '/connection.php'; // must set $pdo
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error', 'debug' => $e->getMessage()]);
    exit;
}

// ---------- simple env helpers (PHP 7 safe) ----------
function envs($k, $def = '')
{
    $v = getenv($k);
    return ($v === false || $v === null || $v === '') ? $def : (string)$v;
}
function read_env_value($file, $key)
{
    if (!is_file($file)) return null;
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return null;
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] === '#') continue;
        $pos = strpos($ln, '=');
        if ($pos === false) continue;
        $k = rtrim(substr($ln, 0, $pos));
        if ($k !== $key) continue;
        $val = ltrim(substr($ln, $pos + 1));
        // strip quotes without PHP 8 functions
        if ((strlen($val) >= 2) &&
            (($val[0] === '"' && substr($val, -1) === '"') ||
                ($val[0] === "'" && substr($val, -1) === "'"))
        ) {
            $val = substr($val, 1, -1);
        }
        return $val;
    }
    return null;
}
$SUPABASE_URL = envs('SUPABASE_URL');
if ($SUPABASE_URL === '') {
    $fromFile = read_env_value($BACKEND_DIR . '/.env', 'SUPABASE_URL');
    if ($fromFile) {
        $SUPABASE_URL = $fromFile;
        putenv('SUPABASE_URL=' . $SUPABASE_URL);
        $_ENV['SUPABASE_URL'] = $SUPABASE_URL;
        $_SERVER['SUPABASE_URL'] = $SUPABASE_URL;
    }
}

$BUCKET = 'incident_report';                // confirmed by your screenshot
$BASE   = rtrim((string)$SUPABASE_URL, '/'); // e.g. https://abcd1234.supabase.co
if ($BASE === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'Missing SUPABASE_URL in environment',
        'hint'  => "Add to {$BACKEND_DIR}/.env:\nSUPABASE_URL=https://YOUR-PROJECT-REF.supabase.co"
    ]);
    exit;
}

// ---------- helpers ----------
function encode_path_segments($p)
{
    $p = ltrim((string)$p, '/');
    $parts = array_filter(explode('/', $p), static function ($s) {
        return $s !== '';
    });
    return implode('/', array_map('rawurlencode', $parts));
}
function public_storage_url($base, $bucket, $path)
{
    $base   = rtrim($base, '/');
    $bucket = rawurlencode($bucket);
    return $base . '/storage/v1/object/public/' . $bucket . '/' . encode_path_segments($path);
}

// ---------- input ----------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

// ---------- query ----------
try {
    $st = $pdo->prepare('
        SELECT id, user_id, who, what, "where", "when", why,
               contact_no, photos, category, more_description, status, created_at
        FROM public.incident_report
        WHERE id = :id
        LIMIT 1
    ');
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    // photos jsonb -> array of PUBLIC URLs
    $photoUrls = [];
    if (!empty($row['photos'])) {
        $arr = is_array($row['photos']) ? $row['photos'] : json_decode((string)$row['photos'], true);
        if (is_array($arr)) {
            foreach ($arr as $item) {
                $p = is_string($item) ? $item
                    : (is_array($item) ? ($item['path'] ?? $item['name'] ?? $item['key'] ?? '') : '');
                $p = trim((string)$p);
                if ($p === '') continue;

                if (preg_match('~^https?://~i', $p)) {
                    $photoUrls[] = $p; // already a full URL
                } else {
                    $photoUrls[] = public_storage_url($BASE, $BUCKET, $p);
                }
            }
        }
    }

    echo json_encode([
        'id'          => $row['id'],
        'user_id'     => $row['user_id'],
        'who'         => $row['who'],
        'what'        => $row['what'],
        'where'       => $row['where'],
        'when'        => $row['when'],
        'why'         => $row['why'],
        'contact_no'  => $row['contact_no'],
        'category'    => $row['category'],
        'description' => $row['more_description'],
        'status'      => $row['status'],
        'created_at'  => $row['created_at'],
        'photo_urls'  => $photoUrls,
        'debug_bucket' => $BUCKET,
        'debug_base'  => $BASE
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'debug' => $e->getMessage()]);
}
