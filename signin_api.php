<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

ensure_system_schema($conn);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$studentId = isset($data['student_id']) ? trim($data['student_id']) : '';
$locationText = isset($data['location_text']) ? trim($data['location_text']) : '';
$latitude = isset($data['latitude']) ? trim($data['latitude']) : '';
$longitude = isset($data['longitude']) ? trim($data['longitude']) : '';

if ($studentId === '') {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'message' => '签到失败：学号不能为空。'));
    exit;
}

if (is_student_user()) {
    $sessionStudentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : '';
    if ($sessionStudentId !== '' && $studentId !== $sessionStudentId) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'message' => '学生账号只能为本人签到。'));
        exit;
    }
}

$studentStmt = $conn->prepare('SELECT `id`, `user`, `Dno` FROM `student` WHERE `id` = ? LIMIT 1');
if (!$studentStmt) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'message' => '查询学生信息失败。'));
    exit;
}

$studentStmt->bind_param('s', $studentId);
$studentStmt->execute();
$studentStmt->store_result();
if ($studentStmt->num_rows === 0) {
    $studentStmt->close();
    http_response_code(404);
    echo json_encode(array('ok' => false, 'message' => '未找到该学生。'));
    exit;
}
$studentStmt->bind_result($dbStudentId, $dbStudentName, $dbDormNo);
$studentStmt->fetch();
$studentStmt->close();

$signTime = date('Y-m-d H:i:s');
$studentName = $dbStudentName;
$dormNo = $dbDormNo;
$operatorName = current_user_name();
$operatorRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$insertStmt = $conn->prepare('INSERT INTO `signin_records` (`student_id`, `student_name`, `dorm_no`, `sign_time`, `location_text`, `latitude`, `longitude`, `operator_name`, `operator_role`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$insertStmt) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'message' => '写入签到记录失败。'));
    exit;
}

$insertStmt->bind_param('sssssssss', $studentId, $studentName, $dormNo, $signTime, $locationText, $latitude, $longitude, $operatorName, $operatorRole);
$ok = $insertStmt->execute();
$insertStmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'message' => '签到失败，请稍后重试。'));
    exit;
}

write_operation_log($conn, '扫码签到', '为学号 ' . $studentId . ' 写入签到记录');

echo json_encode(array(
    'ok' => true,
    'message' => '签到成功',
    'data' => array(
        'student_id' => $studentId,
        'student_name' => $studentName,
        'dorm_no' => $dormNo,
        'sign_time' => $signTime,
        'location_text' => $locationText
    )
));
?>
