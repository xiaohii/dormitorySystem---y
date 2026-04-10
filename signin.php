<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login();

ensure_system_schema($conn);

$studentOnly = is_student_user();
$sessionStudentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : '';
$signinStart = system_setting($conn, 'dorm_checkin_start', '21:00');
$signinEnd = system_setting($conn, 'dorm_checkin_end', '23:30');

$filterStudentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
if ($studentOnly) {
    $filterStudentId = $sessionStudentId;
}
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

$conditions = array();
if ($filterStudentId !== '') {
    $studentIdSafe = $conn->real_escape_string($filterStudentId);
    $conditions[] = "`student_id` = '{$studentIdSafe}'";
}
if ($filterDate !== '') {
    $dateSafe = $conn->real_escape_string($filterDate);
    $conditions[] = "DATE(`sign_time`) = '{$dateSafe}'";
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

$listSql = "SELECT `student_id`, `student_name`, `dorm_no`, `sign_time`, `location_text`, `operator_name` FROM `signin_records` {$whereSql} ORDER BY `record_id` DESC LIMIT 30";
$listResult = $conn->query($listSql);

$studentOptions = array();
if (can_manage_records()) {
    $studentRes = $conn->query('SELECT `id`, `user` FROM `student` ORDER BY `id` ASC');
    if ($studentRes) {
        while ($stu = $studentRes->fetch_assoc()) {
            $studentOptions[] = $stu;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>扫码签到</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>扫码签到</h2>
        <div class="note">支持扫码签到，系统自动记录签到时间与位置信息；也可手动输入学号签到。当前宿舍签到时间段：<?php echo h($signinStart); ?> - <?php echo h($signinEnd); ?>。</div>

        <div class="qr-layout">
            <div class="panel" style="margin:0;">
                <h3>生成签到二维码</h3>
                <div class="field">
                    <label for="qrStudentId">学号</label>
                    <?php if (can_manage_records()): ?>
                        <select id="qrStudentId">
                            <option value="">请选择学生</option>
                            <?php foreach ($studentOptions as $stu): ?>
                                <option value="<?php echo h($stu['id']); ?>"><?php echo h($stu['id'] . ' - ' . $stu['user']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="qrStudentId" value="<?php echo h($sessionStudentId); ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                    <button class="btn btn-primary" type="button" id="makeQrBtn">生成二维码</button>
                </div>
                <div id="qrcode" style="margin-top:14px; min-height:160px;"></div>
            </div>

            <div class="panel" style="margin:0;">
                <h3>扫码或手动签到</h3>
                <div id="qr-reader"></div>
                <div class="btn-row" style="margin-top:10px;">
                    <button class="btn btn-primary" type="button" id="startScanBtn">开始扫描</button>
                    <button class="btn btn-muted" type="button" id="stopScanBtn">停止扫描</button>
                </div>

                <div class="field" style="margin-top:14px;">
                    <label for="manualStudentId">手动签到学号</label>
                    <input id="manualStudentId" placeholder="请输入学号" value="<?php echo $studentOnly ? h($sessionStudentId) : ''; ?>" <?php echo $studentOnly ? 'readonly' : ''; ?>>
                </div>
                <div class="btn-row" style="margin-top:10px;">
                    <button class="btn btn-primary" type="button" id="manualSignBtn">手动签到</button>
                </div>

                <div class="small-muted" id="locationText" style="margin-top:10px;">定位状态：未获取</div>
                <div id="signMsg" class="note" style="display:none; margin-top:10px;"></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>最近签到记录</h3>

        <form method="get" action="signin.php" class="form-grid" style="margin-bottom:10px;">
            <?php if (can_manage_records()): ?>
            <div class="field">
                <label for="student_id">学号</label>
                <input id="student_id" name="student_id" value="<?php echo h($filterStudentId); ?>" placeholder="精确匹配">
            </div>
            <?php endif; ?>
            <div class="field">
                <label for="date">日期</label>
                <input id="date" type="date" name="date" value="<?php echo h($filterDate); ?>">
            </div>
            <div class="btn-row" style="align-items:flex-end;">
                <button class="btn btn-primary" type="submit">筛选</button>
                <a class="btn btn-muted" href="signin.php">重置</a>
            </div>
        </form>

        <table class="data-table">
            <thead>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <th>宿舍号</th>
                <th>签到时间</th>
                <th>位置</th>
                <?php if (can_manage_records()): ?>
                    <th>操作人</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if ($listResult && $listResult->num_rows > 0): ?>
                <?php while ($row = $listResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td><?php echo h($row['student_name']); ?></td>
                        <td><?php echo h($row['dorm_no']); ?></td>
                        <td><?php echo h($row['sign_time']); ?></td>
                        <td><?php echo h($row['location_text']); ?></td>
                        <?php if (can_manage_records()): ?>
                            <td><?php echo h($row['operator_name']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo can_manage_records() ? '6' : '5'; ?>">暂无签到记录。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    var qrContainer = document.getElementById('qrcode');
    var qrStudentInput = document.getElementById('qrStudentId');
    var makeQrBtn = document.getElementById('makeQrBtn');
    var manualStudentInput = document.getElementById('manualStudentId');
    var manualSignBtn = document.getElementById('manualSignBtn');
    var signMsg = document.getElementById('signMsg');
    var locationTextDom = document.getElementById('locationText');
    var startScanBtn = document.getElementById('startScanBtn');
    var stopScanBtn = document.getElementById('stopScanBtn');

    var locationCache = {
        location_text: '',
        latitude: '',
        longitude: ''
    };

    var html5QrCode = new Html5Qrcode('qr-reader');
    var scanning = false;

    function showMsg(text, isError) {
        signMsg.style.display = 'block';
        signMsg.className = isError ? 'alert' : 'note';
        signMsg.textContent = text;
    }

    function parseStudentId(raw) {
        if (!raw) return '';
        var text = String(raw).trim();
        if (!text) return '';

        try {
            var parsed = JSON.parse(text);
            if (parsed && parsed.student_id) {
                return String(parsed.student_id).trim();
            }
        } catch (e) {}

        if (text.indexOf('student_id=') !== -1) {
            var match = text.match(/[?&]student_id=([^&]+)/);
            if (match && match[1]) {
                return decodeURIComponent(match[1]);
            }
        }

        return text;
    }

    function fetchLocation(callback) {
        if (!navigator.geolocation) {
            locationTextDom.textContent = '定位状态：当前浏览器不支持定位。';
            callback();
            return;
        }

        navigator.geolocation.getCurrentPosition(function (position) {
            locationCache.latitude = String(position.coords.latitude);
            locationCache.longitude = String(position.coords.longitude);
            locationCache.location_text = '经度 ' + position.coords.longitude.toFixed(6) + '，纬度 ' + position.coords.latitude.toFixed(6);
            locationTextDom.textContent = '定位状态：' + locationCache.location_text;
            callback();
        }, function () {
            locationCache.location_text = '定位失败或超时';
            locationTextDom.textContent = '定位状态：' + locationCache.location_text;
            callback();
        }, {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 10000
        });
    }

    function submitSignin(studentId) {
        studentId = String(studentId || '').trim();
        if (!studentId) {
            showMsg('签到失败：学号不能为空。', true);
            return;
        }

        fetchLocation(function () {
            var payload = {
                student_id: studentId,
                location_text: locationCache.location_text,
                latitude: locationCache.latitude,
                longitude: locationCache.longitude
            };

            fetch('signin_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (data.ok) {
                    showMsg('签到成功：' + data.data.student_name + '（' + data.data.student_id + '）', false);
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } else {
                    showMsg(data.message || '签到失败。', true);
                }
            }).catch(function () {
                showMsg('网络异常，签到失败。', true);
            });
        });
    }

    makeQrBtn.addEventListener('click', function () {
        var studentId = qrStudentInput.value.trim();
        if (!studentId) {
            showMsg('请先输入或选择学号。', true);
            return;
        }
        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
            text: studentId,
            width: 170,
            height: 170
        });
    });

    manualSignBtn.addEventListener('click', function () {
        submitSignin(manualStudentInput.value);
    });

    startScanBtn.addEventListener('click', function () {
        if (scanning) return;

        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 230, height: 230 } },
            function (decodedText) {
                var id = parseStudentId(decodedText);
                if (id) {
                    submitSignin(id);
                    html5QrCode.stop();
                    scanning = false;
                }
            },
            function () {}
        ).then(function () {
            scanning = true;
        }).catch(function () {
            showMsg('无法打开摄像头，请检查浏览器权限。', true);
        });
    });

    stopScanBtn.addEventListener('click', function () {
        if (!scanning) return;
        html5QrCode.stop().then(function () {
            scanning = false;
        }).catch(function () {
            scanning = false;
        });
    });
})();
</script>
</body>
</html>
