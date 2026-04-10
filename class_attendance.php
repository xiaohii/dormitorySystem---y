<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'teacher'));

ensure_system_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'create_session') {
        $className = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
        $sessionName = isset($_POST['session_name']) ? trim($_POST['session_name']) : system_setting($conn, 'class_attendance_default_session', '第1-2节');
        $signDate = isset($_POST['sign_date']) ? trim($_POST['sign_date']) : date('Y-m-d');
        $signStartTime = isset($_POST['sign_start_time']) ? trim($_POST['sign_start_time']) : '08:00';
        $signEndTime = isset($_POST['sign_end_time']) ? trim($_POST['sign_end_time']) : '08:20';

        if ($className === '' || $sessionName === '' || $signDate === '' || $signStartTime === '' || $signEndTime === '') {
            flash_set('error', '请完整填写班级、节次、日期和签到时间段。');
            redirect_to('class_attendance.php');
        }

        $signStart = $signDate . ' ' . $signStartTime . ':00';
        $signEnd = $signDate . ' ' . $signEndTime . ':00';
        if (strtotime($signEnd) <= strtotime($signStart)) {
            flash_set('error', '签到截止时间必须晚于开始时间。');
            redirect_to('class_attendance.php');
        }

        $now = date('Y-m-d H:i:s');
        $creatorName = current_user_name();
        $creatorRole = current_role();

        $insertSessionStmt = $conn->prepare("INSERT INTO `class_signin_sessions` (`class_name`, `session_name`, `sign_date`, `sign_start`, `sign_end`, `status`, `created_by`, `created_role`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, '进行中', ?, ?, ?, ?)");
        if (!$insertSessionStmt) {
            flash_set('error', '创建签到场次失败，请稍后重试。');
            redirect_to('class_attendance.php');
        }
        $insertSessionStmt->bind_param('sssssssss', $className, $sessionName, $signDate, $signStart, $signEnd, $creatorName, $creatorRole, $now, $now);
        $sessionOk = $insertSessionStmt->execute();
        $sessionId = (int) $insertSessionStmt->insert_id;
        $insertSessionStmt->close();

        if (!$sessionOk) {
            flash_set('error', '创建签到场次失败，请稍后重试。');
            redirect_to('class_attendance.php');
        }

        $studentStmt = $conn->prepare('SELECT `id`, `user`, `Dno` FROM `student` WHERE `class` = ? ORDER BY `id` ASC');
        if (!$studentStmt) {
            flash_set('error', '读取班级学生失败，请稍后重试。');
            redirect_to('class_attendance.php');
        }
        $studentStmt->bind_param('s', $className);
        $studentStmt->execute();
        $studentStmt->store_result();
        $studentStmt->bind_result($dbStudentId, $dbStudentName, $dbDormNo);

        $checkStmt = $conn->prepare("SELECT `record_id` FROM `attendance_records` WHERE `attendance_type` = 'class' AND `student_id` = ? AND `class_name` = ? AND `attendance_date` = ? AND `session_name` = ? LIMIT 1");
        $updateStmt = $conn->prepare("UPDATE `attendance_records` SET `status` = ?, `remark` = ?, `updated_by` = ?, `updated_at` = ? WHERE `record_id` = ? LIMIT 1");
        $insertStmt = $conn->prepare("INSERT INTO `attendance_records` (`student_id`, `student_name`, `class_name`, `dorm_no`, `attendance_type`, `attendance_date`, `status`, `session_name`, `remark`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 'class', ?, ?, ?, ?, ?, ?, ?, ?)");

        $insertedCount = 0;
        $updatedCount = 0;
        $leaveCount = 0;
        if ($checkStmt && $updateStmt && $insertStmt) {
            while ($studentStmt->fetch()) {
                $studentId = $dbStudentId;
                $studentName = $dbStudentName;
                $dormNo = $dbDormNo;
                $status = '缺勤';
                $remark = '课堂签到已发起，等待学生签到。';

                if (has_approved_leave($conn, $studentId, $className, $signDate)) {
                    $status = '请假';
                    $remark = '已通过请假，自动标记为请假。';
                    $leaveCount++;
                }

                $checkStmt->bind_param('ssss', $studentId, $className, $signDate, $sessionName);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $checkStmt->bind_result($recordId);
                    $checkStmt->fetch();
                    $updateStmt->bind_param('ssssi', $status, $remark, $creatorName, $now, $recordId);
                    if ($updateStmt->execute()) {
                        $updatedCount++;
                    }
                } else {
                    $insertStmt->bind_param('ssssssssssss', $studentId, $studentName, $className, $dormNo, $signDate, $status, $sessionName, $remark, $creatorName, $creatorName, $now, $now);
                    if ($insertStmt->execute()) {
                        $insertedCount++;
                    }
                }
            }
        }

        if ($checkStmt) {
            $checkStmt->close();
        }
        if ($updateStmt) {
            $updateStmt->close();
        }
        if ($insertStmt) {
            $insertStmt->close();
        }
        $studentStmt->close();

        write_operation_log($conn, '课堂签到发起', '班级：' . $className . '，节次：' . $sessionName . '，日期：' . $signDate . '，新增 ' . $insertedCount . ' 条，更新 ' . $updatedCount . ' 条');
        flash_set('success', '课堂签到已发起：新增 ' . $insertedCount . ' 条，更新 ' . $updatedCount . ' 条，自动请假 ' . $leaveCount . ' 条。');
        redirect_to('class_attendance.php?session_id=' . $sessionId);
    }

    if ($action === 'close_session') {
        $sessionId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
        if ($sessionId > 0) {
            $closeStmt = $conn->prepare("UPDATE `class_signin_sessions` SET `status` = '已结束', `updated_at` = NOW() WHERE `session_id` = ? LIMIT 1");
            if ($closeStmt) {
                $closeStmt->bind_param('i', $sessionId);
                $closeStmt->execute();
                $closeStmt->close();
                write_operation_log($conn, '课堂签到结束', '结束课堂签到场次 ID：' . $sessionId);
                flash_set('success', '课堂签到场次已结束。');
            }
        }
        redirect_to('class_attendance.php');
    }
}

