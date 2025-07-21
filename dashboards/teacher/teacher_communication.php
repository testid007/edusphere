<?php

session_start();
require_once '../../includes/db.php';

// Allow both teachers and parents to access
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'parent'])) {
    header('Location: ../../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['teacher_name'] ?? $_SESSION['parent_name'] ?? 'User';
$user_email = $_SESSION['teacher_email'] ?? $_SESSION['parent_email'] ?? 'user@example.com';

// For teachers: get list of parents
// For parents: get list of teachers (or just one teacher if you want)
if ($user_role === 'teacher') {
    $stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE role = 'parent' ORDER BY first_name");
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // For parent, show all teachers (or you can filter to assigned teacher)
    $stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE role = 'teacher' ORDER BY first_name");
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Select contact to chat with - default to first in list or from GET param
$selected_contact_id = intval($_GET['contact_id'] ?? 0);
if ($selected_contact_id === 0 && count($contacts) > 0) {
    $selected_contact_id = $contacts[0]['id'];
}

// Get selected contact info for display
$selected_contact = null;
foreach ($contacts as $c) {
    if ($c['id'] === $selected_contact_id) {
        $selected_contact = $c;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Communication</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/teacher-dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/communication.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .container { display: flex; min-height: 100vh; }
    aside.sidebar { width: 250px; background: #2c3e50; color: white; padding: 1rem; }
    aside.sidebar a { color: #ecf0f1; text-decoration: none; display: block; margin: 10px 0; }
    aside.sidebar a.active { font-weight: bold; }
    main.main { flex: 1; padding: 1rem; display: flex; flex-direction: column; }
    .contact-list { width: 200px; border-right: 1px solid #ccc; overflow-y: auto; }
    .contact-list div { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
    .contact-list div.selected { background-color: #3498db; color: white; font-weight: bold; }
    .chat-container { flex: 1; display: flex; flex-direction: column; }
    .chat-header { padding: 10px; border-bottom: 1px solid #ccc; font-weight: bold; }
    .messages-content { flex: 1; overflow-y: auto; padding: 10px; background: #f4f7f9; }
    .message { max-width: 70%; margin-bottom: 12px; padding: 8px 12px; border-radius: 8px; position: relative; }
    .message.sent { background-color: #3498db; color: white; margin-left: auto; }
    .message.received { background-color: #ecf0f1; color: #333; margin-right: auto; }
    .msg-date { font-size: 0.75rem; opacity: 0.6; margin-top: 4px; }
    .message-form { display: flex; border-top: 1px solid #ccc; }
    .message-form textarea { flex: 1; padding: 8px; font-size: 1rem; border: none; resize: none; }
    .message-form button { padding: 0 20px; background: #2980b9; color: white; border: none; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">
        <img src="../../assets/img/logo.png" alt="Logo" width="40" />
      </div>
      <nav class="nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-assignments.php"><i class="fas fa-tasks"></i> Manage Assignments</a>
        <a href="gradebook.php"><i class="fas fa-book-open"></i> Grade Book</a>
        <a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="communication.php" class="active"><i class="fas fa-comments"></i> Communication</a>
        <a href="../../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </nav>
      <div class="profile">
        <img src="../../assets/img/user.jpg" alt="User" />
        <div class="name"><?= htmlspecialchars($user_name) ?></div>
        <div class="email"><?= htmlspecialchars($user_email) ?></div>
      </div>
    </aside>

    <main class="main">
      <div style="display: flex; height: 100%;">
        <!-- Contact list -->
        <div class="contact-list" id="contactList">
          <?php foreach ($contacts as $contact): ?>
            <div data-contact-id="<?= $contact['id'] ?>"
                 class="<?= $contact['id'] === $selected_contact_id ? 'selected' : '' ?>">
              <?= htmlspecialchars($contact['first_name']) ?> (<?= htmlspecialchars($contact['email']) ?>)
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Chat area -->
        <div class="chat-container" style="flex:1; display:flex; flex-direction: column;">
          <div class="chat-header" id="chatHeader">
            <?php if ($selected_contact): ?>
              Chat with <?= htmlspecialchars($selected_contact['first_name']) ?>
            <?php else: ?>
              Select a contact to start chatting
            <?php endif; ?>
          </div>

          <div class="messages-content" id="messagesContent" tabindex="0" style="outline:none;">
            <!-- Messages will be loaded here -->
          </div>

          <?php if ($selected_contact): ?>
          <form class="message-form" id="messageForm">
            <textarea id="messageInput" placeholder="Type a message..." rows="2" required></textarea>
            <button type="submit">Send</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

<script>
  const contactList = document.getElementById('contactList');
  const messagesContent = document.getElementById('messagesContent');
  const messageForm = document.getElementById('messageForm');
  const messageInput = document.getElementById('messageInput');
  let selectedContactId = <?= $selected_contact_id ?>;
  const currentUserId = <?= $user_id ?>;

  // Load messages function
  async function loadMessages() {
    if (!selectedContactId) {
      messagesContent.innerHTML = '<p>Select a contact to view messages.</p>';
      return;
    }
    const res = await fetch(`fetch_messages.php?other_user_id=${selectedContactId}`);
    const messages = await res.json();

    messagesContent.innerHTML = '';
    messages.forEach(msg => {
      const div = document.createElement('div');
      div.className = msg.sender_id == currentUserId ? 'message sent' : 'message received';
      div.innerHTML = `${escapeHtml(msg.message).replace(/\n/g, '<br>')}<div class="msg-date">${new Date(msg.timestamp).toLocaleString()}</div>`;
      messagesContent.appendChild(div);
    });
    messagesContent.scrollTop = messagesContent.scrollHeight;
  }

  // Escape HTML to prevent XSS
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Handle sending new message
  if (messageForm) {
    messageForm.addEventListener('submit', async e => {
      e.preventDefault();
      const message = messageInput.value.trim();
      if (!message) return;

      const formData = new URLSearchParams();
      formData.append('receiver_id', selectedContactId);
      formData.append('message', message);

      const res = await fetch('send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      });

      const data = await res.json();
      if (data.status === 'success') {
        messageInput.value = '';
        loadMessages();
      } else {
        alert('Error sending message: ' + (data.message || 'Unknown error'));
      }
    });
  }

  // Change selected contact when clicked
  contactList.addEventListener('click', e => {
    const contactDiv = e.target.closest('div[data-contact-id]');
    if (!contactDiv) return;

    const newContactId = parseInt(contactDiv.getAttribute('data-contact-id'));
    if (newContactId === selectedContactId) return;

    // Change selection style
    contactList.querySelectorAll('div').forEach(div => div.classList.remove('selected'));
    contactDiv.classList.add('selected');

    selectedContactId = newContactId;

    // Change chat header text
    document.getElementById('chatHeader').textContent = 'Chat with ' + contactDiv.textContent;

    // Clear message input and reload messages
    if (messageInput) messageInput.value = '';
    loadMessages();
  });

  // Initial load
  loadMessages();

  // Poll for new messages every 5 seconds
  setInterval(loadMessages, 5000);
</script>

</body>
</html>