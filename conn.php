<?php
$servername = 'localhost';
$username = 'root';
$password = '123456';
$database = 'work1';

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die('数据库连接失败：' . $conn->connect_error);
}

$conn->set_charset('utf8');
?>