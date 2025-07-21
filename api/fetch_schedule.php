<?php
require_once __DIR__.'/../functions/ScheduleManager.php';

$grade = $_GET['grade'] ?? 1;
$scheduleManager = new ScheduleManager();
$schedule = $scheduleManager->getClassSchedule($grade);

ob_start();
include __DIR__.'/../dashboards/admin/schedule-view.php';
echo ob_get_clean();