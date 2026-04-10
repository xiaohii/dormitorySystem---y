<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_core.php';
require_login(array('admin'));

ensure_system_schema($conn);

function backup_table_rows($conn, $tableName)
{
    $rows = array();
    $result = $conn->query("SELECT * FROM `{$tableName}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function restore_backup_tables($conn, $tables)
{
    $restoredTables = 0;
    foreach (backup_table_names() as $tableName) {
        if (!isset($tables[$tableName]) || !is_array($tables[$tableName])) {
            continue;
        }

        $conn->query("DELETE FROM `{$tableName}`");
        foreach ($tables[$tableName] as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }

            $columns = array();
            $values = array();
            foreach ($row as $column => $value) {
                $safeColumn = str_replace('`', '', $column);
                $columns[] = '`' . $safeColumn . '`';
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $conn->real_escape_string((string) $value) . "'";
                }
            }

            $sql = "INSERT INTO `{$tableName}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
            $conn->query($sql);
        }

        $restoredTables++;
    }

    return $restoredTables;
}

if (isset($_GET['download']) && $_GET['download'] === 'backup') {
    $payload = array(
        'meta' => array(
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => current_user_name(),
            'version' => '1.0'
        ),
        'tables' => array()
    );

    foreach (backup_table_names() as $tableName) {
        $payload['tables'][$tableName] = backup_table_rows($conn, $tableName);
    }

    write_operation_log($conn, '数据备份', '导出系统备份文件');

    $fileName = 'dormitory_backup_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . rawurlencode($fileName));
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'change_password') {
        $oldPassword = isset($_POST['old_password']) ? trim($_POST['old_password']) : '';
        $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        $currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : '';

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            flash_set('error', '请完整填写原密码、新密码和确认密码。');
            redirect_to('system.php');
        }
        if ($newPassword !== $confirmPassword) {
            flash_set('error', '两次输入的新密码不一致。');
            redirect_to('system.php');
        }

        $stmt = $conn->prepare('SELECT `password` FROM `system_users` WHERE `username` = ? LIMIT 1');
        if (!$stmt) {
            flash_set('error', '读取当前账号失败，请稍后重试。');
            redirect_to('system.php');
        }

        $stmt->bind_param('s', $currentUsername);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            flash_set('error', '当前账号不存在，无法修改密码。');
            redirect_to('system.php');
        }

        $stmt->bind_result($dbPassword);
        $stmt->fetch();
        $stmt->close();

        if ($dbPassword !== $oldPassword) {
            flash_set('error', '原密码输入错误。');
            redirect_to('system.php');
        }

        $updateStmt = $conn->prepare('UPDATE `system_users` SET `password` = ?, `updated_at` = NOW() WHERE `username` = ? LIMIT 1');
        if ($updateStmt) {
            $updateStmt->bind_param('ss', $newPassword, $currentUsername);
            $updateStmt->execute();
            $updateStmt->close();
        }
        $legacyUpdateStmt = $conn->prepare('UPDATE `admin` SET `pwd` = ? WHERE `name` = ?');
        if ($legacyUpdateStmt) {
            $legacyUpdateStmt->bind_param('ss', $newPassword, $currentUsername);
            $legacyUpdateStmt->execute();
            $legacyUpdateStmt->close();
        }

        write_operation_log($conn, '修改密码', '管理员修改了自己的登录密码');
        flash_set('success', '密码修改成功，请使用新密码重新登录。');
        redirect_to('system.php');
    }

    if ($action === 'save_settings') {
        $settingMap = array(
            'signin_absence_days',
            'late_return_window_days',
            'late_return_threshold',
            'not_return_window_days',
            'not_return_threshold',
            'alert_auto_scan_enabled',
            'holiday_end_dates',
            'dorm_checkin_start',
            'dorm_checkin_end',
            'class_attendance_default_session',
            'alert_notify_channels'
        );

        foreach ($settingMap as $settingKey) {
            $settingValue = isset($_POST[$settingKey]) ? trim($_POST[$settingKey]) : '';
            save_system_setting($conn, $settingKey, $settingValue);
        }

        write_operation_log($conn, '参数设置', '更新系统参数与预警阈值');
        flash_set('success', '系统参数已保存。');
        redirect_to('system.php');
    }

    if ($action === 'save_user') {
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'dorm';
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if ($username === '' || !in_array($role, array('admin', 'teacher', 'dorm'), true)) {
            flash_set('error', '请填写完整的账号信息，并选择有效角色。');
            redirect_to('system.php');
        }

        $existsStmt = $conn->prepare('SELECT `user_id` FROM `system_users` WHERE `username` = ? AND `user_id` <> ? LIMIT 1');
        if ($existsStmt) {
            $existsStmt->bind_param('si', $username, $userId);
            $existsStmt->execute();
            $existsStmt->store_result();
            if ($existsStmt->num_rows > 0) {
                $existsStmt->close();
                flash_set('error', '该账号名已存在，请更换后重试。');
                redirect_to('system.php');
            }
            $existsStmt->close();
        }

        if ($userId > 0) {
            $fetchStmt = $conn->prepare('SELECT `username`, `password` FROM `system_users` WHERE `user_id` = ? LIMIT 1');
            if (!$fetchStmt) {
                flash_set('error', '读取账号信息失败。');
                redirect_to('system.php');
            }

            $fetchStmt->bind_param('i', $userId);
            $fetchStmt->execute();
            $fetchStmt->store_result();
            if ($fetchStmt->num_rows === 0) {
                $fetchStmt->close();
                flash_set('error', '未找到需要修改的账号。');
                redirect_to('system.php');
            }

            $fetchStmt->bind_result($oldUsername, $oldPassword);
            $fetchStmt->fetch();
            $fetchStmt->close();

            if ($oldUsername === current_user_name() && $enabled !== 1) {
                flash_set('error', '不能停用当前正在登录的管理员账号。');
                redirect_to('system.php');
            }

            $finalPassword = $password !== '' ? $password : $oldPassword;
            $updateStmt = $conn->prepare('UPDATE `system_users` SET `username` = ?, `password` = ?, `role` = ?, `enabled` = ?, `updated_at` = NOW() WHERE `user_id` = ? LIMIT 1');
            if ($updateStmt) {
                $updateStmt->bind_param('sssii', $username, $finalPassword, $role, $enabled, $userId);
                $ok = $updateStmt->execute();
                $updateStmt->close();
                if ($ok) {
                    $deleteLegacyStmt = $conn->prepare('DELETE FROM `admin` WHERE `name` = ?');
                    if ($deleteLegacyStmt) {
                        $deleteLegacyStmt->bind_param('s', $oldUsername);
                        $deleteLegacyStmt->execute();
                        $deleteLegacyStmt->close();
                    }
                    $insertLegacyStmt = $conn->prepare('INSERT INTO `admin` (`name`, `pwd`) VALUES (?, ?)');
                    if ($insertLegacyStmt) {
                        $insertLegacyStmt->bind_param('ss', $username, $finalPassword);
                        $insertLegacyStmt->execute();
                        $insertLegacyStmt->close();
                    }
                    write_operation_log($conn, '账号管理', '更新后台账号：' . $username . '，角色：' . role_label($role));
                    flash_set('success', '后台账号已更新。');
                    redirect_to('system.php');
                }
            }

            flash_set('error', '更新后台账号失败，请稍后重试。');
            redirect_to('system.php');
        }

        if ($password === '') {
            flash_set('error', '新建账号时必须填写密码。');
            redirect_to('system.php');
        }

        $insertStmt = $conn->prepare('INSERT INTO `system_users` (`username`, `password`, `role`, `enabled`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, NOW(), NOW())');
        if ($insertStmt) {
            $insertStmt->bind_param('sssi', $username, $password, $role, $enabled);
            $ok = $insertStmt->execute();
            $insertStmt->close();
            if ($ok) {
                $deleteLegacyStmt = $conn->prepare('DELETE FROM `admin` WHERE `name` = ?');
                if ($deleteLegacyStmt) {
                    $deleteLegacyStmt->bind_param('s', $username);
                    $deleteLegacyStmt->execute();
                    $deleteLegacyStmt->close();
                }
                $insertLegacyStmt = $conn->prepare('INSERT INTO `admin` (`name`, `pwd`) VALUES (?, ?)');
                if ($insertLegacyStmt) {
                    $insertLegacyStmt->bind_param('ss', $username, $password);
                    $insertLegacyStmt->execute();
                    $insertLegacyStmt->close();
                }
                write_operation_log($conn, '账号管理', '新增后台账号：' . $username . '，角色：' . role_label($role));
                flash_set('success', '后台账号已创建。');
                redirect_to('system.php');
            }
        }

        flash_set('error', '创建后台账号失败，请稍后重试。');
        redirect_to('system.php');
    }

    if ($action === 'delete_user') {
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ($userId > 0) {
            $fetchStmt = $conn->prepare('SELECT `username` FROM `system_users` WHERE `user_id` = ? LIMIT 1');
            if ($fetchStmt) {
                $fetchStmt->bind_param('i', $userId);
                $fetchStmt->execute();
                $fetchStmt->store_result();
                if ($fetchStmt->num_rows === 1) {
                    $fetchStmt->bind_result($deleteUsername);
                    $fetchStmt->fetch();
                    $fetchStmt->close();

                    if ($deleteUsername === current_user_name()) {
                        flash_set('error', '不能删除当前正在登录的管理员账号。');
                        redirect_to('system.php');
                    }

                    $deleteStmt = $conn->prepare('DELETE FROM `system_users` WHERE `user_id` = ? LIMIT 1');
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $userId);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                    }
                    $deleteLegacyStmt = $conn->prepare('DELETE FROM `admin` WHERE `name` = ?');
                    if ($deleteLegacyStmt) {
                        $deleteLegacyStmt->bind_param('s', $deleteUsername);
                        $deleteLegacyStmt->execute();
                        $deleteLegacyStmt->close();
                    }
                    write_operation_log($conn, '账号管理', '删除后台账号：' . $deleteUsername);
                    flash_set('success', '后台账号已删除。');
                } else {
                    $fetchStmt->close();
                }
            }
        }

        redirect_to('system.php');
    }

    if ($action === 'restore_backup') {
        if (!isset($_FILES['backup_file']) || !isset($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['tmp_name'] === '') {
            flash_set('error', '请先选择备份文件后再恢复。');
            redirect_to('system.php');
        }

        $rawContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        $payload = json_decode($rawContent, true);
        if (!is_array($payload) || !isset($payload['tables']) || !is_array($payload['tables'])) {
            flash_set('error', '备份文件格式不正确，恢复失败。');
            redirect_to('system.php');
        }

        ensure_system_schema($conn);
        $restoredTables = restore_backup_tables($conn, $payload['tables']);
        ensure_system_schema($conn);
        write_operation_log($conn, '数据恢复', '恢复系统备份，共覆盖 ' . $restoredTables . ' 张表');
        flash_set('success', '数据恢复完成，共处理 ' . $restoredTables . ' 张表。');
        redirect_to('system.php');
    }
}

