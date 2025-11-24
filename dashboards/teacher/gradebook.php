<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_name  = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

// ---------- AJAX HANDLERS ----------
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    try {
        if ($action === 'list') {
            // Join with users to show student name (optional)
            $stmt = $conn->query("
                SELECT g.*, 
                       CONCAT(u.first_name, ' ', u.last_name) AS student_name
                FROM grades g
                LEFT JOIN users u ON g.student_id = u.id
                ORDER BY g.date_added DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        if ($action === 'get' && isset($_GET['id'])) {
            $id   = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM grades WHERE id = ?");
            $stmt->execute([$id]);
            $grade = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($grade) {
                echo json_encode(['success' => true, 'data' => $grade]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Grade not found']);
            }
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $category   = trim($_POST['category'] ?? '');
            $title      = trim($_POST['title'] ?? '');
            $score      = trim($_POST['score'] ?? '');
            $grade_val  = trim($_POST['grade'] ?? '');
            $comments   = trim($_POST['comments'] ?? '');

            $errors = [];
            if ($student_id <= 0) $errors[] = 'Invalid student ID';
            $allowed_cat = ['Assignment','Exam','Discipline','Classroom Activity'];
            if (!in_array($category, $allowed_cat, true)) $errors[] = 'Invalid category';
            if ($title === '')  $errors[] = 'Title is required';
            if ($score === '')  $errors[] = 'Score is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO grades (student_id, category, title, score, grade, comments)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade added successfully' : 'Failed to add grade'
                ]);
                exit;
            } else { // update
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing grade ID']);
                    exit;
                }
                $id   = (int)$_POST['id'];
                $stmt = $conn->prepare("
                    UPDATE grades
                    SET student_id = ?, category = ?, title = ?, score = ?, grade = ?, comments = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$student_id, $category, $title, $score, $grade_val, $comments, $id]);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Grade updated successfully' : 'Failed to update grade'
                ]);
                exit;
            }
        }

        if ($action === 'delete' && isset($_POST['id'])) {
            $id   = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
            $ok   = $stmt->execute([$id]);
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Grade deleted successfully' : 'Failed to delete grade'
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

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
      background:#ffffff;
      padding:20px 25px;
      border-radius:14px;
      box-shadow:0 3px 15px rgba(76,175,80,0.08);
      max-width:600px;
      margin:2rem auto 3rem;
    }
    form.grade-form label {
      display:block;
      font-weight:600;
      margin-bottom:8px;
      color:#388e3c;
    }
    form.grade-form input[type="text"],
    form.grade-form input[type="number"],
    form.grade-form select,
    form.grade-form textarea {
      width:100%;
      padding:10px 14px;
      border:2px solid #b2ded8;
      border-radius:10px;
      background-color:#f0f9f8;
      margin-bottom:12px;
      font-size:15px;
    }
    form.grade-form button {
      background:#4caf50;
      color:#fff;
      border:none;
      border-radius:12px;
      padding:10px 20px;
      font-weight:700;
      cursor:pointer;
    }
    form.grade-form button#cancelBtn {
      background:#e74c3c;
      margin-left:10px;
    }
    .message {
      padding:12px 18px;
      border-radius:8px;
      margin-bottom:18px;
      font-weight:600;
      display:none;
    }
    .message.success { background:#e8f5e9;color:#388e3c; }
    .message.error { background:#ffebee;color:#c0392b; }
    table.gradebook-table {
      width:100%;
      border-collapse:collapse;
      margin:2rem 0;
      background:#fff;
      border-radius:10px;
      overflow:hidden;
    }
    .gradebook-table th,.gradebook-table td {
      padding:10px 12px;
      border-bottom:1px solid #e0e0e0;
      text-align:left;
      font-size:0.9rem;
    }
    .gradebook-table th {
      background:#4caf50;
      color:#fff;
    }
    .btn-edit,.btn-delete {
      border:none;border-radius:6px;padding:5px 10px;font-size:0.9rem;cursor:pointer;
      color:#fff;
    }
    .btn-edit { background:#4caf50; }
    .btn-delete { background:#e74c3c; }
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
      <div id="message" class="message"></div>

      <form id="gradeForm" class="grade-form">
        <input type="hidden" id="grade-id" name="id" />

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
        <textarea id="comments" name="comments" rows="3" placeholder="Comments (optional)"></textarea>

        <button type="submit" id="submitBtn">Add Grade</button>
        <button type="button" id="cancelBtn" style="display:none;">Cancel</button>
      </form>

      <h3>Grades List</h3>
      <table class="gradebook-table" id="gradesTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Category</th>
            <th>Title</th>
            <th>Score</th>
            <th>Grade</th>
            <th>Comments</th>
            <th>Date Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </section>
  </main>
</div>

<script>
  const msgBox   = document.getElementById('message');
  const gradeForm= document.getElementById('gradeForm');
  const submitBtn= document.getElementById('submitBtn');
  const cancelBtn= document.getElementById('cancelBtn');
  const tbody    = document.querySelector('#gradesTable tbody');

  let editingId = null;

  function showMessage(text, type='success') {
    msgBox.textContent = text;
    msgBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
    msgBox.style.display = 'block';
    setTimeout(() => msgBox.style.display = 'none', 4000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[m]);
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
        if (!data.success) {
          showMessage('Failed to load grades.', 'error');
          return;
        }
        tbody.innerHTML = '';
        if (!data.data || data.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9">No grades found.</td></tr>';
          return;
        }
        data.data.forEach(g => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${g.id}</td>
            <td>${escapeHtml(g.student_name || ('ID ' + g.student_id))}</td>
            <td>${escapeHtml(g.category)}</td>
            <td>${escapeHtml(g.title)}</td>
            <td>${escapeHtml(g.score)}</td>
            <td>${escapeHtml(g.grade || '')}</td>
            <td>${escapeHtml(g.comments || '')}</td>
            <td>${escapeHtml(g.date_added || '')}</td>
            <td>
              <button class="btn-edit" data-id="${g.id}">Edit</button>
              <button class="btn-delete" data-id="${g.id}">Delete</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
        attachRowHandlers();
      })
      .catch(() => showMessage('Error loading grades.', 'error'));
  }

  function attachRowHandlers() {
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        const id = btn.dataset.id;
        fetch(`?action=get&id=${id}`)
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              showMessage('Failed to load grade.', 'error');
              return;
            }
            editingId = id;
            submitBtn.textContent = 'Update Grade';
            cancelBtn.style.display = 'inline-block';
            const g = data.data;
            document.getElementById('grade-id').value   = g.id;
            document.getElementById('student_id').value = g.student_id;
            document.getElementById('category').value   = g.category;
            document.getElementById('title').value      = g.title;
            document.getElementById('score').value      = g.score;
            document.getElementById('grade').value      = g.grade || '';
            document.getElementById('comments').value   = g.comments || '';
          });
      };
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.onclick = e => {
        e.preventDefault();
        if (!confirm('Delete this grade?')) return;
        const id = btn.dataset.id;
        fetch('?action=delete', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showMessage(data.message || 'Deleted.', 'success');
            loadGrades();
            if (editingId === id) clearForm();
          } else {
            showMessage(data.message || 'Failed to delete.', 'error');
          }
        });
      };
    });
  }

  gradeForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new URLSearchParams(new FormData(gradeForm));

    let action = 'create';
    if (editingId) {
      action = 'update';
      formData.append('id', editingId);
    }

    fetch(`?action=${action}`, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showMessage(data.message || 'Saved.', 'success');
        loadGrades();
        clearForm();
      } else {
        showMessage((data.errors && data.errors.join(', ')) || data.message || 'Failed to save.', 'error');
      }
    })
    .catch(() => showMessage('Error saving grade.', 'error'));
  });

  cancelBtn.addEventListener('click', clearForm);

  loadGrades();
</script>
</body>
</html>