$classFilter = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';
$dateFilter = isset($_GET['sign_date']) ? trim($_GET['sign_date']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 10;

$conditions = array();
if ($classFilter !== '') {
    $classSafe = $conn->real_escape_string($classFilter);
    $conditions[] = "`class_name` = '{$classSafe}'";
}
if ($dateFilter !== '') {
    $dateSafe = $conn->real_escape_string($dateFilter);
    $conditions[] = "`sign_date` = '{$dateSafe}'";
}
if ($statusFilter !== '') {
    $statusSafe = $conn->real_escape_string($statusFilter);
    $conditions[] = "`status` = '{$statusSafe}'";
}
$whereSql = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countResult = $conn->query("SELECT COUNT(*) AS total FROM `class_signin_sessions` {$whereSql}");
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

$sessionSql = "SELECT `session_id`, `class_name`, `session_name`, `sign_date`, `sign_start`, `sign_end`, `status`, `created_by`, `created_at` FROM `class_signin_sessions` {$whereSql} ORDER BY `session_id` DESC LIMIT {$offset}, {$pageSize}";
$sessionResult = $conn->query($sessionSql);

$classOptions = array();
$classResult = $conn->query("SELECT DISTINCT `class` FROM `student` WHERE `class` <> '' ORDER BY `class` ASC");
if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classOptions[] = $row['class'];
    }
}

$selectedSessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$selectedSession = null;
$recordRows = array();
$recordSummary = array(
    'total' => 0,
    'normal' => 0,
    'late' => 0,
    'absent' => 0,
    'leave' => 0
);

if ($selectedSessionId > 0) {
    $selectedStmt = $conn->prepare('SELECT `session_id`, `class_name`, `session_name`, `sign_date`, `status` FROM `class_signin_sessions` WHERE `session_id` = ? LIMIT 1');
    if ($selectedStmt) {
        $selectedStmt->bind_param('i', $selectedSessionId);
        $selectedStmt->execute();
        $selectedStmt->store_result();
        if ($selectedStmt->num_rows === 1) {
            $selectedStmt->bind_result($dbSessionId, $dbClassName, $dbSessionName, $dbSignDate, $dbStatus);
            $selectedStmt->fetch();
            $selectedSession = array(
                'session_id' => $dbSessionId,
                'class_name' => $dbClassName,
                'session_name' => $dbSessionName,
                'sign_date' => $dbSignDate,
                'status' => $dbStatus
            );
        }
        $selectedStmt->close();
    }

    if ($selectedSession) {
        $recordStmt = $conn->prepare("SELECT `student_id`, `student_name`, `status`, `remark`, `updated_by`, `updated_at` FROM `attendance_records` WHERE `attendance_type` = 'class' AND `class_name` = ? AND `attendance_date` = ? AND `session_name` = ? ORDER BY `student_id` ASC");
        if ($recordStmt) {
            $recordStmt->bind_param('sss', $selectedSession['class_name'], $selectedSession['sign_date'], $selectedSession['session_name']);
            $recordStmt->execute();
            $recordStmt->bind_result($dbRecordStudentId, $dbRecordStudentName, $dbRecordStatus, $dbRecordRemark, $dbRecordUpdatedBy, $dbRecordUpdatedAt);
            while ($recordStmt->fetch()) {
                $row = array(
                    'student_id' => $dbRecordStudentId,
                    'student_name' => $dbRecordStudentName,
                    'status' => $dbRecordStatus,
                    'remark' => $dbRecordRemark,
                    'updated_by' => $dbRecordUpdatedBy,
                    'updated_at' => $dbRecordUpdatedAt
                );
                $recordRows[] = $row;
                $recordSummary['total']++;
                if ($dbRecordStatus === '出勤') {
                    $recordSummary['normal']++;
                } elseif ($dbRecordStatus === '迟到') {
                    $recordSummary['late']++;
                } elseif ($dbRecordStatus === '缺勤') {
                    $recordSummary['absent']++;
                } elseif ($dbRecordStatus === '请假') {
                    $recordSummary['leave']++;
                }
            }
            $recordStmt->close();
        }
    }
}

