<?php
session_start();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$other_user_id = intval($_GET['other_user_id'] ?? 0);

if ($other_user_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM messages
    WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
    ORDER BY timestamp ASC
");
$stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
$messages = $stmt->fetchAll();

echo json_encode($messages);
