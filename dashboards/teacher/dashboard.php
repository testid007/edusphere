<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .cards {
      display: flex;
      flex-wrap: wrap;
      gap: 24px;
      margin: 32px 0 0 0;
      justify-content: flex-start;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(76,175,80,0.10);
      padding: 20px 24px;
      min-width: 200px;
      max-width: 240px;
      flex: 1 1 200px;
      display: flex;
      align-items: flex-start;
      border: 2px solid #4caf50;
      transition: box-shadow 0.2s, border-color 0.2s;
    }
    .card:hover {
      box-shadow: 0 4px 16px rgba(76,175,80,0.18);
      border-color: #388e3c;
      background: #f6fff6;
    }
    .card h3 {
      margin: 0 0 8px 0;
      font-size: 1.08rem;
      color: #4caf50;
      font-weight: 700;
      letter-spacing: 0.01em;
    }
    .card p {
      margin: 0 0 5px 0;
      color: #222;
      font-size: 0.97rem;
      font-weight: 400;
    }
    .card strong {
      color: #388e3c;
      font-weight: 600;
    }
    @media (max-width: 900px) {
      .cards {
        flex-direction: column;
        gap: 16px;
      }
      .card {
        max-width: 100%;
        min-width: 0;
      }
    }
    /* Profile Modal Styles */
    #profileModal {
      display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.3); z-index: 999; align-items: center; justify-content: center;
    }
    #profileModal .modal-content {
      background: #fff; padding: 32px 28px 18px 28px; border-radius: 10px; min-width: 320px; max-width: 90vw; position: relative;
      box-shadow: 0 4px 24px rgba(44,62,80,0.13);
    }
    #closeProfileModal {
      position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 1.2rem; cursor: pointer;
    }
    #profileModal h3 { margin-top: 0; color: #4caf50; }
    #profileForm input[type="text"], #profileForm input[type="email"] {
      width: 100%; padding: 7px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px;
    }
    #profileForm button[type="submit"] {
      background: #4caf50; color: #fff; padding: 7px 18px; border: none; border-radius: 5px; cursor: pointer;
    }
    #profileMsg { margin-top: 10px; }
    .profile-btn {
      margin-left: 20px; background: #4caf50; color: #fff; border: none; padding: 7px 18px; border-radius: 5px; cursor: pointer;
      font-size: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <?php include '../components/sidebar_teacher.php'; ?>

    <main class="main">
      <?php include '../components/header.php'; ?>

      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h2 style="margin:0;">Welcome, <span class="name"><?= htmlspecialchars($teacher_name) ?></span>!</h2>
        <button id="viewProfileBtn" class="profile-btn"><i class="fas fa-user"></i> View Profile</button>
      </div>

      <section class="cards">
        <div class="card">
          <div>
            <h3>Class Summary</h3>
            <p>You teach <strong>5</strong> classes.</p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Assignments Overview</h3>
            <p>Total assignments: <strong>20</strong></p>
            <p>Pending: <strong>3</strong></p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Student Attendance</h3>
            <p>Average attendance: <strong>92%</strong></p>
          </div>
        </div>
        <div class="card">
          <div>
            <h3>Messages</h3>
            <p>You have <strong>7</strong> unread messages.</p>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Profile Modal -->
  <div id="profileModal">
    <div class="modal-content">
      <button id="closeProfileModal">&times;</button>
      <h3>Edit Profile</h3>
      <form id="profileForm">
        <label>Name:<br>
          <input type="text" name="teacher_name" id="profileName" value="<?= htmlspecialchars($teacher_name) ?>" required>
        </label>
        <label>Email:<br>
          <input type="email" name="teacher_email" id="profileEmail" value="<?= htmlspecialchars($teacher_email) ?>" required>
        </label>
        <button type="submit">Save Changes</button>
      </form>
      <div id="profileMsg"></div>
    </div>
  </div>

  <script>
    // Profile Modal Logic
    const viewProfileBtn = document.getElementById('viewProfileBtn');
    const profileModal = document.getElementById('profileModal');
    const closeProfileModal = document.getElementById('closeProfileModal');
    const profileForm = document.getElementById('profileForm');
    const profileMsg = document.getElementById('profileMsg');
    const profileName = document.getElementById('profileName');
    const profileEmail = document.getElementById('profileEmail');

    viewProfileBtn.addEventListener('click', () => {
      profileModal.style.display = 'flex';
      profileMsg.textContent = '';
    });
    closeProfileModal.addEventListener('click', () => {
      profileModal.style.display = 'none';
    });
    profileModal.addEventListener('click', (e) => {
      if (e.target === profileModal) profileModal.style.display = 'none';
    });

    profileForm.addEventListener('submit', function(e) {
      e.preventDefault();
      profileMsg.textContent = 'Saving...';
      fetch('update_profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `teacher_name=${encodeURIComponent(profileName.value)}&teacher_email=${encodeURIComponent(profileEmail.value)}`
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          profileMsg.style.color = '#4caf50';
          profileMsg.textContent = 'Profile updated!';
          setTimeout(() => profileModal.style.display = 'none', 1000);
          document.querySelectorAll('.name').forEach(el => el.textContent = profileName.value);
          document.querySelectorAll('.email').forEach(el => el.textContent = profileEmail.value);
        } else {
          profileMsg.style.color = 'red';
          profileMsg.textContent = data.message || 'Update failed.';
        }
      })
      .catch(() => {
        profileMsg.style.color = 'red';
        profileMsg.textContent = 'Error updating profile.';
      });