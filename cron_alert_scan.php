<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/system_core.php';

ensure_system_schema($conn);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(
        array(
            'ok' => false,
            'message' => 'Only CLI is allowed.'
        ),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$scanResult = run_alert_scan_job($conn, false);
if (!is_array($scanResult)) {
    $scanResult = array(
        'ok' => false,
        'skipped' => true,
        'reason' => 'unknown',
        'generated' => 0,
        'scan_date' => date('Y-m-d')
    );
}

if (isset($scanResult['skipped']) && !$scanResult['skipped']) {
    $generated = isset($scanResult['generated']) ? (int) $scanResult['generated'] : 0;
    write_operation_log($conn, '异常预警扫描', 'Cron每日扫描执行，生成或刷新预警 ' . $generated . ' 条');
}

echo json_encode($scanResult, JSON_UNESCAPED_UNICODE);

