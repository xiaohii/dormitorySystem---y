<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login(array('admin', 'dorm'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$Dno = isset($_POST['Dno']) ? trim($_POST['Dno']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$class = isset($_POST['class']) ? trim($_POST['class']) : '';

if ($user === '' || $id === '' || $Dno === '' || $gender === '' || $phone === '' || $class === '') {
    flash_set('success', '修改失败：请完整填写学生信息。');
    redirect_to('update3.php?id=' . urlencode($id));
}

$updateStmt = $conn->prepare('UPDATE `student` SET `user` = ?, `Dno` = ?, `gender` = ?, `phone` = ?, `class` = ? WHERE `id` = ? LIMIT 1');
if (!$updateStmt) {
    flash_set('success', '修改失败：数据库操作异常。');
    redirect_to('index.php');
}

$updateStmt->bind_param('ssssss', $user, $Dno, $gender, $phone, $class, $id);
$ok = $updateStmt->execute();
$updateStmt->close();

if ($ok) {
    flash_set('success', '学生信息已更新。');
    redirect_to('index.php');
}

flash_set('success', '修改失败，请稍后重试。');
redirect_to('update3.php?id=' . urlencode($id));
?>