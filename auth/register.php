<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db.php';
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';

$formData = [
    'firstName'        => '',
    'lastName'         => '',
    'email'            => '',
    'phone'            => '',
    'password'         => '',
    'confirmPassword'  => '',
    'gender'           => '',
    'role'             => 'Student',  // default
    'class'            => '',
    'dob'              => '',
    'relationship'     => '',
];

$roles = ['Student', 'Teacher', 'Admin', 'Parent'];

define('TEACHER_SECRET', 'teacher123');
define('ADMIN_SECRET', 'admin123');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fill formData from POST
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key])) {
            $formData[$key] = trim($_POST[$key]);
        }
    }

    $formData['role'] = $_POST['role'] ?? 'Student'; // trust hidden field / JS
    $secretCode       = $_POST['secret_code'] ?? '';
    $studentIdRaw     = $_POST['student_id'] ?? '';

    // ------------- VALIDATION -------------

    if (!preg_match('/^[A-Za-z\s]{2,}$/', $formData['firstName'])) {
        $error = "Enter a valid First Name (letters only, min 2 chars)";
    } elseif (!preg_match('/^[A-Za-z\s]{2,}$/', $formData['lastName'])) {
        $error = "Enter a valid Last Name (letters only, min 2 chars)";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid Email address";
    } elseif (!preg_match('/^\d{10}$/', $formData['phone'])) {
        $error = "Enter a valid 10-digit Phone number";
    } elseif (
        strlen($formData['password']) < 6 ||
        !preg_match('/[A-Z]/', $formData['password']) ||
        !preg_match('/[a-z]/', $formData['password']) ||
        !preg_match('/[0-9]/', $formData['password']) ||
        !preg_match('/[\W_]/', $formData['password'])
    ) {
        $error = "Password must be at least 6 characters and include uppercase, lowercase, number, and special character";
    } elseif ($formData['password'] !== $formData['confirmPassword']) {
        $error = "Passwords do not match";
    } elseif (empty($formData['gender'])) {
        $error = "Please select a Gender";
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
    } elseif ($formData['role'] === 'Parent' && (empty($studentIdRaw) || empty($formData['relationship']))) {
        $error = "Please provide Student ID and relationship for Parent role";
    } elseif ($formData['role'] === 'Teacher' && $secretCode !== TEACHER_SECRET) {
        $error = "Invalid secret code for Teacher";
    } elseif ($formData['role'] === 'Admin' && $secretCode !== ADMIN_SECRET) {
        $error = "Invalid secret code for Admin";
    }

    // ------------- INSERT INTO DB -------------
    if (!$error) {
        try {
            // Check for duplicate email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered";
            } else {
                // Direct insert (OTP flow skipped, but left here if you want later)

                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (first_name, last_name, email, password_hash, phone, role, gender)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $passwordHash,
                    $formData['phone'],
                    $formData['role'],
                    $formData['gender'],
                ]);

                $userId = $conn->lastInsertId();

                // Role specific inserts
                if ($formData['role'] === 'Student') {

                    // Generate student_serial like 98725xxxxx
                    $fixedPrefix = '987';
                    $year        = date('y');
                    $likePattern = $fixedPrefix . $year . '%';

                    $stmt = $conn->prepare("
                        SELECT student_serial
                        FROM students
                        WHERE student_serial LIKE ?
                        ORDER BY student_serial DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$likePattern]);
                    $lastSerial = $stmt->fetchColumn();

                    $newCount      = $lastSerial ? ((int)substr($lastSerial, 5)) + 1 : 1;
                    $studentSerial = $fixedPrefix . $year . str_pad($newCount, 5, '0', STR_PAD_LEFT);

                    $stmt = $conn->prepare("
                        INSERT INTO students (user_id, class, dob, student_serial)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $formData['class'], $formData['dob'], $studentSerial]);

                } elseif ($formData['role'] === 'Teacher') {

                    $stmt = $conn->prepare("
                        INSERT INTO teachers (user_id, subject, department)
                        VALUES (?, '', '')
                    ");
                    $stmt->execute([$userId]);

                } elseif ($formData['role'] === 'Parent') {

                    $studentSerial = trim($studentIdRaw);

                    $stmtCheck = $conn->prepare("
                        SELECT user_id
                        FROM students
                        WHERE student_serial = ?
                    ");
                    $stmtCheck->execute([$studentSerial]);

                    if ($stmtCheck->rowCount() === 0) {
                        $error = "Student ID not found";
                        // roll back user insert if parent fails
                        $stmtDel = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmtDel->execute([$userId]);
                    } else {
                        $studentUserId = $stmtCheck->fetchColumn();
                        $stmt = $conn->prepare("
                            INSERT INTO parents (user_id, student_id, relationship)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$userId, $studentUserId, $formData['relationship']]);
                    }

                } elseif ($formData['role'] === 'Admin') {

                    $stmt = $conn->prepare("
                        INSERT INTO admins (user_id, role_description)
                        VALUES (?, '')
                    ");
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
    <!-- LEFT SECTION -->
    <div class="left-section">
      <img src="../assets/img/logo.png" class="logo-img" alt="Logo" />
      <h2>Welcome to EduSphere</h2>
      <p><strong>Please fill out the form to create an account</strong></p>
      <a href="login.php"><button class="login-btn">Go to Login</button></a>
    </div>

    <!-- RIGHT SECTION -->
    <div class="right-section">
      <form class="form-box" method="POST" novalidate>
        <!-- Header row -->
        <div class="form-meta">
          <span>
            Registering as:
            <strong id="active-role-label">
              <?= htmlspecialchars($formData['role']) ?>
            </strong>
          </span>
          <span>All fields are securely encrypted.</span>
        </div>

        <!-- Role toggle buttons -->
        <div class="role-toggle">
          <?php foreach ($roles as $role): ?>
            <button
              type="button"
              class="<?= $formData['role'] === $role ? 'active' : '' ?>">
              <?= htmlspecialchars($role) ?>
            </button>
          <?php endforeach; ?>
        </div>

        <!-- Hidden role input (updated by JS) -->
        <input type="hidden" name="role" value="<?= htmlspecialchars($formData['role']) ?>" />

        <?php if (!empty($error)): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- BASIC DETAILS -->
        <div class="section-label">Basic Details</div>
        <div class="form-row">
          <input
            type="text"
            name="firstName"
            placeholder="First Name"
            value="<?= htmlspecialchars($formData['firstName']) ?>"
            required
          />
          <input
            type="text"
            name="lastName"
            placeholder="Last Name"
            value="<?= htmlspecialchars($formData['lastName']) ?>"
            required
          />
        </div>

        <div class="form-row">
          <input
            type="email"
            name="email"
            placeholder="Email"
            value="<?= htmlspecialchars($formData['email']) ?>"
            required
          />
          <input
            type="text"
            name="phone"
            placeholder="Phone"
            value="<?= htmlspecialchars($formData['phone']) ?>"
            required
          />
        </div>

        <!-- SECURITY -->
        <div class="section-label">Security</div>
        <div class="form-row">
          <input
            type="password"
            name="password"
            id="password"
            placeholder="Password"
            required
          />
          <input
            type="password"
            name="confirmPassword"
            id="confirmPassword"
            placeholder="Confirm Password"
            required
          />
        </div>

        <!-- Password strength -->
        <div class="password-strength-wrapper">
          <div class="password-bar">
            <div class="password-bar-fill" id="password-bar-fill"></div>
          </div>
          <div id="password-strength-text"></div>
        </div>

        <!-- Secret code row (Teacher/Admin) -->
        <div class="form-row" id="secret-code-row" style="display: none;">
          <input type="password" name="secret_code" placeholder="Enter Secret Code" />
        </div>

        <!-- PROFILE -->
        <div class="section-label">Profile</div>
        <div class="radio-row">
          <label>
            <input
              type="radio"
              name="gender"
              value="Male"
              <?= $formData['gender'] === 'Male' ? 'checked' : '' ?>
              required
            /> Male
          </label>
          <label>
            <input
              type="radio"
              name="gender"
              value="Female"
              <?= $formData['gender'] === 'Female' ? 'checked' : '' ?>
              required
            /> Female
          </label>
          <label>
            <input
              type="radio"
              name="gender"
              value="Other"
              <?= $formData['gender'] === 'Other' ? 'checked' : '' ?>
              required
            /> Other
          </label>
        </div>

        <!-- Class (Student) -->
        <div
          class="form-row"
          id="class-row"
          style="<?= $formData['role'] === 'Student' ? '' : 'display:none;' ?>"
        >
          <select name="class" <?= $formData['role'] === 'Student' ? 'required' : '' ?>>
            <option value="">Select Class</option>
            <option value="PG"      <?= $formData['class'] === 'PG' ? 'selected' : '' ?>>PG</option>
            <option value="Nursery" <?= $formData['class'] === 'Nursery' ? 'selected' : '' ?>>Nursery</option>
            <option value="LKG"     <?= $formData['class'] === 'LKG' ? 'selected' : '' ?>>LKG</option>
            <option value="UKG"     <?= $formData['class'] === 'UKG' ? 'selected' : '' ?>>UKG</option>
            <?php for ($i = 1; $i <= 10; $i++): ?>
              <option value="<?= $i ?>"
                <?= $formData['class'] == $i ? 'selected' : '' ?>>
                Class <?= $i ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <!-- DOB (Student) -->
        <div
          class="form-row"
          id="dob-row"
          style="<?= $formData['role'] === 'Student' ? '' : 'display:none;' ?>"
        >
          <input
            type="date"
            name="dob"
            placeholder="Date of Birth"
            value="<?= htmlspecialchars($formData['dob']) ?>"
          />
        </div>

        <!-- Parent extra fields -->
        <div
          class="form-row"
          id="parent-extra"
          style="<?= $formData['role'] === 'Parent' ? '' : 'display:none;' ?>"
        >
          <input
            type="text"
            name="student_id"
            placeholder="Student ID"
            value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>"
          />
          <input
            type="text"
            name="relationship"
            placeholder="Relationship to Student"
            value="<?= htmlspecialchars($formData['relationship']) ?>"
          />
        </div>

        <!-- Tips -->
        <div class="form-tips">
          <span class="form-tip-pill">✓ Use your school email if possible</span>
          <span class="form-tip-pill">✓ Strong password = more secure account</span>
        </div>

        <button type="submit" class="register-btn">Register</button>
      </form>
    </div>
  </div>

  <!-- INLINE JS: role switching + password strength -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const roleButtons = document.querySelectorAll(".role-toggle button");
      const roleInput   = document.querySelector('input[name="role"]');
      const roleLabel   = document.getElementById("active-role-label");

      const secretRow   = document.getElementById("secret-code-row");
      const classRow    = document.getElementById("class-row");
      const dobRow      = document.getElementById("dob-row");
      const parentExtra = document.getElementById("parent-extra");

      const classSelect       = document.querySelector('select[name="class"]');
      const dobInput          = document.querySelector('input[name="dob"]');
      const studentIdInput    = document.querySelector('input[name="student_id"]');
      const relationshipInput = document.querySelector('input[name="relationship"]');

      function setRequired(el, required) {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
      }

      function switchRole(role) {
        if (roleInput) roleInput.value = role;
        if (roleLabel) roleLabel.textContent = role;

        // Toggle active class on buttons
        roleButtons.forEach(btn => {
          const btnRole = btn.textContent.trim();
          if (btnRole.toLowerCase() === role.toLowerCase()) {
            btn.classList.add("active");
          } else {
            btn.classList.remove("active");
          }
        });

        // Student
        if (role === "Student") {
          if (classRow) classRow.style.display = "";
          if (dobRow) dobRow.style.display = "";
          if (parentExtra) parentExtra.style.display = "none";
          if (secretRow) secretRow.style.display = "none";

          setRequired(classSelect, true);
          setRequired(dobInput, true);
          setRequired(studentIdInput, false);
          setRequired(relationshipInput, false);

        // Parent
        } else if (role === "Parent") {
          if (classRow) classRow.style.display = "none";
          if (dobRow) dobRow.style.display = "none";
          if (parentExtra) parentExtra.style.display = "";
          if (secretRow) secretRow.style.display = "none";

          setRequired(classSelect, false);
          setRequired(dobInput, false);
          setRequired(studentIdInput, true);
          setRequired(relationshipInput, true);

        // Teacher / Admin
        } else if (role === "Teacher" || role === "Admin") {
          if (classRow) classRow.style.display = "none";
          if (dobRow) dobRow.style.display = "none";
          if (parentExtra) parentExtra.style.display = "none";
          if (secretRow) secretRow.style.display = "";

          setRequired(classSelect, false);
          setRequired(dobInput, false);
          setRequired(studentIdInput, false);
          setRequired(relationshipInput, false);

        } else {
          if (classRow) classRow.style.display = "none";
          if (dobRow) dobRow.style.display = "none";
          if (parentExtra) parentExtra.style.display = "none";
          if (secretRow) secretRow.style.display = "none";

          setRequired(classSelect, false);
          setRequired(dobInput, false);
          setRequired(studentIdInput, false);
          setRequired(relationshipInput, false);
        }
      }

      // Attach click handlers
      roleButtons.forEach(btn => {
        btn.addEventListener("click", () => {
          const role = btn.textContent.trim();
          switchRole(role);
        });
      });

      // Initialize based on current PHP value
      if (roleInput && roleInput.value) {
        switchRole(roleInput.value);
      }

      // --- Simple password strength indicator ---
      const passwordInput = document.getElementById("password");
      const barFill       = document.getElementById("password-bar-fill");
      const strengthText  = document.getElementById("password-strength-text");

      function updateStrength() {
        if (!passwordInput) return;
        const val = passwordInput.value || "";
        let score = 0;

        if (val.length >= 6) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[\W_]/.test(val)) score++;

        const percent = (score / 5) * 100;
        if (barFill) barFill.style.width = percent + "%";

        if (!strengthText) return;
        if (!val) {
          strengthText.textContent = "";
        } else if (score <= 2) {
          strengthText.textContent = "Weak password";
        } else if (score === 3 || score === 4) {
          strengthText.textContent = "Good password";
        } else {
          strengthText.textContent = "Strong password";
        }
      }

      if (passwordInput) {
        passwordInput.addEventListener("input", updateStrength);
        updateStrength();
      }
    });
  </script>
</body>
</html>
