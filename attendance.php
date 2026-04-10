<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'dorm'));

ensure_system_schema($conn);

$validTypes = array('dorm', 'class');
$currentType = isset($_GET['attendance_type']) ? trim($_GET['attendance_type']) : 'dorm';
if (!in_array($currentType, $validTypes, true)) {
    $currentType = 'dorm';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'save';

    if ($action === 'delete') {
        $deleteId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
        $deleteType = isset($_POST['attendance_type']) ? trim($_POST['attendance_type']) : $currentType;
        if (!in_array($deleteType, $validTypes, true)) {
            $deleteType = 'dorm';
        }

        if ($deleteId > 0) {
            $deleteStmt = $conn->prepare('DELETE FROM `attendance_records` WHERE `record_id` = ? LIMIT 1');
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $deleteId);
                $deleteStmt->execute();
                $deleteStmt->close();
                write_operation_log($conn, '考勤删除', '删除' . attendance_type_label($deleteType) . '记录 ID：' . $deleteId);
                flash_set('success', '考勤记录已删除。');
            }
        }

        redirect_to('attendance.php?attendance_type=' . urlencode($deleteType));
    }

    $recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
    $formType = isset($_POST['attendance_type']) ? trim($_POST['attendance_type']) : 'dorm';
    $studentId = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $attendanceDate = isset($_POST['attendance_date']) ? trim($_POST['attendance_date']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $sessionName = isset($_POST['session_name']) ? trim($_POST['session_name']) : '';
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';

    if (!in_array($formType, $validTypes, true)) {
        $formType = 'dorm';
    }

    $statusOptions = attendance_status_options($formType);
    if ($studentId === '' || $attendanceDate === '' || !in_array($status, $statusOptions, true)) {
        $errorMessage = '请完整填写学号、考勤日期和有效的考勤状态。';
        redirect_to('attendance.php?attendance_type=' . urlencode($formType) . '&error=' . urlencode($errorMessage));
    }

    $student = fetch_student_profile($conn, $studentId);
    if (!$student) {
        redirect_to('attendance.php?attendance_type=' . urlencode($formType) . '&error=' . urlencode('未找到对应学生，请先确认学号。'));
    }

    if ($sessionName === '') {
        $sessionName = $formType === 'class' ? system_setting($conn, 'class_attendance_default_session', '第1-2节') : '晚点名';
    }

    $studentName = $student['user'];
    $className = $student['class'];
    $dormNo = $student['Dno'];
    $operatorName = current_user_name();
    $now = date('Y-m-d H:i:s');

    if ($recordId > 0) {
        $updateStmt = $conn->prepare('UPDATE `attendance_records` SET `student_id` = ?, `student_name` = ?, `class_name` = ?, `dorm_no` = ?, `attendance_type` = ?, `attendance_date` = ?, `status` = ?, `session_name` = ?, `remark` = ?, `updated_by` = ?, `updated_at` = ? WHERE `record_id` = ? LIMIT 1');
        if ($updateStmt) {
            $updateStmt->bind_param('sssssssssssi', $studentId, $studentName, $className, $dormNo, $formType, $attendanceDate, $status, $sessionName, $remark, $operatorName, $now, $recordId);
            $ok = $updateStmt->execute();
            $updateStmt->close();
            if ($ok) {
                write_operation_log($conn, '考勤修改', '更新' . attendance_type_label($formType) . '记录，学号：' . $studentId . '，日期：' . $attendanceDate . '，状态：' . $status);
                flash_set('success', '考勤记录已更新。');
                redirect_to('attendance.php?attendance_type=' . urlencode($formType));
            }
        }

        redirect_to('attendance.php?attendance_type=' . urlencode($formType) . '&error=' . urlencode('更新考勤记录失败，请稍后重试。'));
    }

    $insertStmt = $conn->prepare('INSERT INTO `attendance_records` (`student_id`, `student_name`, `class_name`, `dorm_no`, `attendance_type`, `attendance_date`, `status`, `session_name`, `remark`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($insertStmt) {
        $insertStmt->bind_param('sssssssssssss', $studentId, $studentName, $className, $dormNo, $formType, $attendanceDate, $status, $sessionName, $remark, $operatorName, $operatorName, $now, $now);
        $ok = $insertStmt->execute();
        $insertStmt->close();
        if ($ok) {
            write_operation_log($conn, '考勤新增', '新增' . attendance_type_label($formType) . '记录，学号：' . $studentId . '，日期：' . $attendanceDate . '，状态：' . $status);
            flash_set('success', '考勤记录已新增。');
            redirect_to('attendance.php?attendance_type=' . urlencode($formType));
        }
    }

    redirect_to('attendance.php?attendance_type=' . urlencode($formType) . '&error=' . urlencode('新增考勤记录失败，请稍后重试。'));
}