$editUserId = isset($_GET['edit_user_id']) ? (int) $_GET['edit_user_id'] : 0;
$editUser = array(
    'user_id' => 0,
    'username' => '',
    'role' => 'dorm',
    'enabled' => 1
);
if ($editUserId > 0) {
    $editStmt = $conn->prepare('SELECT `user_id`, `username`, `role`, `enabled` FROM `system_users` WHERE `user_id` = ? LIMIT 1');
    if ($editStmt) {
        $editStmt->bind_param('i', $editUserId);
        $editStmt->execute();
        $editStmt->store_result();
        if ($editStmt->num_rows === 1) {
            $editStmt->bind_result($dbUserId, $dbUsername, $dbRole, $dbEnabled);
            $editStmt->fetch();
            $editUser = array(
                'user_id' => $dbUserId,
                'username' => $dbUsername,
                'role' => $dbRole,
                'enabled' => (int) $dbEnabled
            );
        }
        $editStmt->close();
    }
}

$systemUsers = array();
$userResult = $conn->query('SELECT `user_id`, `username`, `role`, `enabled`, `created_at`, `updated_at` FROM `system_users` ORDER BY `user_id` DESC');
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $systemUsers[] = $row;
    }
}

$logRows = array();
$logResult = $conn->query('SELECT `log_id`, `username`, `role`, `action_type`, `action_detail`, `created_at` FROM `operation_logs` ORDER BY `log_id` DESC LIMIT 20');
if ($logResult) {
    while ($row = $logResult->fetch_assoc()) {
        $logRows[] = $row;
    }
}

