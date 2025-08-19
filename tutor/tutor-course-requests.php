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

// Handle AJAX Accept/Reject requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == 1) {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $response = ['success' => false, 'message' => ''];

    if ($request_id && is_numeric($request_id) && in_array($action, ['accept', 'reject'])) {
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
                $updateReq = $pdo->prepare("UPDATE course_requests SET status = 'accepted' WHERE request_id = ?");
                $updateReq->execute([$request_id]);

                $checkEnroll = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = ? AND learner_uid = ?");
                $checkEnroll->execute([$request['course_id'], $request['learner_uid']]);
                if ($checkEnroll->fetchColumn() == 0) {
                    $enroll = $pdo->prepare("INSERT INTO course_enrollments (course_id, learner_uid) VALUES (?, ?)");
                    $enroll->execute([$request['course_id'], $request['learner_uid']]);
                }
                $response['success'] = true;
                $response['status'] = 'accepted';
                $response['message'] = 'Request accepted and learner enrolled.';
            } elseif ($action === 'reject') {
                $deleteReq = $pdo->prepare("DELETE FROM course_requests WHERE request_id = ?");
                $deleteReq->execute([$request_id]);
                $response['success'] = true;
                $response['status'] = 'rejected';
                $response['message'] = 'Request rejected and removed.';
            }
        } else {
            $response['message'] = "Invalid request or permission denied.";
        }
    } else {
        $response['message'] = "Invalid action or request ID.";
    }

    echo json_encode($response);
    exit();
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

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Course Requests - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

main.dashboard { flex:1; max-width:1200px; margin:30px auto 60px auto; padding:20px; color:#1e3a5f; background: #e5f4fc; border-radius:8px; box-shadow:0 0 10px rgba(0,124,199,0.15); box-sizing:border-box; overflow-x:auto; }
.profile-section h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; }

table { width: 100%; border-collapse: collapse; margin-top: 1em; min-width: 600px; }
th, td { padding: 0.75em; border: 1px solid #007cc7; text-align: left; }
th { background-color: #007cc7; color: #fff; font-weight: 600; }

.btn { padding: 0.3em 0.7em; margin-right: 0.3em; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: 600; transition: 0.3s; }
.btn-accept { background-color: #28a745; }
.btn-accept:hover { background-color: #1e7e34; }
.btn-reject { background-color: #dc3545; }
.btn-reject:hover { background-color: #bd2130; }

.status-pending { color: #ffc107; font-weight: 600; }
.status-accepted { color: #28a745; font-weight: 600; }
.status-rejected { color: #dc3545; font-weight: 600; }

.message { margin-top: 1em; font-weight: 600; color: #dc3545; }

.popup-bg { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
.popup { background: #fff; padding: 1.5em; border-radius: 8px; max-width: 400px; text-align: center; box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.popup h3 { margin-top: 0; }
.popup button { margin: 0.5em; cursor: pointer; }

footer.footer { background: #0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

@media(max-width:768px){
    header.header { flex-direction:column; align-items:flex-start; gap:15px; }
    .header-left { gap:10px; }
    .logo { width:80px; height:50px; }
    .title-text h1 { font-size:22px; }
    .header-right { width:100%; justify-content:space-between; }
    nav.top-nav { justify-content:flex-start; overflow-x:auto; padding:10px; gap:8px; flex-wrap: nowrap; }
    nav.top-nav a, nav.top-nav .dropbtn { padding:6px 12px; font-size:14px; }
    main.dashboard { margin:20px 15px 40px 15px; padding:15px; }
    table { min-width:500px; }
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
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="tutor-dashboard.php" class="active">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main class="dashboard">
  <section class="profile-section">
    <h2>📚 Course Requests</h2>

    <?php if (count($requests) === 0): ?>
      <p>No course requests found.</p>
    <?php else: ?>
      <table id="requests-table">
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
            <tr id="req-<?php echo $req['request_id']; ?>">
              <td><?php echo htmlspecialchars($req['course_name']); ?></td>
              <td><?php echo htmlspecialchars($req['learner_name']); ?></td>
              <td><?php echo date("M d, Y H:i", strtotime($req['request_date'])); ?></td>
              <td class="status-<?php echo htmlspecialchars($req['status']); ?>">
                <?php echo ucfirst(htmlspecialchars($req['status'])); ?>
              </td>
              <td>
                <?php if ($req['status'] === 'pending'): ?>
                  <button class="btn btn-accept" onclick="handleAction(<?php echo $req['request_id']; ?>,'accept', this)">Accept</button>
                  <button class="btn btn-reject" onclick="handleAction(<?php echo $req['request_id']; ?>,'reject', this)">Reject</button>
                <?php else: ?>
                  <em>No actions available</em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

<script>
function handleAction(requestId, action, btn) {
    if(action === 'reject' && !confirm('Are you sure you want to reject this request?')) return;

    const formData = new FormData();
    formData.append('ajax', 1);
    formData.append('request_id', requestId);
    formData.append('action', action);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            const row = document.getElementById('req-' + requestId);
            const statusCell = row.querySelector('td:nth-child(4)');
            statusCell.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            statusCell.className = 'status-' + data.status;
            row.querySelector('td:nth-child(5)').innerHTML = '<em>No actions available</em>';
        } else {
            alert(data.message);
        }
    })
    .catch(err => alert('Action failed'));
}
</script>

</body>
</html>
