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

$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$firstName = explode(' ', trim($student['name']))[0];
$errors = [];
$success = false;

// Get next serial number for display
$stmt = $pdo->query("SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'lost_items'");
$lost_id = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lost_date = $_POST['lost_date'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    $image_data = null;
    $image_type = null;
    if (!empty($_FILES['item_image']['tmp_name'])) {
        $image_data = file_get_contents($_FILES['item_image']['tmp_name']);
        $image_type = $_FILES['item_image']['type'];
    }

    // Validation: all required except image
    if (!$item_name) $errors[] = "Item Name is required.";
    if (!$description) $errors[] = "Item Description is required.";
    if (!$lost_date) $errors[] = "Lost Date is required.";
    if (!$location) $errors[] = "Location is required.";
    if (!$contact_number) $errors[] = "Contact Number is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO lost_items 
            (reporter_uid, item_name, description, lost_date, location, contact_number, image, image_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                $student['uid'],
                $item_name,
                $description,
                $lost_date,
                $location,
                $contact_number,
                $image_data,
                $image_type
            ]);
            // Redirect with success flag (Post/Redirect/Get)
            header("Location: lost-item-report.php?success=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error reporting lost item: " . $e->getMessage();
        }
    }
}

// Check if redirected after successful submission
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
    $_POST = [];
    // Refresh lost_id for next entry
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'lost_items'");
    $lost_id = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Report Lost Item - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; }
nav.top-nav a { margin-right:15px; text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }

main { flex:1; max-width:700px; margin:30px auto 60px auto; padding:0 20px; color:#1e3a5f; }
main h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; text-align:center; }
form { background:#e5f4fc; padding:1.5em; border-radius:8px; box-shadow:0 0 10px rgba(0,124,199,0.15); display:flex; flex-direction:column; gap:1em; }
form label { font-weight:600; margin-bottom:0.3em; }
form input, form textarea { padding:0.6em; border:1px solid #007cc7; border-radius:5px; width:100%; font-size:15px; box-sizing:border-box; }
form input[readonly] { background:#d1e7f7; color:#1e3a5f; }
form button { background:#007cc7; color:white; padding:0.7em 1.5em; border:none; border-radius:5px; cursor:pointer; transition:0.3s; font-weight:600; }
form button:hover { background:#005fa3; }

.error-msg { padding:10px; border-radius:6px; font-size:14px; margin-bottom:15px; background:#ffe6e6; color:#b30000; }
.success-popup { padding:15px; border-radius:6px; font-size:15px; margin-bottom:15px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; text-align:center; }

footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

@media(max-width:768px){
    nav.top-nav { justify-content:flex-start; overflow-x:auto; padding:10px; gap:8px; }
    nav.top-nav a { padding:6px 12px; font-size:14px; }
}
</style>
</head>
<body>

<header class="header">
    <div style="display:flex; align-items:center;">
        <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" />
        <div class="title-text">
            <h1>Campus Connect</h1>
            <p class="tagline">Bridge to Your IUB Community</p>
        </div>
    </div>
    <div>
        <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<nav class="top-nav">
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php" class="active">Lost &amp; Found</a>
    <a href="../tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main>
    <h2>Report Lost Item</h2>

    <?php if ($errors): ?>
        <div class="error-msg">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-popup">
            We received your report successfully. We will notify you if we found the item. Thank you.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <label for="lost_id">Serial Number</label>
        <input type="text" id="lost_id" name="lost_id" value="<?php echo $lost_id; ?>" readonly />

        <label for="item_name">Item Name <sup style="color:red">*</sup></label>
        <input type="text" id="item_name" name="item_name" required value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" />

        <label for="description">Item Description <sup style="color:red">*</sup></label>
        <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

        <label for="lost_date">Lost Date <sup style="color:red">*</sup></label>
        <input type="date" id="lost_date" name="lost_date" required value="<?php echo htmlspecialchars($_POST['lost_date'] ?? ''); ?>" />

        <label for="location">Location <sup style="color:red">*</sup></label>
        <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" />

        <label for="contact_number">Contact Number <sup style="color:red">*</sup></label>
        <input type="text" id="contact_number" name="contact_number" required value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" />

        <label for="item_image">Image (optional)</label>
        <input type="file" id="item_image" name="item_image" accept="image/*" />

        <button type="submit">Submit Report</button>
    </form>
</main>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
