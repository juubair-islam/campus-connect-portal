<?php
session_start();

// Only administrative_staff or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'administrative_staff' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit();
}

// DB Connection
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// -----------------------
// Handle AJAX Mark Claimed
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['found_id']) && isset($_POST['action'])) {
    $found_id = $_POST['found_id'];
    $action = $_POST['action'];

    if($action === 'claim' && isset($_POST['owner_name'], $_POST['owner_iub_id'], $_POST['owner_contact'])){
        // Insert collection record
        $stmt = $pdo->prepare("INSERT INTO found_item_collections (found_id, owner_name, owner_iub_id, owner_contact) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $found_id,
            $_POST['owner_name'],
            $_POST['owner_iub_id'],
            $_POST['owner_contact']
        ]);

        // Update found item status
        $stmt2 = $pdo->prepare("UPDATE found_items SET status='claimed' WHERE found_id=?");
        $stmt2->execute([$found_id]);
        echo 'success';
    } else {
        echo 'failed';
    }
    exit();
}

// -----------------------
// Handle AJAX Details Popup
// -----------------------
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['details_id'])){
    $found_id = $_POST['details_id'];

    $stmt = $pdo->prepare("
        SELECT f.item_name, f.status, c.owner_name, c.owner_iub_id, c.owner_contact, c.collected_at
        FROM found_items f
        LEFT JOIN found_item_collections c ON f.found_id = c.found_id
        WHERE f.found_id=:found_id
        ORDER BY c.collection_id DESC LIMIT 1
    ");
    $stmt->execute(['found_id'=>$found_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if($res){
        echo json_encode(['success'=>true,'data'=>$res]);
    } else {
        echo json_encode(['success'=>false]);
    }
    exit();
}

// -----------------------
// Fetch staff info
// -----------------------
$stmt = $pdo->prepare("SELECT uid, full_name FROM administrative_staff WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$staff){
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// -----------------------
// Fetch Found Items (only added by staff)
// -----------------------
$stmt = $pdo->query("
    SELECT f.found_id, f.item_name, f.category, f.found_date, f.location, f.image, f.image_type, f.status,
           s.full_name AS finder_name
    FROM found_items f
    LEFT JOIN administrative_staff s ON f.finder_uid = s.uid
    WHERE f.finder_uid IS NOT NULL
    ORDER BY f.found_id DESC
");
$foundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Found Items - Campus Connect</title>
<link rel="stylesheet" href="css/student.css" />
<style>
/* Navbar */
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; }
nav.top-nav a { margin-right:15px; text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }

/* Main */
main { flex:1; max-width:900px; margin:30px auto 60px auto; padding:0 20px; color:#1e3a5f; }
main h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; text-align:center; }

/* Search */
#searchBox { margin-bottom:15px; padding:8px 12px; width:100%; max-width:300px; border:1px solid #007cc7; border-radius:5px; font-size:15px; }

/* Table */
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { border:1px solid #007cc7; padding:10px; text-align:center; font-size:14px; }
th { background:#e5f4fc; color:#007cc7; }
img.found-img { width:150px; height:150px; object-fit:cover; border-radius:8px; }

/* Buttons */
button.claim-btn { background:#28a745; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; }
button.details-btn { background:#007cc7; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; }
button.claim-btn:hover { background:#218838; }
button.details-btn:hover { background:#005fa3; }

/* Popup */
.popup-overlay { 
    display:none; 
    position:fixed; 
    top:0; left:0; 
    width:100%; height:100%; 
    background:rgba(0,0,0,0.5); 
    justify-content:center; 
    align-items:center; 
    z-index:1000; 
}
.popup-content { 
    background:#fff; 
    padding:20px; 
    border-radius:10px; 
    max-width:400px; 
    width:90%; 
    position:relative; 
    text-align:left; 
}
.popup-content h3 { 
    margin-bottom:15px; 
    color:#007cc7; 
    text-align:center; 
}
.popup-content div#detailsInfo {
    text-align: left; 
}

#submitClaim { background:#28a745; color:#fff; }
#closePopup, #closeDetailsPopup { position:absolute; top:10px; right:10px; background:#ccc; color:#000; font-weight:bold; border-radius:50%; width:25px; height:25px; cursor:pointer; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; }

/* Responsive */
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    th { display:none; }
    td { display:flex; justify-content:space-between; padding:10px; border:none; border-bottom:1px solid #007cc7; }
    td::before { content: attr(data-label); font-weight:bold; }
    img.found-img { width:100%; height:auto; margin:10px 0; }
    button.claim-btn, button.details-btn { width:100%; margin:5px 0; }
}
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
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<nav class="top-nav">
  <a href="staff-dashboard.php">Home</a>
  <a href="staff-Profile.php">Profile</a>
  <a href="staff-found-item-report.php">Found Report</a>
  <a href="lost & found/staff-lost-items.php">Lost Item Reports</a>
  <a href="staff-found-items.php" class="active">Found Items</a>
</nav>

<main>
    <h2>Found Items</h2>

    <input type="text" id="searchBox" placeholder="Search items..." />

    <table id="foundTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Found Date</th>
                <th>Location</th>
                <th>Image</th>
                <th>Status / Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($foundItems as $item): ?>
            <tr>
                <td data-label="ID"><?php echo $item['found_id']; ?></td>
                <td data-label="Item Name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td data-label="Category"><?php echo htmlspecialchars($item['category']); ?></td>
                <td data-label="Found Date"><?php echo htmlspecialchars($item['found_date']); ?></td>
                <td data-label="Location"><?php echo htmlspecialchars($item['location']); ?></td>
                <td data-label="Image"><?php if($item['image']) echo '<img src="data:'.$item['image_type'].';base64,'.base64_encode($item['image']).'" class="found-img"/>'; ?></td>
                <td data-label="Status / Action">
                    <?php if($item['status']=='claimed'): ?>
                        <button class="details-btn" data-id="<?php echo $item['found_id']; ?>">Details</button>
                    <?php else: ?>
                        <button class="claim-btn" data-id="<?php echo $item['found_id']; ?>">Mark Claimed</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>

<!-- Claim Popup -->
<div class="popup-overlay" id="claimPopup">
    <div class="popup-content">
        <span id="closePopup">×</span>
        <h3>Mark Item as Claimed</h3>
        <input type="text" id="owner_name" placeholder="Owner Name" required />
        <input type="text" id="owner_iub_id" placeholder="Owner IUB ID" required />
        <input type="text" id="owner_contact" placeholder="Owner Contact" required />
        <button id="submitClaim">Submit</button>
    </div>
</div>

<!-- Details Popup -->
<div class="popup-overlay" id="detailsPopup">
    <div class="popup-content">
        <span id="closeDetailsPopup">×</span>
        <h3>Claim Details</h3>
        <div id="detailsInfo"></div>
    </div>
</div>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

<script>
// Search
document.getElementById('searchBox').addEventListener('input', function(){
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#foundTable tbody tr').forEach(row=>{
        row.style.display = Array.from(row.cells).some(td=>td.innerText.toLowerCase().includes(filter)) ? '' : 'none';
    });
});

// Claim Popup
let claimPopup = document.getElementById('claimPopup');
let ownerName = document.getElementById('owner_name');
let ownerIUB = document.getElementById('owner_iub_id');
let ownerContact = document.getElementById('owner_contact');
let currentId = null;

document.querySelectorAll('.claim-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
        currentId = this.dataset.id;
        ownerName.value = '';
        ownerIUB.value = '';
        ownerContact.value = '';
        claimPopup.style.display = 'flex';
    });
});

document.getElementById('closePopup').addEventListener('click', ()=>{ claimPopup.style.display='none'; });

document.getElementById('submitClaim').addEventListener('click', ()=>{
    if(!ownerName.value || !ownerIUB.value || !ownerContact.value){
        alert('Please fill all fields');
        return;
    }
    let xhr = new XMLHttpRequest();
    xhr.open('POST','staff-found-items.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.responseText=='success'){ location.reload(); } 
        else { alert('Failed to mark claimed'); }
    };
    xhr.send('found_id='+currentId+'&action=claim&owner_name='+encodeURIComponent(ownerName.value)+'&owner_iub_id='+encodeURIComponent(ownerIUB.value)+'&owner_contact='+encodeURIComponent(ownerContact.value));
});

// Details Popup
let detailsPopup = document.getElementById('detailsPopup');
let detailsInfo = document.getElementById('detailsInfo');
document.getElementById('closeDetailsPopup').addEventListener('click', ()=>{ detailsPopup.style.display='none'; });

document.querySelectorAll('.details-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
        let foundId = this.dataset.id;
        let xhr = new XMLHttpRequest();
        xhr.open('POST','staff-found-items.php',true);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.onload = function(){
            try {
                let res = JSON.parse(xhr.responseText);
                if(res.success){
                    let d = res.data;
                    detailsInfo.innerHTML = "<strong>Item:</strong> "+d.item_name+"<br>"+
                                            "<strong>Status:</strong> "+d.status+"<br>"+
                                            "<strong>Owner Name:</strong> "+(d.owner_name??'N/A')+"<br>"+
                                            "<strong>Owner IUB ID:</strong> "+(d.owner_iub_id??'N/A')+"<br>"+
                                            "<strong>Owner Contact:</strong> "+(d.owner_contact??'N/A')+"<br>"+
                                            "<strong>Collected At:</strong> "+(d.collected_at??'N/A');
                    detailsPopup.style.display='flex';
                } else { alert('No details found'); }
            } catch(e){ console.error(e); alert('Error fetching details'); }
        };
        xhr.send('details_id='+foundId);
    });
});
</script>
</body>
</html>
