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
