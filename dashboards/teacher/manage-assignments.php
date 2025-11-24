<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/db.php';

$teacher_name  = $_SESSION['teacher_name']  ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

header('Content-Type: text/html; charset=utf-8');

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ---------- AJAX HANDLERS ----------
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action   = $_GET['action'];
    $response = ['success' => false, 'errors' => [], 'message' => '', 'data' => null];

    try {
        if ($action === 'list') {
            $stmt = $conn->query("SELECT * FROM assignments ORDER BY due_date ASC");
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            echo json_encode($response);
            exit;
        }

        if ($action === 'get' && isset($_GET['id'])) {
            $id   = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
            $stmt->execute([$id]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($assignment) {
                $response['success'] = true;
                $response['data']    = $assignment;
            } else {
                $response['errors'][] = 'Assignment not found.';
            }
            echo json_encode($response);
            exit;
        }

        if (in_array($action, ['create', 'update'], true)) {
            // multipart/form-data for file upload
            $title      = trim($_POST['title'] ?? '');
            $due_date   = $_POST['due_date'] ?? '';
            $status     = $_POST['status'] ?? 'Open';
            $class_name = trim($_POST['class_name'] ?? '');

            if ($title === '')   $response['errors'][] = 'Assignment title is required.';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
                $response['errors'][] = 'Invalid due date format. Use YYYY-MM-DD.';
            }
            if (!in_array($status, ['Open', 'Closed'], true)) {
                $response['errors'][] = 'Invalid status value.';
            }
            if ($class_name === '') $response['errors'][] = 'Class is required.';

            $file_url = '';

            // File upload handling
            if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['assignment_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $response['errors'][] = 'File upload error. Code: ' . $file['error'];
                } else {
                    $allowedTypes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];
                    if (!in_array($file['type'], $allowedTypes, true)) {
                        $response['errors'][] = 'Only PDF or Word documents are allowed.';
                    }
                    if ($file['size'] > 5 * 1024 * 1024) {
                        $response['errors'][] = 'File size must be under 5MB.';
                    }

                    if (empty($response['errors'])) {
                        $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $newFileName = uniqid('assign_', true) . '.' . $ext;
                        $destination = $uploadDir . $newFileName;
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $file_url = 'uploads/' . $newFileName;
                        } else {
                            $response['errors'][] = 'Failed to move uploaded file.';
                        }
                    }
                }
            } elseif ($action === 'update') {
                // Keep existing file if update and no new upload
                $file_url = $_POST['existing_file_url'] ?? '';
            }

            if (empty($response['errors'])) {
                if ($action === 'create') {
                    $stmt = $conn->prepare("
                        INSERT INTO assignments (title, due_date, status, class_name, file_url)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$title, $due_date, $status, $class_name, $file_url])) {
                        $response['success'] = true;
                        $response['message'] = 'Assignment created successfully.';
                    } else {
                        $response['errors'][] = 'Database error while creating assignment.';
                    }
                } else { // update
                    if (empty($_POST['id'])) {
                        $response['errors'][] = 'Missing assignment ID for update.';
                    } else {
                        $id   = (int)$_POST['id'];
                        $stmt = $conn->prepare("
                            UPDATE assignments
                            SET title = ?, due_date = ?, status = ?, class_name = ?, file_url = ?
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$title, $due_date, $status, $class_name, $file_url, $id])) {
                            $response['success'] = true;
                            $response['message'] = 'Assignment updated successfully.';
                        } else {
                            $response['errors'][] = 'Database error while updating assignment.';
                        }
                    }
                }
            }

            echo json_encode($response);
            exit;
        }

        if ($action === 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];

            // Delete file from disk first
            $stmt = $conn->prepare("SELECT file_url FROM assignments WHERE id = ?");
            $stmt->execute([$id]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($assignment && !empty($assignment['file_url'])) {
                $filePath = __DIR__ . '/' . $assignment['file_url'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
            if ($stmt->execute([$id])) {
                echo json_encode(['success' => true, 'message' => 'Assignment deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete assignment.']);
            }
            exit;
        }

        $response['message'] = 'Invalid action.';
        echo json_encode($response);
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
  <title>Manage Assignments</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    form.assignment-form {
      margin-bottom: 2em;
      background: #fff;
      padding: 1rem;
      border-radius: 14px;
      max-width: 500px;
      box-shadow: 0 2px 12px rgba(60, 72, 88, 0.07);
      font-size: 15px;
    }
    form.assignment-form label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 700;
      color: #111;
    }
    form.assignment-form input[type="text"],
    form.assignment-form input[type="date"],
    form.assignment-form select,
    form.assignment-form input[type="file"] {
      width: 100%;
      padding: 0.4rem 0.6rem;
      margin-bottom: 1rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      color: #111;
      background-color: #f9fafb;
    }
    form.assignment-form button {
      background-color: #1abc9c;
      border: none;
      padding: 0.6rem 1.2rem;
      color: white;
      cursor: pointer;
      border-radius: 8px;
      font-weight: 700;
      font-size: 15px;
    }
    form.assignment-form button:hover {
      background-color: #159d85;
    }
    .message {
      padding: 12px 16px;
      margin-bottom: 1rem;
      border-radius: 14px;
      font-size: 15px;
      display:none;
    }
    .message.success {
      background-color: #e2f0e2;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    table.assignments-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 15px;
      margin-top: 1rem;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(60,72,88,0.07);
      overflow: hidden;
    }
    table.assignments-table th, table.assignments-table td {
      border: 1px solid #ddd;
      padding: 12px 16px;
      text-align: center;
    }
    table.assignments-table thead {
      background-color: #e2f0e2;
      font-weight: 700;
    }
    .assignments-table td.status.open { color:#1abc9c;font-weight:700; }
    .assignments-table td.status.closed { color:#e74c3c;font-weight:700; }
    .assignments-table a.btn-edit,
    .assignments-table a.btn-delete {
      cursor: pointer;
      text-decoration: none;
      margin-right: 10px;
      font-weight: 600;
      font-size: 15px;
    }
    .assignments-table a.btn-edit { color:#1abc9c; }
    .assignments-table a.btn-delete { color:#e74c3c; }
  </style>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" />
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-assignments.php" class="active"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
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
        <h2>Manage Assignments</h2>
        <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
      </header>

      <section class="table-container">
        <div id="message" class="message"></div>

        <h3 id="form-title">Create New Assignment</h3>

        <form id="assignmentForm" class="assignment-form" enctype="multipart/form-data">
          <input type="hidden" id="assignment-id" name="id" />
          <input type="hidden" id="existing_file_url" name="existing_file_url" />

          <label for="title">Assignment Title</label>
          <input type="text" id="title" name="title" required />

          <label for="due_date">Due Date</label>
          <input type="date" id="due_date" name="due_date" required />

          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="Open">Open</option>
            <option value="Closed">Closed</option>
          </select>

          <label for="class_name">Class</label>
          <!-- TODO: adapt these class values to your actual class names -->
          <select id="class_name" name="class_name" required>
            <option value="">Select Class</option>
            <option value="Class 1">Class 1</option>
            <option value="Class 2">Class 2</option>
            <option value="Class 3">Class 3</option>
          </select>

          <label for="assignment_file">Assignment File (PDF/Word, max 5MB)</label>
          <input type="file" id="assignment_file" name="assignment_file" accept=".pdf,.doc,.docx" />

          <button type="submit" id="submit-btn">Create Assignment</button>
          <button type="button" id="cancel-btn" style="display:none;margin-left:10px;">Cancel</button>
        </form>

        <h3>Assignment List</h3>
        <table class="assignments-table" id="assignments-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Class</th>
              <th>File</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </section>
    </main>
  </div>

  <script>
    const messageBox = document.getElementById('message');
    const formTitle  = document.getElementById('form-title');
    const assignmentForm = document.getElementById('assignmentForm');
    const submitBtn  = document.getElementById('submit-btn');
    const cancelBtn  = document.getElementById('cancel-btn');
    const tbody      = document.querySelector('#assignments-table tbody');

    let editingId = null;

    function showMessage(text, type='success') {
      messageBox.textContent = text;
      messageBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
      messageBox.style.display = 'block';
      setTimeout(() => { messageBox.style.display = 'none'; }, 4000);
    }

    function clearForm() {
      editingId = null;
      assignmentForm.reset();
      document.getElementById('assignment-id').value = '';
      document.getElementById('existing_file_url').value = '';
      formTitle.textContent = 'Create New Assignment';
      submitBtn.textContent = 'Create Assignment';
      cancelBtn.style.display = 'none';
    }

    function escapeHtml(text) {
      if (!text) return '';
      return text.replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
      })[c]);
    }

    function loadAssignments() {
      fetch('?action=list')
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            showMessage('Failed to load assignments.', 'error');
            return;
          }
          tbody.innerHTML = '';
          if (!data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No assignments found.</td></tr>';
            return;
          }
          data.data.forEach(a => {
            const tr = document.createElement('tr');
            const statusClass = a.status ? a.status.toLowerCase() : '';

            tr.innerHTML = `
              <td>${escapeHtml(a.title)}</td>
              <td>${escapeHtml(a.due_date)}</td>
              <td class="status ${statusClass}">${escapeHtml(a.status)}</td>
              <td>${escapeHtml(a.class_name || '')}</td>
              <td>
                ${a.file_url ? `<a href="${escapeHtml(a.file_url)}" target="_blank">Download</a>` : '—'}
              </td>
              <td>
                <a href="#" class="btn-edit" data-id="${a.id}">Edit</a>
                <a href="#" class="btn-delete" data-id="${a.id}">Delete</a>
              </td>
            `;
            tbody.appendChild(tr);
          });
          attachRowHandlers();
        })
        .catch(() => showMessage('Error loading assignments.', 'error'));
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
                showMessage('Failed to load assignment.', 'error');
                return;
              }
              const a = data.data;
              editingId = id;
              formTitle.textContent = 'Edit Assignment';
              submitBtn.textContent = 'Update Assignment';
              cancelBtn.style.display = 'inline-block';

              document.getElementById('assignment-id').value = id;
              document.getElementById('title').value = a.title || '';
              document.getElementById('due_date').value = a.due_date || '';
              document.getElementById('status').value = a.status || 'Open';
              document.getElementById('class_name').value = a.class_name || '';
              document.getElementById('existing_file_url').value = a.file_url || '';
              document.getElementById('assignment_file').value = '';
            });
        };
      });

      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.onclick = e => {
          e.preventDefault();
          if (!confirm('Delete this assignment?')) return;
          const id = btn.dataset.id;
          fetch('?action=delete', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(id)
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              showMessage(data.message || 'Deleted.', 'success');
              loadAssignments();
              if (editingId === id) clearForm();
            } else {
              showMessage(data.message || 'Failed to delete.', 'error');
            }
          });
        };
      });
    }

    assignmentForm.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(assignmentForm);
      let action = 'create';
      if (editingId) {
        action = 'update';
        formData.append('id', editingId);
      }

      fetch(`?action=${action}`, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showMessage(data.message || 'Saved.', 'success');
          loadAssignments();
          clearForm();
        } else {
          showMessage((data.errors && data.errors.join(', ')) || data.message || 'Failed to save.', 'error');
        }
      })
      .catch(() => showMessage('Error saving assignment.', 'error'));
    });

    cancelBtn.addEventListener('click', clearForm);

    loadAssignments();
  </script>
</body>
</html>
