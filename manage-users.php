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
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteId = intval($_POST['user_id']);
    $deleteRole = $_POST['role'];

    if ($deleteRole === 'Student') {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM administrative_staff WHERE id = ?");
    }

    echo json_encode(['success' => $stmt->execute([$deleteId])]);
    exit();
}

// Fetch students
$students = $pdo->query("SELECT id, uid, iub_id, name, email, contact_number, created_at, 'Student' as role FROM students ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch staff
$staff = $pdo->query("SELECT id, uid, employee_id, full_name as name, iub_email as email, contact_number, created_at, 'Staff' as role FROM administrative_staff ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Merge users
$users = array_merge($students, $staff);

// Recent 5 users
$recentUsers = array_slice($users, 0, 5);

// Total users
$totalUsers = count($users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - Admin | Campus Connect</title>
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

/* Recent Users Card */
/* Recent Users Card */
.recent-card {
    background:white; 
    border:2px solid #007cc7; 
    border-radius:8px; 
    padding:15px; 
    width:300px;          /* fixed width for left alignment */
    box-shadow:0 4px 10px rgba(0,0,0,0.08); 
    text-align:left; 
    margin:20px 0 20px 20px;  /* top/bottom margin + left margin */
}
.recent-card h3 { margin-top:0; color:#007cc7; }
.recent-card ul { list-style:none; padding-left:0; margin:0; }
.recent-card li { padding:6px 0; border-bottom:1px solid #eee; color:#1e3a5f; }
.recent-card li:last-child { border-bottom:none; }

/* Search box */
.search-container { display:flex; justify-content:flex-end; margin:10px 0; max-width:1200px; margin-left:auto; margin-right:auto; }
.search-container input { padding:8px 12px; border:1px solid #007cc7; border-radius:5px; width:250px; outline:none; }

/* Table */
.container { max-width:1200px; margin:0 auto 30px auto; }
.container table { width:100%; border-collapse:collapse; background:white; border:2px solid #007cc7; }
th, td { padding:12px 15px; text-align:left; font-size:14px; border:1px solid #007cc7; }
thead { background:#007cc7; color:white; }
tbody tr:nth-child(even) { background:#e5f4fc; }
tbody tr:hover { background:#d0ebfc; transition:0.3s; }
.delete-btn { color:white; background:#e53935; border:none; padding:6px 10px; border-radius:5px; cursor:pointer; }
.delete-btn:hover { background:#b71c1c; }

/* Total Users Header Row */
.total-row td { color: #007cc7;text-align:center; font-weight:bold; background:#d0ebfc; font-size:16px; }

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
  <a href="manage-users.php" class="active">Manage Users</a>
  <a href="manage-courses.php">Manage Courses</a>
  <a href="found-items.php">Found Items</a>
  <a href="lost-items.php">Lost Items</a>
  <a href="announcements.php">Announcements</a>
</nav>

<!-- Recent Users Card -->
<div class="recent-card">
    <h3>Recent Users</h3>
    <ul>
        <?php foreach($recentUsers as $ru): ?>
            <li><?php echo htmlspecialchars(($ru['role']=='Student'? $ru['iub_id']:$ru['employee_id']) . ' - ' . $ru['name']); ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Search and Table -->
<div class="search-container">
    <input type="text" id="searchBox" placeholder="Search users...">
</div>

<div class="container">
    <div class="success-msg" id="successMsg">User deleted successfully!</div>
    <table id="usersTable">
        <thead>
            <tr class="total-row">
                <td colspan="7">Total Users: <?php echo $totalUsers; ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <th>IUB ID / Employee ID</th>
                <th>Role</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr id="user-<?php echo $user['id']; ?>">
                <td data-label="Name"><?php echo htmlspecialchars($user['name']); ?></td>
                <td data-label="IUB ID / Employee ID"><?php echo htmlspecialchars($user['iub_id'] ?? $user['employee_id'] ?? '-'); ?></td>
                <td data-label="Role"><?php echo htmlspecialchars($user['role']); ?></td>
                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                <td data-label="Contact"><?php echo htmlspecialchars($user['contact_number'] ?? '-'); ?></td>
                <td data-label="Created At"><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></td>
                <td data-label="Action">
                    <button class="delete-btn" onclick="showModal(<?php echo $user['id']; ?>,'<?php echo $user['role']; ?>')">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <p>Are you sure you want to delete this user?</p>
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
let deleteUserId = null;
let deleteUserRole = null;

function showModal(userId, role){
    deleteUserId = userId;
    deleteUserRole = role;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal(){
    document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function(){
    if(deleteUserId){
        fetch('manage-users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_user&user_id=' + deleteUserId + '&role=' + deleteUserRole
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                const row = document.getElementById('user-' + deleteUserId);
                if(row) row.remove();
                const msg = document.getElementById('successMsg');
                msg.style.display = 'block';
                setTimeout(()=>{ msg.style.display = 'none'; }, 3000);
            } else {
                alert('Failed to delete user.');
            }
            closeModal();
        });
    }
});

// Search filter
document.getElementById('searchBox').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>
