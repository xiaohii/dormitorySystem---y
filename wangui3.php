<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'dorm'));

ensure_system_schema($conn);

if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    if ($deleteId > 0) {
        $delStmt = $conn->prepare('DELETE FROM `late_return_records` WHERE `record_id` = ? LIMIT 1');
        if ($delStmt) {
            $delStmt->bind_param('i', $deleteId);
            $delStmt->execute();
            $delStmt->close();
            write_operation_log($conn, '晚归记录删除', '删除晚归记录 ID：' . $deleteId);
            flash_set('success', '晚归记录已删除。');
        }
    }
    redirect_to('wangui3.php');
}

$studentIdFilter = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$notReturnFilter = isset($_GET['is_not_return']) ? trim($_GET['is_not_return']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 10;

$conditions = array();
if ($studentIdFilter !== '') {
    $sidSafe = $conn->real_escape_string($studentIdFilter);
    $conditions[] = "`student_id` = '{$sidSafe}'";
}
if ($dateFrom !== '') {
    $fromSafe = $conn->real_escape_string($dateFrom);
    $conditions[] = "`late_date` >= '{$fromSafe}'";
}
if ($dateTo !== '') {
    $toSafe = $conn->real_escape_string($dateTo);
    $conditions[] = "`late_date` <= '{$toSafe}'";
}
if ($notReturnFilter === '是') {
    $conditions[] = "(`is_not_return` = '是' OR `is_not_return` = 'Yes')";
} elseif ($notReturnFilter === '否') {
    $conditions[] = "(`is_not_return` = '否' OR `is_not_return` = 'No')";
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

$total = 0;
$countSql = "SELECT COUNT(*) AS total FROM `late_return_records` {$whereSql}";
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

$listSql = "SELECT `record_id`, `student_id`, `student_name`, `dorm_no`, `late_date`, `is_not_return`, `remark`, `created_by`, `created_at` FROM `late_return_records` {$whereSql} ORDER BY `record_id` DESC LIMIT {$offset}, {$pageSize}";
$listResult = $conn->query($listSql);

$successTip = flash_get('success');
$baseQuery = array(
    'student_id' => $studentIdFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'is_not_return' => $notReturnFilter
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>晚归记录</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>晚归记录查询</h2>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>

        <form method="get" action="wangui3.php" class="form-grid">
            <div class="field">
                <label for="student_id">学号</label>
                <input id="student_id" name="student_id" value="<?php echo h($studentIdFilter); ?>" placeholder="精确匹配">
            </div>
            <div class="field">
                <label for="date_from">晚归日期（起）</label>
                <input id="date_from" type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
            </div>
            <div class="field">
                <label for="date_to">晚归日期（止）</label>
                <input id="date_to" type="date" name="date_to" value="<?php echo h($dateTo); ?>">
            </div>
            <div class="field">
                <label for="is_not_return">是否未归</label>
                <select id="is_not_return" name="is_not_return">
                    <option value="">全部</option>
                    <option value="是" <?php echo $notReturnFilter === '是' ? 'selected' : ''; ?>>是</option>
                    <option value="否" <?php echo $notReturnFilter === '否' ? 'selected' : ''; ?>>否</option>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">筛选</button>
                <a class="btn btn-muted" href="wangui3.php">重置</a>
                <a class="btn btn-muted" href="wangui.php">去登记</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>记录列表（共 <?php echo (int) $total; ?> 条）</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>学号</th>
                <th>姓名</th>
                <th>宿舍号</th>
                <th>晚归日期</th>
                <th>是否未归</th>
                <th>备注</th>
                <th>登记人</th>
                <th>登记时间</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($row = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int) $row['record_id']; ?></td>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td><?php echo h($row['student_name']); ?></td>
                        <td><?php echo h($row['dorm_no']); ?></td>
                        <td><?php echo h($row['late_date']); ?></td>
                        <td><?php echo h($row['is_not_return']); ?></td>
                        <td><?php echo h($row['remark']); ?></td>
                        <td><?php echo h($row['created_by']); ?></td>
                        <td><?php echo h($row['created_at']); ?></td>
                        <td>
                            <a href="wangui3.php?delete_id=<?php echo (int) $row['record_id']; ?>" onclick="return confirm('确认删除该晚归记录吗？');">删除</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">暂无晚归记录。</td>
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
            <a href="wangui3.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="wangui3.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="wangui3.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="wangui3.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>
</div>
</body>
</html>
