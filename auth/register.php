<?php
session_start();

$error = '';
$success = '';
$formData = [
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'confirmPassword' => '',
    'gender' => '',
    'role' => 'Student',
    'class' => ''
];

$roles = ['Student', 'Teacher', 'Admin', 'Parent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key])) {
            $formData[$key] = trim(htmlspecialchars($_POST[$key]));
        }
    }

    // Validate passwords match
    if ($formData['password'] !== $formData['confirmPassword']) {
        $error = "Passwords do not match";
    }
    // Validate required fields
    elseif (
        empty($formData['firstName']) || empty($formData['lastName']) || empty($formData['email']) ||
        empty($formData['phone']) || empty($formData['password']) || empty($formData['gender'])
    ) {
        $error = "Please fill in all required fields";
    }
    // If role is Student, class is required
    elseif ($formData['role'] === 'Student' && empty($formData['class'])) {
        $error = "Please select a class for Student role";
    }
    else {
        // Here you can insert the registration data into a database, hash the password, etc.

        // For demo, just success message and redirect to login page
        $_SESSION['success_message'] = "Registered successfully as {$formData['role']}!";
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register - EduSphere</title>
<link rel="stylesheet" href="../assets/css/register.css" />
<style>
/* Add any extra inline styles if needed */
</style>
</head>
<body>

<div class="main-container">
  <div class="left-section">
    <img src="../assets/img/sitelogo.png" alt="EduSphere logo" class="logo-img" />
    <h2 class="animated-text">Welcome To EduSphere</h2>
    <p style="font-weight: bold;">Smart School Engagement & Management Portal.</p>
    <button onclick="window.location.href='login.php'" class="login-btn">Login</button>
  </div>

  <div class="right-section">
    <form method="POST" class="form-box" action="">
      <div class="role-toggle">
        <?php foreach ($roles as $roleOption): ?>
          <button 
            type="button" 
            class="<?php echo ($formData['role'] === $roleOption) ? 'active' : ''; ?>"
          >
            <?php echo $roleOption; ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Hidden input to hold the selected role for form submission -->
      <input type="hidden" name="role" value="<?php echo htmlspecialchars($formData['role']); ?>" />

      <h2>Apply as a <?php echo htmlspecialchars($formData['role']); ?></h2>

      <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
      <?php endif; ?>

      <div class="form-row">
        <input type="text" name="firstName" placeholder="First Name *" value="<?php echo htmlspecialchars($formData['firstName']); ?>" required />
        <input type="text" name="lastName" placeholder="Last Name *" value="<?php echo htmlspecialchars($formData['lastName']); ?>" required />
      </div>

      <div class="form-row">
        <input type="email" name="email" placeholder="Your Email *" value="<?php echo htmlspecialchars($formData['email']); ?>" required />
        <input type="text" name="phone" placeholder="Your Phone *" value="<?php echo htmlspecialchars($formData['phone']); ?>" required />
      </div>

      <div class="form-row">
        <input type="password" name="password" placeholder="Password *" required />
        <input type="password" name="confirmPassword" placeholder="Confirm Password *" required />
      </div>

      <div class="form-row" id="class-row" style="display: <?php echo ($formData['role'] === 'Student') ? 'flex' : 'none'; ?>;">
        <select name="class" <?php echo ($formData['role'] === 'Student') ? 'required' : ''; ?>>
          <option value="">Select Class</option>
          <?php for ($i = 1; $i <= 10; $i++): ?>
            <option value="Class <?php echo $i; ?>" <?php echo ($formData['class'] === "Class $i") ? 'selected' : ''; ?>>
              Class <?php echo $i; ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="form-row radio-row">
        <label>
          <input type="radio" name="gender" value="male" <?php echo ($formData['gender'] === 'male') ? 'checked' : ''; ?> required /> Male
        </label>
        <label>
          <input type="radio" name="gender" value="female" <?php echo ($formData['gender'] === 'female') ? 'checked' : ''; ?> /> Female
        </label>
      </div>

      <button type="submit" class="register-btn">Register</button>
    </form>
  </div>
</div>

<script src="../assets/js/register.js"></script>
</body>
</html>
