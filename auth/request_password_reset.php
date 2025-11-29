<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    $_SESSION['reset_notice'] = 'Please enter a valid email address.';
    header('Location: login.php');
    exit;
}

try {
    require_once '../includes/db.php';

    // Check if user exists – we don’t reveal the result to the user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // TODO:
        // 1. Generate a secure token.
        // 2. Store it in a `password_resets` table with expiry.
        // 3. Send reset link via email.
        //
        // Example reset link (once implemented):
        // $resetLink = 'https://yourdomain.com/auth/reset_password.php?token=' . $token;
    }

    // We always show this generic message.
    $_SESSION['reset_notice'] =
        'If this email is registered with EduSphere, we\'ve sent a password reset link. '
        . 'Please check your inbox.';

} catch (Throwable $e) {
    // Fallback error message
    $_SESSION['reset_notice'] =
        'Something went wrong while requesting a reset. Please try again in a moment.';
}

header('Location: login.php');
exit;
