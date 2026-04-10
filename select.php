<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_login();

$studentOnly = is_student_user();
$studentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : '';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$classFilter = isset($_GET['class']) ? trim($_GET['class']) : '';
$dormFilter = isset($_GET['dorm']) ? trim($_GET['dorm']) : '';
$genderFilter = isset($_GET['gender']) ? trim($_GET['gender']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 10;

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
if ($genderFilter !== '') {
    $genderSafe = $conn->real_escape_string($genderFilter);
    $conditions[] = "`gender` = '{$genderSafe}'";
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
$genderOptions = array();
$optionResult = $conn->query('SELECT DISTINCT `class`, `Dno`, `gender` FROM `student` ORDER BY `class` ASC, `Dno` ASC');
if ($optionResult) {
    while ($opt = $optionResult->fetch_assoc()) {
        if ($opt['class'] !== '' && !in_array($opt['class'], $classOptions, true)) {
            $classOptions[] = $opt['class'];
        }
        if ($opt['Dno'] !== '' && !in_array($opt['Dno'], $dormOptions, true)) {
            $dormOptions[] = $opt['Dno'];
        }
        if ($opt['gender'] !== '' && !in_array($opt['gender'], $genderOptions, true)) {
            $genderOptions[] = $opt['gender'];
        }
    }
}

function highlight_text($text, $keyword)
{
    $safeText = h($text);
    if ($keyword === '') {
        return $safeText;
    }

    $safeKeyword = h($keyword);
    if ($safeKeyword === '') {
        return $safeText;
    }

    return str_ireplace($safeKeyword, '<mark>' . $safeKeyword . '</mark>', $safeText);
}

$baseQuery = array(
    'keyword' => $keyword,
    'class' => $classFilter,
    'dorm' => $dormFilter,
    'gender' => $genderFilter
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生信息查询</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>学生信息查询</h2>
        <div class="note">支持按姓名/学号模糊查询，并可按班级、宿舍、性别组合筛选。</div>

        <form method="get" action="select.php" class="form-grid">
            <div class="field">
                <label for="keyword">姓名 / 学号（模糊）</label>
                <input id="keyword" name="keyword" value="<?php echo h($keyword); ?>" placeholder="例如：张 / 2025">
            </div>
            <div class="field">
                <label for="class">班级</label>
                <select id="class" name="class">
                    <option value="">全部</option>
                    <?php foreach ($classOptions as $className): ?>
                        <option value="<?php echo h($className); ?>" <?php echo $classFilter === $className ? 'selected' : ''; ?>>
                            <?php echo h($className); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="dorm">宿舍号</label>
                <select id="dorm" name="dorm">
                    <option value="">全部</option>
                    <?php foreach ($dormOptions as $dormNo): ?>
                        <option value="<?php echo h($dormNo); ?>" <?php echo $dormFilter === $dormNo ? 'selected' : ''; ?>>
                            <?php echo h($dormNo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="gender">性别</label>
                <select id="gender" name="gender">
                    <option value="">全部</option>
                    <?php foreach ($genderOptions as $genderName): ?>
                        <option value="<?php echo h($genderName); ?>" <?php echo $genderFilter === $genderName ? 'selected' : ''; ?>>
                            <?php echo h($genderName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">执行查询</button>
                <a class="btn btn-muted" href="select.php">清空条件</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>查询结果（共 <?php echo (int) $total; ?> 条）</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <th>性别</th>
                <th>宿舍号</th>
                <th>联系电话</th>
                <th>班级</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($stu = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo highlight_text($stu['id'], $keyword); ?></td>
                        <td><?php echo highlight_text($stu['user'], $keyword); ?></td>
                        <td><?php echo h($stu['gender']); ?></td>
                        <td><?php echo h($stu['Dno']); ?></td>
                        <td><?php echo h($stu['phone']); ?></td>
                        <td><?php echo h($stu['class']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">未查询到匹配的学生信息。</td>
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
            <a href="select.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="select.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="select.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="select.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>
</div>
</body>
</html>