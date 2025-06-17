<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../includes/db.php';

$error = '';
$formData = [
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'confirmPassword' => '',
    'gender' => '',
    'role' => 'Student',
    'class' => '',
    'dob' => '',
    'relationship' => '',
];

$roles = ['Student', 'Teacher', 'Admin', 'Parent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key])) {
            $formData[$key] = trim(htmlspecialchars($_POST[$key]));
        }
    }

    if ($formData['password'] !== $formData['confirmPassword']) {
        $error = "Passwords do not match";
    } elseif (
        empty($formData['firstName']) || empty($formData['lastName']) ||
        empty($formData['email']) || empty($formData['phone']) ||
        empty($formData['password']) || empty($formData['gender'])
    ) {
        $error = "Please fill in all required fields";
    } elseif ($formData['role'] === 'Student' && empty($formData['class'])) {
        $error = "Please select a class for Student role";
    } elseif ($formData['role'] === 'Student' && empty($formData['dob'])) {
        $error = "Please enter Date of Birth for Student";
    } elseif ($formData['role'] === 'Parent' && (empty($_POST['student_id']) || empty($formData['relationship']))) {
        $error = "Please provide Student ID and relationship for Parent role";
    }

    if (!$error) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, password_hash, phone, role, gender) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $passwordHash,
                    $formData['phone'],
                    $formData['role'],
                    $formData['gender']
                ]);

                $userId = $conn->lastInsertId();

                if ($formData['role'] === 'Student') {
                    $fixedPrefix = '987';
                    $year = date('y');
                    $likePattern = $fixedPrefix . $year . '%';

                    $stmt = $conn->prepare("SELECT student_serial FROM students WHERE student_serial LIKE ? ORDER BY student_serial DESC LIMIT 1");
                    $stmt->execute([$likePattern]);
                    $lastSerial = $stmt->fetchColumn();

                    $newCount = $lastSerial ? ((int)substr($lastSerial, 5)) + 1 : 1;
                    $studentSerial = $fixedPrefix . $year . str_pad($newCount, 5, '0', STR_PAD_LEFT);

                    $stmt = $conn->prepare("INSERT INTO students (user_id, class, dob, student_serial) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $formData['class'], $formData['dob'], $studentSerial]);
                } elseif ($formData['role'] === 'Teacher') {
                    $stmt = $conn->prepare("INSERT INTO teachers (user_id, subject, department) VALUES (?, '', '')");
                    $stmt->execute([$userId]);
                } elseif ($formData['role'] === 'Parent') {
                    $studentId = (int)$_POST['student_id'];
                    $stmtCheck = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
                    $stmtCheck->execute([$studentId]);

                    if ($stmtCheck->rowCount() === 0) {
                        $error = "Student ID not found";
                        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO parents (user_id, student_id, relationship) VALUES (?, ?, ?)");
                        $stmt->execute([$userId, $studentId, $formData['relationship']]);
                    }
                } elseif ($formData['role'] === 'Admin') {
                    $stmt = $conn->prepare("INSERT INTO admins (user_id, role_description) VALUES (?, '')");
                    $stmt->execute([$userId]);
                }

                if (!$error) {
                    $_SESSION['success_message'] = "Registered successfully as {$formData['role']}!";
                    header('Location: login.php');
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!-- HTML CODE -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register</title>
  <link rel="stylesheet" href="../assets/css/register.css" />
</head>
<body>
  <div class="main-container">
    <div class="left-section">
      <img src="../assets/img/sitelogo.png" class="logo-img" alt="Logo" />
      <h2>Welcome to EduSphere</h2>
      <p><strong>Please fill out the form to create an account</strong></p>
      <a href="login.php"><button class="login-btn">Go to Login</button></a>
    </div>
    <div class="right-section">
      <form class="form-box" method="POST">
        <div class="role-toggle">
          <?php foreach ($roles as $role): ?>
            <button type="button" class="<?= $formData['role'] === $role ? 'active' : '' ?>"><?= $role ?></button>
          <?php endforeach; ?>
        </div>

        <input type="hidden" name="role" value="<?= htmlspecialchars($formData['role']) ?>" />

        <?php if (!empty($error)): ?>
          <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <div class="form-row">
          <input type="text" name="firstName" placeholder="First Name" value="<?= htmlspecialchars($formData['firstName']) ?>" required />
          <input type="text" name="lastName" placeholder="Last Name" value="<?= htmlspecialchars($formData['lastName']) ?>" required />
        </div>

        <div class="form-row">
          <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($formData['email']) ?>" required />
          <input type="text" name="phone" placeholder="Phone" value="<?= htmlspecialchars($formData['phone']) ?>" required />
        </div>

        <div class="form-row">
          <input type="password" name="password" id="password" placeholder="Password" required />
          <input type="password" name="confirmPassword" placeholder="Confirm Password" required />
        </div>

        <div id="password-strength-text" style="color: green; font-weight: bold;"></div>

        <div class="radio-row">
          <label><input type="radio" name="gender" value="Male" <?= $formData['gender'] === 'Male' ? 'checked' : '' ?> required /> Male</label>
          <label><input type="radio" name="gender" value="Female" <?= $formData['gender'] === 'Female' ? 'checked' : '' ?> required /> Female</label>
          <label><input type="radio" name="gender" value="Other" <?= $formData['gender'] === 'Other' ? 'checked' : '' ?> required /> Other</label>
        </div>

        <div class="form-row" id="class-row" style="<?= $formData['role'] === 'Student' ? '' : 'display: none;' ?>">
          <select name="class" required>
            <option value="">Select Class</option>
            <option value="PG">PG</option>
            <option value="Nursery">Nursery</option>
            <option value="LKG">LKG</option>
            <option value="UKG">UKG</option>
            <?php for ($i = 1; $i <= 10; $i++) echo "<option value=\"$i\">Class $i</option>"; ?>
          </select>
        </div>

        <div class="form-row" id="dob-row" style="<?= $formData['role'] === 'Student' ? '' : 'display: none;' ?>">
          <input type="date" name="dob" placeholder="Date of Birth" value="<?= htmlspecialchars($formData['dob']) ?>" />
        </div>

        <div class="form-row" id="parent-extra" style="<?= $formData['role'] === 'Parent' ? '' : 'display: none;' ?>">
          <input type="text" name="student_id" placeholder="Student ID" />
          <input type="text" name="relationship" placeholder="Relationship to Student" value="<?= htmlspecialchars($formData['relationship']) ?>" />
        </div>

        <button type="submit" class="register-btn">Register</button>
      </form>
    </div>
  </div>

  <script src="../assets/js/register.js"></script>
</body>
</html>
