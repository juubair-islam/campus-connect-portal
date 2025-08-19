<?php
session_start();

// Only allow staff or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'administrative_staff' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
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
    die("Database connection failed: " . $e->getMessage());
}

// Fetch staff details
$stmt = $pdo->prepare("SELECT uid, full_name, employee_id, department, contact_number, iub_email, role, created_at
                       FROM administrative_staff
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Extract first name
$firstName = explode(' ', trim($staff['full_name']))[0];

// Flash message
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Delete confirmation
$show_delete_confirm = isset($_GET['confirm_delete']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Staff Profile - Campus Connect</title>
<link rel="stylesheet" href="css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
/* Navbar */
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; gap:10px; }
nav.top-nav a { text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }

/* Dashboard / Profile */
main.dashboard { flex:1; max-width:900px; margin:30px auto 60px auto; padding:0 20px; display:flex; flex-direction:column; gap:20px; color:#1e3a5f; }

.profile-section h2 { color:#007cc7; font-weight:700; margin-bottom:20px; font-size:22px; }
.profile-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius:15px;
    box-shadow:0 8px 20px rgba(0,124,199,0.1);
    padding:25px 35px;
    color:#1e3a5f;
}
.profile-card p { font-size:16px; margin:12px 0; font-weight:500; }
.profile-card p strong { color:#005b9f; width:160px; display:inline-block; }

.profile-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px; }
.profile-actions a { padding:6px 14px; border-radius:6px; font-weight:600; text-decoration:none; margin-left:10px; }
.btn-edit { background-color:#007cc7; color:white; } .btn-edit:hover { background-color:#005f99; }
.btn-delete { background-color:#9b1b14; color:white; } .btn-delete:hover { background-color:#7a130f; }

.alert { padding:12px 20px; margin-bottom:20px; border-radius:8px; }
.alert.warning { background:#ffe5e5; color:#9b1b14; }
.confirm-delete .confirm-buttons { display:flex; gap:10px; margin-top:10px; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

/* Responsive */
@media(max-width:768px){
    .profile-card p { font-size:14px; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-left" style="display:flex; align-items:center; gap:10px;">
    <img src="images/logo.png" alt="Campus Connect Logo" class="logo" />
    <div class="title-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </div>
  <div class="header-right">
    <span class="user-name"><?php echo htmlspecialchars($staff['full_name']); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="staff-dashboard.php">Home</a>
  <a href="staff-Profile.php"class="active">Profile</a>
  <a href="staff-found-item-report.php">Found Report</a>
  <a href="lost & found/staff-lost-items.php">Lost Item Reports</a>
  <a href="staff-found-items.php">Found Items</a>
</nav>


<main class="dashboard">
<section class="profile-section">
    <div class="profile-header">
        <h2>📋 Staff Profile</h2>
        <div class="profile-actions">
            <a href="staff-EditProfile.php" class="btn btn-edit">Edit Profile</a>

        </div>
    </div>

    <?php if($message): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="profile-card">
      <p><strong>Name:</strong> <?php echo htmlspecialchars($staff['full_name']); ?></p>
      <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($staff['employee_id']); ?></p>
      <p><strong>Department:</strong> <?php echo htmlspecialchars($staff['department']); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['iub_email']); ?></p>
      <p><strong>Contact:</strong> <?php echo htmlspecialchars($staff['contact_number']); ?></p>
      <p><strong>Joined On:</strong> <?php echo date("F j, Y", strtotime($staff['created_at'])); ?></p>
    </div>



</section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
