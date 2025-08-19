<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'administrative_staff' && $_SESSION['role'] !== 'admin')) {
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

// Fetch staff info
$stmt = $pdo->prepare("SELECT full_name FROM administrative_staff WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$firstName = explode(' ', trim($staff['full_name']))[0];

$errors = [];

// Get next serial number for display
$stmt = $pdo->query("SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'found_items'");
$found_id = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $found_date = $_POST['found_date'] ?? date('Y-m-d');
    $location = trim($_POST['location'] ?? '');
    $giver_name = trim($_POST['giver_name'] ?? '');
    $giver_id = trim($_POST['giver_id'] ?? '');
    $giver_contact = trim($_POST['giver_contact'] ?? '');

    $image_data = null;
    $image_type = null;
    if (!empty($_FILES['item_image']['tmp_name'])) {
        $image_type = $_FILES['item_image']['type'];
        $src = file_get_contents($_FILES['item_image']['tmp_name']);

        if (function_exists('imagecreatefromstring')) {
            $img = imagecreatefromstring($src);
            if ($img) {
                $width = imagesx($img);
                $height = imagesy($img);
                $new_width = 200;
                $new_height = 200;
                $tmp_img = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($tmp_img, $img, 0,0,0,0,$new_width,$new_height,$width,$height);
                ob_start();
                if ($image_type === 'image/png') imagepng($tmp_img);
                else imagejpeg($tmp_img, null, 90);
                $image_data = ob_get_clean();
                imagedestroy($img);
                imagedestroy($tmp_img);
            } else {
                $image_data = $src;
            }
        } else {
            $image_data = $src;
        }
    }

    // Validation
    if (!$item_name) $errors[] = "Item Name is required.";
    if (!$description) $errors[] = "Item Description is required.";
    if (!$found_date) $errors[] = "Found Date is required.";
    if (!$location) $errors[] = "Found Location is required.";
    if (!$giver_name) $errors[] = "Found Item Giver Name is required.";
    if (!$giver_contact) $errors[] = "Found Item Giver Contact is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO found_items 
            (finder_uid, item_name, description, found_date, location, image, image_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'unclaimed')");
        try {
            $stmt->execute([
                $_SESSION['user_id'],
                $item_name,
                $description,
                $found_date,
                $location,
                $image_data,
                $image_type
            ]);

            // Redirect to prevent form resubmission (PRG pattern)
            header("Location: staff-found-item-report.php?success=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error adding found item: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Found Item - Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<style>
main { flex:1; max-width:700px; margin:30px auto; padding:0 20px; color:#1e3a5f; }
main h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; text-align:center; }
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }


form { background:#e5f4fc; padding:1.5em; border-radius:8px; box-shadow:0 0 10px rgba(0,124,199,0.15); display:flex; flex-direction:column; gap:1em; }
form label { font-weight:600; margin-bottom:0.3em; }
form input, form textarea { padding:0.6em; border:1px solid #007cc7; border-radius:5px; width:100%; font-size:15px; box-sizing:border-box; }
form input[readonly] { background:#d1e7f7; color:#1e3a5f; }
form button { background:#007cc7; color:white; padding:0.7em 1.5em; border:none; border-radius:5px; cursor:pointer; transition:0.3s; font-weight:600; }
form button:hover { background:#005fa3; }
.error-msg { padding:10px; border-radius:6px; font-size:14px; margin-bottom:15px; background:#ffe6e6; color:#b30000; }
.success-popup { padding:15px; border-radius:6px; font-size:15px; margin-bottom:15px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; text-align:center; }
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }
@media(max-width:768px){ form input, form textarea, form button { font-size:14px; } }
</style>
</head>
<body>

<header class="header">
    <div style="display:flex; align-items:center;">
        <img src="images/logo.png" alt="Campus Connect Logo" class="logo" />
        <div class="title-text">
            <h1>Campus Connect</h1>
            <p class="tagline">Bridge to Your IUB Community</p>
        </div>
    </div>
    <div>
        <span class="user-name"><?php echo htmlspecialchars($staff['full_name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<nav class="top-nav">
  <a href="staff-dashboard.php">Home</a>
  <a href="staff-Profile.php">Profile</a>
  <a href="staff-found-item-report.php"class="active">Found Report</a>
  <a href="lost & found/staff-lost-items.php">Lost Item Reports</a>
  <a href="staff-found-items.php">Found Items</a>
</nav>

<main>
    <h2>Report Found Item</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-msg">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-popup">
            Found item added successfully.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <label for="found_id">Serial Number</label>
        <input type="text" id="found_id" name="found_id" value="<?php echo $found_id; ?>" readonly />

        <label for="found_date">Found Date <sup style="color:red">*</sup></label>
        <input type="date" id="found_date" name="found_date" value="<?php echo htmlspecialchars($_POST['found_date'] ?? date('Y-m-d')); ?>" required />

        <label for="item_name">Item Name <sup style="color:red">*</sup></label>
        <input type="text" id="item_name" name="item_name" value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>" required />

        <label for="description">Item Description <sup style="color:red">*</sup></label>
        <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

        <label for="location">Found Location <sup style="color:red">*</sup></label>
        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required />

        <label for="item_image">Image (optional)</label>
        <input type="file" id="item_image" name="item_image" accept="image/*" />

        <label for="giver_name">Found Item Giver Name <sup style="color:red">*</sup></label>
        <input type="text" id="giver_name" name="giver_name" value="<?php echo htmlspecialchars($_POST['giver_name'] ?? ''); ?>" required />

        <label for="giver_id">IUB ID / Designation</label>
        <input type="text" id="giver_id" name="giver_id" value="<?php echo htmlspecialchars($_POST['giver_id'] ?? ''); ?>" />

        <label for="giver_contact">Contact <sup style="color:red">*</sup></label>
        <input type="text" id="giver_contact" name="giver_contact" value="<?php echo htmlspecialchars($_POST['giver_contact'] ?? ''); ?>" required />

        <button type="submit">Submit Found Item</button>
    </form>
</main>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
