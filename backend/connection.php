<?php

// $host = "localhost";
// $user = "root";
// $pass = "";
// $dbname = "cenro_argao";

// $conn = new mysqli($host, $user, $pass, $dbname);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }


// connection.php

declare(strict_types=1);

$host = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port = '6543';
$db   = 'postgres';
$user = 'postgres.odbjapuchpxwzdghjfof';
$pass = 'argao.cenr0*';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw new Exception('Database connection failed');
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://odbjapuchpxwzdghjfof.supabase.co');
}
if (!defined('SUPABASE_SERVICE_KEY')) {
    define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9kYmphcHVjaHB4d3pkZ2hqZm9mIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1NjIyODc5NCwiZXhwIjoyMDcxODA0Nzk0fQ.fqpUdJfOobnzdxZ7WUaE5OiTHMFtOEhQiBm8GWJccpg');              // <-- CHANGE THIS
}


if (!defined('SUPABASE_BUCKET')) {
    define('SUPABASE_BUCKET', 'user_profiles');
}
if (!defined('SUPABASE_BUCKET_PROFILES')) {
    define('SUPABASE_BUCKET_PROFILES', 'user_profiles');
}
if (!defined('REQUIREMENTS_BUCKET')) {
    define('REQUIREMENTS_BUCKET', 'requirements');
}

// --- Make constants visible as environment variables for pages using env_get() ---
if (!getenv('SUPABASE_URL')) {
    putenv('SUPABASE_URL=' . SUPABASE_URL);
    $_ENV['SUPABASE_URL'] = SUPABASE_URL;
    $_SERVER['SUPABASE_URL'] = SUPABASE_URL;
}

// NOTE: your constant is SUPABASE_SERVICE_KEY but the code expects SUPABASE_SERVICE_ROLE_KEY
if (!getenv('SUPABASE_SERVICE_ROLE_KEY')) {
    putenv('SUPABASE_SERVICE_ROLE_KEY=' . SUPABASE_SERVICE_KEY);
    $_ENV['SUPABASE_SERVICE_ROLE_KEY'] = SUPABASE_SERVICE_KEY;
    $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] = SUPABASE_SERVICE_KEY;
}

// Buckets (optional but recommended so previews/regeneration match)
if (!getenv('SUPABASE_REQUIREMENTS_BUCKET')) {
    putenv('SUPABASE_REQUIREMENTS_BUCKET=' . REQUIREMENTS_BUCKET);
    $_ENV['SUPABASE_REQUIREMENTS_BUCKET'] = REQUIREMENTS_BUCKET;
}
if (!getenv('SUPABASE_REQUIREMENTS_PUBLIC')) {
    putenv('SUPABASE_REQUIREMENTS_PUBLIC=true'); // or 'false' if your requirements bucket is private
    $_ENV['SUPABASE_REQUIREMENTS_PUBLIC'] = 'true';
}
if (!getenv('SUPABASE_SIGNATURES_BUCKET')) {
    putenv('SUPABASE_SIGNATURES_BUCKET=signatures'); // adjust to your actual signatures bucket
    $_ENV['SUPABASE_SIGNATURES_BUCKET'] = 'signatures';
}
// Also expose these names for compatibility with other scripts:
if (!getenv('SUPABASE_SERVICE_ROLE')) {
    putenv('SUPABASE_SERVICE_ROLE=' . SUPABASE_SERVICE_KEY);
    $_ENV['SUPABASE_SERVICE_ROLE'] = SUPABASE_SERVICE_KEY;
    $_SERVER['SUPABASE_SERVICE_ROLE'] = SUPABASE_SERVICE_KEY;
}
if (!getenv('SUPABASE_ANON_KEY')) {
    // If you don't have a separate anon key handy, reuse the service key on server-side only.
    putenv('SUPABASE_ANON_KEY=' . SUPABASE_SERVICE_KEY);
    $_ENV['SUPABASE_ANON_KEY'] = SUPABASE_SERVICE_KEY;
    $_SERVER['SUPABASE_ANON_KEY'] = SUPABASE_SERVICE_KEY;
}
