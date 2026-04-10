<?php
require_once __DIR__ . '/auth.php';

function ensure_system_schema($conn)
{
    static $schemaReady = false;

    if ($schemaReady) {
        return;
    }

    $sqlList = array(
        "CREATE TABLE IF NOT EXISTS `signin_records` (
            `record_id` INT NOT NULL AUTO_INCREMENT,
            `student_id` VARCHAR(20) NOT NULL,
            `student_name` VARCHAR(50) NOT NULL,
            `dorm_no` VARCHAR(30) DEFAULT '',
            `sign_time` DATETIME NOT NULL,
            `location_text` VARCHAR(255) DEFAULT '',
            `latitude` VARCHAR(30) DEFAULT '',
            `longitude` VARCHAR(30) DEFAULT '',
            `operator_name` VARCHAR(50) DEFAULT '',
            `operator_role` VARCHAR(20) DEFAULT '',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`record_id`),
            KEY `idx_student_id` (`student_id`),
            KEY `idx_sign_time` (`sign_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `late_return_records` (
            `record_id` INT NOT NULL AUTO_INCREMENT,
            `student_id` VARCHAR(20) NOT NULL,
            `student_name` VARCHAR(50) NOT NULL,
            `dorm_no` VARCHAR(30) DEFAULT '',
            `late_date` DATE NOT NULL,
            `is_not_return` VARCHAR(10) NOT NULL,
            `remark` VARCHAR(255) DEFAULT '',
            `created_by` VARCHAR(50) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`record_id`),
            KEY `idx_student_id` (`student_id`),
            KEY `idx_late_date` (`late_date`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `attendance_records` (
            `record_id` INT NOT NULL AUTO_INCREMENT,
            `student_id` VARCHAR(20) NOT NULL,
            `student_name` VARCHAR(50) NOT NULL,
            `class_name` VARCHAR(50) DEFAULT '',
            `dorm_no` VARCHAR(30) DEFAULT '',
            `attendance_type` VARCHAR(20) NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `session_name` VARCHAR(50) DEFAULT '',
            `remark` VARCHAR(255) DEFAULT '',
            `created_by` VARCHAR(50) DEFAULT '',
            `updated_by` VARCHAR(50) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`record_id`),
            KEY `idx_student_date` (`student_id`, `attendance_date`),
            KEY `idx_type_date` (`attendance_type`, `attendance_date`),
            KEY `idx_class_name` (`class_name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `class_signin_sessions` (
            `session_id` INT NOT NULL AUTO_INCREMENT,
            `class_name` VARCHAR(50) NOT NULL,
            `session_name` VARCHAR(50) NOT NULL,
            `sign_date` DATE NOT NULL,
            `sign_start` DATETIME NOT NULL,
            `sign_end` DATETIME NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT '进行中',
            `created_by` VARCHAR(50) DEFAULT '',
            `created_role` VARCHAR(20) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`session_id`),
            KEY `idx_class_date` (`class_name`, `sign_date`),
            KEY `idx_status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `leave_records` (
            `leave_id` INT NOT NULL AUTO_INCREMENT,
            `student_id` VARCHAR(20) NOT NULL,
            `student_name` VARCHAR(50) NOT NULL,
            `class_name` VARCHAR(50) NOT NULL,
            `leave_type` VARCHAR(20) NOT NULL,
            `start_time` DATETIME NOT NULL,
            `end_time` DATETIME NOT NULL,
            `reason` VARCHAR(255) DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '待审批',
            `reviewer_name` VARCHAR(50) DEFAULT '',
            `review_comment` VARCHAR(255) DEFAULT '',
            `review_time` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`leave_id`),
            KEY `idx_student` (`student_id`),
            KEY `idx_class_status` (`class_name`, `status`),
            KEY `idx_start_time` (`start_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `alert_notifications` (
            `alert_id` INT NOT NULL AUTO_INCREMENT,
            `alert_key` VARCHAR(120) NOT NULL,
            `student_id` VARCHAR(20) NOT NULL,
            `student_name` VARCHAR(50) NOT NULL,
            `alert_type` VARCHAR(30) NOT NULL,
            `alert_level` VARCHAR(20) NOT NULL,
            `message` VARCHAR(255) NOT NULL,
            `notify_channels` VARCHAR(100) DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT '待处理',
            `created_at` DATETIME NOT NULL,
            `resolved_at` DATETIME NULL DEFAULT NULL,
            `resolved_by` VARCHAR(50) DEFAULT '',
            PRIMARY KEY (`alert_id`),
            UNIQUE KEY `uniq_alert_key` (`alert_key`),
            KEY `idx_alert_status` (`status`),
            KEY `idx_alert_student` (`student_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `system_users` (
            `user_id` INT NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(100) NOT NULL,
            `role` VARCHAR(20) NOT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`user_id`),
            UNIQUE KEY `uniq_username` (`username`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `system_settings` (
            `setting_key` VARCHAR(60) NOT NULL,
            `setting_value` VARCHAR(255) NOT NULL,
            `updated_by` VARCHAR(50) DEFAULT '',
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `operation_logs` (
            `log_id` INT NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) DEFAULT '',
            `role` VARCHAR(20) DEFAULT '',
            `action_type` VARCHAR(50) NOT NULL,
            `action_detail` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`log_id`),
            KEY `idx_action_type` (`action_type`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8"
    );

    foreach ($sqlList as $sql) {
        $conn->query($sql);
    }

    ensure_student_extra_columns($conn);
    seed_system_users($conn);
    seed_system_settings($conn);
    $schemaReady = true;
}

function ensure_student_extra_columns($conn)
{
    $columns = array(
        'academy' => "ALTER TABLE `student` ADD COLUMN `academy` VARCHAR(50) DEFAULT ''",
        'major' => "ALTER TABLE `student` ADD COLUMN `major` VARCHAR(50) DEFAULT ''"
    );

    foreach ($columns as $columnName => $alterSql) {
        $checkSql = "SHOW COLUMNS FROM `student` LIKE '" . $conn->real_escape_string($columnName) . "'";
        $result = $conn->query($checkSql);
        if ($result && $result->num_rows > 0) {
            continue;
        }

        $conn->query($alterSql);
    }
}

function seed_system_users($conn)
{
    $count = 0;
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM `system_users`");
    if ($countResult && $row = $countResult->fetch_assoc()) {
        $count = (int) $row['total'];
    }

    if ($count > 0) {
        $teacherExists = false;
        $teacherResult = $conn->query("SELECT `user_id` FROM `system_users` WHERE `username` = 'teacher' LIMIT 1");
        if ($teacherResult && $teacherResult->num_rows > 0) {
            $teacherExists = true;
        }

        if (!$teacherExists) {
            $insertTeacherStmt = $conn->prepare("INSERT INTO `system_users` (`username`, `password`, `role`, `enabled`, `created_at`, `updated_at`) VALUES ('teacher', 'teacher', 'teacher', 1, NOW(), NOW())");
            if ($insertTeacherStmt) {
                $insertTeacherStmt->execute();
                $insertTeacherStmt->close();
            }
        }
        return;
    }

    $seedUsers = array();
    $legacyResult = $conn->query("SELECT `name`, `pwd` FROM `admin`");
    if ($legacyResult) {
        while ($row = $legacyResult->fetch_assoc()) {
            $username = isset($row['name']) ? trim($row['name']) : '';
            $password = isset($row['pwd']) ? trim($row['pwd']) : '';
            if ($username === '' || isset($seedUsers[$username])) {
                continue;
            }

            $seedUsers[$username] = array(
                'password' => $password,
                'role' => 'admin'
            );
        }
    }

    if (!isset($seedUsers['admin'])) {
        $seedUsers['admin'] = array('password' => 'admin', 'role' => 'admin');
    }
    if (!isset($seedUsers['dorm'])) {
        $seedUsers['dorm'] = array('password' => 'dorm', 'role' => 'dorm');
    }
    if (!isset($seedUsers['teacher'])) {
        $seedUsers['teacher'] = array('password' => 'teacher', 'role' => 'teacher');
    }

    $insertStmt = $conn->prepare('INSERT INTO `system_users` (`username`, `password`, `role`, `enabled`, `created_at`, `updated_at`) VALUES (?, ?, ?, 1, NOW(), NOW())');
    if (!$insertStmt) {
        return;
    }

    foreach ($seedUsers as $username => $userInfo) {
        $password = $userInfo['password'];
        $role = $userInfo['role'];
        $insertStmt->bind_param('sss', $username, $password, $role);
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function seed_system_settings($conn)
{
    $defaults = array(
        'signin_absence_days' => '3',
        'late_return_window_days' => '7',
        'late_return_threshold' => '3',
        'not_return_window_days' => '7',
        'not_return_threshold' => '1',
        'alert_auto_scan_enabled' => '1',
        'last_alert_scan_date' => '',
        'holiday_end_dates' => '',
        'dorm_checkin_start' => '21:00',
        'dorm_checkin_end' => '23:30',
        'class_attendance_default_session' => '第1-2节',
        'alert_notify_channels' => '系统消息,短信,邮件'
    );

    $checkStmt = $conn->prepare('SELECT `setting_key` FROM `system_settings` WHERE `setting_key` = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES (?, ?, ?, NOW())');
    if (!$checkStmt || !$insertStmt) {
        return;
    }

    foreach ($defaults as $settingKey => $settingValue) {
        $checkStmt->bind_param('s', $settingKey);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            continue;
        }

        $updatedBy = '系统初始化';
        $insertStmt->bind_param('sss', $settingKey, $settingValue, $updatedBy);
        $insertStmt->execute();
    }

    $checkStmt->close();
    $insertStmt->close();
}

function system_setting($conn, $key, $defaultValue = '')
{
    ensure_system_schema($conn);

    $stmt = $conn->prepare('SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = ? LIMIT 1');
    if (!$stmt) {
        return $defaultValue;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return $defaultValue;
    }

    $stmt->bind_result($settingValue);
    $stmt->fetch();
    $stmt->close();
    return $settingValue;
}

function system_setting_int($conn, $key, $defaultValue = 0)
{
    return (int) system_setting($conn, $key, (string) $defaultValue);
}

function save_system_setting($conn, $key, $value)
{
    ensure_system_schema($conn);

    $updatedBy = current_user_name();
    $stmt = $conn->prepare("INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $key, $value, $updatedBy);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function write_operation_log($conn, $actionType, $actionDetail)
{
    ensure_system_schema($conn);

    $username = current_user_name();
    $role = current_role();
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $stmt = $conn->prepare('INSERT INTO `operation_logs` (`username`, `role`, `action_type`, `action_detail`, `ip_address`, `created_at`) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssss', $username, $role, $actionType, $actionDetail, $ipAddress);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function authenticate_staff_user($conn, $username, $password, $role)
{
    ensure_system_schema($conn);

    $result = array(
        'ok' => false,
        'display_name' => $username,
        'message' => '账号或密码错误，请重试。'
    );

    $stmt = $conn->prepare('SELECT `username`, `password`, `role`, `enabled` FROM `system_users` WHERE `username` = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($dbUsername, $dbPassword, $dbRole, $dbEnabled);
            $stmt->fetch();
            $stmt->close();

            if ((int) $dbEnabled !== 1) {
                $result['message'] = '该账号已被停用，请联系管理员。';
                return $result;
            }

            if ($dbPassword !== $password) {
                return $result;
            }

            if ($dbRole !== $role) {
                $result['message'] = '所选角色与账号权限不匹配。';
                return $result;
            }

            $result['ok'] = true;
            $result['display_name'] = $dbUsername;
            $result['message'] = '';
            return $result;
        }

        $stmt->close();
    }

    if ($role === 'teacher') {
        return $result;
    }

    $legacyStmt = $conn->prepare('SELECT `name` FROM `admin` WHERE `name` = ? AND `pwd` = ? LIMIT 1');
    if ($legacyStmt) {
        $legacyStmt->bind_param('ss', $username, $password);
        $legacyStmt->execute();
        $legacyStmt->store_result();
        if ($legacyStmt->num_rows === 1) {
            $legacyStmt->bind_result($legacyName);
            $legacyStmt->fetch();
            $legacyStmt->close();

            $result['ok'] = true;
            $result['display_name'] = $legacyName;
            $result['message'] = '';
            return $result;
        }
        $legacyStmt->close();
    }

    return $result;
}

function fetch_student_profile($conn, $studentId)
{
    $stmt = $conn->prepare('SELECT `id`, `user`, `gender`, `Dno`, `phone`, `class` FROM `student` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $stmt->bind_result($dbId, $dbUser, $dbGender, $dbDormNo, $dbPhone, $dbClass);
    $stmt->fetch();
    $stmt->close();
    return array(
        'id' => $dbId,
        'user' => $dbUser,
        'gender' => $dbGender,
        'Dno' => $dbDormNo,
        'phone' => $dbPhone,
        'class' => $dbClass
    );
}

function attendance_type_label($type)
{
    return $type === 'class' ? '上课考勤' : '宿舍考勤';
}

function attendance_status_options($type)
{
    if ($type === 'class') {
        return array('出勤', '迟到', '缺勤', '请假');
    }

    return array('已归寝', '晚归', '未归', '请假');
}

function attendance_present_statuses($type)
{
    if ($type === 'class') {
        return array('出勤', '迟到');
    }

    return array('已归寝', '晚归');
}

function approved_leave_exists_sql($attendanceAlias)
{
    return "({$attendanceAlias}.`attendance_type` = 'class' AND EXISTS (
        SELECT 1 FROM `leave_records` lr
        WHERE lr.`student_id` COLLATE utf8_general_ci = {$attendanceAlias}.`student_id` COLLATE utf8_general_ci
          AND lr.`class_name` COLLATE utf8_general_ci = {$attendanceAlias}.`class_name` COLLATE utf8_general_ci
          AND lr.`status` COLLATE utf8_general_ci = '已通过' COLLATE utf8_general_ci
          AND {$attendanceAlias}.`attendance_date` BETWEEN DATE(lr.`start_time`) AND DATE(lr.`end_time`)
    ))";
}

function has_approved_leave($conn, $studentId, $className, $attendanceDate)
{
    ensure_system_schema($conn);

    $stmt = $conn->prepare("SELECT `leave_id` FROM `leave_records` WHERE `student_id` = ? AND `class_name` = ? AND `status` = '已通过' AND ? BETWEEN DATE(`start_time`) AND DATE(`end_time`) LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $studentId, $className, $attendanceDate);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function sync_leave_to_attendance($conn, $studentId, $className, $startDate, $endDate, $operatorName)
{
    ensure_system_schema($conn);

    $remarkNote = '审批通过自动转请假';
    $stmt = $conn->prepare("UPDATE `attendance_records` SET `status` = '请假', `remark` = IF(`remark` = '', ?, CONCAT(`remark`, '；', ?)), `updated_by` = ?, `updated_at` = NOW() WHERE `attendance_type` = 'class' AND `student_id` = ? AND `class_name` = ? AND `attendance_date` >= ? AND `attendance_date` <= ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssssss', $remarkNote, $remarkNote, $operatorName, $studentId, $className, $startDate, $endDate);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function normalize_not_return_value($value)
{
    if ($value === '是' || $value === 'Yes') {
        return '是';
    }

    return '否';
}

function create_alert_notification($conn, $alertKey, $studentId, $studentName, $alertType, $alertLevel, $message, $notifyChannels)
{
    ensure_system_schema($conn);

    $stmt = $conn->prepare("INSERT INTO `alert_notifications` (`alert_key`, `student_id`, `student_name`, `alert_type`, `alert_level`, `message`, `notify_channels`, `status`, `created_at`, `resolved_by`) VALUES (?, ?, ?, ?, ?, ?, ?, '待处理', NOW(), '') ON DUPLICATE KEY UPDATE `message` = VALUES(`message`), `notify_channels` = VALUES(`notify_channels`), `created_at` = NOW(), `status` = IF(`status` = '已处理', '已处理', '待处理')");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssssss', $alertKey, $studentId, $studentName, $alertType, $alertLevel, $message, $notifyChannels);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function parse_holiday_end_dates($rawValue)
{
    $text = trim((string) $rawValue);
    if ($text === '') {
        return array();
    }

    $text = str_replace(array('，', ';', '；', "\r", "\n", "\t"), ',', $text);
    $parts = explode(',', $text);
    $dates = array();
    foreach ($parts as $part) {
        $dateText = trim($part);
        if ($dateText === '') {
            continue;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateText)) {
            continue;
        }

        if (!in_array($dateText, $dates, true)) {
            $dates[] = $dateText;
        }
    }

    return $dates;
}

function evaluate_holiday_return_alerts($conn, $scanDate, $channels)
{
    $generatedCount = 0;
    $holidayEndDates = parse_holiday_end_dates(system_setting($conn, 'holiday_end_dates', ''));
    if (empty($holidayEndDates)) {
        return 0;
    }

    $targetReturnDates = array();
    foreach ($holidayEndDates as $endDate) {
        $returnDate = date('Y-m-d', strtotime($endDate . ' +1 day'));
        if ($returnDate === $scanDate && !in_array($returnDate, $targetReturnDates, true)) {
            $targetReturnDates[] = $returnDate;
        }
    }

    if (empty($targetReturnDates)) {
        return 0;
    }

    $students = $conn->query("SELECT `id`, `user`, `class` FROM `student` ORDER BY `id` ASC");
    if (!$students) {
        return 0;
    }

    while ($student = $students->fetch_assoc()) {
        $studentId = $student['id'];
        $studentName = $student['user'];
        $className = isset($student['class']) ? $student['class'] : '';
        $studentIdSafe = $conn->real_escape_string($studentId);

        foreach ($targetReturnDates as $returnDate) {
            $returnDateSafe = $conn->real_escape_string($returnDate);

            $leaveSql = "SELECT COUNT(*) AS total FROM `leave_records` WHERE `student_id` = '{$studentIdSafe}' AND `status` = '已通过' AND '{$returnDateSafe}' BETWEEN DATE(`start_time`) AND DATE(`end_time`)";
            $leaveResult = $conn->query($leaveSql);
            $leaveCount = 0;
            if ($leaveResult && $row = $leaveResult->fetch_assoc()) {
                $leaveCount = (int) $row['total'];
            }
            if ($leaveCount > 0) {
                continue;
            }

            $signinSql = "SELECT COUNT(*) AS total FROM `signin_records` WHERE `student_id` = '{$studentIdSafe}' AND DATE(`sign_time`) = '{$returnDateSafe}'";
            $signinResult = $conn->query($signinSql);
            $signinCount = 0;
            if ($signinResult && $row = $signinResult->fetch_assoc()) {
                $signinCount = (int) $row['total'];
            }
            if ($signinCount > 0) {
                continue;
            }

            $alertKey = 'holiday_return|' . $studentId . '|' . date('Ymd', strtotime($returnDate));
            $message = '学生 ' . $studentName . '（' . $className . '）在节假日后首日 ' . $returnDate . ' 未返校签到，请及时核查。';
            if (create_alert_notification($conn, $alertKey, $studentId, $studentName, '节后未返校', '高', $message, $channels)) {
                $generatedCount++;
            }
        }
    }

    return $generatedCount;
}

function evaluate_alerts($conn, $scanDate = null)
{
    ensure_system_schema($conn);

    if ($scanDate === null || trim($scanDate) === '') {
        $scanDate = date('Y-m-d');
    }

    $generatedCount = 0;
    $absenceDays = max(1, system_setting_int($conn, 'signin_absence_days', 3));
    $lateWindowDays = max(1, system_setting_int($conn, 'late_return_window_days', 7));
    $lateThreshold = max(1, system_setting_int($conn, 'late_return_threshold', 3));
    $notReturnWindowDays = max(1, system_setting_int($conn, 'not_return_window_days', 7));
    $notReturnThreshold = max(1, system_setting_int($conn, 'not_return_threshold', 1));
    $channels = system_setting($conn, 'alert_notify_channels', '系统消息,短信,邮件');
    $scanDateToken = date('Ymd', strtotime($scanDate));
    $students = $conn->query("SELECT `id`, `user` FROM `student` ORDER BY `id` ASC");

    if (!$students) {
        return 0;
    }

    while ($student = $students->fetch_assoc()) {
        $studentId = $student['id'];
        $studentName = $student['user'];

        $absenceStreak = 0;
        for ($i = 0; $i < $absenceDays; $i++) {
            $checkDate = date('Y-m-d', strtotime($scanDate . ' -' . $i . ' day'));
            $studentIdSafe = $conn->real_escape_string($studentId);
            $checkDateSafe = $conn->real_escape_string($checkDate);
            $sql = "SELECT COUNT(*) AS total FROM `signin_records` WHERE `student_id` = '{$studentIdSafe}' AND DATE(`sign_time`) = '{$checkDateSafe}'";
            $result = $conn->query($sql);
            $count = 0;
            if ($result && $row = $result->fetch_assoc()) {
                $count = (int) $row['total'];
            }

            if ($count > 0) {
                break;
            }

            $absenceStreak++;
        }

        if ($absenceStreak >= $absenceDays) {
            $message = '学生 ' . $studentName . ' 已连续 ' . $absenceStreak . ' 天未签到，请及时核查。';
            $alertKey = 'signin_absence|' . $studentId . '|' . $scanDateToken;
            if (create_alert_notification($conn, $alertKey, $studentId, $studentName, '连续未签到', '高', $message, $channels)) {
                $generatedCount++;
            }
        }

        $lateStartDate = date('Y-m-d', strtotime($scanDate . ' -' . ($lateWindowDays - 1) . ' day'));
        $studentIdSafe = $conn->real_escape_string($studentId);
        $lateStartSafe = $conn->real_escape_string($lateStartDate);
        $scanDateSafe = $conn->real_escape_string($scanDate);
        $lateSql = "SELECT COUNT(*) AS total FROM `late_return_records` WHERE `student_id` = '{$studentIdSafe}' AND `late_date` >= '{$lateStartSafe}' AND `late_date` <= '{$scanDateSafe}'";
        $lateResult = $conn->query($lateSql);
        $lateCount = 0;
        if ($lateResult && $row = $lateResult->fetch_assoc()) {
            $lateCount = (int) $row['total'];
        }

        if ($lateCount >= $lateThreshold) {
            $message = '学生 ' . $studentName . ' 在最近 ' . $lateWindowDays . ' 天内晚归 ' . $lateCount . ' 次，请重点关注。';
            $alertKey = 'late_return|' . $studentId . '|' . $scanDateToken;
            if (create_alert_notification($conn, $alertKey, $studentId, $studentName, '频繁晚归', '中', $message, $channels)) {
                $generatedCount++;
            }
        }

        $notReturnStartDate = date('Y-m-d', strtotime($scanDate . ' -' . ($notReturnWindowDays - 1) . ' day'));
        $notReturnStartSafe = $conn->real_escape_string($notReturnStartDate);
        $notReturnSql = "SELECT COUNT(*) AS total FROM `late_return_records` WHERE `student_id` = '{$studentIdSafe}' AND `late_date` >= '{$notReturnStartSafe}' AND `late_date` <= '{$scanDateSafe}' AND (`is_not_return` = '是' OR `is_not_return` = 'Yes')";
        $notReturnResult = $conn->query($notReturnSql);
        $notReturnCount = 0;
        if ($notReturnResult && $row = $notReturnResult->fetch_assoc()) {
            $notReturnCount = (int) $row['total'];
        }

        if ($notReturnCount >= $notReturnThreshold) {
            $message = '学生 ' . $studentName . ' 在最近 ' . $notReturnWindowDays . ' 天内出现未归 ' . $notReturnCount . ' 次，请立即处理。';
            $alertKey = 'not_return|' . $studentId . '|' . $scanDateToken;
            if (create_alert_notification($conn, $alertKey, $studentId, $studentName, '未归预警', '高', $message, $channels)) {
                $generatedCount++;
            }
        }
    }

    $generatedCount += evaluate_holiday_return_alerts($conn, $scanDate, $channels);
    return $generatedCount;
}

function run_alert_scan_job($conn, $force = false)
{
    ensure_system_schema($conn);

    $today = date('Y-m-d');
    $autoEnabled = system_setting($conn, 'alert_auto_scan_enabled', '1');
    $lastScanDate = system_setting($conn, 'last_alert_scan_date', '');

    if (!$force && $autoEnabled !== '1') {
        return array(
            'ok' => true,
            'skipped' => true,
            'reason' => 'auto_disabled',
            'generated' => 0,
            'scan_date' => $today
        );
    }

    if (!$force && $lastScanDate === $today) {
        return array(
            'ok' => true,
            'skipped' => true,
            'reason' => 'already_scanned_today',
            'generated' => 0,
            'scan_date' => $today
        );
    }

    $generated = evaluate_alerts($conn, $today);
    save_system_setting($conn, 'last_alert_scan_date', $today);

    return array(
        'ok' => true,
        'skipped' => false,
        'reason' => $force ? 'manual' : 'daily_job',
        'generated' => $generated,
        'scan_date' => $today
    );
}

function backup_table_names()
{
    return array(
        'student',
        'signin_records',
        'late_return_records',
        'attendance_records',
        'class_signin_sessions',
        'leave_records',
        'alert_notifications',
        'system_users',
        'system_settings',
        'operation_logs'
    );
}
?>
