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

// Fetch tutor info
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tutor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tutor) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$firstName = explode(' ', trim($tutor['name']))[0];

// Handle Accept/Reject actions
$action_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($request_id && is_numeric($request_id) && in_array($action, ['accept', 'reject'])) {
        // Verify request belongs to a course owned by this tutor
        $checkStmt = $pdo->prepare("
            SELECT cr.*, c.course_name, c.course_id 
            FROM course_requests cr
            JOIN courses c ON cr.course_id = c.course_id
            WHERE cr.request_id = ? AND c.tutor_uid = ?
        ");
        $checkStmt->execute([$request_id, $tutor['uid']]);
        $request = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            if ($action === 'accept') {
                // Update request status
                $updateReq = $pdo->prepare("UPDATE course_requests SET status = 'accepted' WHERE request_id = ?");
                $updateReq->execute([$request_id]);

                // Insert enrollment if not already enrolled
                $checkEnroll = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ? AND learner_uid = ?");
                $checkEnroll->execute([$request['course_id'], $request['learner_uid']]);
                if ($checkEnroll->fetchColumn() == 0) {
                    $enroll = $pdo->prepare("INSERT INTO course_enrollments (course_id, learner_uid) VALUES (?, ?)");
                    $enroll->execute([$request['course_id'], $request['learner_uid']]);
                }
                $action_msg = "Request accepted and learner enrolled successfully.";
            } elseif ($action === 'reject') {
                // Update request status to rejected
                $updateReq = $pdo->prepare("UPDATE course_requests SET status = 'rejected' WHERE request_id = ?");
                $updateReq->execute([$request_id]);
                $action_msg = "Request rejected successfully.";
            }
        } else {
            $action_msg = "Invalid request or permission denied.";
        }
    } else {
        $action_msg = "Invalid action or request ID.";
    }
}

// Fetch all requests for this tutor's courses
$requestsStmt = $pdo->prepare("
    SELECT cr.request_id, cr.status, cr.request_date,
           c.course_name,
           s.name AS learner_name
    FROM course_requests cr
    JOIN courses c ON cr.course_id = c.course_id
    JOIN students s ON cr.learner_uid = s.uid
    WHERE c.tutor_uid = ?
    ORDER BY cr.request_date DESC
");
$requestsStmt->execute([$tutor['uid']]);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Course Requests - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<style>
  main {
    max-width: 900px;
    margin: 1em auto;
    background: #e5f4fc;
    padding: 1.5em;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,124,199,0.15);
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1em;
  }
  th, td {
    padding: 0.75em;
    border: 1px solid #007cc7;
    text-align: left;
  }
  th {
    background-color: #007cc7;
    color: white;
  }
  .btn {
    padding: 0.3em 0.7em;
    margin-right: 0.3em;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    color: white;
    font-weight: 600;
  }
  .btn-accept {
    background-color: #28a745;
  }
  .btn-accept:hover {
    background-color: #1e7e34;
  }
  .btn-reject {
    background-color: #dc3545;
  }
  .btn-reject:hover {
    background-color: #bd2130;
  }
  .status-pending {
    color: #ffc107;
    font-weight: 600;
  }
  .status-accepted {
    color: #28a745;
    font-weight: 600;
  }
  .status-rejected {
    color: #dc3545;
    font-weight: 600;
  }
  .message {
    margin-top: 1em;
    font-weight: 600;
  }
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
    <span class="user-name"><?php echo htmlspecialchars($tutor['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="../StudentProfile.php">👤 Profile</a>
  <a href="../lost-found.php">🏷️ Lost &amp; Found</a>
  <a href="../cctv-reporting.php">📹 CCTV Reporting</a>
  <a href="../event-booking.php">📅 Event Booking</a>
  <a href="../learner/learner-dashboard.php">🎓 Learner Panel</a>
  <a href="tutor-courses-list.php">📝 Tutor Courses</a>
</nav>

<main>
  <h2>Course Requests</h2>

  <?php if ($action_msg): ?>
    <div class="message"><?php echo htmlspecialchars($action_msg); ?></div>
  <?php endif; ?>

  <?php if (count($requests) === 0): ?>
    <p>No course requests found.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Course Name</th>
          <th>Learner Name</th>
          <th>Request Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $req): ?>
          <tr>
            <td><?php echo htmlspecialchars($req['course_name']); ?></td>
            <td><?php echo htmlspecialchars($req['learner_name']); ?></td>
            <td><?php echo date("M d, Y H:i", strtotime($req['request_date'])); ?></td>
            <td class="status-<?php echo htmlspecialchars($req['status']); ?>">
              <?php echo ucfirst(htmlspecialchars($req['status'])); ?>
            </td>
            <td>
              <?php if ($req['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>" />
                  <button type="submit" name="action" value="accept" class="btn btn-accept">Accept</button>
                  <button type="submit" name="action" value="reject" class="btn btn-reject">Reject</button>
                </form>
              <?php else: ?>
                <em>No actions available</em>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
