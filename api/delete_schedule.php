<?php
session_start();
require_once __DIR__.'/../functions/ScheduleManager.php';

header('Content-Type: application/json');

$role   = strtolower($_SESSION['user_role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || $role !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$grade = $_POST['grade'] ?? '';
$grade = trim($grade);

if (!$grade) {
  echo json_encode(['success' => false, 'message' => 'Grade is required']);
  exit;
}

$manager = new ScheduleManager();
$ok = $manager->clearClassSchedule($grade);

echo json_encode([
  'success' => $ok,
  'message' => $ok ? '✅ Schedule deleted!' : '❌ Delete failed'
]);
