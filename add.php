<?php
require_once __DIR__ . '/auth.php';
require_login(array('admin', 'dorm'));
$tip = flash_get('success');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新生入住登记</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>新生入住登记</h2>
        <?php if ($tip !== ''): ?>
            <div class="note"><?php echo h($tip); ?></div>
        <?php endif; ?>

        <form action="add2.php" method="post" class="form-grid">
            <div class="field">
                <label for="user">姓名</label>
                <input id="user" name="user" required>
            </div>
            <div class="field">
                <label for="id">学号</label>
                <input id="id" name="id" required>
            </div>
            <div class="field">
                <label for="gender">性别</label>
                <select id="gender" name="gender" required>
                    <option value="">请选择</option>
                    <option value="男">男</option>
                    <option value="女">女</option>
                </select>
            </div>
            <div class="field">
                <label for="phone">联系电话</label>
                <input id="phone" name="phone" required>
            </div>
            <div class="field">
                <label for="class">班级</label>
                <input id="class" name="class" required>
            </div>
            <div class="field">
                <label for="Dno">宿舍号</label>
                <input id="Dno" name="Dno" required>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">提交</button>
                <button class="btn btn-muted" type="reset">重置</button>
            </div>
        </form>
    </section>
</div>
</body>
</html>