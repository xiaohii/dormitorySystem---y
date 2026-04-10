<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'teacher'));

ensure_system_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    if ($action === 'review') {
        $leaveId = isset($_POST['leave_id']) ? (int) $_POST['leave_id'] : 0;
        $decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
        $reviewComment = isset($_POST['review_comment']) ? trim($_POST['review_comment']) : '';

        if ($leaveId <= 0 || !in_array($decision, array('已通过', '已驳回'), true)) {
            flash_set('error', '审批参数无效，请重试。');
            redirect_to('leave_review.php');
        }

        $fetchStmt = $conn->prepare('SELECT `student_id`, `class_name`, `start_time`, `end_time`, `status` FROM `leave_records` WHERE `leave_id` = ? LIMIT 1');
        if (!$fetchStmt) {
            flash_set('error', '读取请假记录失败，请稍后重试。');
            redirect_to('leave_review.php');
        }
        $fetchStmt->bind_param('i', $leaveId);
        $fetchStmt->execute();
        $fetchStmt->store_result();
        if ($fetchStmt->num_rows === 0) {
            $fetchStmt->close();
            flash_set('error', '请假记录不存在。');
            redirect_to('leave_review.php');
        }
        $fetchStmt->bind_result($studentId, $className, $startTime, $endTime, $oldStatus);
        $fetchStmt->fetch();
        $fetchStmt->close();

        if ($oldStatus !== '待审批') {
            flash_set('error', '该请假记录已审批，不能重复操作。');
            redirect_to('leave_review.php');
        }

        $reviewer = current_user_name();
        $updateStmt = $conn->prepare('UPDATE `leave_records` SET `status` = ?, `reviewer_name` = ?, `review_comment` = ?, `review_time` = NOW(), `updated_at` = NOW() WHERE `leave_id` = ? LIMIT 1');
        if ($updateStmt) {
            $updateStmt->bind_param('sssi', $decision, $reviewer, $reviewComment, $leaveId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        if ($decision === '已通过') {
            $startDate = date('Y-m-d', strtotime($startTime));
            $endDate = date('Y-m-d', strtotime($endTime));
            sync_leave_to_attendance($conn, $studentId, $className, $startDate, $endDate, $reviewer);
        }

        write_operation_log($conn, '请假审批', '审批请假记录 ID：' . $leaveId . '，结果：' . $decision);
        flash_set('success', '审批已完成：' . $decision . '。');
        redirect_to('leave_review.php');
    }
}

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$classFilter = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 10;

$conditions = array();
if ($keyword !== '') {
    $keywordSafe = $conn->real_escape_string($keyword);
    $conditions[] = "(`student_id` LIKE '%{$keywordSafe}%' OR `student_name` LIKE '%{$keywordSafe}%')";
}
if ($statusFilter !== '') {
    $statusSafe = $conn->real_escape_string($statusFilter);
    $conditions[] = "`status` = '{$statusSafe}'";
}
if ($classFilter !== '') {
    $classSafe = $conn->real_escape_string($classFilter);
    $conditions[] = "`class_name` = '{$classSafe}'";
}
if ($dateFrom !== '') {
    $dateFromSafe = $conn->real_escape_string($dateFrom);
    $conditions[] = "DATE(`start_time`) >= '{$dateFromSafe}'";
}
if ($dateTo !== '') {
    $dateToSafe = $conn->real_escape_string($dateTo);
    $conditions[] = "DATE(`end_time`) <= '{$dateToSafe}'";
}
$whereSql = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countResult = $conn->query("SELECT COUNT(*) AS total FROM `leave_records` {$whereSql}");
$total = 0;
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

$listSql = "SELECT `leave_id`, `student_id`, `student_name`, `class_name`, `leave_type`, `start_time`, `end_time`, `reason`, `status`, `reviewer_name`, `review_comment`, `review_time`, `created_at`
FROM `leave_records`
{$whereSql}
ORDER BY `leave_id` DESC
LIMIT {$offset}, {$pageSize}";
$listResult = $conn->query($listSql);

$classOptions = array();
$classResult = $conn->query("SELECT DISTINCT `class` FROM `student` WHERE `class` <> '' ORDER BY `class` ASC");
if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classOptions[] = $row['class'];
    }
}