$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$editRecord = array(
    'record_id' => 0,
    'student_id' => '',
    'attendance_type' => $currentType,
    'attendance_date' => date('Y-m-d'),
    'status' => attendance_status_options($currentType)[0],
    'session_name' => $currentType === 'class' ? system_setting($conn, 'class_attendance_default_session', '第1-2节') : '晚点名',
    'remark' => ''
);

if ($editId > 0) {
    $editStmt = $conn->prepare('SELECT `record_id`, `student_id`, `attendance_type`, `attendance_date`, `status`, `session_name`, `remark` FROM `attendance_records` WHERE `record_id` = ? LIMIT 1');
    if ($editStmt) {
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editStmt->store_result();
        if ($editStmt->num_rows === 1) {
            $editStmt->bind_result($dbRecordId, $dbStudentId, $dbType, $dbDate, $dbStatus, $dbSessionName, $dbRemark);
            $editStmt->fetch();
            $editRecord = array(
                'record_id' => $dbRecordId,
                'student_id' => $dbStudentId,
                'attendance_type' => $dbType,
                'attendance_date' => $dbDate,
                'status' => $dbStatus,
                'session_name' => $dbSessionName,
                'remark' => $dbRemark
            );
            $currentType = $dbType;
        }
        $editStmt->close();
    }
}

$statusOptions = attendance_status_options($currentType);
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$classFilter = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$pageSize = 10;

$conditions = array();
$typeSafe = $conn->real_escape_string($currentType);
$conditions[] = "`attendance_type` = '{$typeSafe}'";

if ($keyword !== '') {
    $keywordSafe = $conn->real_escape_string($keyword);
    $conditions[] = "(`student_id` LIKE '%{$keywordSafe}%' OR `student_name` LIKE '%{$keywordSafe}%')";
}
if ($classFilter !== '') {
    $classSafe = $conn->real_escape_string($classFilter);
    $conditions[] = "`class_name` = '{$classSafe}'";
}
if ($statusFilter !== '') {
    $statusSafe = $conn->real_escape_string($statusFilter);
    $conditions[] = "`status` = '{$statusSafe}'";
}
if ($dateFrom !== '') {
    $dateFromSafe = $conn->real_escape_string($dateFrom);
    $conditions[] = "`attendance_date` >= '{$dateFromSafe}'";
}
if ($dateTo !== '') {
    $dateToSafe = $conn->real_escape_string($dateTo);
    $conditions[] = "`attendance_date` <= '{$dateToSafe}'";
}

$whereSql = 'WHERE ' . implode(' AND ', $conditions);
$countSql = "SELECT COUNT(*) AS total FROM `attendance_records` {$whereSql}";
$countResult = $conn->query($countSql);
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

$listSql = "SELECT `record_id`, `student_id`, `student_name`, `class_name`, `dorm_no`, `attendance_date`, `status`, `session_name`, `remark`, `updated_by`, `updated_at` FROM `attendance_records` {$whereSql} ORDER BY `attendance_date` DESC, `record_id` DESC LIMIT {$offset}, {$pageSize}";
$listResult = $conn->query($listSql);

$classOptions = array();
$classResult = $conn->query("SELECT DISTINCT `class` FROM `student` WHERE `class` <> '' ORDER BY `class` ASC");
if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classOptions[] = $row['class'];
    }
}

