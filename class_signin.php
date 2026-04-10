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

$studentClass = $student['class'];
$studentName = $student['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    if ($action === 'signin') {
        $sessionId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        if ($sessionId <= 0) {
            flash_set('error', '无效的课堂签到场次。');
            redirect_to('class_signin.php');
        }

        $sessionStmt = $conn->prepare('SELECT `session_id`, `class_name`, `session_name`, `sign_date`, `sign_start`, `sign_end`, `status` FROM `class_signin_sessions` WHERE `session_id` = ? LIMIT 1');
        if (!$sessionStmt) {
            flash_set('error', '读取课堂签到场次失败，请稍后重试。');
            redirect_to('class_signin.php');
        }
        $sessionStmt->bind_param('i', $sessionId);
        $sessionStmt->execute();
        $sessionStmt->store_result();
        $session = null;
        if ($sessionStmt->num_rows === 1) {
            $sessionStmt->bind_result($dbSessionId, $dbClassName, $dbSessionName, $dbSignDate, $dbSignStart, $dbSignEnd, $dbStatus);
            $sessionStmt->fetch();
            $session = array(
                'session_id' => $dbSessionId,
                'class_name' => $dbClassName,
                'session_name' => $dbSessionName,
                'sign_date' => $dbSignDate,
                'sign_start' => $dbSignStart,
                'sign_end' => $dbSignEnd,
                'status' => $dbStatus
            );
        }
        $sessionStmt->close();

        if (!$session) {
            flash_set('error', '课堂签到场次不存在。');
            redirect_to('class_signin.php');
        }
        if ($session['class_name'] !== $studentClass) {
            flash_set('error', '该签到场次不属于你的班级。');
            redirect_to('class_signin.php');
        }
        if ($session['status'] !== '进行中') {
            flash_set('error', '该签到场次已结束，无法签到。');
            redirect_to('class_signin.php');
        }

        $nowTs = time();
        $startTs = strtotime($session['sign_start']);
        $endTs = strtotime($session['sign_end']);
        if ($nowTs < $startTs) {
            flash_set('error', '签到尚未开始，请在规定时间内签到。');
            redirect_to('class_signin.php');
        }
        if ($nowTs > $endTs) {
            flash_set('error', '签到已截止。');
            redirect_to('class_signin.php');
        }

        if (has_approved_leave($conn, $studentId, $studentClass, $session['sign_date'])) {
            flash_set('success', '该节次你已有通过的请假记录，系统已按请假处理。');
            redirect_to('class_signin.php');
        }

        $lateBoundaryTs = $startTs + 10 * 60;
        $status = $nowTs <= $lateBoundaryTs ? '出勤' : '迟到';
        $remark = '学生课堂签到';
        $now = date('Y-m-d H:i:s');

        $checkStmt = $conn->prepare("SELECT `record_id`, `status` FROM `attendance_records` WHERE `attendance_type` = 'class' AND `student_id` = ? AND `class_name` = ? AND `attendance_date` = ? AND `session_name` = ? LIMIT 1");
        $updateStmt = $conn->prepare('UPDATE `attendance_records` SET `status` = ?, `remark` = ?, `updated_by` = ?, `updated_at` = ? WHERE `record_id` = ? LIMIT 1');
        $insertStmt = $conn->prepare("INSERT INTO `attendance_records` (`student_id`, `student_name`, `class_name`, `dorm_no`, `attendance_type`, `attendance_date`, `status`, `session_name`, `remark`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 'class', ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$checkStmt || !$updateStmt || !$insertStmt) {
            flash_set('error', '签到失败，请稍后重试。');
            redirect_to('class_signin.php');
        }

        $checkStmt->bind_param('ssss', $studentId, $studentClass, $session['sign_date'], $session['session_name']);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $checkStmt->bind_result($recordId, $oldStatus);
            $checkStmt->fetch();
            if ($oldStatus !== '请假') {
                $updateStmt->bind_param('ssssi', $status, $remark, $studentName, $now, $recordId);
                $updateStmt->execute();
            }
        } else {
            $dormNo = $student['Dno'];
            $insertStmt->bind_param('ssssssssssss', $studentId, $studentName, $studentClass, $dormNo, $session['sign_date'], $status, $session['session_name'], $remark, $studentName, $studentName, $now, $now);
            $insertStmt->execute();
        }

        $checkStmt->close();
        $updateStmt->close();
        $insertStmt->close();

        write_operation_log($conn, '课堂签到', '学生 ' . $studentId . ' 完成课堂签到，节次：' . $session['session_name'] . '，状态：' . $status);
        flash_set('success', '课堂签到成功，当前状态：' . $status . '。');
        redirect_to('class_signin.php');
    }
}

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$sessionSql = "SELECT `session_id`, `class_name`, `session_name`, `sign_date`, `sign_start`, `sign_end`, `status`, `created_by`
FROM `class_signin_sessions`
WHERE `class_name` = ? AND `status` = '进行中' AND `sign_date` = ? AND `sign_start` <= ? AND `sign_end` >= ?
ORDER BY `sign_start` ASC";
$sessionStmt = $conn->prepare($sessionSql);
$activeSessions = array();
if ($sessionStmt) {
    $sessionStmt->bind_param('ssss', $studentClass, $today, $now, $now);
    $sessionStmt->execute();
    $sessionStmt->bind_result($dbSessionId2, $dbClassName2, $dbSessionName2, $dbSignDate2, $dbSignStart2, $dbSignEnd2, $dbStatus2, $dbCreatedBy2);
    while ($sessionStmt->fetch()) {
        $activeSessions[] = array(
            'session_id' => $dbSessionId2,
            'class_name' => $dbClassName2,
            'session_name' => $dbSessionName2,
            'sign_date' => $dbSignDate2,
            'sign_start' => $dbSignStart2,
            'sign_end' => $dbSignEnd2,
            'status' => $dbStatus2,
            'created_by' => $dbCreatedBy2
        );
    }
    $sessionStmt->close();
}