$userCount = count($systemUsers);
$enabledCount = 0;
foreach ($systemUsers as $item) {
    if ((int) $item['enabled'] === 1) {
        $enabledCount++;
    }
}
$settingsCountResult = $conn->query('SELECT COUNT(*) AS total FROM `system_settings`');
$settingsCount = 0;
if ($settingsCountResult && $row = $settingsCountResult->fetch_assoc()) {
    $settingsCount = (int) $row['total'];
}

$settingValues = array(
    'signin_absence_days' => system_setting($conn, 'signin_absence_days', '3'),
    'late_return_window_days' => system_setting($conn, 'late_return_window_days', '7'),
    'late_return_threshold' => system_setting($conn, 'late_return_threshold', '3'),
    'not_return_window_days' => system_setting($conn, 'not_return_window_days', '7'),
    'not_return_threshold' => system_setting($conn, 'not_return_threshold', '1'),
    'alert_auto_scan_enabled' => system_setting($conn, 'alert_auto_scan_enabled', '1'),
    'holiday_end_dates' => system_setting($conn, 'holiday_end_dates', ''),
    'last_alert_scan_date' => system_setting($conn, 'last_alert_scan_date', ''),
    'dorm_checkin_start' => system_setting($conn, 'dorm_checkin_start', '21:00'),
    'dorm_checkin_end' => system_setting($conn, 'dorm_checkin_end', '23:30'),
    'class_attendance_default_session' => system_setting($conn, 'class_attendance_default_session', '第1-2节'),
    'alert_notify_channels' => system_setting($conn, 'alert_notify_channels', '系统消息,短信,邮件')
);

