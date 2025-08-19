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
// Handle AJAX Update status
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lost_id']) && isset($_POST['status'])) {
    $lost_id = $_POST['lost_id'];
    $status = $_POST['status'] === 'found' ? 'found' : 'under process';

    // If marking as found and found_id is null, create a dummy found_items record
    if($status === 'found'){
        // Check if found_id exists
        $stmtCheck = $pdo->prepare("SELECT found_id FROM lost_items WHERE lost_id=:lost_id");
        $stmtCheck->execute(['lost_id'=>$lost_id]);
        $found_id = $stmtCheck->fetchColumn();
        if(!$found_id){
            // Insert into found_items (minimal info)
            $stmtInsert = $pdo->prepare("INSERT INTO found_items (finder_uid, item_name, found_date, status) 
                                         SELECT reporter_uid, item_name, CURRENT_DATE, 'unclaimed' FROM lost_items WHERE lost_id=:lost_id");
            $stmtInsert->execute(['lost_id'=>$lost_id]);
            $found_id = $pdo->lastInsertId();

            // Update lost_items with found_id
            $stmtUpd = $pdo->prepare("UPDATE lost_items SET found_id=:found_id WHERE lost_id=:lost_id");
            $stmtUpd->execute(['found_id'=>$found_id,'lost_id'=>$lost_id]);
        }
    }

    // Update lost_items status
    $stmt = $pdo->prepare("UPDATE lost_items SET status=:status WHERE lost_id=:lost_id");
    if ($stmt->execute(['status'=>$status, 'lost_id'=>$lost_id])) {
        echo 'success';
    } else {
        echo 'failed';
    }
    exit();
}

