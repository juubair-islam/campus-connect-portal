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

// Fetch student info
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Fetch ALL found items (latest first)
$stmt = $pdo->prepare("SELECT found_id, item_name, description, found_date, location, image, image_type, created_at 
                       FROM found_items 
                       ORDER BY found_id DESC");
$stmt->execute();
$foundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Found Items - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
body { font-family: Arial, sans-serif; background:#f8fafc; margin:0; display:flex; flex-direction:column; min-height:100vh; }
header.header { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; background:#e5f4fc; flex-wrap:wrap; }
header.header .title-text h1 { margin:0; color:#007cc7; }
header.header .title-text p { margin:0; font-size:0.9em; color:#007cc7; }
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; }
nav.top-nav a { margin-right:15px; text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }
main { flex:1; max-width:1000px; margin:30px auto 60px auto; padding:0 20px; color:#1e3a5f; }
main h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }

.search-box { display:flex; justify-content:flex-end; margin-bottom:10px; }
.search-box input { padding:6px 10px; width:200px; border:1px solid #007cc7; border-radius:5px; }

/* Table */
table { width:100%; border-collapse:collapse; margin-top:10px; text-align:center; }
th, td { border:1px solid #007cc7; padding:10px; font-size:14px; }
th { background:#e5f4fc; color:#007cc7; }
img.item-img { width:200px; height:200px; object-fit:cover; border-radius:8px; }
.no-items { text-align:center; padding:20px; color:#555; }

footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

/* Responsive Table */
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; width:100%; }
    thead { display:none; }
    tr { margin-bottom:15px; border:1px solid #007cc7; border-radius:10px; padding:10px; background:#f5fbff; }
    td { display:flex; flex-direction:column; justify-content:flex-start; align-items:flex-start; padding:10px; border:none; border-bottom:1px solid #007cc7; }
    td:last-child { border-bottom:none; }
    td::before { content: attr(data-label); font-weight:bold; color:#007cc7; margin-bottom:5px; }
    img.item-img { width:100%; height:auto; margin:10px 0; border-radius:8px; }
}
</style>
<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let table = document.getElementById("foundTable");
    let tr = table.getElementsByTagName("tr");
    for (let i=1;i<tr.length;i++){
        let show=false;
        let td=tr[i].getElementsByTagName("td");
        for(let j=0;j<td.length;j++){
            if(td[j].innerText.toLowerCase().includes(input)){
                show=true; break;
            }
        }
        tr[i].style.display = show?"":"none";
    }
}
</script>
</head>
<body>

<header class="header">
    <div style="display:flex; align-items:center;">
        <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" style="height:50px;margin-right:10px;" />
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
    <h2>
        Found Items List
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">
        </div>
    </h2>

    <?php if(empty($foundItems)): ?>
        <div class="no-items">No found items listed yet.</div>
    <?php else: ?>
    <table id="foundTable">
        <thead>
            <tr>
                <th>SL</th>
                <th>Found Date</th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Location</th>
                <th>Image</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($foundItems as $index => $item): ?>
            <tr>
                <td data-label="SL"><?php echo $index+1; ?></td>
                <td data-label="Found Date"><?php echo htmlspecialchars($item['found_date']); ?></td>
                <td data-label="Item Name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td data-label="Description"><?php echo htmlspecialchars($item['description']); ?></td>
                <td data-label="Location"><?php echo htmlspecialchars($item['location']); ?></td>
                <td data-label="Image">
                    <?php if(!empty($item['image'])): ?>
                        <img class="item-img" src="data:<?php echo $item['image_type']; ?>;base64,<?php echo base64_encode($item['image']); ?>" alt="Found Item">
                    <?php else: ?>N/A<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
