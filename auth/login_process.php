<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../includes/db.php';  // ✅ Database connection

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = strtolower(trim($_POST['role'] ?? '')); // Normalize role to lowercase

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // 🔍 Fetch user by email and role (case-insensitive)
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND LOWER(role) = ? LIMIT 1");
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // ✅ Login success, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $role;

                $firstName = $user['first_name'] ?? 'User';
                $email = $user['email'] ?? '';

                // 🔀 Redirect based on role
                switch ($role) {
                    case 'student':
                        $_SESSION['student_name'] = $firstName;
                        $_SESSION['student_email'] = $email;
                        header('Location: ../dashboards/student/dashboard.php');
                        exit();

                    case 'teacher':
                        $_SESSION['teacher_name'] = $firstName;
                        $_SESSION['teacher_email'] = $email;
                        header('Location: ../dashboards/teacher/dashboard.php');
                        exit();

                    case 'admin':
                        $_SESSION['admin_name'] = $firstName;
                        $_SESSION['admin_email'] = $email;
                        header('Location: ../dashboards/admin/dashboard.php');
                        exit();

                    case 'parent':
                        $_SESSION['parent_name'] = $firstName;
                        $_SESSION['parent_email'] = $email;
                        header('Location: ../dashboards/parent/dashboard.php');
                        exit();

                    default:
                        $error = "Unknown role provided.";
                        break;
                }
            } else {
                $error = "Invalid email, role, or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
} else {
    //  Direct access without POST
    header('Location: login.php');
    exit();
}

//  On failure: save error and redirect back
$_SESSION['login_error'] = $error;
header('Location: login.php');
exit();
