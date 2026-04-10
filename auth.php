<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_to($url)
{
    header('Location: ' . $url);
    exit;
}

function require_login($roles = array())
{
    $loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

    if (!$loggedIn || $role === '') {
        redirect_to('login.html?error=' . urlencode('请先登录后再访问系统。'));
    }

    if (!empty($roles) && !in_array($role, $roles, true)) {
        redirect_to('index.php?error=' . urlencode('当前账号无权限访问该页面。'));
    }
}

function role_label($role)
{
    if ($role === 'admin') {
        return '管理员';
    }

    if ($role === 'teacher') {
        return '任课教师';
    }

    if ($role === 'dorm') {
        return '宿管人员';
    }

    if ($role === 'student') {
        return '学生';
    }

    return '访客';
}

function current_user_name()
{
    if (isset($_SESSION['display_name']) && $_SESSION['display_name'] !== '') {
        return $_SESSION['display_name'];
    }

    if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
        return $_SESSION['username'];
    }

    return '访客';
}

function current_role()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}

function is_admin_user()
{
    return current_role() === 'admin';
}

function is_teacher_user()
{
    return current_role() === 'teacher';
}

function is_student_user()
{
    return current_role() === 'student';
}

function can_manage_records()
{
    $role = current_role();
    return $role === 'admin' || $role === 'dorm';
}

function can_manage_class_attendance()
{
    $role = current_role();
    return $role === 'admin' || $role === 'teacher';
}

function flash_set($key, $message)
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = array();
    }

    $_SESSION['flash'][$key] = $message;
}

function flash_get($key)
{
    if (!isset($_SESSION['flash'][$key])) {
        return '';
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
