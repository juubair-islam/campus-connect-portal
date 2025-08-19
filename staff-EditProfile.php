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

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch staff info
$stmt = $pdo->prepare("SELECT uid, full_name, iub_email, contact_number FROM administrative_staff WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$firstName = explode(' ', trim($staff['full_name']))[0];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $contact = $_POST['contact_number'];
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } elseif (empty($contact)) {
        $message = "Contact number cannot be empty.";
        $message_type = "error";
    } else {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE administrative_staff SET iub_email=?, contact_number=?, password=? WHERE id=?");
            $stmt->execute([$email, $contact, $hashed, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE administrative_staff SET iub_email=?, contact_number=? WHERE id=?");
            $stmt->execute([$email, $contact, $_SESSION['user_id']]);
        }
        $_SESSION['message'] = "Profile updated successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: staff-Profile.php");
        exit();
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Profile - Campus Connect</title>
<link rel="stylesheet" href="css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

/* Main */
main.dashboard { flex:1; max-width:700px; margin:30px auto 60px auto; padding:0 20px; color:#1e3a5f; }
.profile-section h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; }
.edit-profile-form { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius:15px; padding:25px; display:flex; flex-direction:column; gap:15px; box-shadow:0 8px 20px rgba(0,124,199,0.1); }
.edit-profile-form label { font-weight:600; color:#005b9f; }
.edit-profile-form input { padding:8px 12px; border-radius:6px; border:1px solid #b6e0f7; outline:none; width:100%; }
.btn-edit { background-color:#007cc7; color:white; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; font-weight:600; transition:0.3s; }
.btn-edit:hover { background:#005f99; }
.btn-cancel { text-decoration:none; padding:8px 14px; border-radius:6px; background:#9b1b14; color:white; font-weight:600; text-align:center; display:inline-block; text-align:center; }
.alert.error { background:#ffe5e5; color:#9b1b14; padding:12px 20px; border-radius:8px; }
.alert.success { background:#e5f4fc; color:#007cc7; padding:12px 20px; border-radius:8px; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

/* Responsive */
@media(max-width:768px){
    header.header { flex-direction:column; align-items:flex-start; gap:15px; }
    .header-left { gap:10px; }
    .logo { width:80px; height:50px; }
    .title-text h1 { font-size:22px; }
    .header-right { width:100%; justify-content:space-between; }
    nav.top-nav { justify-content:flex-start; overflow-x:auto; padding:10px; gap:8px; }
    nav.top-nav a { padding:6px 12px; font-size:14px; }
    main.dashboard { margin:20px 15px 40px 15px; padding:0 10px; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-left">
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
    <h2>✏️ Edit Profile</h2>

    <?php if($message): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-profile-form">
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($staff['iub_email']); ?>" required>

        <label>Contact Number:</label>
        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($staff['contact_number']); ?>" required>

        <label>New Password (leave blank to keep current):</label>
        <input type="password" name="password" placeholder="Enter new password">

        <button type="submit" class="btn btn-edit">Save Changes</button>
        <a href="staff-Profile.php" class="btn btn-cancel">Cancel</a>
    </form>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
