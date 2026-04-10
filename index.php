<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login();

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$studentOnly = is_student_user();
$studentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : '';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$classFilter = isset($_GET['class']) ? trim($_GET['class']) : '';
$dormFilter = isset($_GET['dorm']) ? trim($_GET['dorm']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 8;

$conditions = array();

if ($studentOnly && $studentId !== '') {
    $studentIdSafe = $conn->real_escape_string($studentId);
    $conditions[] = "`id` = '{$studentIdSafe}'";
}

if ($keyword !== '') {
    $keywordSafe = $conn->real_escape_string($keyword);
    $conditions[] = "(`user` LIKE '%{$keywordSafe}%' OR `id` LIKE '%{$keywordSafe}%')";
}

if ($classFilter !== '') {
    $classSafe = $conn->real_escape_string($classFilter);
    $conditions[] = "`class` = '{$classSafe}'";
}

if ($dormFilter !== '') {
    $dormSafe = $conn->real_escape_string($dormFilter);
    $conditions[] = "`Dno` = '{$dormSafe}'";
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

$total = 0;
$countSql = "SELECT COUNT(*) AS total FROM `student` {$whereSql}";
$countResult = $conn->query($countSql);
if ($countResult && $row = $countResult->fetch_assoc()) {
    $total = (int) $row['total'];
}

$totalPages = (int) ceil($total / $pageSize);
if ($totalPages < 1) {
    $totalPages = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $pageSize;

$listSql = "SELECT `user`, `id`, `gender`, `Dno`, `phone`, `class` FROM `student` {$whereSql} ORDER BY `id` LIMIT {$offset}, {$pageSize}";
$listResult = $conn->query($listSql);

$classOptions = array();
$dormOptions = array();
$optionResult = $conn->query('SELECT DISTINCT `class`, `Dno` FROM `student` ORDER BY `class` ASC, `Dno` ASC');
if ($optionResult) {
    while ($opt = $optionResult->fetch_assoc()) {
        if ($opt['class'] !== '' && !in_array($opt['class'], $classOptions, true)) {
            $classOptions[] = $opt['class'];
        }
        if ($opt['Dno'] !== '' && !in_array($opt['Dno'], $dormOptions, true)) {
            $dormOptions[] = $opt['Dno'];
        }
    }
}

$errorTip = isset($_GET['error']) ? trim($_GET['error']) : '';
$successTip = flash_get('success');

$baseQuery = array(
    'keyword' => $keyword,
    'class' => $classFilter,
    'dorm' => $dormFilter
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生信息展示</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>学生信息展示</h2>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-title">当前查询结果</div>
                <div class="stat-value"><?php echo (int) $total; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">当前页码</div>
                <div class="stat-value"><?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">当前身份</div>
                <div class="stat-value" style="font-size:20px;"><?php echo h(role_label($role)); ?></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <form method="get" action="index.php" class="form-grid">
            <div class="field">
                <label for="keyword">姓名 / 学号</label>
                <input id="keyword" name="keyword" value="<?php echo h($keyword); ?>" placeholder="支持模糊查询">
            </div>
            <div class="field">
                <label for="class">班级筛选</label>
                <select id="class" name="class">
                    <option value="">全部班级</option>
                    <?php foreach ($classOptions as $className): ?>
                        <option value="<?php echo h($className); ?>" <?php echo $classFilter === $className ? 'selected' : ''; ?>>
                            <?php echo h($className); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="dorm">宿舍筛选</label>
                <select id="dorm" name="dorm">
                    <option value="">全部宿舍</option>
                    <?php foreach ($dormOptions as $dormNo): ?>
                        <option value="<?php echo h($dormNo); ?>" <?php echo $dormFilter === $dormNo ? 'selected' : ''; ?>>
                            <?php echo h($dormNo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="btn-row" style="align-items:flex-end;">
                <button class="btn btn-primary" type="submit">查询</button>
                <a class="btn btn-muted" href="index.php">重置</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <table class="data-table">
            <thead>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <th>性别</th>
                <th>宿舍号</th>
                <th>联系电话</th>
                <th>班级</th>
                <?php if (can_manage_records()): ?>
                    <th>操作</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($stu = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($stu['id']); ?></td>
                        <td><?php echo h($stu['user']); ?></td>
                        <td><?php echo h($stu['gender']); ?></td>
                        <td><?php echo h($stu['Dno']); ?></td>
                        <td><?php echo h($stu['phone']); ?></td>
                        <td><?php echo h($stu['class']); ?></td>
                        <?php if (can_manage_records()): ?>
                            <td>
                                <a href="update3.php?id=<?php echo urlencode($stu['id']); ?>">编辑</a>
                                |
                                <a href="delete.php?id=<?php echo urlencode($stu['id']); ?>" onclick="return confirm('确认删除该学生记录吗？');">删除</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo can_manage_records() ? '7' : '6'; ?>">暂无符合条件的学生信息。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
            $firstQuery = $baseQuery;
            $firstQuery['page'] = 1;
            $prevQuery = $baseQuery;
            $prevQuery['page'] = max(1, $page - 1);
            $nextQuery = $baseQuery;
            $nextQuery['page'] = min($totalPages, $page + 1);
            $lastQuery = $baseQuery;
            $lastQuery['page'] = $totalPages;
            ?>
            <a href="index.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="index.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="index.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="index.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>
</div>
</body>
</html>