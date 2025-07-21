<?php
session_start();

// DB connection settings (adjust to your environment)
$mysqli = new mysqli('localhost', 'root', '', 'edusphere');
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $result = $mysqli->query("SELECT g.*, '' AS student_name FROM grades g ORDER BY g.date_added DESC");
    if ($result) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch grades']);
    }
    exit;
}

if ($action === 'get' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("SELECT * FROM grades WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $grade = $res->fetch_assoc();
    if ($grade) {
        echo json_encode(['success' => true, 'data' => $grade]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Grade not found']);
    }
    exit;
}

if ($action === 'create' || $action === 'update') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $category = $_POST['category'] ?? '';
    $title = $_POST['title'] ?? '';
    $score = $_POST['score'] ?? '';
    $grade_val = $_POST['grade'] ?? '';
    $comments = $_POST['comments'] ?? '';

    $errors = [];
    if ($student_id <= 0) $errors[] = "Invalid student ID";
    if (!in_array($category, ['Assignment', 'Exam', 'Discipline', 'Classroom Activity'])) $errors[] = "Invalid category";
    if (!$title) $errors[] = "Title is required";
    if (!$score) $errors[] = "Score is required";

    if (count($errors) > 0) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    if ($action === 'create') {
        $stmt = $mysqli->prepare("INSERT INTO grades (student_id, category, title, score, grade, comments) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $student_id, $category, $title, $score, $grade_val, $comments);
        $exec = $stmt->execute();
        if ($exec) {
            echo json_encode(['success' => true, 'message' => 'Grade added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
        }
        exit;
    }

    if ($action === 'update' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $mysqli->prepare("UPDATE grades SET student_id=?, category=?, title=?, score=?, grade=?, comments=? WHERE id=?");
        $stmt->bind_param('isssssi', $student_id, $category, $title, $score, $grade_val, $comments, $id);
        $exec = $stmt->execute();
        if ($exec) {
            echo json_encode(['success' => true, 'message' => 'Grade updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
        }
        exit;
    }
}

if ($action === 'delete' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $mysqli->prepare("DELETE FROM grades WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Grade deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete grade']);
    }
    exit;
}

// If no action, output HTML below:
header('Content-Type: text/html');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Grade Book</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<style>
  form.grade-form {
    background: #ffffff;
    padding: 20px 25px;
    border-radius: 14px;
    box-shadow: 0 3px 15px rgba(76,175,80,0.08);
    max-width: 600px;
    margin: 2rem auto 3rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    transition: box-shadow 0.3s ease;
  }
  form.grade-form:hover {
    box-shadow: 0 5px 25px rgba(76,175,80,0.15);
  }
  form.grade-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #388e3c;
    letter-spacing: 0.03em;
  }
  form.grade-form input[type="text"],
  form.grade-form input[type="number"],
  form.grade-form select,
  form.grade-form textarea {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #b2ded8;
    border-radius: 10px;
    background-color: #f0f9f8;
    font-size: 16px;
    color: #0e766f;
    box-sizing: border-box;
    transition: border-color 0.3s ease, background-color 0.3s ease;
  }
  form.grade-form input[type="text"]:focus,
  form.grade-form input[type="number"]:focus,
  form.grade-form select:focus,
  form.grade-form textarea:focus {
    outline: none;
    border-color: #4caf50;
    background-color: #e0f3f2;
    box-shadow: 0 0 8px rgba(76,175,80,0.12);
  }
  form.grade-form button {
    background-color: #4caf50;
    color: white;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    padding: 12px 25px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.25s ease;
    box-shadow: 0 3px 10px rgba(76,175,80,0.10);
  }
  form.grade-form button:hover {
    background-color: #388e3c;
    box-shadow: 0 4px 15px rgba(56,142,60,0.13);
  }
  form.grade-form button#cancelBtn {
    background-color: #e74c3c;
    box-shadow: 0 3px 10px rgba(231, 76, 60, 0.13);
    margin-left: 12px;
  }
  form.grade-form button#cancelBtn:hover {
    background-color: #c0392b;
    box-shadow: 0 4px 15px rgba(192, 57, 43, 0.18);
  }
  form.grade-form input::placeholder,
  form.grade-form textarea::placeholder {
    color: #72b8af;
    font-style: italic;
  }
  @media (max-width: 650px) {
    form.grade-form {
      padding: 15px 20px;
    }
    form.grade-form button {
      width: 100%;
      margin-bottom: 10px;
    }
    form.grade-form button#cancelBtn {
      margin-left: 0;
    }
  }
  .gradebook-table {
    width: 100%;
    border-collapse: collapse;
    margin: 2rem 0;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    font-size: 1rem;
  }
  .gradebook-table th, .gradebook-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e0e0e0;
    text-align: left;
  }
  .gradebook-table th {
    background: #4caf50;
    color: #fff;
    font-weight: 700;
  }
  .gradebook-table tr:last-child td {
    border-bottom: none;
  }
  .gradebook-table tr:hover {
    background: #f6fff6;
  }
  .btn-edit, .btn-delete {
    background: #4caf50;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    margin-right: 6px;
    cursor: pointer;
    font-size: 0.97rem;
    transition: background 0.2s;
  }
  .btn-delete {
    background: #e74c3c;
  }
  .btn-edit:hover {
    background: #388e3c;
  }
  .btn-delete:hover {
    background: #c0392b;
  }
  .message {
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-weight: 600;
    display: none;
  }
  .message.success { background: #e8f5e9; color: #388e3c; }
  .message.error { background: #ffebee; color: #c0392b; }
  .sidebar .profile .name { color: #222; }
  .sidebar .profile .email { color: #555; }
</style>
</head>
<body>
<div class="container">
  <aside class="sidebar">
    <div class="logo">
      <img src="../../assets/img/logo.png" alt="Logo" width="30" />
    </div>
    <nav class="nav">
      <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
      <a href="gradebook.php" class="active"><i class="fas fa-book-open"></i> Grade Book</a>
      <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
      <a href="communication.php"><i class="fas fa-comments"></i> Communication</a>
      <a href="/edusphere/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="profile">
      <img src="../../assets/img/user.jpg" alt="Teacher" />
      <div class="name"><?= htmlspecialchars($teacher_name) ?></div>
      <div class="email"><?= htmlspecialchars($teacher_email) ?></div>
    </div>
  </aside>

  <main class="main">
    <header class="header">
      <h2>Grade Book</h2>
      <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
    </header>

    <section>
      <div id="message" class="message" style="display:none;"></div>

      <form id="gradeForm" class="grade-form">
        <input type="hidden" id="grade-id" name="id" value="" />

        <label for="student_id">Student ID</label>
        <input type="number" id="student_id" name="student_id" required placeholder="Enter Student ID" />

        <label for="category">Category</label>
        <select id="category" name="category" required>
          <option value="">Select Category</option>
          <option value="Assignment">Assignment</option>
          <option value="Exam">Exam</option>
          <option value="Discipline">Discipline</option>
          <option value="Classroom Activity">Classroom Activity</option>
        </select>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" required placeholder="Enter title" />

        <label for="score">Score</label>
        <input type="text" id="score" name="score" required placeholder="Enter score e.g. 45/50" />

        <label for="grade">Grade</label>
        <input type="text" id="grade" name="grade" placeholder="Enter grade (optional)" />

        <label for="comments">Comments</label>
        <textarea id="comments" name="comments" rows="3" placeholder="Enter comments (optional)"></textarea>

        <button type="submit" id="submitBtn">Add Grade</button>
        <button type="button" id="cancelBtn" style="display:none; margin-left: 10px;">Cancel</button>
      </form>

      <h3>Grades List</h3>
      <table class="gradebook-table" id="gradesTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Student ID</th>
            <th>Category</th>
            <th>Title</th>
            <th>Score</th>
            <th>Grade</th>
            <th>Comments</th>
            <th>Date Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Filled dynamically -->
        </tbody>
      </table>
    </section>
  </main>
</div>

<script>
  const messageBox = document.getElementById('message');
  const gradeForm = document.getElementById('gradeForm');
  const submitBtn = document.getElementById('submitBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const gradesTableBody = document.querySelector('#gradesTable tbody');

  let editingId = null;

  function showMessage(text, type = 'success') {
    messageBox.textContent = text;
    messageBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
    messageBox.style.display = 'block';
    setTimeout(() => { messageBox.style.display = 'none'; }, 4000);
  }

  function clearForm() {
    editingId = null;
    gradeForm.reset();
    document.getElementById('grade-id').value = '';
    submitBtn.textContent = 'Add Grade';
    cancelBtn.style.display = 'none';
  }

  function loadGrades() {
    fetch('?action=list')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          gradesTableBody.innerHTML = '';
          if (data.data.length === 0) {
            gradesTableBody.innerHTML = '<tr><td colspan="9">No grades found.</td></tr>';
            return;
          }
          data.data.forEach(g => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${g.id}</td>
              <td>${g.student_id}</td>
              <td>${escapeHtml(g.category)}</td>
              <td>${escapeHtml(g.title)}</td>
              <td>${escapeHtml(g.score)}</td>
              <td>${escapeHtml(g.grade || '')}</td>
              <td>${escapeHtml(g.comments || '')}</td>
              <td>${g.date_added}</td>
              <td>
                <button class="btn-edit" data-id="${g.id}">Edit</button>
                <button class="btn-delete" data-id="${g.id}">Delete</button>
              </td>
            `;
            gradesTableBody.appendChild(tr);
          });
          attachTableEvents();
        } else {
          showMessage('Failed to load grades.', 'error');
        }
      })
      .catch(() => showMessage('Error loading grades.', 'error'));
  }

  function attachTableEvents() {
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.onclick = function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        fetch(`?action=get&id=${id}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              editingId = id;
              submitBtn.textContent = 'Update Grade';
              cancelBtn.style.display = 'inline-block';

              document.getElementById('grade-id').value = data.data.id;
              document.getElementById('student_id').value = data.data.student_id;
              document.getElementById('category').value = data.data.category;
              document.getElementById('title').value = data.data.title;
              document.getElementById('score').value = data.data.score;
              document.getElementById('grade').value = data.data.grade || '';
              document.getElementById('comments').value = data.data.comments || '';
            } else {
              showMessage(data.message || 'Failed to load grade data.', 'error');
            }
          });
      };
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.onclick = function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this grade?')) return;
        const id = this.getAttribute('data-id');
        fetch('?action=delete', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}`
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showMessage(data.message, 'success');
            loadGrades();
            if (editingId === id) clearForm();
          } else {
            showMessage(data.message || 'Failed to delete grade.', 'error');
          }
        });
      };
    });
  }

  gradeForm.addEventListener('submit', e => {
    e.preventDefault();

    const formData = new FormData(gradeForm);
    const data = {};
    formData.forEach((v, k) => data[k] = v.trim());

    let action = editingId ? 'update' : 'create';
    if (editingId) data.id = editingId;

    fetch(`?action=${action}`, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams(data)
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        showMessage(resp.message, 'success');
        loadGrades();
        clearForm();
      } else {
        showMessage((resp.errors && resp.errors.join(', ')) || resp.message || 'Failed to save grade.', 'error');
      }
    })
    .catch(() => showMessage('Error saving grade.', 'error'));
  });

  cancelBtn.onclick = () => clearForm();

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[m]);
  }

  loadGrades();
</script>
</body>
</html>