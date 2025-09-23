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
