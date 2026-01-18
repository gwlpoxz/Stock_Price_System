<?php
$host = 'localhost';
$db   = 'stock_system';
$user = 'root';      // XAMPP 預設帳號
$pass = '';          // XAMPP 預設密碼為空

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    session_start(); // 啟用 Session 功能
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
?>