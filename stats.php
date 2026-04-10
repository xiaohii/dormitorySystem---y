<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'teacher', 'dorm'));

ensure_system_schema($conn);

function stats_time_dimension_parts($field, $dimension)
{
    if ($dimension === 'week') {
        return array(
            'group' => "YEARWEEK({$field}, 1)",
            'label' => "CONCAT(SUBSTRING(YEARWEEK({$field}, 1), 1, 4), '-W', LPAD(MOD(YEARWEEK({$field}, 1), 100), 2, '0'))"
        );
    }

    if ($dimension === 'month') {
        return array(
            'group' => "DATE_FORMAT({$field}, '%Y-%m')",
            'label' => "DATE_FORMAT({$field}, '%Y-%m')"
        );
    }

    if ($dimension === 'semester') {
        $semesterExpr = "CASE
            WHEN MONTH({$field}) BETWEEN 2 AND 7 THEN CONCAT(YEAR({$field}), '春季学期')
            WHEN MONTH({$field}) = 1 THEN CONCAT(YEAR({$field}) - 1, '秋季学期')
            ELSE CONCAT(YEAR({$field}), '秋季学期')
        END";
        return array(
            'group' => $semesterExpr,
            'label' => $semesterExpr
        );
    }

    return array(
        'group' => "DATE({$field})",
        'label' => "DATE_FORMAT({$field}, '%Y-%m-%d')"
    );
}

function stats_object_dimension_parts($dimension)
{
    if ($dimension === 'academy') {
        $expr = "COALESCE(NULLIF(s.`academy`, ''), '未设置学院')";
        return array('group' => $expr, 'label' => $expr, 'title' => '学院');
    }

    if ($dimension === 'major') {
        $expr = "COALESCE(NULLIF(s.`major`, ''), '未设置专业')";
        return array('group' => $expr, 'label' => $expr, 'title' => '专业');
    }

    if ($dimension === 'student') {
        $expr = "CONCAT(a.`student_id`, ' - ', a.`student_name`)";
        return array('group' => $expr, 'label' => $expr, 'title' => '学生');
    }

    $expr = "COALESCE(NULLIF(s.`class`, ''), '未设置班级')";
    return array('group' => $expr, 'label' => $expr, 'title' => '班级');
}

function stats_status_case($alias)
{
    $leaveExists = approved_leave_exists_sql($alias);
    return "CASE
        WHEN {$leaveExists} THEN '请假'
        WHEN {$alias}.`status` IN ('出勤', '已归寝', '正常') THEN '正常'
        WHEN {$alias}.`status` IN ('迟到', '晚归') THEN '迟到'
        WHEN {$alias}.`status` IN ('缺勤', '未归') THEN '缺勤'
        WHEN {$alias}.`status` = '请假' THEN '请假'
        ELSE '其他'
    END";
}

function stats_student_join_sql($studentAlias, $attendanceAlias)
{
    return "LEFT JOIN `student` {$studentAlias} ON {$studentAlias}.`id` COLLATE utf8_general_ci = {$attendanceAlias}.`student_id` COLLATE utf8_general_ci";
}

