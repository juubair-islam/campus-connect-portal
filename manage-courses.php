<?php
session_start();

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX delete request
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_course') {
        $courseId = intval($_POST['course_id']);
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
        echo json_encode(['success' => $stmt->execute([$courseId])]);
        exit();
    }
}

// Fetch courses with tutor name
$courses = $pdo->query("
    SELECT c.course_id, c.course_code, c.course_name, c.available_days, c.start_time, c.end_time, c.status, c.created_at,
           s.name AS tutor_name
    FROM courses c
    LEFT JOIN students s ON c.tutor_uid = s.uid
    ORDER BY c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch 5 most recent courses
$recentCourses = $pdo->query("
    SELECT course_code, course_name 
    FROM courses 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Total courses
$totalCourses = count($courses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Courses - Admin | Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f8fa; color:#1e3a5f; min-height:100vh; display:flex; flex-direction:column; }

/* Header */
.header { display:flex; justify-content:space-between; align-items:center; background:#007cc7; color:white; padding:10px 20px; flex-wrap:wrap; }
.header-left { display:flex; align-items:center; gap:15px; }
.header-left .logo { height:50px; }
.header-right { display:flex; align-items:center; gap:15px; }
.header-right .logout-btn { color:white; text-decoration:none; background:#e53935; padding:6px 12px; border-radius:5px; }
.header-right .logout-btn:hover { background:#b71c1c; }

/* Top Nav */
.top-nav { display:flex; gap:20px; background:#e5f4fc; padding:10px 20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); flex-wrap:wrap; }
.top-nav a { text-decoration:none; color:#007cc7; font-weight:600; padding:8px 12px; border-radius:6px; transition:0.3s; }
.top-nav a:hover, .top-nav a.active { background:#007cc7; color:white; }

/* Layout for recent courses + table */
.main-content { display:flex; gap:20px; max-width:1200px; margin:20px auto; flex-wrap:wrap; }

/* Recent Courses Card */
.recent-card { background:white; border:2px solid #007cc7; border-radius:8px; padding:15px; width:300px; box-shadow:0 4px 10px rgba(0,0,0,0.08); text-align:left; }
.recent-card h3 { margin-top:0; color:#007cc7; }
.recent-card ul { list-style:none; padding-left:0; margin:0; }
.recent-card li { padding:6px 0; border-bottom:1px solid #eee; color:#1e3a5f; }
.recent-card li:last-child { border-bottom:none; }

/* Table Container */
.table-container { flex:1; }

/* Search box */
.search-container { display:flex; justify-content:flex-end; margin-bottom:10px; }
.search-container input { padding:8px 12px; border:1px solid #007cc7; border-radius:5px; width:250px; outline:none; }

/* Table */
.container table { width:100%; border-collapse:collapse; background:white; border:2px solid #007cc7; }
th, td { padding:12px 15px; text-align:left; font-size:14px; border:1px solid #007cc7; }
thead { background:#007cc7; color:white; }
tbody tr:nth-child(even) { background:#e5f4fc; }
tbody tr:hover { background:#d0ebfc; transition:0.3s; }
.delete-btn { color:white; background:#e53935; border:none; padding:6px 10px; border-radius:5px; cursor:pointer; }
.delete-btn:hover { background:#b71c1c; }

/* Total Courses Header Row */
.total-row td { color: #007cc7; text-align:center; font-weight:bold; background:#d0ebfc; font-size:16px; }

/* Modal */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
.modal-content { background:white; padding:20px; border-radius:12px; max-width:400px; width:90%; text-align:center; }
.modal-buttons { margin-top:15px; display:flex; justify-content:space-around; }
.modal-buttons button { padding:8px 14px; border:none; border-radius:6px; cursor:pointer; }
.modal-buttons .cancel-btn { background:#4caf50; color:white; }
.modal-buttons .confirm-btn { background:#e53935; color:white; }

.success-msg { display:none; margin-bottom:15px; padding:10px; background:#4caf50; color:white; border-radius:8px; }

/* Responsive */
@media(max-width:768px){
    .main-content { flex-direction:column; }
    .recent-card { width:100%; margin-bottom:15px; }
    .search-container { justify-content:center; margin-bottom:10px; }
    table, thead, tbody, th, td, tr { display:block; }
    thead { display:none; }
    tbody tr { margin-bottom:15px; border:1px solid #007cc7; border-radius:8px; padding:10px; background:white; }
    td { display:flex; justify-content:space-between; padding:10px; border:none; border-bottom:1px solid #ddd; }
    td::before { 
        content: attr(data-label); 
        font-weight:bold; 
        color:#007cc7; 
        flex-basis:40%; 
        padding-right:10px;
    }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-left">
    <img src="images/logo.png" alt="Campus Connect Logo" class="logo">
    <div class="title-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </div>
  <div class="header-right">
    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="admin-dashboard.php">Dashboard</a>
  <a href="manage-users.php">Manage Users</a>
  <a href="manage-courses.php"class="active">Manage Courses</a>
  <a href="found-items.php">Found Items</a>
  <a href="lost-items.php">Lost Items</a>
  <a href="announcements.php">Announcements</a>
</nav>
<div class="main-content">

    <!-- Recent Courses Card -->
    <div class="recent-card">
        <h3>Recent Courses</h3>
        <ul>
            <?php foreach($recentCourses as $rc): ?>
                <li><?php echo htmlspecialchars($rc['course_code'] . ' - ' . $rc['course_name']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Courses Table -->
    <div class="table-container">
        <div class="search-container">
            <input type="text" id="searchBox" placeholder="Search courses...">
        </div>
        <div class="success-msg" id="successMsg">Course deleted successfully!</div>
        <div class="container">
            <table id="coursesTable">
                <thead>
                    <tr class="total-row">
                        <td colspan="9">Total Courses: <?php echo $totalCourses; ?></td>
                    </tr>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Tutor Name</th>
                        <th>Days</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($courses as $c): ?>
                    <tr id="course-<?php echo $c['course_id']; ?>">
                        <td data-label="Course Code"><?php echo htmlspecialchars($c['course_code']); ?></td>
                        <td data-label="Course Name"><?php echo htmlspecialchars($c['course_name']); ?></td>
                        <td data-label="Tutor Name"><?php echo htmlspecialchars($c['tutor_name'] ?? '-'); ?></td>
                        <td data-label="Days"><?php echo htmlspecialchars($c['available_days']); ?></td>
                        <td data-label="Start Time"><?php echo htmlspecialchars($c['start_time']); ?></td>
                        <td data-label="End Time"><?php echo htmlspecialchars($c['end_time']); ?></td>
                        <td data-label="Status"><?php echo htmlspecialchars($c['status']); ?></td>
                        <td data-label="Created At"><?php echo date('d M Y, H:i', strtotime($c['created_at'])); ?></td>
                        <td data-label="Action">
                            <button class="delete-btn" onclick="showModal(<?php echo $c['course_id']; ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <p>Are you sure you want to delete this course?</p>
    <div class="modal-buttons">
      <button class="cancel-btn" onclick="closeModal()">Cancel</button>
      <button class="confirm-btn" id="confirmDeleteBtn">Delete</button>
    </div>
  </div>
</div>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

<script>
let deleteCourseId = null;

function showModal(courseId){
    deleteCourseId = courseId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal(){
    document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function(){
    if(deleteCourseId){
        fetch('manage-courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_course&course_id=' + deleteCourseId
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                const row = document.getElementById('course-' + deleteCourseId);
                if(row) row.remove();
                const msg = document.getElementById('successMsg');
                msg.style.display = 'block';
                setTimeout(()=>{ msg.style.display = 'none'; }, 3000);
            } else {
                alert('Failed to delete course.');
            }
            closeModal();
        });
    }
});

// Search filter
document.getElementById('searchBox').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#coursesTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>
