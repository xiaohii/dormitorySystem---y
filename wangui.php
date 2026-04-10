<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin', 'dorm'));

ensure_system_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentIds = isset($_POST['student_id']) && is_array($_POST['student_id']) ? $_POST['student_id'] : array();
    $studentNames = isset($_POST['student_name']) && is_array($_POST['student_name']) ? $_POST['student_name'] : array();
    $dormNos = isset($_POST['dorm_no']) && is_array($_POST['dorm_no']) ? $_POST['dorm_no'] : array();
    $lateDates = isset($_POST['late_date']) && is_array($_POST['late_date']) ? $_POST['late_date'] : array();
    $notBackFlags = isset($_POST['is_not_return']) && is_array($_POST['is_not_return']) ? $_POST['is_not_return'] : array();
    $remarks = isset($_POST['remark']) && is_array($_POST['remark']) ? $_POST['remark'] : array();

    $insertStmt = $conn->prepare('INSERT INTO `late_return_records` (`student_id`, `student_name`, `dorm_no`, `late_date`, `is_not_return`, `remark`, `created_by`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $okCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    if ($insertStmt) {
        $maxCount = max(count($studentIds), count($lateDates));
        for ($i = 0; $i < $maxCount; $i++) {
            $sid = isset($studentIds[$i]) ? trim($studentIds[$i]) : '';
            $sname = isset($studentNames[$i]) ? trim($studentNames[$i]) : '';
            $dorm = isset($dormNos[$i]) ? trim($dormNos[$i]) : '';
            $date = isset($lateDates[$i]) ? trim($lateDates[$i]) : '';
            $notReturn = isset($notBackFlags[$i]) ? trim($notBackFlags[$i]) : '否';
            $remark = isset($remarks[$i]) ? trim($remarks[$i]) : '';

            if ($sid === '' && $sname === '' && $date === '' && $remark === '') {
                $skipCount++;
                continue;
            }

            if ($sid === '' || $sname === '' || $date === '') {
                $errorCount++;
                continue;
            }

            if ($notReturn !== '是' && $notReturn !== '否') {
                $notReturn = '否';
            }

            $createdBy = current_user_name();
            $createdAt = date('Y-m-d H:i:s');

            $insertStmt->bind_param('ssssssss', $sid, $sname, $dorm, $date, $notReturn, $remark, $createdBy, $createdAt);
            if ($insertStmt->execute()) {
                $okCount++;
            } else {
                $errorCount++;
            }
        }
        $insertStmt->close();
    } else {
        $errorCount++;
    }

    $summary = '批量提交完成：成功 ' . $okCount . ' 条';
    if ($errorCount > 0) {
        $summary .= '，失败 ' . $errorCount . ' 条';
    }
    if ($skipCount > 0) {
        $summary .= '，空行跳过 ' . $skipCount . ' 条';
    }
    write_operation_log($conn, '晚归登记', $summary);
    flash_set('success', $summary);
    redirect_to('wangui.php');
}

$today = date('Y-m-d');
$successTip = flash_get('success');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>晚归登记</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>晚归登记</h2>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <div class="note">支持一次登记多名学生。至少填写学号、姓名、晚归日期，系统将自动写入数据库。</div>

        <form method="post" action="wangui.php">
            <table class="data-table batch-table">
                <thead>
                <tr>
                    <th>学号</th>
                    <th>姓名</th>
                    <th>宿舍号</th>
                    <th>晚归日期</th>
                    <th>是否未归</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody id="batchBody">
                <tr>
                    <td><input name="student_id[]" placeholder="例如 2025001"></td>
                    <td><input name="student_name[]" placeholder="例如 张三"></td>
                    <td><input name="dorm_no[]" placeholder="例如 A201"></td>
                    <td><input name="late_date[]" type="date" value="<?php echo h($today); ?>"></td>
                    <td>
                        <select name="is_not_return[]">
                            <option value="否">否</option>
                            <option value="是">是</option>
                        </select>
                    </td>
                    <td><input name="remark[]" placeholder="可选"></td>
                    <td><button class="btn btn-danger" type="button" onclick="removeRow(this)">删除行</button></td>
                </tr>
                </tbody>
            </table>

            <div class="btn-row" style="margin-top:12px;">
                <button class="btn btn-muted" type="button" id="addRowBtn">新增一行</button>
                <button class="btn btn-primary" type="submit">提交批量登记</button>
                <a class="btn btn-muted" href="wangui3.php">查看记录</a>
            </div>
        </form>
    </section>
</div>

<script>
function removeRow(btn) {
    var body = document.getElementById('batchBody');
    if (body.rows.length <= 1) {
        return;
    }
    btn.closest('tr').remove();
}

(function () {
    var addBtn = document.getElementById('addRowBtn');
    var body = document.getElementById('batchBody');
    addBtn.addEventListener('click', function () {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input name="student_id[]" placeholder="例如 2025001"></td>' +
            '<td><input name="student_name[]" placeholder="例如 张三"></td>' +
            '<td><input name="dorm_no[]" placeholder="例如 A201"></td>' +
            '<td><input name="late_date[]" type="date"></td>' +
            '<td><select name="is_not_return[]"><option value="否">否</option><option value="是">是</option></select></td>' +
            '<td><input name="remark[]" placeholder="可选"></td>' +
            '<td><button class="btn btn-danger" type="button" onclick="removeRow(this)">删除行</button></td>';
        body.appendChild(tr);
    });
})();
</script>
</body>
</html>
