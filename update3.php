<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login(array('admin', 'dorm'));

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') {
    flash_set('success', '缺少学号参数。');
    redirect_to('index.php');
}

$stmt = $conn->prepare('SELECT `user`, `id`, `Dno`, `gender`, `phone`, `class` FROM `student` WHERE `id` = ? LIMIT 1');
if (!$stmt) {
    flash_set('success', '查询失败，请稍后重试。');
    redirect_to('index.php');
}

$stmt->bind_param('s', $id);
$stmt->execute();
$stmt->store_result();
$student = null;
if ($stmt->num_rows === 1) {
    $stmt->bind_result($dbUser, $dbId, $dbDorm, $dbGender, $dbPhone, $dbClass);
    $stmt->fetch();
    $student = array(
        'user' => $dbUser,
        'id' => $dbId,
        'Dno' => $dbDorm,
        'gender' => $dbGender,
        'phone' => $dbPhone,
        'class' => $dbClass
    );
}
$stmt->close();

if (!$student) {
    flash_set('success', '未找到该学生信息。');
    redirect_to('index.php');
}

$tip = flash_get('success');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑学生信息</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>编辑学生信息</h2>
        <?php if ($tip !== ''): ?>
            <div class="note"><?php echo h($tip); ?></div>
        <?php endif; ?>

        <form action="update2.php" method="post" class="form-grid">
            <div class="field">
                <label for="user">姓名</label>
                <input id="user" name="user" value="<?php echo h($student['user']); ?>" required>
            </div>
            <div class="field">
                <label for="id">学号</label>
                <input id="id" name="id" value="<?php echo h($student['id']); ?>" readonly>
            </div>
            <div class="field">
                <label for="Dno">宿舍号</label>
                <input id="Dno" name="Dno" value="<?php echo h($student['Dno']); ?>" required>
            </div>
            <div class="field">
                <label for="gender">性别</label>
                <select id="gender" name="gender" required>
                    <option value="男" <?php echo $student['gender'] === '男' ? 'selected' : ''; ?>>男</option>
                    <option value="女" <?php echo $student['gender'] === '女' ? 'selected' : ''; ?>>女</option>
                </select>
            </div>
            <div class="field">
                <label for="phone">联系电话</label>
                <input id="phone" name="phone" value="<?php echo h($student['phone']); ?>" required>
            </div>
            <div class="field">
                <label for="class">班级</label>
                <input id="class" name="class" value="<?php echo h($student['class']); ?>" required>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">保存修改</button>
                <a class="btn btn-muted" href="index.php">返回列表</a>
            </div>
        </form>
    </section>
</div>
</body>
</html>