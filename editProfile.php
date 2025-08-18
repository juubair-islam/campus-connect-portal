<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch student
$stmt = $pdo->prepare("SELECT email, contact_number FROM students WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

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
            $stmt = $pdo->prepare("UPDATE students SET email=?, contact_number=?, password=? WHERE id=?");
            $stmt->execute([$email, $contact, $hashed, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE students SET email=?, contact_number=? WHERE id=?");
            $stmt->execute([$email, $contact, $_SESSION['user_id']]);
        }
        $_SESSION['message'] = "Profile updated successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: StudentProfile.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Profile - Campus Connect</title>
<link rel="stylesheet" href="css/studprofile.css" />
<link rel="stylesheet" href="css/student.css" />
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
    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<main class="dashboard">
  <section class="profile-section">
    <h2>✏️ Edit Profile</h2>

    <?php if($message): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-profile-form">
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>

        <label>Contact Number:</label>
        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($student['contact_number']); ?>" required>

        <label>New Password (leave blank to keep current):</label>
        <input type="password" name="password" placeholder="Enter new password">

        <button type="submit" class="btn btn-edit">Save Changes</button>
        <a href="StudentProfile.php" class="btn btn-cancel">Cancel</a>
    </form>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>
</body>
</html>
