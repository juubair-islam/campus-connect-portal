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

// Get logged in user's UID and name
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');

// Query to get all courses:
// - NOT created by current user (c.tutor_uid != currentUser['uid'])
// - NOT enrolled by current user (ce.enrollment_id IS NULL)
// - Include request status from course_requests if exists
$sql = "
SELECT c.course_id, c.course_code, c.course_name, c.available_days, c.start_time, c.end_time, c.description,
       s.name AS tutor_name,
       cr.status AS request_status,
       ce.enrollment_id
FROM courses c
JOIN students s ON c.tutor_uid = s.uid
LEFT JOIN course_requests cr ON cr.course_id = c.course_id AND cr.learner_uid = :current_uid
LEFT JOIN course_enrollments ce ON ce.course_id = c.course_id AND ce.learner_uid = :current_uid
WHERE c.tutor_uid != :current_uid
  AND ce.enrollment_id IS NULL
";

$params = ['current_uid' => $currentUser['uid']];

if ($search !== '') {
    $sql .= " AND (c.course_code LIKE :search OR c.course_name LIKE :search OR c.description LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Available Courses - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<style>
/* --- Styles preserved from original --- */


nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

.dashboard { max-width: 1200px; margin: 20px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 20px; }

.today-courses {
    background: #e5f4fc;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,124,199,0.1);
    font-size: 1em;
    color: #007cc7;
}
.today-courses strong { display: block; margin-bottom: 8px; }

.glass-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
.glass-card { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 30px 20px; text-align: center; text-decoration: none; color: #0f172a; transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; }
.glass-card:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }

.card-icon { font-size: 40px; margin-bottom: 15px; color: #007cc7; }
.card-title { font-weight: bold; font-size: 1.2em; margin-bottom: 5px; }
.card-desc { font-size: 0.9em; color: #333; }

.notification-badge {
    position: absolute;
    top: 10px;
    right: 15px;
    background: #dc3545;
    color: #fff;
    font-size: 0.8em;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 12px;
}


main {
    max-width: 1000px;
    margin: 1em auto;
    background: #e5f4fc;
    padding: 1.5em;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,124,199,0.15);
}
h2 { color: #007cc7; }
form.search-form { margin-bottom: 1em; text-align: right; }
form.search-form input[type="text"] { padding: 0.4em 0.6em; border: 1px solid #007cc7; border-radius: 4px; font-size: 1em; width: 280px; }
form.search-form button { background-color: #007cc7; border: none; color: white; padding: 0.5em 1em; font-size: 1em; border-radius: 4px; cursor: pointer; margin-left: 0.5em; transition: background-color 0.3s ease; }
form.search-form button:hover { background-color: #005fa3; }
table { width: 100%; border-collapse: collapse; margin-top: 0.3em; font-size: 0.95em; }
th, td { padding: 0.7em; border: 1px solid #007cc7; vertical-align: top; text-align: left; }
th { background-color: #007cc7; color: white; }
.no-courses { font-style: italic; color: #555; margin-top: 1em; }
button.action-btn { background-color: #007cc7; border: none; color: white; padding: 0.35em 0.8em; font-size: 0.9em; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease; }
button.action-btn:hover { background-color: #005fa3; }
button.cancel-btn { background-color: crimson; }
button.cancel-btn:hover { background-color: darkred; }
button.pending-btn { background-color: orange; cursor: default; }
button.pending-btn:hover { background-color: orange; }

/* Modal styles */
.modal-overlay { display: none; position: fixed; z-index: 9999; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
.modal { background: white; max-width: 500px; width: 90%; padding: 1.5em; border-radius: 8px; box-shadow: 0 0 15px rgba(0,124,199,0.3); }
.modal h3 { margin-top: 0; color: #007cc7; }
.modal p { margin: 0.3em 0; }
.modal-buttons { margin-top: 1.2em; text-align: right; }
.modal-buttons button { margin-left: 0.7em; padding: 0.4em 1em; border-radius: 5px; border: none; font-size: 1em; cursor: pointer; }
.modal-buttons .btn-cancel { background: #ccc; color: #333; }
.modal-buttons .btn-confirm { background: #007cc7; color: white; }
.modal-buttons .btn-confirm:hover { background: #005fa3; }

/* Message box */
#messageBox { display: none; position: fixed; top: 15px; right: 15px; background: #007cc7; color: white; padding: 1em 1.5em; border-radius: 6px; box-shadow: 0 0 10px rgba(0,124,199,0.7); z-index: 11000; font-weight: 600; }
#messageBox.error { background: #c0392b; }

footer.footer { background: #0f172a; color: #e2e8f0; text-align: center; padding: 20px 0; user-select: none; margin-top: auto; }

</style>
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
    <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>
<nav class="top-nav">
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="../tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php"class="active">Learner Panel</a>
</nav>
<main>
  <h2>Available Courses to Join</h2>

  <form class="search-form" method="GET" action="">
    <input 
      type="text" 
      name="search" 
      placeholder="Search by Course Code, Name or Description..." 
      value="<?php echo htmlspecialchars($search); ?>" 
      autocomplete="off" 
    />
    <button type="submit">Search</button>
  </form>

  <?php if (empty($courses)): ?>
    <p class="no-courses">No courses found<?php echo $search ? " matching '" . htmlspecialchars($search) . "'" : ""; ?>.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Name</th>
          <th>Available Days</th>
          <th>Available Time</th>
          <th>Description</th>
          <th>Tutor</th>
          <th>Request Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($courses as $course): ?>
        <tr data-course='<?php echo json_encode($course, JSON_HEX_APOS|JSON_HEX_QUOT); ?>'>
          <td><?php echo htmlspecialchars($course['course_code']); ?></td>
          <td><?php echo htmlspecialchars($course['course_name']); ?></td>
          <td><?php echo htmlspecialchars(str_replace(',', ', ', $course['available_days'])); ?></td>
          <td><?php 
            echo htmlspecialchars(
              date('h:i A', strtotime($course['start_time'])) . " - " . 
              date('h:i A', strtotime($course['end_time']))
            ); 
          ?></td>
          <td><?php echo nl2br(htmlspecialchars($course['description'])); ?></td>
          <td><?php echo htmlspecialchars($course['tutor_name']); ?></td>
          <td>
            <?php
              if ($course['request_status'] === 'pending') {
                echo '<span style="color:orange;font-weight:600;">Pending</span>';
              } elseif (!empty($course['enrollment_id'])) {
                echo '<span style="color:green;font-weight:600;">Accepted</span>';
              } else {
                echo '<span style="color:gray;">None</span>';
              }
            ?>
          </td>
          <td>
            <?php
              if ($course['request_status'] === 'pending') {
                  echo '<button class="action-btn cancel-request-btn" data-course-id="'.$course['course_id'].'">Cancel Request</button>';
              } elseif (!empty($course['enrollment_id'])) {
                  echo '<em>Enrolled</em>';
              } else {
                  echo '<button class="action-btn send-request-btn" data-course-id="'.$course['course_id'].'">Send Request</button>';
              }
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

<!-- Send Request Modal -->
<div class="modal-overlay" id="send-request-modal" role="dialog" aria-modal="true" aria-labelledby="send-modal-title" tabindex="-1">
  <div class="modal">
    <h3 id="send-modal-title">Confirm Course Request</h3>
    <div id="send-modal-content"></div>
    <div class="modal-buttons">
      <button class="btn-cancel" id="send-modal-cancel-btn" type="button">Cancel</button>
      <button class="btn-confirm" id="send-modal-confirm-btn" type="button">Send Request</button>
    </div>
  </div>
</div>

<!-- Cancel Request Modal -->
<div class="modal-overlay" id="cancel-request-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title" tabindex="-1">
  <div class="modal">
    <h3 id="cancel-modal-title">Cancel Course Request</h3>
    <p>Are you sure you want to cancel your request?</p>
    <div class="modal-buttons">
      <button class="btn-cancel" id="cancel-modal-cancel-btn" type="button">No</button>
      <button class="btn-confirm" id="cancel-modal-confirm-btn" type="button">Yes, Cancel Request</button>
    </div>
  </div>
</div>

<!-- Message Box -->
<div id="messageBox"></div>

<script>
// --- JS preserved from original ---
const sendModal = document.getElementById('send-request-modal');
const sendModalContent = document.getElementById('send-modal-content');
const sendCancelBtn = document.getElementById('send-modal-cancel-btn');
const sendConfirmBtn = document.getElementById('send-modal-confirm-btn');

const cancelModal = document.getElementById('cancel-request-modal');
const cancelCancelBtn = document.getElementById('cancel-modal-cancel-btn');
const cancelConfirmBtn = document.getElementById('cancel-modal-confirm-btn');

const messageBox = document.getElementById('messageBox');

let currentCourseId = null;
let currentActionButton = null;

function showMessage(text, isError = false) {
    messageBox.textContent = text;
    messageBox.className = '';
    if(isError) messageBox.classList.add('error');
    messageBox.style.display = 'block';
    setTimeout(() => { messageBox.style.display = 'none'; }, 4000);
}

function formatTime(t) {
    const dt = new Date(`1970-01-01T${t}Z`);
    if (isNaN(dt)) return t;
    return dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true});
}

// Send Request button click: open send modal
document.querySelectorAll('.send-request-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        const course = JSON.parse(tr.getAttribute('data-course'));
        currentCourseId = course.course_id;
        currentActionButton = btn;

        sendModalContent.innerHTML = `
            <p><strong>Course Code:</strong> ${course.course_code}</p>
            <p><strong>Course Name:</strong> ${course.course_name}</p>
            <p><strong>Available Days:</strong> ${course.available_days.replace(/,/g, ', ')}</p>
            <p><strong>Available Time:</strong> ${formatTime(course.start_time)} - ${formatTime(course.end_time)}</p>
            <p><strong>Description:</strong><br>${course.description.replace(/\n/g, '<br>')}</p>
            <p><strong>Tutor:</strong> ${course.tutor_name}</p>
        `;
        sendModal.style.display = 'flex';
        sendModal.focus();
    });
});

sendCancelBtn.addEventListener('click', () => {
    sendModal.style.display = 'none';
    currentCourseId = null;
    currentActionButton = null;
});

sendConfirmBtn.addEventListener('click', () => {
    if (!currentCourseId) return;

    sendConfirmBtn.disabled = true;
    fetch('course-request-action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'send', course_id: currentCourseId })
    })
    .then(res => res.json())
    .then(data => {
        sendConfirmBtn.disabled = false;
        sendModal.style.display = 'none';

        if (data.success) {
            currentActionButton.textContent = 'Pending';
            currentActionButton.disabled = true;
            currentActionButton.classList.add('pending-btn');
            showMessage(data.message);
        } else {
            showMessage(data.message || 'Failed to send request', true);
        }
        currentCourseId = null;
        currentActionButton = null;
    })
    .catch(() => {
        sendConfirmBtn.disabled = false;
        sendModal.style.display = 'none';
        showMessage('Network error. Please try again.', true);
        currentCourseId = null;
        currentActionButton = null;
    });
});

// Cancel Request modal
document.querySelectorAll('.cancel-request-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        currentCourseId = btn.getAttribute('data-course-id');
        currentActionButton = btn;
        cancelModal.style.display = 'flex';
        cancelModal.focus();
    });
});

cancelCancelBtn.addEventListener('click', () => {
    cancelModal.style.display = 'none';
    currentCourseId = null;
    currentActionButton = null;
});

cancelConfirmBtn.addEventListener('click', () => {
    if (!currentCourseId) return;

    cancelConfirmBtn.disabled = true;
    fetch('course-request-action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'cancel', course_id: currentCourseId })
    })
    .then(res => res.json())
    .then(data => {
        cancelConfirmBtn.disabled = false;
        cancelModal.style.display = 'none';

        if (data.success) {
            currentActionButton.textContent = 'Send Request';
            currentActionButton.disabled = false;
            currentActionButton.classList.remove('pending-btn');
            showMessage(data.message);
        } else {
            showMessage(data.message || 'Failed to cancel request', true);
        }
        currentCourseId = null;
        currentActionButton = null;
    })
    .catch(() => {
        cancelConfirmBtn.disabled = false;
        cancelModal.style.display = 'none';
        showMessage('Network error. Please try again.', true);
        currentCourseId = null;
        currentActionButton = null;
    });
});

// Close modals if clicking outside modal
window.addEventListener('click', e => {
    if(e.target === sendModal) {
        sendModal.style.display = 'none';
        currentCourseId = null;
        currentActionButton = null;
    }
    if(e.target === cancelModal) {
        cancelModal.style.display = 'none';
        currentCourseId = null;
        currentActionButton = null;
    }
});
</script>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
