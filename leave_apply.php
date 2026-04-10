<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('student'));

ensure_system_schema($conn);

$studentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : '';
$student = $studentId !== '' ? fetch_student_profile($conn, $studentId) : null;
if (!$student) {
    redirect_to('login.html?error=' . urlencode('请先使用学生账号登录。'));
}

$studentName = $student['user'];
$className = $student['class'];
if ($className === '') {
    flash_set('error', '当前账号未绑定班级，暂时无法提交请假，请联系管理员。');
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveType = isset($_POST['leave_type']) ? trim($_POST['leave_type']) : '';
    $startTimeRaw = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $endTimeRaw = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    $startTime = str_replace('T', ' ', $startTimeRaw);
    $endTime = str_replace('T', ' ', $endTimeRaw);
    if (strlen($startTime) === 16) {
        $startTime .= ':00';
    }
    if (strlen($endTime) === 16) {
        $endTime .= ':00';
    }

    if (!in_array($leaveType, array('事假', '病假', '其他'), true) || $startTime === '' || $endTime === '' || $reason === '') {
        flash_set('error', '请完整填写请假类型、起止时间和请假原因。');
        redirect_to('leave_apply.php');
    }
    if (strtotime($endTime) < strtotime($startTime)) {
        flash_set('error', '请假结束时间不能早于开始时间。');
        redirect_to('leave_apply.php');
    }

    $insertStmt = $conn->prepare("INSERT INTO `leave_records` (`student_id`, `student_name`, `class_name`, `leave_type`, `start_time`, `end_time`, `reason`, `status`, `reviewer_name`, `review_comment`, `review_time`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, '待审批', '', '', NULL, NOW(), NOW())");
    if (!$insertStmt) {
        flash_set('error', '提交请假申请失败，请稍后重试。');
        redirect_to('leave_apply.php');
    }
    $insertStmt->bind_param('sssssss', $studentId, $studentName, $className, $leaveType, $startTime, $endTime, $reason);
    $ok = $insertStmt->execute();
    $insertStmt->close();

    if (!$ok) {
        flash_set('error', '提交请假申请失败，请稍后重试。');
        redirect_to('leave_apply.php');
    }

    write_operation_log($conn, '请假申请', '学生 ' . $studentId . ' 提交请假申请，时间：' . $startTime . ' 至 ' . $endTime);
    flash_set('success', '请假申请已提交，等待审批。');
    redirect_to('leave_apply.php');
}

$listStmt = $conn->prepare("SELECT `leave_id`, `class_name`, `leave_type`, `start_time`, `end_time`, `reason`, `status`, `reviewer_name`, `review_comment`, `review_time`, `created_at` FROM `leave_records` WHERE `student_id` = ? ORDER BY `leave_id` DESC");
$records = array();
if ($listStmt) {
    $listStmt->bind_param('s', $studentId);
    $listStmt->execute();
    $listStmt->bind_result($dbLeaveId, $dbClassName, $dbLeaveType, $dbStartTime, $dbEndTime, $dbReason, $dbStatus, $dbReviewerName, $dbReviewComment, $dbReviewTime, $dbCreatedAt);
    while ($listStmt->fetch()) {
        $records[] = array(
            'leave_id' => $dbLeaveId,
            'class_name' => $dbClassName,
            'leave_type' => $dbLeaveType,
            'start_time' => $dbStartTime,
            'end_time' => $dbEndTime,
            'reason' => $dbReason,
            'status' => $dbStatus,
            'reviewer_name' => $dbReviewerName,
            'review_comment' => $dbReviewComment,
            'review_time' => $dbReviewTime,
            'created_at' => $dbCreatedAt
        );
    }
    $listStmt->close();
}

$successTip = flash_get('success');
$errorTip = flash_get('error');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请假申请</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>学生请假申请</h2>
        <div class="note">请假状态流转：待审批 → 已通过 / 已驳回。已通过的请假将自动参与课堂考勤处理，不计入缺勤统计。</div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>

        <form method="post" action="leave_apply.php" class="form-grid">
            <div class="field">
                <label>学号</label>
                <input value="<?php echo h($studentId); ?>" readonly>
            </div>
            <div class="field">
                <label>姓名</label>
                <input value="<?php echo h($studentName); ?>" readonly>
            </div>
            <div class="field">
                <label>班级</label>
                <input value="<?php echo h($className); ?>" readonly>
            </div>
            <div class="field">
                <label for="leave_type">请假类型</label>
                <select id="leave_type" name="leave_type" required>
                    <option value="事假">事假</option>
                    <option value="病假">病假</option>
                    <option value="其他">其他</option>
                </select>
            </div>
            <div class="field">
                <label for="start_time">开始时间</label>
                <input id="start_time" type="datetime-local" name="start_time" required>
            </div>
            <div class="field">
                <label for="end_time">结束时间</label>
                <input id="end_time" type="datetime-local" name="end_time" required>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="reason">请假原因</label>
                <textarea id="reason" name="reason" required placeholder="请简要说明请假原因"></textarea>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">提交申请</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>我的请假记录</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>班级</th>
                <th>类型</th>
                <th>开始时间</th>
                <th>结束时间</th>
                <th>原因</th>
                <th>状态</th>
                <th>审批人</th>
                <th>审批意见</th>
                <th>审批时间</th>
                <th>提交时间</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($records)): ?>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['leave_id']; ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['leave_type']); ?></td>
                        <td><?php echo h($row['start_time']); ?></td>
                        <td><?php echo h($row['end_time']); ?></td>
                        <td><?php echo h($row['reason']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td><?php echo h($row['reviewer_name']); ?></td>
                        <td><?php echo h($row['review_comment']); ?></td>
                        <td><?php echo h($row['review_time']); ?></td>
                        <td><?php echo h($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11">暂无请假记录。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