function stats_build_payload($conn, $params)
{
    $timeDimension = isset($params['time_dimension']) ? trim($params['time_dimension']) : 'day';
    if (!in_array($timeDimension, array('day', 'week', 'month', 'semester'), true)) {
        $timeDimension = 'day';
    }

    $objectDimension = isset($params['object_dimension']) ? trim($params['object_dimension']) : 'class';
    if (!in_array($objectDimension, array('academy', 'major', 'class', 'student'), true)) {
        $objectDimension = 'class';
    }

    $attendanceType = isset($params['attendance_type']) ? trim($params['attendance_type']) : 'all';
    if (!in_array($attendanceType, array('all', 'dorm', 'class'), true)) {
        $attendanceType = 'all';
    }

    $startDate = isset($params['start_date']) ? trim($params['start_date']) : date('Y-m-01');
    $endDate = isset($params['end_date']) ? trim($params['end_date']) : date('Y-m-d');
    $academy = isset($params['academy']) ? trim($params['academy']) : '';
    $major = isset($params['major']) ? trim($params['major']) : '';
    $className = isset($params['class_name']) ? trim($params['class_name']) : '';
    $studentKeyword = isset($params['student_keyword']) ? trim($params['student_keyword']) : '';

    $conditions = array();
    if ($attendanceType !== 'all') {
        $typeSafe = $conn->real_escape_string($attendanceType);
        $conditions[] = "a.`attendance_type` = '{$typeSafe}'";
    }
    if ($startDate !== '') {
        $startSafe = $conn->real_escape_string($startDate);
        $conditions[] = "a.`attendance_date` >= '{$startSafe}'";
    }
    if ($endDate !== '') {
        $endSafe = $conn->real_escape_string($endDate);
        $conditions[] = "a.`attendance_date` <= '{$endSafe}'";
    }
    if ($academy !== '') {
        $academySafe = $conn->real_escape_string($academy);
        if ($academy === '未设置学院') {
            $conditions[] = "(s.`academy` = '' OR s.`academy` IS NULL)";
        } else {
            $conditions[] = "s.`academy` = '{$academySafe}'";
        }
    }
    if ($major !== '') {
        $majorSafe = $conn->real_escape_string($major);
        if ($major === '未设置专业') {
            $conditions[] = "(s.`major` = '' OR s.`major` IS NULL)";
        } else {
            $conditions[] = "s.`major` = '{$majorSafe}'";
        }
    }
    if ($className !== '') {
        $classSafe = $conn->real_escape_string($className);
        if ($className === '未设置班级') {
            $conditions[] = "(s.`class` = '' OR s.`class` IS NULL)";
        } else {
            $conditions[] = "s.`class` = '{$classSafe}'";
        }
    }
    if ($studentKeyword !== '') {
        $studentSafe = $conn->real_escape_string($studentKeyword);
        $conditions[] = "(a.`student_id` LIKE '%{$studentSafe}%' OR a.`student_name` LIKE '%{$studentSafe}%')";
    }

    $whereSql = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    $statusCase = stats_status_case('a');
    $timePart = stats_time_dimension_parts('a.`attendance_date`', $timeDimension);
    $objectPart = stats_object_dimension_parts($objectDimension);

    $summarySql = "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN {$statusCase} = '正常' THEN 1 ELSE 0 END) AS normal_count,
        SUM(CASE WHEN {$statusCase} = '迟到' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN {$statusCase} = '缺勤' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN {$statusCase} = '请假' THEN 1 ELSE 0 END) AS leave_count
    FROM `attendance_records` a
    " . stats_student_join_sql('s', 'a') . "
    {$whereSql}";
    $summaryResult = $conn->query($summarySql);
    $summary = array(
        'total_count' => 0,
        'normal_count' => 0,
        'late_count' => 0,
        'absent_count' => 0,
        'leave_count' => 0,
        'attendance_rate' => 0
    );
    if ($summaryResult && $row = $summaryResult->fetch_assoc()) {
        $summary['total_count'] = (int) $row['total_count'];
        $summary['normal_count'] = (int) $row['normal_count'];
        $summary['late_count'] = (int) $row['late_count'];
        $summary['absent_count'] = (int) $row['absent_count'];
        $summary['leave_count'] = (int) $row['leave_count'];
        if ($summary['total_count'] > 0) {
            $summary['attendance_rate'] = round(($summary['normal_count'] + $summary['late_count']) / $summary['total_count'] * 100, 2);
        }
    }

    $objectSql = "SELECT
        {$objectPart['group']} AS object_key,
        {$objectPart['label']} AS object_label,
        COUNT(*) AS total_count,
        SUM(CASE WHEN {$statusCase} = '正常' THEN 1 ELSE 0 END) AS normal_count,
        SUM(CASE WHEN {$statusCase} = '迟到' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN {$statusCase} = '缺勤' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN {$statusCase} = '请假' THEN 1 ELSE 0 END) AS leave_count
    FROM `attendance_records` a
    " . stats_student_join_sql('s', 'a') . "
    {$whereSql}
    GROUP BY object_key, object_label
    ORDER BY total_count DESC, object_label ASC
    LIMIT 20";
    $objectResult = $conn->query($objectSql);
    $objectRows = array();
    $barLabels = array();
    $barNormal = array();
    $barLate = array();
    $barAbsent = array();
    $barLeave = array();
    if ($objectResult) {
        while ($row = $objectResult->fetch_assoc()) {
            $totalCount = (int) $row['total_count'];
            $normalCount = (int) $row['normal_count'];
            $lateCount = (int) $row['late_count'];
            $absentCount = (int) $row['absent_count'];
            $leaveCount = (int) $row['leave_count'];
            $attendanceRate = $totalCount > 0 ? round(($normalCount + $lateCount) / $totalCount * 100, 2) : 0;

            $objectRows[] = array(
                'label' => $row['object_label'],
                'total' => $totalCount,
                'normal' => $normalCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'leave' => $leaveCount,
                'attendance_rate' => $attendanceRate
            );
            $barLabels[] = $row['object_label'];
            $barNormal[] = $normalCount;
            $barLate[] = $lateCount;
            $barAbsent[] = $absentCount;
            $barLeave[] = $leaveCount;
        }
    }

    $trendSql = "SELECT
        {$timePart['group']} AS time_key,
        {$timePart['label']} AS time_label,
        COUNT(*) AS total_count,
        SUM(CASE WHEN {$statusCase} = '正常' THEN 1 ELSE 0 END) AS normal_count,
        SUM(CASE WHEN {$statusCase} = '迟到' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN {$statusCase} = '缺勤' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN {$statusCase} = '请假' THEN 1 ELSE 0 END) AS leave_count
    FROM `attendance_records` a
    " . stats_student_join_sql('s', 'a') . "
    {$whereSql}
    GROUP BY time_key, time_label
    ORDER BY MIN(a.`attendance_date`) ASC";
    $trendResult = $conn->query($trendSql);
    $trendRows = array();
    $trendLabels = array();
    $trendAttendanceRate = array();
    if ($trendResult) {
        while ($row = $trendResult->fetch_assoc()) {
            $totalCount = (int) $row['total_count'];
            $normalCount = (int) $row['normal_count'];
            $lateCount = (int) $row['late_count'];
            $attendanceRate = $totalCount > 0 ? round(($normalCount + $lateCount) / $totalCount * 100, 2) : 0;
            $trendRows[] = array(
                'label' => $row['time_label'],
                'total' => $totalCount,
                'normal' => $normalCount,
                'late' => $lateCount,
                'absent' => (int) $row['absent_count'],
                'leave' => (int) $row['leave_count'],
                'attendance_rate' => $attendanceRate
            );
            $trendLabels[] = $row['time_label'];
            $trendAttendanceRate[] = $attendanceRate;
        }
    }

    $pieRows = array(
        array('name' => '正常', 'value' => $summary['normal_count']),
        array('name' => '迟到', 'value' => $summary['late_count']),
        array('name' => '缺勤', 'value' => $summary['absent_count']),
        array('name' => '请假', 'value' => $summary['leave_count'])
    );

    return array(
        'filters' => array(
            'time_dimension' => $timeDimension,
            'object_dimension' => $objectDimension,
            'attendance_type' => $attendanceType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'academy' => $academy,
            'major' => $major,
            'class_name' => $className,
            'student_keyword' => $studentKeyword
        ),
        'object_title' => $objectPart['title'],
        'summary' => $summary,
        'object_rows' => $objectRows,
        'trend_rows' => $trendRows,
        'charts' => array(
            'bar' => array(
                'labels' => $barLabels,
                'normal' => $barNormal,
                'late' => $barLate,
                'absent' => $barAbsent,
                'leave' => $barLeave
            ),
            'pie' => $pieRows,
            'trend' => array(
                'labels' => $trendLabels,
                'attendance_rate' => $trendAttendanceRate
            )
        )
    );
}

