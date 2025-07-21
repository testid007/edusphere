<?php
session_start();
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['teacher_email'] ?? 'teacher@example.com';

$mysqli = new mysqli('localhost', 'root', '', 'edusphere');
if ($mysqli->connect_errno) {
    die('Failed to connect to MySQL: ' . $mysqli->connect_error);
}

header('Content-Type: text/html; charset=utf-8');

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    $response = ['success' => false, 'errors' => [], 'message' => '', 'data' => null];
    $action = $_GET['action'];

    if ($action === 'list') {
        $result = $mysqli->query("SELECT * FROM assignments ORDER BY due_date ASC");
        $assignments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $response['success'] = true;
        $response['data'] = $assignments;
        echo json_encode($response);
        exit;
    }

    if ($action === 'get' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $mysqli->prepare("SELECT * FROM assignments WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $assignment = $res->fetch_assoc();
        if ($assignment) {
            $response['success'] = true;
            $response['data'] = $assignment;
        } else {
            $response['errors'][] = 'Assignment not found.';
        }
        echo json_encode($response);
        exit;
    }

    if (in_array($action, ['create', 'update'])) {
        // We expect multipart/form-data for file uploads, so $_POST and $_FILES used here
        $title = trim($_POST['title'] ?? '');
        $due_date = $_POST['due_date'] ?? '';
        $status = $_POST['status'] ?? 'Open';
        $class_name = trim($_POST['class_name'] ?? '');

        if (empty($title)) {
            $response['errors'][] = 'Assignment title is required.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            $response['errors'][] = 'Invalid due date format. Use YYYY-MM-DD.';
        }

        if (!in_array($status, ['Open', 'Closed'])) {
            $response['errors'][] = 'Invalid status value.';
        }

        if (empty($class_name)) {
            $response['errors'][] = 'Class is required.';
        }

        // Handle file upload
        $file_url = '';
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['assignment_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $response['errors'][] = 'File upload error. Code: ' . $file['error'];
            } else {
                // Validate file type and size if needed
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!in_array($file['type'], $allowedTypes)) {
                    $response['errors'][] = 'Only PDF or Word documents are allowed.';
                }
                if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                    $response['errors'][] = 'File size must be under 5MB.';
                }

                if (count($response['errors']) === 0) {
                    // Generate unique filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $newFileName = uniqid('assign_', true) . '.' . $ext;
                    $destination = $uploadDir . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $file_url = 'uploads/' . $newFileName; // relative path for client usage
                    } else {
                        $response['errors'][] = 'Failed to move uploaded file.';
                    }
                }
            }
        } else if ($action === 'update') {
            // On update, if no new file uploaded, keep existing file_url from DB
            if (isset($_POST['existing_file_url'])) {
                $file_url = $_POST['existing_file_url'];
            }
        }

        if (count($response['errors']) === 0) {
            if ($action === 'create') {
                $stmt = $mysqli->prepare("INSERT INTO assignments (title, due_date, status, class_name, file_url) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('sssss', $title, $due_date, $status, $class_name, $file_url);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Assignment created successfully.';
                } else {
                    $response['errors'][] = 'Database error: ' . $mysqli->error;
                }
            } else if ($action === 'update' && isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $stmt = $mysqli->prepare("UPDATE assignments SET title = ?, due_date = ?, status = ?, class_name = ?, file_url = ? WHERE id = ?");
                $stmt->bind_param('sssssi', $title, $due_date, $status, $class_name, $file_url, $id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Assignment updated successfully.';
                } else {
                    $response['errors'][] = 'Database error: ' . $mysqli->error;
                }
            } else {
                $response['errors'][] = 'Missing assignment ID for update.';
            }
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);

        // Delete file physically before deleting DB record
        $stmt = $mysqli->prepare("SELECT file_url FROM assignments WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $assignment = $res->fetch_assoc();
        if ($assignment && !empty($assignment['file_url'])) {
            $filePath = __DIR__ . '/' . $assignment['file_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $mysqli->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Assignment deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete assignment: ' . $mysqli->error]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
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
    /* Form & messages */
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
      transition: border-color 0.3s ease;
    }

    form.assignment-form input[type="text"]:focus,
    form.assignment-form input[type="date"]:focus,
    form.assignment-form select:focus,
    form.assignment-form input[type="file"]:focus {
      outline: none;
      border-color: #1abc9c;
      background-color: #fff;
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
      transition: background-color 0.3s ease;
    }

    form.assignment-form button:hover {
      background-color: #159d85;
    }

    .message {
      padding: 12px 16px;
      margin-bottom: 1rem;
      border-radius: 14px;
      font-size: 15px;
    }

    .assignments-table td.status.open {
      color: #1abc9c; /* soft green */
      font-weight: 700;
    }

    .assignments-table td.status.closed {
      color: #e74c3c; /* soft red */
      font-weight: 700;
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

    .assignments-table a.btn-edit,
    .assignments-table a.btn-delete {
      cursor: pointer;
      text-decoration: none;
      margin-right: 10px;
      font-weight: 600;
      font-size: 15px;
      transition: color 0.3s ease;
    }

    .assignments-table a.btn-edit {
      color: #1abc9c;
    }

    .assignments-table a.btn-edit:hover {
      color: #159d85;
    }

    .assignments-table a.btn-delete {
      color: #e74c3c;
    }

    .assignments-table a.btn-delete:hover {
      color: #c0392b;
    }

    /* Modal styles */
    #assignmentModal {
      display: none;
      position: fixed;
      top: 0; left: 0; right:0; bottom: 0;
      background: rgba(60, 72, 88, 0.3);
      align-items: center;
      justify-content: center;
      z-index: 9999;
      font-family: Arial, sans-serif;
    }

    #assignmentModal .modal-content {
      background: white;
      padding: 20px;
      border-radius: 14px;
      max-width: 500px;
      width: 90%;
      position: relative;
      box-shadow: 0 2px 12px rgba(60, 72, 88, 0.15);
      font-size: 15px;
      color: #111;
      animation: fadeInUp 0.3s ease forwards;
    }

    #assignmentModal .close-btn {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 28px;
      font-weight: 700;
      color: #555;
      cursor: pointer;
      border: none;
      background: none;
      transition: color 0.3s ease;
    }

    #assignmentModal .close-btn:hover {
      color: #1abc9c;
    }

    #assignmentModal a#modalDownload {
      display: inline-block;
      margin-top: 12px;
      color: #1abc9c;
      text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      transition: text-decoration 0.3s ease;
    }

    #assignmentModal a#modalDownload:hover {
      text-decoration: underline;
    }

    /* Animation */
    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(12px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Table styles */
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
      color: #111;
    }

    table.assignments-table th:first-child {
      border-top-left-radius: 14px;
    }

    table.assignments-table th:last-child {
      border-top-right-radius: 14px;
    }

    table.assignments-table tbody tr:nth-child(even) {
      background-color: #f9fafb;
    }

    table.assignments-table tbody tr:hover {
      background-color: #e6f4ea;
      transition: background 0.2s;
    }
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
        <a href="manage-assignments.php" class="active"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
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
        <h2>Manage Assignments</h2>
        <p>Welcome, <?= htmlspecialchars($teacher_name) ?>!</p>
      </header>

      <section class="table-container">

        <div id="message" class="message" style="display:none;"></div>

        <h3 id="form-title">Create New Assignment</h3>

        <form id="assignmentForm" class="assignment-form" enctype="multipart/form-data">
          <input type="hidden" id="assignment-id" name="id" value="" />
          <input type="hidden" id="existing_file_url" name="existing_file_url" value="" />

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
          <select id="class_name" name="class_name" required>
            <option value="Class 1">Class 1</option>
            <option value="Class 2">Class 2</option>
            <option value="Class 3">Class 3</option>
            <!-- Add more as needed -->
          </select>

          <label for="assignment_file">Assignment File (PDF or Word, max 5MB)</label>
          <input type="file" id="assignment_file" name="assignment_file" accept=".pdf,.doc,.docx" />

          <button type="submit" id="submit-btn">Create Assignment</button>
          <button type="button" id="cancel-btn" style="display:none; margin-left:10px;">Cancel</button>
        </form>

        <h3>Assignment List</h3>
        <table class="assignments-table" id="assignments-table">
          <thead>
            <tr>
              <th>Assignment Title</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Class</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Filled dynamically by JS -->
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <!-- Assignment Details Modal -->
  <div id="assignmentModal">
    <div class="modal-content">
      <button class="close-btn" id="closeModal">&times;</button>
      <h3 id="modalTitle"></h3>
      <p><strong>Class:</strong> <span id="modalClass"></span></p>
      <p><strong>Due Date:</strong> <span id="modalDueDate"></span></p>
      <p><strong>Status:</strong> <span id="modalStatus"></span></p>
      <p><strong>Assignment File:</strong></p>
      <p id="modalNoFile">No file available.</p>
      <a href="#" id="modalDownload" target="_blank" rel="noopener noreferrer">Download Assignment</a>
    </div>
  </div>

  <script>
    const messageBox = document.getElementById('message');
    const formTitle = document.getElementById('form-title');
    const assignmentForm = document.getElementById('assignmentForm');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const assignmentsTableBody = document.querySelector('#assignments-table tbody');

    const modal = document.getElementById('assignmentModal');
    const closeModalBtn = document.getElementById('closeModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalClass = document.getElementById('modalClass');
    const modalDueDate = document.getElementById('modalDueDate');
    const modalStatus = document.getElementById('modalStatus');
    const modalDownload = document.getElementById('modalDownload');
    const modalNoFile = document.getElementById('modalNoFile');

    let editingId = null;

    function showMessage(text, type = 'success') {
      messageBox.textContent = text;
      messageBox.className = 'message ' + (type === 'success' ? 'success' : 'error');
      messageBox.style.display = 'block';
      setTimeout(() => { messageBox.style.display = 'none'; }, 4000);
    }

    function clearForm() {
      editingId = null;
      assignmentForm.reset();
      formTitle.textContent = 'Create New Assignment';
      submitBtn.textContent = 'Create Assignment';
      cancelBtn.style.display = 'none';
      document.getElementById('assignment-id').value = '';
      document.getElementById('existing_file_url').value = '';
    }

    function loadAssignments() {
      fetch('?action=list')
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            assignmentsTableBody.innerHTML = '';
            if (data.data.length === 0) {
              assignmentsTableBody.innerHTML = '<tr><td colspan="5">No assignments found.</td></tr>';
              return;
            }
            data.data.forEach(assign => {
              const tr = document.createElement('tr');

              const statusClass = assign.status.toLowerCase();
              tr.innerHTML = `
                <td data-label="Assignment Title">${escapeHtml(assign.title)}</td>
                <td data-label="Due Date">${escapeHtml(assign.due_date)}</td>
                <td data-label="Status" class="status ${statusClass}">${escapeHtml(assign.status)}</td>
                <td data-label="Class">${escapeHtml(assign.class_name || '')}</td>
                <td data-label="Actions">
                  <a href="#" class="btn-edit" data-id="${assign.id}">Edit</a>
                  <a href="#" class="btn-delete" data-id="${assign.id}">Delete</a>
                </td>
              `;
              assignmentsTableBody.appendChild(tr);
            });
            attachTableEventListeners();
          } else {
            showMessage('Failed to load assignments.', 'error');
          }
        })
        .catch(() => showMessage('Error loading assignments.', 'error'));
    }

    function attachTableEventListeners() {
      document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.onclick = function(e) {
          e.preventDefault();
          const id = this.getAttribute('data-id');
          fetch(`?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                editingId = id;
                formTitle.textContent = 'Edit Assignment';
                submitBtn.textContent = 'Update Assignment';
                cancelBtn.style.display = 'inline-block';
                document.getElementById('assignment-id').value = id;
                document.getElementById('title').value = data.data.title;
                document.getElementById('due_date').value = data.data.due_date;
                document.getElementById('status').value = data.data.status;
                document.getElementById('class_name').value = data.data.class_name || 'Class 1';
                document.getElementById('existing_file_url').value = data.data.file_url || '';
                // Clear file input when editing
                document.getElementById('assignment_file').value = '';
              } else {
                showMessage(data.errors.join(', ') || 'Failed to load assignment data.', 'error');
              }
            });
        };
      });

      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.onclick = function(e) {
          e.preventDefault();
          if (!confirm('Are you sure you want to delete this assignment?')) return;
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
              loadAssignments();
              if (editingId === id) clearForm();
            } else {
              showMessage(data.message || 'Failed to delete assignment.', 'error');
            }
          });
        };
      });

      // Add click event on status cells for "Open" assignments
      document.querySelectorAll('#assignments-table tbody tr').forEach(row => {
        const statusCell = row.querySelector('td.status.open');
        if (statusCell) {
          statusCell.style.cursor = 'pointer';
          statusCell.title = 'Click to view assignment details';
          statusCell.onclick = function() {
            const id = row.querySelector('.btn-edit').getAttribute('data-id');
            fetch(`?action=get&id=${id}`)
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  openModal(data.data);
                } else {
                  showMessage('Failed to load assignment details.', 'error');
                }
              });
          };
        }
      });
    }

    assignmentForm.addEventListener('submit', e => {
      e.preventDefault();

      const formData = new FormData(assignmentForm);
      if (editingId) {
        formData.append('id', editingId);
      }

      let action = editingId ? 'update' : 'create';

      fetch(`?action=${action}`, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(resp => {
        if (resp.success) {
          showMessage(resp.message, 'success');
          loadAssignments();
          clearForm();
        } else {
          showMessage((resp.errors && resp.errors.join(', ')) || resp.message || 'Failed to save assignment.', 'error');
        }
      })
      .catch(() => showMessage('Error saving assignment.', 'error'));
    });

    cancelBtn.onclick = () => clearForm();

    // Modal handlers
    function openModal(data) {
      modalTitle.textContent = data.title;
      modalClass.textContent = data.class_name || 'N/A';
      modalDueDate.textContent = data.due_date;
      modalStatus.textContent = data.status;

      if (data.file_url) {
        modalDownload.href = data.file_url;
        modalDownload.style.display = 'inline';
        modalNoFile.style.display = 'none';
      } else {
        modalDownload.style.display = 'none';
        modalNoFile.style.display = 'block';
      }

      modal.style.display = 'flex';
    }

    closeModalBtn.onclick = () => {
      modal.style.display = 'none';
    };

    window.onclick = e => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    };

    // Simple escape to avoid XSS
    function escapeHtml(text) {
      if (!text) return '';
      return text.replace(/[&<>"']/g, function(m) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        }[m];
      });
    }

    // Initial load
    loadAssignments();
  </script>
</body>
</html>