$recentStmt = $conn->prepare("SELECT `attendance_date`, `session_name`, `status`, `remark`, `updated_at` FROM `attendance_records` WHERE `attendance_type` = 'class' AND `student_id` = ? ORDER BY `attendance_date` DESC, `record_id` DESC LIMIT 20");
$recentRows = array();
if ($recentStmt) {
    $recentStmt->bind_param('s', $studentId);
    $recentStmt->execute();
    $recentStmt->bind_result($dbAttendanceDate, $dbSessionName3, $dbStatus3, $dbRemark3, $dbUpdatedAt3);
    while ($recentStmt->fetch()) {
        $recentRows[] = array(
            'attendance_date' => $dbAttendanceDate,
            'session_name' => $dbSessionName3,
            'status' => $dbStatus3,
            'remark' => $dbRemark3,
            'updated_at' => $dbUpdatedAt3
        );
    }
    $recentStmt->close();
}

$successTip = flash_get('success');
$errorTip = flash_get('error');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生课堂签到</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>学生课堂签到</h2>
        <div class="note">当前账号：<?php echo h($studentName); ?>（<?php echo h($studentId); ?>），班级：<?php echo h($studentClass); ?>。签到开始后 10 分钟内记为出勤，之后记为迟到。</div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>

        <h3>可签到场次</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>班级</th>
                <th>节次</th>
                <th>日期</th>
                <th>签到时间段</th>
                <th>发起人</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($activeSessions)): ?>
                <?php foreach ($activeSessions as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['session_id']; ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['session_name']); ?></td>
                        <td><?php echo h($row['sign_date']); ?></td>
                        <td><?php echo h(date('H:i', strtotime($row['sign_start'])) . ' - ' . date('H:i', strtotime($row['sign_end']))); ?></td>
                        <td><?php echo h($row['created_by']); ?></td>
                        <td>
                            <form method="post" action="class_signin.php" style="display:inline-block;">
                                <input type="hidden" name="action" value="signin">
                                <input type="hidden" name="session_id" value="<?php echo (int) $row['session_id']; ?>">
                                <button class="btn btn-primary" type="submit">立即签到</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">当前没有进行中的课堂签到场次。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3>最近课堂考勤记录</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>日期</th>
                <th>节次</th>
                <th>状态</th>
                <th>备注</th>
                <th>更新时间</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($recentRows)): ?>
                <?php foreach ($recentRows as $row): ?>
                    <tr>
                        <td><?php echo h($row['attendance_date']); ?></td>
                        <td><?php echo h($row['session_name']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td><?php echo h($row['remark']); ?></td>
                        <td><?php echo h($row['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">暂无课堂考勤记录。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
