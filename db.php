<?php
$host     = 'sql12.freesqldatabase.com';
$db_name  = 'sql12826794';
$username = 'sql12826794';
$password = 'zwrjS4a8DvPort';
$port     = '3306';

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
