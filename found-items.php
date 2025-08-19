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



try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all found items
    $stmt = $pdo->query("
        SELECT found_id, item_name, description, found_date, location, image, image_type, status
        FROM found_items
        ORDER BY created_at DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch 5 most recent found items
    $recentStmt = $pdo->query("
        SELECT found_id, item_name, found_date, image, image_type
        FROM found_items
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentItems = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}

// Handle AJAX delete request
if (isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    $foundId = intval($_POST['found_id']);
    $stmt = $pdo->prepare("DELETE FROM found_items WHERE found_id = ?");
    echo json_encode(['success' => $stmt->execute([$foundId])]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Found Items | Campus Connect</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f7fa; color:#1e3a5f; min-height:100vh; display:flex; flex-direction:column; }

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

/* Container */
.container { max-width:1300px; margin:20px auto; padding:20px; display:flex; flex-direction:column; gap:20px; }

/* Recent Found Items Card */
.recent-card { background:white; border:2px solid #007cc7; border-radius:8px; padding:15px; width:100%; max-width:400px; }
.recent-card h3 { margin-top:0; color:#007cc7; }
.recent-card ul { list-style:none; padding-left:0; margin:0; }
.recent-card li { padding:6px 0; border-bottom:1px solid #eee; color:#1e3a5f; display:flex; align-items:center; gap:8px; }
.recent-card li:last-child { border-bottom:none; }
.recent-card img.thumb { width:40px; height:40px; object-fit:cover; border-radius:5px; }

/* Table Header + Search */
.table-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}
.table-header h2 { text-align:center; flex:1; color:#007cc7; margin:0; }
.search-box { text-align:right; flex:1; }
.search-box input { padding:6px 10px; border:1px solid #007cc7; border-radius:5px; }

/* Table */
table { width:100%; border-collapse:collapse; background:white; border:1px solid #007cc7; }
th, td { padding:10px; border:1px solid #007cc7; font-size:14px; text-align:center; }
thead { background:#007cc7; color:white; }
tbody tr:nth-child(even) { background:#e5f4fc; }
tbody tr:hover { background:#d0ebfc; }
img.thumb { width:60px; height:60px; object-fit:cover; border-radius:5px; }
.delete-btn { color:white; background:#e53935; border:none; padding:6px 10px; border-radius:5px; cursor:pointer; }
.delete-btn:hover { background:#b71c1c; }
.success-msg { display:none; margin-bottom:15px; padding:10px; background:#4caf50; color:white; border-radius:8px; }

/* Modal */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
.modal-content { background:white; padding:20px; border-radius:12px; max-width:400px; width:90%; text-align:center; }
.modal-buttons { margin-top:15px; display:flex; justify-content:space-around; }
.modal-buttons button { padding:8px 14px; border:none; border-radius:6px; cursor:pointer; }
.modal-buttons .cancel-btn { background:#4caf50; color:white; }
.modal-buttons .confirm-btn { background:#e53935; color:white; }

/* Footer */
.footer { background:#007cc7; color:white; text-align:center; padding:10px 0; margin-top:auto; }

/* Responsive */
@media(max-width:768px){
    .recent-card { width:100%; }
    table, thead, tbody, th, td, tr { display:block; }
    thead { display:none; }
    tbody tr { margin-bottom:15px; border:1px solid #007cc7; border-radius:8px; padding:10px; background:white; }
    td { display:flex; justify-content:space-between; padding:10px; border:none; border-bottom:1px solid #ddd; }
    td::before { content: attr(data-label); font-weight:bold; color:#007cc7; flex-basis:40%; padding-right:10px; }
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
  <a href="manage-courses.php">Manage Courses</a>
  <a href="found-items.php"class="active">Found Items</a>
  <a href="lost-items.php">Lost Items</a>
  <a href="announcements.php">Announcements</a>
</nav>
<div class="container">

    <!-- Recent Found Items Card -->
    <div class="recent-card">
        <h3>Recent Found Items</h3>
        <ul>
            <?php if ($recentItems): ?>
                <?php foreach ($recentItems as $ri): ?>
                    <li>
                        <?php if (!empty($ri['image'])): ?>
                            <img src="data:<?= $ri['image_type'] ?>;base64,<?= base64_encode($ri['image']) ?>" class="thumb">
                        <?php endif; ?>
                        <?= htmlspecialchars($ri['found_id'] . ' - ' . $ri['item_name'] . ' (' . $ri['found_date'] . ')') ?>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No recent items</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Table Header + Search -->
    <div class="table-header">
        <div style="flex:1;"></div>
        <h2>All Found Items</h2>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search items...">
        </div>
    </div>

    <div class="success-msg" id="successMsg">Item deleted successfully!</div>

    <table id="foundItemsTable">
        <thead>
            <tr>
                <th>SL</th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Found Date</th>
                <th>Location</th>
                <th>Image</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($items): ?>
                <?php $sl=1; foreach ($items as $item): ?>
                    <tr id="item-<?= $item['found_id'] ?>">
                        <td data-label="SL"><?= $sl++ ?></td>
                        <td data-label="Item Name"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
                        <td data-label="Found Date"><?= htmlspecialchars($item['found_date']) ?></td>
                        <td data-label="Location"><?= htmlspecialchars($item['location']) ?></td>
                        <td data-label="Image">
                            <?php if (!empty($item['image'])): ?>
                                <img src="data:<?= $item['image_type'] ?>;base64,<?= base64_encode($item['image']) ?>" class="thumb">
                            <?php else: ?>
                                No image
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?= htmlspecialchars($item['status']) ?></td>
                        <td data-label="Action">
                            <button class="delete-btn" onclick="showModal(<?= $item['found_id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8">No found items available</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Delete Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <p>Are you sure you want to delete this item?</p>
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
let deleteItemId = null;
function showModal(id){ deleteItemId = id; document.getElementById('deleteModal').style.display='flex'; }
function closeModal(){ document.getElementById('deleteModal').style.display='none'; }

// Delete via AJAX
document.getElementById('confirmDeleteBtn').addEventListener('click', function(){
    if(deleteItemId){
        fetch('found-items.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=delete_item&found_id='+deleteItemId
        })
        .then(res=>res.json())
        .then(data=>{
            if(data.success){
                const row = document.getElementById('item-'+deleteItemId);
                if(row) row.remove();
                const msg = document.getElementById('successMsg');
                msg.style.display='block';
                setTimeout(()=>{ msg.style.display='none'; }, 3000);
            } else { alert('Failed to delete item.'); }
            closeModal();
        });
    }
});

// Search filter
document.getElementById("searchInput").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#foundItemsTable tbody tr");
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});
</script>
</body>
</html>