function export_stats_excel($payload)
{
    $fileName = '统计报表_' . date('Ymd_His') . '.xls';
    $vendorAutoload = __DIR__ . '/vendor/autoload.php';
    $canUsePhpSpreadsheet = false;

    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        $canUsePhpSpreadsheet = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    }

    if ($canUsePhpSpreadsheet) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', $payload['object_title'])
            ->setCellValue('B1', '应出勤')
            ->setCellValue('C1', '正常')
            ->setCellValue('D1', '迟到')
            ->setCellValue('E1', '缺勤')
            ->setCellValue('F1', '请假')
            ->setCellValue('G1', '出勤率');

        $rowIndex = 2;
        foreach ($payload['object_rows'] as $row) {
            $sheet->setCellValue('A' . $rowIndex, $row['label'])
                ->setCellValue('B' . $rowIndex, $row['total'])
                ->setCellValue('C' . $rowIndex, $row['normal'])
                ->setCellValue('D' . $rowIndex, $row['late'])
                ->setCellValue('E' . $rowIndex, $row['absent'])
                ->setCellValue('F' . $rowIndex, $row['leave'])
                ->setCellValue('G' . $rowIndex, $row['attendance_rate'] . '%');
            $rowIndex++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=' . rawurlencode(str_replace('.xls', '.xlsx', $fileName)));
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . rawurlencode($fileName));
    echo chr(239) . chr(187) . chr(191);
    echo '<table border="1">';
    echo '<tr><th>' . h($payload['object_title']) . '</th><th>应出勤</th><th>正常</th><th>迟到</th><th>缺勤</th><th>请假</th><th>出勤率</th></tr>';
    foreach ($payload['object_rows'] as $row) {
        echo '<tr>';
        echo '<td>' . h($row['label']) . '</td>';
        echo '<td>' . (int) $row['total'] . '</td>';
        echo '<td>' . (int) $row['normal'] . '</td>';
        echo '<td>' . (int) $row['late'] . '</td>';
        echo '<td>' . (int) $row['absent'] . '</td>';
        echo '<td>' . (int) $row['leave'] . '</td>';
        echo '<td>' . h(number_format((float) $row['attendance_rate'], 2)) . '%</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

$requestParams = array(
    'time_dimension' => isset($_GET['time_dimension']) ? $_GET['time_dimension'] : 'day',
    'object_dimension' => isset($_GET['object_dimension']) ? $_GET['object_dimension'] : 'class',
    'attendance_type' => isset($_GET['attendance_type']) ? $_GET['attendance_type'] : 'all',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'),
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'),
    'academy' => isset($_GET['academy']) ? $_GET['academy'] : '',
    'major' => isset($_GET['major']) ? $_GET['major'] : '',
    'class_name' => isset($_GET['class_name']) ? $_GET['class_name'] : '',
    'student_keyword' => isset($_GET['student_keyword']) ? $_GET['student_keyword'] : ''
);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    $payload = stats_build_payload($conn, $requestParams);
    echo json_encode(array('code' => 200, 'data' => $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $payload = stats_build_payload($conn, $requestParams);
    write_operation_log($conn, '统计导出', '导出 ' . $payload['object_title'] . ' 维度统计报表');
    export_stats_excel($payload);
}

$academyOptions = array();
$academyResult = $conn->query("SELECT DISTINCT COALESCE(NULLIF(`academy`, ''), '未设置学院') AS label FROM `student` ORDER BY label ASC");
if ($academyResult) {
    while ($row = $academyResult->fetch_assoc()) {
        $academyOptions[] = $row['label'];
    }
}

$majorOptions = array();
$majorResult = $conn->query("SELECT DISTINCT COALESCE(NULLIF(`major`, ''), '未设置专业') AS label FROM `student` ORDER BY label ASC");
if ($majorResult) {
    while ($row = $majorResult->fetch_assoc()) {
        $majorOptions[] = $row['label'];
    }
}

$classOptions = array();
$classResult = $conn->query("SELECT DISTINCT COALESCE(NULLIF(`class`, ''), '未设置班级') AS label FROM `student` ORDER BY label ASC");
if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classOptions[] = $row['label'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>统计报表功能</h2>
        <div class="note">使用 ECharts 展示柱状图、饼图和趋势图；支持按日、周、月、学期，以及按学院、专业、班级、个人等维度进行交叉统计，并导出 Excel 报表。已通过请假会自动从缺勤中剔除并计入请假。</div>

        <form id="statsForm" class="form-grid" method="get" action="stats.php">
            <div class="field">
                <label for="time_dimension">时间维度</label>
                <select id="time_dimension" name="time_dimension">
                    <option value="day">按日</option>
                    <option value="week">按周</option>
                    <option value="month">按月</option>
                    <option value="semester">按学期</option>
                </select>
            </div>
            <div class="field">
                <label for="object_dimension">对象维度</label>
                <select id="object_dimension" name="object_dimension">
                    <option value="academy">按学院</option>
                    <option value="major">按专业</option>
                    <option value="class">按班级</option>
                    <option value="student">按个人</option>
                </select>
            </div>
            <div class="field">
                <label for="attendance_type">考勤类型</label>
                <select id="attendance_type" name="attendance_type">
                    <option value="all">全部类型</option>
                    <option value="class">上课考勤</option>
                    <option value="dorm">宿舍考勤</option>
                </select>
            </div>
            <div class="field">
                <label for="start_date">开始日期</label>
                <input id="start_date" type="date" name="start_date" value="<?php echo h($requestParams['start_date']); ?>">
            </div>
            <div class="field">
                <label for="end_date">结束日期</label>
                <input id="end_date" type="date" name="end_date" value="<?php echo h($requestParams['end_date']); ?>">
            </div>
            <div class="field">
                <label for="academy">学院筛选</label>
                <select id="academy" name="academy">
                    <option value="">全部学院</option>
                    <?php foreach ($academyOptions as $item): ?>
                        <option value="<?php echo h($item); ?>"><?php echo h($item); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="major">专业筛选</label>
                <select id="major" name="major">
                    <option value="">全部专业</option>
                    <?php foreach ($majorOptions as $item): ?>
                        <option value="<?php echo h($item); ?>"><?php echo h($item); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="class_name">班级筛选</label>
                <select id="class_name" name="class_name">
                    <option value="">全部班级</option>
                    <?php foreach ($classOptions as $item): ?>
                        <option value="<?php echo h($item); ?>"><?php echo h($item); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="student_keyword">个人筛选</label>
                <input id="student_keyword" name="student_keyword" value="<?php echo h($requestParams['student_keyword']); ?>" placeholder="姓名或学号">
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">生成统计</button>
                <a class="btn btn-muted" href="stats.php">恢复默认</a>
                <button class="btn btn-muted" type="button" id="exportBtn">导出 Excel</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="stats-row stats-row-wide">
            <div class="stat-card">
                <div class="stat-title">应出勤</div>
                <div class="stat-value" id="summaryTotal">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">正常</div>
                <div class="stat-value" id="summaryNormal">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">迟到</div>
                <div class="stat-value" id="summaryLate">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">缺勤</div>
                <div class="stat-value" id="summaryAbsent">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">请假</div>
                <div class="stat-value" id="summaryLeave">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">综合出勤率</div>
                <div class="stat-value" id="summaryRate">0%</div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="chart-grid">
            <div class="chart-card">
                <h3 id="barTitle">对象维度统计柱状图</h3>
                <canvas id="statsBarCanvas" style="display:none;"></canvas>
                <div id="barChart" class="echart-box"></div>
            </div>
            <div class="chart-card">
                <h3>状态占比饼图</h3>
                <div id="pieChart" class="echart-box"></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="chart-card">
            <h3>时间趋势图</h3>
            <div id="trendChart" class="echart-box echart-box-wide"></div>
        </div>
    </section>

    <section class="panel">
        <h3 id="tableTitle">统计明细</h3>
        <div id="statsTip" class="small-muted">正在加载统计数据...</div>
        <table class="data-table">
            <thead>
            <tr>
                <th id="objectHeader">对象</th>
                <th>应出勤</th>
                <th>正常</th>
                <th>迟到</th>
                <th>缺勤</th>
                <th>请假</th>
                <th>出勤率</th>
            </tr>
            </thead>
            <tbody id="statsTableBody">
            <tr>
                <td colspan="7">暂无统计数据。</td>
            </tr>
            </tbody>
        </table>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script>
(function () {
    var form = document.getElementById('statsForm');
    var exportBtn = document.getElementById('exportBtn');
    var summaryTotal = document.getElementById('summaryTotal');
    var summaryNormal = document.getElementById('summaryNormal');
    var summaryLate = document.getElementById('summaryLate');
    var summaryAbsent = document.getElementById('summaryAbsent');
    var summaryLeave = document.getElementById('summaryLeave');
    var summaryRate = document.getElementById('summaryRate');
    var tableBody = document.getElementById('statsTableBody');
    var statsTip = document.getElementById('statsTip');
    var objectHeader = document.getElementById('objectHeader');
    var tableTitle = document.getElementById('tableTitle');
    var barTitle = document.getElementById('barTitle');

    var params = new URLSearchParams(window.location.search);
    if (!params.get('time_dimension')) params.set('time_dimension', 'day');
    if (!params.get('object_dimension')) params.set('object_dimension', 'class');
    if (!params.get('attendance_type')) params.set('attendance_type', 'all');

    Array.prototype.slice.call(form.elements).forEach(function (el) {
        if (!el.name) return;
        if (params.has(el.name)) {
            el.value = params.get(el.name);
        }
    });

    var barChart = echarts.init(document.getElementById('barChart'));
    var pieChart = echarts.init(document.getElementById('pieChart'));
    var trendChart = echarts.init(document.getElementById('trendChart'));

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildQuery(extra) {
        var data = new URLSearchParams(new FormData(form));
        data.set('format', 'json');
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                data.set(key, extra[key]);
            });
        }
        return data.toString();
    }

    function renderTable(objectTitle, rows) {
        objectHeader.textContent = objectTitle;
        tableTitle.textContent = objectTitle + '统计明细';
        barTitle.textContent = objectTitle + '维度统计柱状图';

        if (!rows || !rows.length) {
            tableBody.innerHTML = '<tr><td colspan="7">暂无符合条件的统计数据。</td></tr>';
            return;
        }

        tableBody.innerHTML = rows.map(function (row) {
            return '<tr>' +
                '<td>' + escapeHtml(row.label) + '</td>' +
                '<td>' + row.total + '</td>' +
                '<td>' + row.normal + '</td>' +
                '<td>' + row.late + '</td>' +
                '<td>' + row.absent + '</td>' +
                '<td>' + row.leave + '</td>' +
                '<td>' + Number(row.attendance_rate).toFixed(2) + '%</td>' +
                '</tr>';
        }).join('');
    }

    function renderCharts(data) {
        barChart.setOption({
            tooltip: { trigger: 'axis' },
            legend: { top: 8 },
            grid: { left: 40, right: 20, top: 48, bottom: 80 },
            xAxis: {
                type: 'category',
                data: data.charts.bar.labels,
                axisLabel: { interval: 0, rotate: 25 }
            },
            yAxis: { type: 'value' },
            series: [
                { name: '正常', type: 'bar', data: data.charts.bar.normal, itemStyle: { color: '#1d7862' } },
                { name: '迟到', type: 'bar', data: data.charts.bar.late, itemStyle: { color: '#d9a441' } },
                { name: '缺勤', type: 'bar', data: data.charts.bar.absent, itemStyle: { color: '#d35d5d' } },
                { name: '请假', type: 'bar', data: data.charts.bar.leave, itemStyle: { color: '#6e8fb0' } }
            ]
        });

        pieChart.setOption({
            tooltip: { trigger: 'item' },
            legend: { bottom: 0 },
            series: [{
                type: 'pie',
                radius: ['38%', '68%'],
                data: data.charts.pie,
                label: { formatter: '{b}: {c}' }
            }]
        });

        trendChart.setOption({
            tooltip: { trigger: 'axis' },
            grid: { left: 40, right: 20, top: 24, bottom: 50 },
            xAxis: { type: 'category', data: data.charts.trend.labels },
            yAxis: {
                type: 'value',
                min: 0,
                max: 100,
                axisLabel: { formatter: '{value}%' }
            },
            series: [{
                name: '出勤率',
                type: 'line',
                smooth: true,
                data: data.charts.trend.attendance_rate,
                areaStyle: { color: 'rgba(29,120,98,0.15)' },
                lineStyle: { color: '#1d7862' },
                itemStyle: { color: '#1d7862' }
            }]
        });
    }

    function loadStats(pushState) {
        statsTip.textContent = '正在加载统计数据...';
        fetch('stats.php?' + buildQuery())
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || json.code !== 200) {
                    throw new Error('统计接口返回异常');
                }

                var data = json.data;
                summaryTotal.textContent = data.summary.total_count;
                summaryNormal.textContent = data.summary.normal_count;
                summaryLate.textContent = data.summary.late_count;
                summaryAbsent.textContent = data.summary.absent_count;
                summaryLeave.textContent = data.summary.leave_count;
                summaryRate.textContent = Number(data.summary.attendance_rate).toFixed(2) + '%';
                statsTip.textContent = '统计完成：已按 ' + data.object_title + ' 维度返回 ' + data.object_rows.length + ' 条结果。';
                renderTable(data.object_title, data.object_rows);
                renderCharts(data);

                if (pushState) {
                    var urlParams = new URLSearchParams(new FormData(form));
                    history.replaceState(null, '', 'stats.php?' + urlParams.toString());
                }
            })
            .catch(function () {
                statsTip.textContent = '统计数据加载失败，请检查筛选条件后重试。';
                tableBody.innerHTML = '<tr><td colspan="7">统计数据加载失败。</td></tr>';
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadStats(true);
    });

    exportBtn.addEventListener('click', function () {
        var data = new URLSearchParams(new FormData(form));
        data.set('export', 'excel');
        window.location.href = 'stats.php?' + data.toString();
    });

    window.addEventListener('resize', function () {
        barChart.resize();
        pieChart.resize();
        trendChart.resize();
    });

    loadStats(false);
})();
</script>
</body>
</html>
