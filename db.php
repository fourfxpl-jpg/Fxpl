<?php
$host     = getenv('MYSQL_ADDON_HOST')     ?: 'localhost';
$db_name  = getenv('MYSQL_ADDON_DB')       ?: 'mydb';
$username = getenv('MYSQL_ADDON_USER')     ?: 'root';
$password = getenv('MYSQL_ADDON_PASSWORD') ?: '';
$port     = getenv('MYSQL_ADDON_PORT')     ?: '3306';

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4",
        $username, $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    die("Connection Error: " . $e->getMessage());
}
?>