// -----------------------
// Handle AJAX Details popup
// -----------------------
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['details_id'])){
    $lost_id = $_POST['details_id'];

    // Get lost item found_id
    $stmt = $pdo->prepare("SELECT found_id FROM lost_items WHERE lost_id=:lost_id");
    $stmt->execute(['lost_id'=>$lost_id]);
    $found_id = $stmt->fetchColumn();

    if($found_id){
        // Get collection info
        $stmt2 = $pdo->prepare("SELECT owner_name, owner_contact, collected_at FROM found_item_collections WHERE found_id=:found_id ORDER BY collection_id DESC LIMIT 1");
        $stmt2->execute(['found_id'=>$found_id]);
        $details = $stmt2->fetch(PDO::FETCH_ASSOC);

        if($details){
            echo json_encode([
                'success'=>true,
                'owner_name'=>$details['owner_name'],
                'owner_contact'=>$details['owner_contact'],
                'found_time'=>date('d M Y, h:i A', strtotime($details['collected_at']))
            ]);
        } else {
            echo json_encode(['success'=>false]);
        }
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
$firstName = explode(' ', trim($staff['full_name']))[0];

// -----------------------
// Fetch lost items
// -----------------------
$stmt = $pdo->query("
    SELECT l.lost_id, l.created_at AS report_time, l.item_name, l.lost_date, l.image, l.image_type, s.name AS reporter_name, s.contact_number, l.status, l.found_id
    FROM lost_items l
    JOIN students s ON l.reporter_uid = s.uid
    ORDER BY l.lost_id DESC
");
$lostItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Staff Lost Items - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<style>
/* Navbar */
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; }
nav.top-nav a { margin-right:15px; text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }

/* Table */
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { border:1px solid #007cc7; padding:10px; text-align:center; font-size:14px; }
th { background:#e5f4fc; color:#007cc7; }
img.lost-img { width:200px; height:200px; object-fit:cover; border-radius:8px; }

/* Buttons */
button.update-btn, button.details-btn { background:#007cc7; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; }
button.update-btn:hover, button.details-btn:hover { background:#005fa3; }
button:disabled { background:#ccc; cursor:not-allowed; }

/* Popup */
.popup-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
.popup-content { background:#fff; padding:20px; border-radius:10px; max-width:400px; width:90%; text-align:center; position:relative; }
.popup-content h3 { margin-bottom:15px; color:#007cc7; }
.popup-content button { margin:5px; padding:8px 15px; border:none; border-radius:5px; font-weight:bold; cursor:pointer; }
#foundBtn { background:#28a745; color:#fff; }
#notFoundBtn { background:#dc3545; color:#fff; }
#closePopup, #closeDetailsPopup { position:absolute; top:10px; right:10px; background:#ccc; color:#000; font-weight:bold; border-radius:50%; width:25px; height:25px; cursor:pointer; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; margin-top:20px; }

/* Responsive */
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    th { display:none; }
    td { display:flex; justify-content:space-between; padding:10px; border:none; border-bottom:1px solid #007cc7; }
    td::before { content: attr(data-label); font-weight:bold; }
    img.lost-img { width:100%; height:auto; margin:10px 0; }
}
</style>
</head>
<body>
<header class="header">
    <div class="header-left">
        <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" />
        <div class="title-text">
            <h1>Campus Connect</h1>
            <p class="tagline">Bridge to Your IUB Community</p>
        </div>
    </div>
    <div class="header-right">
        <span class="user-name"><?php echo htmlspecialchars($staff['full_name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<nav class="top-nav">
    <a href="staff-dashboard.php">Home</a>
    <a href="staff-lost-items.php" class="active">Lost Items</a>
    <a href="cctv-reports.php">CCTV Reports</a>
    <a href="event-booking.php">Event Booking</a>
</nav>

<main class="dashboard">
    <h2><?php echo htmlspecialchars($firstName); ?>'s Lost Items</h2>
    <table>
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
        <?php foreach($lostItems as $item): ?>
            <tr>
                <td data-label="Waiting SL"><?php echo $item['lost_id']; ?></td>
                <td data-label="Report Time"><?php echo date('d M Y, h:i A', strtotime($item['report_time'])); ?></td>
                <td data-label="Lost Item Name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td data-label="Lost Date"><?php echo htmlspecialchars($item['lost_date']); ?></td>
                <td data-label="Image"><?php if($item['image']) echo '<img src="data:'.$item['image_type'].';base64,'.base64_encode($item['image']).'" class="lost-img"/>'; ?></td>
                <td data-label="Action">
                    <?php if($item['status']==='found'): ?>
                        <button class="details-btn" data-id="<?php echo $item['lost_id']; ?>">Details</button>
                    <?php else: ?>
                        <button class="update-btn" data-id="<?php echo $item['lost_id']; ?>" data-name="<?php echo htmlspecialchars($item['reporter_name']); ?>" data-contact="<?php echo htmlspecialchars($item['contact_number']); ?>">Update</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>

<!-- Update Popup -->
<div class="popup-overlay" id="statusPopup">
    <div class="popup-content">
        <span id="closePopup">×</span>
        <h3>Status Update</h3>
        <p id="reporterInfo"></p>
        <button id="foundBtn">Found</button>
        <button id="notFoundBtn">Not Found</button>
    </div>
</div>

<!-- Details Popup -->
<div class="popup-overlay" id="detailsPopup">
    <div class="popup-content">
        <span id="closeDetailsPopup">×</span>
        <h3>Item Details</h3>
        <p id="detailsInfo"></p>
    </div>
</div>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

<script>
// Update Popup
let popup = document.getElementById('statusPopup');
let reporterInfo = document.getElementById('reporterInfo');
let foundBtn = document.getElementById('foundBtn');
let notFoundBtn = document.getElementById('notFoundBtn');
let currentId = null;

document.querySelectorAll('.update-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
        currentId = this.dataset.id;
        reporterInfo.innerHTML = "<strong>Reporter:</strong> "+this.dataset.name+"<br><strong>Contact:</strong> "+this.dataset.contact;
        popup.style.display = 'flex';
    });
});
document.getElementById('closePopup').addEventListener('click', ()=>{ popup.style.display='none'; });
foundBtn.addEventListener('click', ()=>{ updateStatus('found'); });
notFoundBtn.addEventListener('click', ()=>{ updateStatus('under process'); });

function updateStatus(status){
    let xhr = new XMLHttpRequest();
    xhr.open('POST','staff-lost-items.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.responseText=='success'){ location.reload(); } 
        else{ alert('Failed to update status.'); }
    };
    xhr.send('lost_id='+currentId+'&status='+status);
}

// Details Popup
let detailsPopup = document.getElementById('detailsPopup');
let detailsInfo = document.getElementById('detailsInfo');
document.getElementById('closeDetailsPopup').addEventListener('click', ()=>{ detailsPopup.style.display='none'; });

document.querySelectorAll('.details-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
        let lostId = this.dataset.id;
        let xhr = new XMLHttpRequest();
        xhr.open('POST','staff-lost-items.php',true);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.onload = function(){
            try {
                let res = JSON.parse(xhr.responseText);
                if(res.success){
                    detailsInfo.innerHTML = "<strong>Found Time:</strong> "+res.found_time+"<br>"+
                                            "<strong>Owner Name:</strong> "+res.owner_name+"<br>"+
                                            "<strong>Owner Contact:</strong> "+res.owner_contact;
                    detailsPopup.style.display='flex';
                } else { alert('Details not found'); }
            } catch(e){ console.error(e); alert('Error fetching details'); }
        };
        xhr.send('details_id='+lostId);
    });
});
</script>
</body>
</html>
