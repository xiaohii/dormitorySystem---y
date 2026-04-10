<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login.html');
}

ensure_system_schema($conn);

$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$pwd = isset($_POST['pwd']) ? trim($_POST['pwd']) : '';
$role = isset($_POST['role']) ? trim($_POST['role']) : '';

if ($user === '' || $pwd === '' || $role === '') {
    redirect_to('login.html?error=' . urlencode('用户名、密码和角色不能为空。'));
}

$loginOk = false;
$displayName = $user;
$studentId = '';

if ($role === 'admin' || $role === 'teacher' || $role === 'dorm') {
    $staffAuth = authenticate_staff_user($conn, $user, $pwd, $role);
    if ($staffAuth['ok']) {
        $displayName = $staffAuth['display_name'];
        $loginOk = true;
    }
} elseif ($role === 'student') {
    $stmt = $conn->prepare('SELECT `id`, `user`, `phone` FROM `student` WHERE `id` = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $user);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($dbId, $dbUser, $dbPhone);
            $stmt->fetch();
            $isPasswordCorrect = ($pwd === $dbId) || ($pwd === $dbPhone);
            if ($isPasswordCorrect) {
                $displayName = $dbUser;
                $studentId = $dbId;
                $loginOk = true;
            }
        }
        $stmt->close();
    }
} else {
    redirect_to('login.html?error=' . urlencode('不支持的登录角色。'));
}

if (!$loginOk) {
    if ($role === 'student') {
        $error = '学生登录失败，请使用学号 +（学号或手机号）作为密码。';
    } elseif (isset($staffAuth['message']) && $staffAuth['message'] !== '') {
        $error = $staffAuth['message'];
    } else {
        $error = '账号或密码错误，请重试。';
    }
    redirect_to('login.html?error=' . urlencode($error));
}

session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['username'] = $user;
$_SESSION['display_name'] = $displayName;
$_SESSION['role'] = $role;
$_SESSION['user'] = $displayName;
$_SESSION['student_id'] = $studentId;
$_SESSION['login_at'] = date('Y-m-d H:i:s');

write_operation_log($conn, '登录', '账号 ' . $displayName . ' 以 ' . role_label($role) . ' 身份登录系统');

redirect_to('index.php');
?>
