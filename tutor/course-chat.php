<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Get current user info
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Validate course_id param
$course_id = $_GET['course_id'] ?? null;
if (!$course_id || !is_numeric($course_id)) {
    die("Invalid course ID.");
}

// Verify user is either tutor or enrolled learner in this course
// Check if user is tutor of the course
$stmt = $pdo->prepare("SELECT tutor_uid, course_code, course_name FROM courses WHERE course_id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    die("Course not found.");
}

$isTutor = ($course['tutor_uid'] === $user['uid']);

// Check if enrolled learner
if (!$isTutor) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ? AND learner_uid = ?");
    $stmt->execute([$course_id, $user['uid']]);
    if ($stmt->fetchColumn() == 0) {
        die("Access denied: You are not enrolled in this course.");
    }
}

// Handle new message submission (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'])) {
    $message_text = trim($_POST['message_text']);
    $receiver_uid = null;

    // Decide receiver UID: 
    // If user is tutor, receiver can be all learners (we'll store receiver_uid = 'all' or NULL)
    // To simplify: we store receiver_uid = NULL (meaning all participants)
    // If user is learner, receiver = tutor's uid

    if ($isTutor) {
        $receiver_uid = null; // broadcast to all learners
    } else {
        $receiver_uid = $course['tutor_uid']; // send message to tutor
    }

    if ($message_text !== '') {
        $insert = $pdo->prepare("INSERT INTO messages (course_id, sender_uid, receiver_uid, message_text) VALUES (?, ?, ?, ?)");
        $insert->execute([$course_id, $user['uid'], $receiver_uid, $message_text]);
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
        exit;
    }
}

// Handle fetching messages (AJAX GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_messages') {
    // Fetch messages visible to this user:
    // For tutor: all messages where course_id = X
    // For learner: messages where (sender or receiver = learner uid) or receiver_uid is NULL (broadcast)
    if ($isTutor) {
        $stmt = $pdo->prepare("SELECT m.*, s.name as sender_name FROM messages m JOIN students s ON m.sender_uid = s.uid WHERE m.course_id = ? ORDER BY m.timestamp ASC");
        $stmt->execute([$course_id]);
    } else {
        $stmt = $pdo->prepare("SELECT m.*, s.name as sender_name FROM messages m JOIN students s ON m.sender_uid = s.uid 
            WHERE m.course_id = ? AND 
            (m.sender_uid = ? OR m.receiver_uid = ? OR m.receiver_uid IS NULL)
            ORDER BY m.timestamp ASC");
        $stmt->execute([$course_id, $user['uid'], $user['uid']]);
    }
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($messages);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Course Chat - <?php echo htmlspecialchars($course['course_code']); ?></title>
<link rel="stylesheet" href="../css/student.css" />
<style>
  main {
    max-width: 700px;
    margin: 1em auto;
    background: #e5f4fc;
    padding: 1.5em;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,124,199,0.15);
    display: flex;
    flex-direction: column;
    height: 80vh;
  }
  #chat-box {
    flex-grow: 1;
    overflow-y: auto;
    border: 1px solid #007cc7;
    border-radius: 5px;
    padding: 1em;
    background: white;
    margin-bottom: 1em;
    font-family: monospace, monospace;
  }
  .message {
    margin-bottom: 0.8em;
  }
  .message .sender {
    font-weight: 700;
    color: #007cc7;
  }
  .message .timestamp {
    font-size: 0.75em;
    color: #555;
    margin-left: 0.5em;
  }
  .message .text {
    margin-top: 0.2em;
  }
  form#chat-form {
    display: flex;
    gap: 0.5em;
  }
  form#chat-form textarea {
    flex-grow: 1;
    padding: 0.6em;
    border: 1px solid #007cc7;
    border-radius: 4px;
    resize: none;
    font-family: inherit;
    font-size: 1em;
    min-height: 40px;
  }
  form#chat-form button {
    background-color: #007cc7;
    border: none;
    color: white;
    padding: 0 1.2em;
    font-size: 1.1em;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }
  form#chat-form button:hover {
    background-color: #005fa3;
  }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function fetchMessages() {
    $.get('course-chat.php', { course_id: <?php echo (int)$course_id; ?>, action: 'fetch_messages' }, function(data) {
        if (Array.isArray(data)) {
            let chatBox = $('#chat-box');
            chatBox.empty();
            data.forEach(msg => {
                let time = new Date(msg.timestamp);
                let timeStr = time.toLocaleString(undefined, { hour12: true, hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short', year: 'numeric' });
                let messageHTML = `
                  <div class="message">
                    <span class="sender">${escapeHtml(msg.sender_name)}</span>
                    <span class="timestamp">${timeStr}</span>
                    <div class="text">${escapeHtml(msg.message_text)}</div>
                  </div>
                `;
                chatBox.append(messageHTML);
            });
            chatBox.scrollTop(chatBox[0].scrollHeight);
        }
    }, 'json');
}

// Simple escape to prevent XSS
function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

$(document).ready(function(){
    fetchMessages();
    setInterval(fetchMessages, 10000); // refresh every 10 seconds

    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        let messageText = $('#message_text').val().trim();
        if (!messageText) return;

        $.post('course-chat.php?course_id=<?php echo (int)$course_id; ?>', { message_text: messageText }, function(response) {
            if (response.success) {
                $('#message_text').val('');
                fetchMessages();
            } else {
                alert(response.error || 'Failed to send message.');
            }
        }, 'json');
    });
});
</script>
</head>
<body>

<header class="header">
  <div class="header-left">
    <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" />
    <div class="title-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </div>
  <div class="header-right">
    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>
<nav class="top-nav">
  <a href="StudentProfile.php" class="active">Profile</a>
  <a href="lost-found.php">Lost &amp; Found</a>
  <a href="cctv-reporting.php">CCTV Reporting</a>
  <a href="event-booking.php">Event Booking</a>

  <!-- Tutor Menu -->
  <div class="dropdown">
    <span class="dropbtn">Tutor ▾</span>
    <div class="dropdown-content">
      <a href="tutor/tutor-courses-list.php">My Courses</a>
      <a href="tutor/tutor-course-requests.php">Course Requests</a>
    </div>
  </div>

  <!-- Learner Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropbtn">Learner▾</a>
    <div class="dropdown-content">
      <a href="learner/learner-courses-list.php">Find Course</a>
      <a href="learner/learner-enrolled-courses.php">Enrolled Courses</a>
    </div>
  </div>
  </div>
</nav>

<main>
  <h2>Course Chat - <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h2>
  
  <div id="chat-box" aria-live="polite" aria-relevant="additions" role="log" tabindex="0">
    <!-- Messages load here -->
  </div>

  <form id="chat-form" autocomplete="off">
    <textarea id="message_text" name="message_text" rows="2" placeholder="Type your message here..." required></textarea>
    <button type="submit" aria-label="Send message">Send</button>
  </form>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
