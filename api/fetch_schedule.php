<?php
session_start();

$roleRaw = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? '');
$role    = strtolower($roleRaw);

if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized: admin only. (role=' . htmlspecialchars($roleRaw) . ')</div>';
    exit;
}

$grade = $_GET['grade'] ?? '';
$grade = trim($grade);

if ($grade === '') {
    echo '<div class="alert alert-info">Please select a class/grade.</div>';
    exit;
}

require_once __DIR__ . '/../functions/ScheduleManager.php';

$scheduleManager = new ScheduleManager();
$schedule        = $scheduleManager->getClassSchedule($grade);

// This should render the timetable HTML for the admin
require_once __DIR__ . '/../dashboards/admin/schedule-view.php';
