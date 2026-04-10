<?php
require_once __DIR__ . '/auth.php';
require_login();

$currentPage = basename($_SERVER['PHP_SELF']);
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$menu = array(
    array('href' => 'index.php', 'label' => '学生信息'),
    array('href' => 'select.php', 'label' => '学生查询')
);

if (!is_teacher_user()) {
    $menu[] = array('href' => 'signin.php', 'label' => '扫码签到');
}

if (can_manage_class_attendance()) {
    $menu[] = array('href' => 'class_attendance.php', 'label' => '课堂签到');
    $menu[] = array('href' => 'leave_review.php', 'label' => '请假审批');
}

if (can_manage_records()) {
    $menu[] = array('href' => 'attendance.php', 'label' => '考勤管理');
    $menu[] = array('href' => 'add.php', 'label' => '新生入住');
    $menu[] = array('href' => 'wangui.php', 'label' => '晚归登记');
    $menu[] = array('href' => 'wangui3.php', 'label' => '晚归记录');
    $menu[] = array('href' => 'alerts.php', 'label' => '异常预警');
}

if (can_manage_records() || can_manage_class_attendance()) {
    $menu[] = array('href' => 'stats.php', 'label' => '数据统计');
}

if (is_student_user()) {
    $menu[] = array('href' => 'class_signin.php', 'label' => '课堂签到');
    $menu[] = array('href' => 'leave_apply.php', 'label' => '请假申请');
}

if (is_admin_user()) {
    $menu[] = array('href' => 'system.php', 'label' => '系统管理');
}
?>
<div class="topbar">
    <div class="topbar-inner">
        <div class="topbar-brand">宿舍管理系统</div>
        <nav class="topbar-nav">
            <?php foreach ($menu as $item): ?>
                <?php $active = $currentPage === $item['href'] ? 'active' : ''; ?>
                <a class="<?php echo $active; ?>" href="<?php echo $item['href']; ?>"><?php echo h($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-user">
            <div><?php echo h(role_label($role)); ?>：<?php echo h(current_user_name()); ?></div>
            <a href="logout.php">退出登录</a>
        </div>
    </div>
</div>