$successTip = flash_get('success');
$errorTip = flash_get('error');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统管理</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/header.php'; ?>

<div class="page-wrap">
    <section class="panel">
        <h2>系统管理</h2>
        <div class="note">管理员可在这里维护后台账号权限、修改密码、配置考勤阈值，并执行数据备份与恢复。</div>
        <?php if ($successTip !== ''): ?>
            <div class="note"><?php echo h($successTip); ?></div>
        <?php endif; ?>
        <?php if ($errorTip !== ''): ?>
            <div class="alert"><?php echo h($errorTip); ?></div>
        <?php endif; ?>

        <div class="stats-row stats-row-wide">
            <div class="stat-card">
                <div class="stat-title">后台账号总数</div>
                <div class="stat-value"><?php echo (int) $userCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">启用账号数</div>
                <div class="stat-value"><?php echo (int) $enabledCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">系统参数项</div>
                <div class="stat-value"><?php echo (int) $settingsCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">最近日志数</div>
                <div class="stat-value"><?php echo (int) count($logRows); ?></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="split-grid">
            <div>
                <h3>修改当前密码</h3>
                <form method="post" action="system.php" class="form-grid">
                    <input type="hidden" name="action" value="change_password">
                    <div class="field">
                        <label for="old_password">原密码</label>
                        <input id="old_password" type="password" name="old_password" required>
                    </div>
                    <div class="field">
                        <label for="new_password">新密码</label>
                        <input id="new_password" type="password" name="new_password" required>
                    </div>
                    <div class="field">
                        <label for="confirm_password">确认新密码</label>
                        <input id="confirm_password" type="password" name="confirm_password" required>
                    </div>
                    <div class="btn-row">
                        <button class="btn btn-primary" type="submit">更新密码</button>
                    </div>
                </form>
            </div>

            <div>
                <h3>数据备份与恢复</h3>
                <div class="note">备份会导出学生、考勤、签到、晚归、预警、账号、参数和日志数据。恢复会覆盖同名表中的现有数据。</div>
                <div class="btn-row" style="margin-bottom:12px;">
                    <a class="btn btn-primary" href="system.php?download=backup">导出备份文件</a>
                </div>
                <form method="post" action="system.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="restore_backup">
                    <div class="field">
                        <label for="backup_file">选择备份文件（JSON）</label>
                        <input id="backup_file" type="file" name="backup_file" accept=".json,application/json" required>
                    </div>
                    <div class="btn-row" style="margin-top:10px;">
                        <button class="btn btn-danger" type="submit" onclick="return confirm('恢复备份会覆盖当前系统数据，确认继续吗？');">恢复备份</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="panel">
        <h3>后台账号与权限</h3>
        <div class="note">支持管理员、任课教师、宿管三类后台角色。编辑账号时，密码留空表示保留原密码不变。</div>

        <form method="post" action="system.php" class="form-grid">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" value="<?php echo (int) $editUser['user_id']; ?>">
            <div class="field">
                <label for="username">账号名</label>
                <input id="username" name="username" value="<?php echo h($editUser['username']); ?>" required>
            </div>
            <div class="field">
                <label for="user_password">密码</label>
                <input id="user_password" type="password" name="password" placeholder="<?php echo $editUser['user_id'] > 0 ? '留空则不修改' : '请输入密码'; ?>">
            </div>
            <div class="field">
                <label for="role">角色</label>
                <select id="role" name="role">
                    <option value="admin" <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>>管理员</option>
                    <option value="teacher" <?php echo $editUser['role'] === 'teacher' ? 'selected' : ''; ?>>任课教师</option>
                    <option value="dorm" <?php echo $editUser['role'] === 'dorm' ? 'selected' : ''; ?>>宿管人员</option>
                </select>
            </div>
            <div class="field">
                <label for="enabled">状态</label>
                <label class="checkbox-row" for="enabled">
                    <input id="enabled" type="checkbox" name="enabled" value="1" style="width:auto;" <?php echo (int) $editUser['enabled'] === 1 ? 'checked' : ''; ?>>
                    启用账号
                </label>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?php echo $editUser['user_id'] > 0 ? '保存账号' : '新增账号'; ?></button>
                <a class="btn btn-muted" href="system.php">清空表单</a>
            </div>
        </form>

        <table class="data-table" style="margin-top:16px;">
            <thead>
            <tr>
                <th>ID</th>
                <th>账号名</th>
                <th>角色</th>
                <th>状态</th>
                <th>创建时间</th>
                <th>更新时间</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($systemUsers)): ?>
                <?php foreach ($systemUsers as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['user_id']; ?></td>
                        <td><?php echo h($row['username']); ?></td>
                        <td><?php echo h(role_label($row['role'])); ?></td>
                        <td><span class="status-pill"><?php echo (int) $row['enabled'] === 1 ? '启用' : '停用'; ?></span></td>
                        <td><?php echo h($row['created_at']); ?></td>
                        <td><?php echo h($row['updated_at']); ?></td>
                        <td>
                            <a href="system.php?edit_user_id=<?php echo (int) $row['user_id']; ?>">编辑</a>
                            <form method="post" action="system.php" style="display:inline-block; margin-left:6px;">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                                <button class="link-button" type="submit" onclick="return confirm('确认删除该账号吗？');">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">暂无后台账号。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h3>参数设置</h3>
        <div class="note">定时扫描脚本：<code>php cron_alert_scan.php</code>。建议用系统计划任务每天执行一次。</div>
        <form method="post" action="system.php" class="form-grid">
            <input type="hidden" name="action" value="save_settings">
            <div class="field">
                <label for="signin_absence_days">连续未签到阈值（天）</label>
                <input id="signin_absence_days" name="signin_absence_days" value="<?php echo h($settingValues['signin_absence_days']); ?>">
            </div>
            <div class="field">
                <label for="late_return_window_days">晚归统计窗口（天）</label>
                <input id="late_return_window_days" name="late_return_window_days" value="<?php echo h($settingValues['late_return_window_days']); ?>">
            </div>
            <div class="field">
                <label for="late_return_threshold">频繁晚归阈值（次）</label>
                <input id="late_return_threshold" name="late_return_threshold" value="<?php echo h($settingValues['late_return_threshold']); ?>">
            </div>
            <div class="field">
                <label for="not_return_window_days">未归统计窗口（天）</label>
                <input id="not_return_window_days" name="not_return_window_days" value="<?php echo h($settingValues['not_return_window_days']); ?>">
            </div>
            <div class="field">
                <label for="not_return_threshold">未归预警阈值（次）</label>
                <input id="not_return_threshold" name="not_return_threshold" value="<?php echo h($settingValues['not_return_threshold']); ?>">
            </div>
            <div class="field">
                <label for="alert_auto_scan_enabled">预警自动日扫描</label>
                <select id="alert_auto_scan_enabled" name="alert_auto_scan_enabled">
                    <option value="1" <?php echo $settingValues['alert_auto_scan_enabled'] === '1' ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo $settingValues['alert_auto_scan_enabled'] === '0' ? 'selected' : ''; ?>>关闭</option>
                </select>
            </div>
            <div class="field">
                <label for="holiday_end_dates">节假日结束日期</label>
                <input id="holiday_end_dates" name="holiday_end_dates" value="<?php echo h($settingValues['holiday_end_dates']); ?>" placeholder="多个日期用逗号分隔，如 2026-05-05,2026-10-07">
            </div>
            <div class="field">
                <label for="last_alert_scan_date">最近扫描日期</label>
                <input id="last_alert_scan_date" value="<?php echo h($settingValues['last_alert_scan_date']); ?>" readonly>
            </div>
            <div class="field">
                <label for="dorm_checkin_start">宿舍签到开始时间</label>
                <input id="dorm_checkin_start" name="dorm_checkin_start" value="<?php echo h($settingValues['dorm_checkin_start']); ?>">
            </div>
            <div class="field">
                <label for="dorm_checkin_end">宿舍签到结束时间</label>
                <input id="dorm_checkin_end" name="dorm_checkin_end" value="<?php echo h($settingValues['dorm_checkin_end']); ?>">
            </div>
            <div class="field">
                <label for="class_attendance_default_session">默认上课节次</label>
                <input id="class_attendance_default_session" name="class_attendance_default_session" value="<?php echo h($settingValues['class_attendance_default_session']); ?>">
            </div>
            <div class="field">
                <label for="alert_notify_channels">预警通知渠道</label>
                <input id="alert_notify_channels" name="alert_notify_channels" value="<?php echo h($settingValues['alert_notify_channels']); ?>" placeholder="例如 系统消息,短信,邮件">
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">保存参数</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>操作日志</h3>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>操作时间</th>
                <th>操作用户</th>
                <th>角色</th>
                <th>操作类型</th>
                <th>操作内容</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($logRows)): ?>
                <?php foreach ($logRows as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['log_id']; ?></td>
                        <td><?php echo h($row['created_at']); ?></td>
                        <td><?php echo h($row['username']); ?></td>
                        <td><?php echo h(role_label($row['role'])); ?></td>
                        <td><?php echo h($row['action_type']); ?></td>
                        <td><?php echo h($row['action_detail']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">暂无操作日志。</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
