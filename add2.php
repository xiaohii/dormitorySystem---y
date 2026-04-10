<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login(array('admin', 'dorm'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('add.php');
}

$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$Dno = isset($_POST['Dno']) ? trim($_POST['Dno']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$class = isset($_POST['class']) ? trim($_POST['class']) : '';

if ($user === '' || $id === '' || $Dno === '' || $gender === '' || $phone === '' || $class === '') {
    flash_set('success', '新增失败：请完整填写学生信息。');
    redirect_to('add.php');
}

$checkStmt = $conn->prepare('SELECT `id` FROM `student` WHERE `id` = ? LIMIT 1');
$exists = false;
if ($checkStmt) {
    $checkStmt->bind_param('s', $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    $exists = $checkStmt->num_rows > 0;
    $checkStmt->close();
}

if ($exists) {
    flash_set('success', '新增失败：该学号已存在。');
    redirect_to('add.php');
}

$insertStmt = $conn->prepare('INSERT INTO `student` (`user`, `id`, `Dno`, `gender`, `phone`, `class`) VALUES (?, ?, ?, ?, ?, ?)');
if (!$insertStmt) {
    flash_set('success', '新增失败：数据库操作异常。');
    redirect_to('add.php');
}

$insertStmt->bind_param('ssssss', $user, $id, $Dno, $gender, $phone, $class);
$ok = $insertStmt->execute();
$insertStmt->close();

if ($ok) {
    flash_set('success', '新生入住登记成功。');
    redirect_to('index.php');
}

flash_set('success', '新增失败，请稍后重试。');
redirect_to('add.php');
?>