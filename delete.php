<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login(array('admin', 'dorm'));

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') {
    flash_set('success', '删除失败：缺少学号参数。');
    redirect_to('index.php');
}

$deleteStmt = $conn->prepare('DELETE FROM `student` WHERE `id` = ? LIMIT 1');
if (!$deleteStmt) {
    flash_set('success', '删除失败：数据库操作异常。');
    redirect_to('index.php');
}

$deleteStmt->bind_param('s', $id);
$deleteStmt->execute();
$affected = $deleteStmt->affected_rows;
$deleteStmt->close();

if ($affected > 0) {
    flash_set('success', '学生记录已删除。');
} else {
    flash_set('success', '未找到对应学号，未删除任何记录。');
}

redirect_to('index.php');
?>