$summaryResult = $conn->query("SELECT 
    COUNT(*) AS total_count,
    SUM(CASE WHEN `status` = '待审批' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN `status` = '已通过' THEN 1 ELSE 0 END) AS passed_count,
    SUM(CASE WHEN `status` = '已驳回' THEN 1 ELSE 0 END) AS rejected_count
FROM `leave_records`");
$summary = array('total_count' => 0, 'pending_count' => 0, 'passed_count' => 0, 'rejected_count' => 0);
if ($summaryResult && $row = $summaryResult->fetch_assoc()) {
    $summary = array(
        'total_count' => (int) $row['total_count'],
        'pending_count' => (int) $row['pending_count'],
        'passed_count' => (int) $row['passed_count'],
        'rejected_count' => (int) $row['rejected_count']
    );
}

$successTip = flash_get('success');
$errorTip = flash_get('error');
$baseQuery = array(
    'keyword' => $keyword,
    'status' => $statusFilter,
    'class_name' => $classFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请假审批</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>请假审批管理</h2>
        <div class="note">审批状态流转为：待审批 → 已通过 / 已驳回。审批通过后会同步课堂考勤为请假状态，避免统计计入缺勤。</div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="stats-row stats-row-wide">
            <div class="stat-card">
                <div class="stat-title">申请总数</div>
                <div class="stat-value"><?php echo (int) $summary['total_count']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">待审批</div>
                <div class="stat-value"><?php echo (int) $summary['pending_count']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">已通过</div>
                <div class="stat-value"><?php echo (int) $summary['passed_count']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">已驳回</div>
                <div class="stat-value"><?php echo (int) $summary['rejected_count']; ?></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>筛选查询</h3>
        <form method="get" action="leave_review.php" class="form-grid">
            <div class="field">
                <label for="keyword">学生姓名 / 学号</label>
                <input id="keyword" name="keyword" value="<?php echo h($keyword); ?>" placeholder="支持模糊查询">
            </div>
            <div class="field">
                <label for="class_name">班级</label>
                <select id="class_name" name="class_name">
                    <option value="">全部班级</option>
                    <?php foreach ($classOptions as $className): ?>
                        <option value="<?php echo h($className); ?>" <?php echo $classFilter === $className ? 'selected' : ''; ?>><?php echo h($className); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">审批状态</label>
                <select id="status" name="status">
                    <option value="">全部状态</option>
                    <option value="待审批" <?php echo $statusFilter === '待审批' ? 'selected' : ''; ?>>待审批</option>
                    <option value="已通过" <?php echo $statusFilter === '已通过' ? 'selected' : ''; ?>>已通过</option>
                    <option value="已驳回" <?php echo $statusFilter === '已驳回' ? 'selected' : ''; ?>>已驳回</option>
                </select>
            </div>
            <div class="field">
                <label for="date_from">开始日期（起）</label>
                <input id="date_from" type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
            </div>
            <div class="field">
                <label for="date_to">结束日期（止）</label>
                <input id="date_to" type="date" name="date_to" value="<?php echo h($dateTo); ?>">
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">筛选</button>
                <a class="btn btn-muted" href="leave_review.php">重置</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>请假申请列表（共 <?php echo (int) $total; ?> 条）</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>学号</th>
                <th>姓名</th>
                <th>班级</th>
                <th>类型</th>
                <th>开始时间</th>
                <th>结束时间</th>
                <th>原因</th>
                <th>状态</th>
                <th>审批信息</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($row = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int) $row['leave_id']; ?></td>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td><?php echo h($row['student_name']); ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['leave_type']); ?></td>
                        <td><?php echo h($row['start_time']); ?></td>
                        <td><?php echo h($row['end_time']); ?></td>
                        <td><?php echo h($row['reason']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td>
                            <?php
                            if ($row['status'] === '待审批') {
                                echo '待审批';
                            } else {
                                echo h($row['reviewer_name'] . ' / ' . $row['review_time'] . ' / ' . $row['review_comment']);
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === '待审批'): ?>
                                <form method="post" action="leave_review.php" style="display:inline-block; margin-right:6px;">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="leave_id" value="<?php echo (int) $row['leave_id']; ?>">
                                    <input type="hidden" name="decision" value="已通过">
                                    <input type="hidden" name="review_comment" value="审批通过">
                                    <button class="link-button" type="submit">通过</button>
                                </form>
                                <form method="post" action="leave_review.php" style="display:inline-block;">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="leave_id" value="<?php echo (int) $row['leave_id']; ?>">
                                    <input type="hidden" name="decision" value="已驳回">
                                    <input type="hidden" name="review_comment" value="审批驳回">
                                    <button class="link-button" type="submit">驳回</button>
                                </form>
                            <?php else: ?>
                                已审批
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11">暂无请假申请记录。</td>
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
            <a href="leave_review.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="leave_review.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="leave_review.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="leave_review.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>
</div>
</body>
</html>
