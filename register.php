<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('register.html');
}

ensure_system_schema($conn);

$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$pwd1 = isset($_POST['pwd1']) ? trim($_POST['pwd1']) : '';
$pwd2 = isset($_POST['pwd2']) ? trim($_POST['pwd2']) : '';

if ($user === '' || $pwd1 === '' || $pwd2 === '') {
    redirect_to('register.html?error=' . urlencode('请完整填写注册信息。'));
}

if ($pwd1 !== $pwd2) {
    redirect_to('register.html?error=' . urlencode('两次输入的密码不一致。'));
}

$checkStmt = $conn->prepare('SELECT `username` FROM `system_users` WHERE `username` = ? LIMIT 1');
if (!$checkStmt) {
    redirect_to('register.html?error=' . urlencode('系统繁忙，请稍后重试。'));
}
$checkStmt->bind_param('s', $user);
$checkStmt->execute();
$checkStmt->store_result();
$exists = $checkStmt->num_rows > 0;
$checkStmt->close();

if ($exists) {
    redirect_to('register.html?error=' . urlencode('该管理员账号已存在。'));
}

$legacyCheckStmt = $conn->prepare('SELECT `name` FROM `admin` WHERE `name` = ? LIMIT 1');
if ($legacyCheckStmt) {
    $legacyCheckStmt->bind_param('s', $user);
    $legacyCheckStmt->execute();
    $legacyCheckStmt->store_result();
    $legacyExists = $legacyCheckStmt->num_rows > 0;
    $legacyCheckStmt->close();

    if ($legacyExists) {
        redirect_to('register.html?error=' . urlencode('该管理员账号已存在。'));
    }
}

$insertUserStmt = $conn->prepare('INSERT INTO `system_users` (`username`, `password`, `role`, `enabled`, `created_at`, `updated_at`) VALUES (?, ?, \'admin\', 1, NOW(), NOW())');
$insertLegacyStmt = $conn->prepare('INSERT INTO `admin` (`name`, `pwd`) VALUES (?, ?)');
if (!$insertUserStmt || !$insertLegacyStmt) {
    redirect_to('register.html?error=' . urlencode('创建账号失败，请稍后重试。'));
}

$insertUserStmt->bind_param('ss', $user, $pwd1);
$ok = $insertUserStmt->execute();
$insertUserStmt->close();

if ($ok) {
    $insertLegacyStmt->bind_param('ss', $user, $pwd1);
    $insertLegacyStmt->execute();
}
$insertLegacyStmt->close();

if (!$ok) {
    redirect_to('register.html?error=' . urlencode('注册失败，请稍后重试。'));
}

write_operation_log($conn, '注册', '创建管理员账号：' . $user);

redirect_to('login.html?error=' . urlencode('注册成功，请登录。'));
?>
