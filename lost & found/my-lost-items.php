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

// Fetch lost items of this student, latest first
$stmt = $pdo->prepare("SELECT lost_id, item_name, lost_date, image, image_type, status, created_at 
                       FROM lost_items 
                       WHERE reporter_uid = ? 
                       ORDER BY lost_id DESC");
$stmt->execute([$student['uid']]);
$lostItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);

// Status mapping
$statusMap = [
    'open' => ['label'=>'Under Process','class'=>'under-process'],
    'found' => ['label'=>'Found','class'=>'found'],
    'closed' => ['label'=>'Not Found','class'=>'not-found']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Lost Items - Campus Connect</title>
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
h2 { text-align:center; color:#007cc7; margin-top:20px; }
table { width:100%; border-collapse:collapse; margin-top:10px; text-align:center; }
th, td { border:1px solid #007cc7; padding:10px; font-size:14px; }
th { background:#e5f4fc; color:#007cc7; }
img.lost-img { width:200px; height:200px; object-fit:cover; border-radius:8px; }


.status-btn { padding:5px 10px; border:none; border-radius:5px; color:#fff; font-weight:600; cursor:default; }
.status-btn.under-process { background:#ffc107; }
.status-btn.found { background:#28a745; }
.status-btn.not-found { background:#dc3545; }
.item-img { width:200px; height:200px; object-fit:cover; border-radius:5px; border:1px solid #ccc; }
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
    
    img.lost-img { width:100%; height:auto; margin:10px 0; border-radius:8px; }

    /* Stack buttons vertically */
    td[data-label="Action"] { display:flex; flex-direction:column; gap:8px; width:100%; }
    td[data-label="Action"] button { width:100%; }
}
</style>
<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let table = document.getElementById("lostTable");
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
        My Reported Lost Items
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()">
        </div>
    </h2>

    <?php if(empty($lostItems)): ?>
        <div class="no-items">You have not reported any lost items yet.</div>
    <?php else: ?>
    <table id="lostTable">
        <thead>
            <tr>
                <th>Waiting SL</th>
                <th>Report Time</th>
                <th>Lost Item Name</th>
                <th>Lost Date</th>
                <th>Image</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($lostItems as $item):
            // Ensure status is valid and exists in statusMap
            $itemStatus = $item['status'] ?? 'open';
            $status = $statusMap[$itemStatus] ?? ['label'=>'Unknown','class'=>'not-found'];
        ?>
            <tr>
                <td><?php echo htmlspecialchars($item['lost_id']); ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo htmlspecialchars($item['lost_date']); ?></td>
                <td>
                    <?php if(!empty($item['image'])): ?>
                        <img class="item-img" src="data:<?php echo $item['image_type']; ?>;base64,<?php echo base64_encode($item['image']); ?>" alt="Lost Item">
                    <?php else: ?>N/A<?php endif; ?>
                </td>
                <td>
                    <button class="status-btn <?php echo $status['class']; ?>"><?php echo $status['label']; ?></button>
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