$successTip = flash_get('success');
$errorTip = flash_get('error');
$baseQuery = array(
    'class_name' => $classFilter,
    'sign_date' => $dateFilter,
    'status' => $statusFilter
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>课堂签到</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>课堂签到发起</h2>
        <div class="note">任课教师或管理员可发起课堂签到。系统会按班级生成当节课堂考勤，默认缺勤；若存在已通过请假，会自动标记为请假。</div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>

        <form method="post" action="class_attendance.php" class="form-grid">
            <input type="hidden" name="action" value="create_session">
            <div class="field">
                <label for="class_name_create">班级</label>
                <select id="class_name_create" name="class_name" required>
                    <option value="">请选择班级</option>
                    <?php foreach ($classOptions as $className): ?>
                        <option value="<?php echo h($className); ?>"><?php echo h($className); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="session_name">节次</label>
                <input id="session_name" name="session_name" value="<?php echo h(system_setting($conn, 'class_attendance_default_session', '第1-2节')); ?>" required>
            </div>
            <div class="field">
                <label for="sign_date">签到日期</label>
                <input id="sign_date" type="date" name="sign_date" value="<?php echo h(date('Y-m-d')); ?>" required>
            </div>
            <div class="field">
                <label for="sign_start_time">开始时间</label>
                <input id="sign_start_time" type="time" name="sign_start_time" value="08:00" required>
            </div>
            <div class="field">
                <label for="sign_end_time">截止时间</label>
                <input id="sign_end_time" type="time" name="sign_end_time" value="08:20" required>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">发起课堂签到</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>签到场次查询</h3>
        <form method="get" action="class_attendance.php" class="form-grid">
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
                <label for="sign_date_filter">签到日期</label>
                <input id="sign_date_filter" type="date" name="sign_date" value="<?php echo h($dateFilter); ?>">
            </div>
            <div class="field">
                <label for="status">状态</label>
                <select id="status" name="status">
                    <option value="">全部</option>
                    <option value="进行中" <?php echo $statusFilter === '进行中' ? 'selected' : ''; ?>>进行中</option>
                    <option value="已结束" <?php echo $statusFilter === '已结束' ? 'selected' : ''; ?>>已结束</option>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">筛选</button>
                <a class="btn btn-muted" href="class_attendance.php">重置</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>课堂签到场次（共 <?php echo (int) $total; ?> 条）</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>班级</th>
                <th>节次</th>
                <th>日期</th>
                <th>开始</th>
                <th>截止</th>
                <th>状态</th>
                <th>发起人</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($sessionResult && $sessionResult->num_rows > 0): ?>
                <?php while ($row = $sessionResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int) $row['session_id']; ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['session_name']); ?></td>
                        <td><?php echo h($row['sign_date']); ?></td>
                        <td><?php echo h($row['sign_start']); ?></td>
                        <td><?php echo h($row['sign_end']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td><?php echo h($row['created_by']); ?></td>
                        <td>
                            <a href="class_attendance.php?<?php echo http_build_query(array_merge($baseQuery, array('session_id' => (int) $row['session_id']))); ?>">查看签到</a>
                            <?php if ($row['status'] === '进行中'): ?>
                                <form method="post" action="class_attendance.php" style="display:inline-block; margin-left:6px;">
                                    <input type="hidden" name="action" value="close_session">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $row['session_id']; ?>">
                                    <button class="link-button" type="submit" onclick="return confirm('确认结束该课堂签到场次吗？');">结束</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">暂无课堂签到场次。</td>
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
            <a href="class_attendance.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="class_attendance.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="class_attendance.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="class_attendance.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>

    <?php if ($selectedSession): ?>
    <section class="panel">
        <h3>签到结果：<?php echo h($selectedSession['class_name'] . ' / ' . $selectedSession['session_name'] . ' / ' . $selectedSession['sign_date']); ?></h3>
        <div class="stats-row stats-row-wide">
            <div class="stat-card">
                <div class="stat-title">总人数</div>
                <div class="stat-value"><?php echo (int) $recordSummary['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">出勤</div>
                <div class="stat-value"><?php echo (int) $recordSummary['normal']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">迟到</div>
                <div class="stat-value"><?php echo (int) $recordSummary['late']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">缺勤</div>
                <div class="stat-value"><?php echo (int) $recordSummary['absent']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">请假</div>
                <div class="stat-value"><?php echo (int) $recordSummary['leave']; ?></div>
            </div>
        </div>

        <table class="data-table" style="margin-top:12px;">
            <thead>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <th>状态</th>
                <th>备注</th>
                <th>更新人</th>
                <th>更新时间</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($recordRows)): ?>
                <?php foreach ($recordRows as $row): ?>
                    <tr>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td><?php echo h($row['student_name']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td><?php echo h($row['remark']); ?></td>
                        <td><?php echo h($row['updated_by']); ?></td>
                        <td><?php echo h($row['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">暂无签到结果。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>
</div>
</body>
</html>