$studentOptions = array();
$studentResult = $conn->query("SELECT `id`, `user`, `class`, `Dno` FROM `student` ORDER BY `id` ASC");
if ($studentResult) {
    while ($row = $studentResult->fetch_assoc()) {
        $studentOptions[] = $row;
    }
}

$successTip = flash_get('success');
$errorTip = isset($_GET['error']) ? trim($_GET['error']) : '';
$baseQuery = array(
    'attendance_type' => $currentType,
    'keyword' => $keyword,
    'class_name' => $classFilter,
    'status' => $statusFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
);
$statusOptionMap = array(
    'dorm' => attendance_status_options('dorm'),
    'class' => attendance_status_options('class')
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>考勤管理</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>考勤记录管理</h2>
        <div class="type-switch">
            <a class="<?php echo $currentType === 'dorm' ? 'active' : ''; ?>" href="attendance.php?attendance_type=dorm">宿舍考勤</a>
            <a class="<?php echo $currentType === 'class' ? 'active' : ''; ?>" href="attendance.php?attendance_type=class">上课考勤</a>
        </div>
        <div class="note">
            当前正在管理<?php echo h(attendance_type_label($currentType)); ?>。
            <?php if ($currentType === 'dorm'): ?>
                可登记已归寝、晚归、未归、请假等状态。
            <?php else: ?>
                可登记出勤、迟到、缺勤、请假等状态。
            <?php endif; ?>
        </div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>

        <form method="post" action="attendance.php?attendance_type=<?php echo h($currentType); ?>" class="form-grid">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="record_id" value="<?php echo (int) $editRecord['record_id']; ?>">
            <div class="field">
                <label for="attendance_type">考勤类型</label>
                <select id="attendance_type" name="attendance_type">
                    <option value="dorm" <?php echo $editRecord['attendance_type'] === 'dorm' ? 'selected' : ''; ?>>宿舍考勤</option>
                    <option value="class" <?php echo $editRecord['attendance_type'] === 'class' ? 'selected' : ''; ?>>上课考勤</option>
                </select>
            </div>
            <div class="field">
                <label for="student_id">学生</label>
                <select id="student_id" name="student_id" required>
                    <option value="">请选择学生</option>
                    <?php foreach ($studentOptions as $student): ?>
                        <?php $selected = $editRecord['student_id'] === $student['id'] ? 'selected' : ''; ?>
                        <option value="<?php echo h($student['id']); ?>" <?php echo $selected; ?>>
                            <?php echo h($student['id'] . ' - ' . $student['user'] . ' - ' . $student['class']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="attendance_date">考勤日期</label>
                <input id="attendance_date" type="date" name="attendance_date" value="<?php echo h($editRecord['attendance_date']); ?>" required>
            </div>
            <div class="field">
                <label for="status">考勤状态</label>
                <select id="status" name="status" required>
                    <?php foreach (attendance_status_options($editRecord['attendance_type']) as $option): ?>
                        <option value="<?php echo h($option); ?>" <?php echo $editRecord['status'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="session_name">节次 / 点名场景</label>
                <input id="session_name" name="session_name" value="<?php echo h($editRecord['session_name']); ?>" placeholder="例如 第1-2节 / 晚点名">
            </div>
            <div class="field">
                <label for="remark">备注</label>
                <input id="remark" name="remark" value="<?php echo h($editRecord['remark']); ?>" placeholder="可选">
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?php echo $editRecord['record_id'] > 0 ? '保存修改' : '新增记录'; ?></button>
                <a class="btn btn-muted" href="attendance.php?attendance_type=<?php echo h($currentType); ?>">清空表单</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3><?php echo h(attendance_type_label($currentType)); ?>筛选查询</h3>
        <form method="get" action="attendance.php" class="form-grid">
            <input type="hidden" name="attendance_type" value="<?php echo h($currentType); ?>">
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
                <label for="date_from">日期（起）</label>
                <input id="date_from" type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
            </div>
            <div class="field">
                <label for="date_to">日期（止）</label>
                <input id="date_to" type="date" name="date_to" value="<?php echo h($dateTo); ?>">
            </div>
            <div class="field">
                <label for="status_filter">状态</label>
                <select id="status_filter" name="status">
                    <option value="">全部状态</option>
                    <?php foreach ($statusOptions as $statusName): ?>
                        <option value="<?php echo h($statusName); ?>" <?php echo $statusFilter === $statusName ? 'selected' : ''; ?>><?php echo h($statusName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">筛选记录</button>
                <a class="btn btn-muted" href="attendance.php?attendance_type=<?php echo h($currentType); ?>">重置条件</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-title">记录总数</div>
                <div class="stat-value"><?php echo (int) $total; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">当前页码</div>
                <div class="stat-value"><?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">考勤类型</div>
                <div class="stat-value" style="font-size:20px;"><?php echo h(attendance_type_label($currentType)); ?></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>考勤记录列表</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>日期</th>
                <th>学号</th>
                <th>姓名</th>
                <th>班级</th>
                <th>宿舍号</th>
                <th>节次 / 点名场景</th>
                <th>状态</th>
                <th>备注</th>
                <th>更新人</th>
                <th>更新时间</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($row = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int) $row['record_id']; ?></td>
                        <td><?php echo h($row['attendance_date']); ?></td>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td><?php echo h($row['student_name']); ?></td>
                        <td><?php echo h($row['class_name']); ?></td>
                        <td><?php echo h($row['dorm_no']); ?></td>
                        <td><?php echo h($row['session_name']); ?></td>
                        <td><span class="status-pill"><?php echo h($row['status']); ?></span></td>
                        <td><?php echo h($row['remark']); ?></td>
                        <td><?php echo h($row['updated_by']); ?></td>
                        <td><?php echo h($row['updated_at']); ?></td>
                        <td>
                            <a href="attendance.php?<?php echo http_build_query(array_merge($baseQuery, array('edit_id' => (int) $row['record_id']))); ?>">编辑</a>
                            <form method="post" action="attendance.php?attendance_type=<?php echo h($currentType); ?>" style="display:inline-block; margin-left:6px;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="record_id" value="<?php echo (int) $row['record_id']; ?>">
                                <input type="hidden" name="attendance_type" value="<?php echo h($currentType); ?>">
                                <button class="link-button" type="submit" onclick="return confirm('确认删除该考勤记录吗？');">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">暂无符合条件的考勤记录。</td>
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
            <a href="attendance.php?<?php echo http_build_query($firstQuery); ?>">首页</a>
            <a href="attendance.php?<?php echo http_build_query($prevQuery); ?>">上一页</a>
            <span class="current"><?php echo (int) $page; ?></span>
            <a href="attendance.php?<?php echo http_build_query($nextQuery); ?>">下一页</a>
            <a href="attendance.php?<?php echo http_build_query($lastQuery); ?>">末页</a>
        </div>
    </section>
</div>

<script>
(function () {
    var typeSelect = document.getElementById('attendance_type');
    var statusSelect = document.getElementById('status');
    var sessionInput = document.getElementById('session_name');
    var statusOptionMap = <?php echo json_encode($statusOptionMap, JSON_UNESCAPED_UNICODE); ?>;

    function renderStatusOptions(type) {
        var options = statusOptionMap[type] || [];
        var currentValue = statusSelect.value;
        statusSelect.innerHTML = '';

        options.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item;
            opt.textContent = item;
            if (item === currentValue) {
                opt.selected = true;
            }
            statusSelect.appendChild(opt);
        });

        if (statusSelect.selectedIndex === -1 && statusSelect.options.length > 0) {
            statusSelect.selectedIndex = 0;
        }
    }

    function syncSessionPlaceholder(type) {
        sessionInput.placeholder = type === 'class' ? '例如 第1-2节 / 晨读' : '例如 晚点名 / 夜查寝';
        if (!sessionInput.value) {
            sessionInput.value = type === 'class' ? '第1-2节' : '晚点名';
        }
    }

    typeSelect.addEventListener('change', function () {
        renderStatusOptions(typeSelect.value);
        syncSessionPlaceholder(typeSelect.value);
    });

    renderStatusOptions(typeSelect.value);
    syncSessionPlaceholder(typeSelect.value);
})();
</script>
</body>
</html>
