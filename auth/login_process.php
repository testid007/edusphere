<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db.php';  // DB connection

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleFromForm = trim($_POST['role'] ?? '');  // "Student", "Teacher", "Admin", "Parent"

    if ($email === '' || $password === '' || $roleFromForm === '') {
        $error = "Please fill in all fields.";
    } else {
        try {
            // 1) Find user by email only
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Invalid email, role, or password.";
            } else {
                // 2) Check password
                if (!password_verify($password, $user['password_hash'])) {
                    $error = "Invalid email, role, or password.";
                } else {
                    // 3) Check role: compare form role vs DB role, case-insensitive
                    $dbRole       = trim($user['role']);            // e.g. "Teacher" from DB
                    $dbRoleLower  = strtolower($dbRole);            // "teacher"
                    $formRoleLower = strtolower($roleFromForm);     // "teacher" from select

                    if ($dbRoleLower !== $formRoleLower) {
                        $error = "Invalid email, role, or password.";
                    } else {
                        // 4) All good → set sessions
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role']    = $dbRoleLower;  // <-- lowercase: "teacher", "admin", etc.

                        $firstName = $user['first_name'] ?? 'User';
                        $userEmail = $user['email'] ?? '';

                        switch ($dbRoleLower) {
                            case 'student':
                                $_SESSION['student_name']  = $firstName;
                                $_SESSION['student_email'] = $userEmail;
                                header('Location: ../dashboards/student/dashboard.php');
                                exit;

                            case 'teacher':
                                $_SESSION['teacher_name']  = $firstName;
                                $_SESSION['teacher_email'] = $userEmail;
                                header('Location: ../dashboards/teacher/dashboard.php');
                                exit;

                            case 'admin':
                                $_SESSION['admin_name']  = $firstName;
                                $_SESSION['admin_email'] = $userEmail;
                                header('Location: ../dashboards/admin/dashboard.php');
                                exit;

                            case 'parent':
                                $_SESSION['parent_name']  = $firstName;
                                $_SESSION['parent_email'] = $userEmail;
                                header('Location: ../dashboards/parent/dashboard.php');
                                exit;

                            default:
                                $error = "Unknown role on account.";
                                break;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
} else {
    // Direct GET → send to login form
    header('Location: login.php');
    exit;
}

// If we reached here, login failed
$_SESSION['login_error'] = $error ?: 'Login failed.';
header('Location: login.php');
exit;
