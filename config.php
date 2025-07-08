<?php
// config.php
date_default_timezone_set('Asia/Manila');    
$host = 'localhost';
$db   = 'DEPEDBAC_DB';         // Your database name
$user = 'root';                 // Your XAMPP MySQL username (default is 'root')
$pass = '';                     // Your XAMPP MySQL password (often empty)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Set MySQL timezone to Philippines Standard Time
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}
?>
