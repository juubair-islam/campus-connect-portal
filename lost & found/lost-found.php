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
    die("Database connection failed: " . $e->getMessage());
}

// Fetch student info
$stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$firstName = explode(' ', trim($student['name']))[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lost & Found - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

.dashboard { max-width: 1200px; margin: 20px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 20px; }

.dashboard h2 { color: #007cc7; font-size: 1.8em; margin-bottom: 5px; }
.dashboard p { font-size: 1em; color: #333; }

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.glass-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 30px 20px;
    text-align: center;
    text-decoration: none;
    color: #0f172a;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.glass-card:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}

.card-icon { font-size: 40px; margin-bottom: 15px; color: #007cc7; }
.card-title { font-weight: bold; font-size: 1.2em; margin-bottom: 5px; }
.card-desc { font-size: 0.9em; color: #333; }

footer.footer { background: #0f172a; color: #e2e8f0; text-align: center; padding: 20px 0; user-select: none; margin-top: auto; }
</style>
</head>
<body>

<header class="header">
  <div class="header-left">
    <img src="../images/logo.png" alt="Campus Connect Logo" class="logo">
    <div class="title-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </div>
  <div class="header-right">
    <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>


<nav class="top-nav">
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php"class="active">Lost &amp; Found</a>
    <a href="../tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main class="dashboard">
  <h2>Lost & Found Dashboard</h2>

  <div class="options-grid">
    <a href="lost-item-report.php" class="glass-card">
        <div class="card-icon"><i class="fas fa-hand-paper"></i></div>
        <div class="card-title">Report Lost Item</div>
        <div class="card-desc">Submit details of your lost belongings</div>
    </a>

    <a href="my-lost-items.php" class="glass-card">
        <div class="card-icon"><i class="fas fa-search"></i></div>
        <div class="card-title">View Lost Items</div>
        <div class="card-desc">Browse items reported as lost</div>
    </a>

    <a href="stud-found-item-list.php" class="glass-card">
        <div class="card-icon"><i class="fas fa-eye"></i></div>
        <div class="card-title">View Found Items</div>
        <div class="card-desc">See items that have been found on campus</div>
    </a>
  </div>
</main>

<footer class="footer">
  &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
