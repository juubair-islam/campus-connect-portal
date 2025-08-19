<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='admin') {
    header("Location: login.php"); exit();
}

$host="localhost"; $dbname="campus_connect_portal"; $username="root"; $password="";
try{ $pdo=new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){die("DB Connection Failed: ".$e->getMessage());}

// Add announcement
if(isset($_POST['action']) && $_POST['action']==='add'){
    $title=trim($_POST['title']); $content=trim($_POST['content']);
    $admin_id=$_SESSION['user_id'];
    if($title && $content){
        $stmt=$pdo->prepare("INSERT INTO announcements(title,content,created_by) VALUES(?,?,?)");
        $stmt->execute([$title,$content,$admin_id]);
        echo json_encode(['success'=>true,'message'=>'Announcement added']); exit();
    }else{ echo json_encode(['success'=>false,'message'=>'Title and Content required']); exit();}
}

// Edit announcement
if(isset($_POST['action']) && $_POST['action']==='edit'){
    $id=intval($_POST['announcement_id']);
    $title=trim($_POST['title']); $content=trim($_POST['content']);
    $stmt=$pdo->prepare("UPDATE announcements SET title=?, content=? WHERE announcement_id=?");
    $success=$stmt->execute([$title,$content,$id]);
    echo json_encode(['success'=>$success,'message'=>$success?'Updated successfully':'Failed to update']); exit();
}

// Delete announcement
if(isset($_POST['action']) && $_POST['action']==='delete'){
    $id=intval($_POST['announcement_id']);
    $stmt=$pdo->prepare("DELETE FROM announcements WHERE announcement_id=?");
    $success=$stmt->execute([$id]);
    echo json_encode(['success'=>$success,'message'=>$success?'Deleted successfully':'Failed to delete']); exit();
}

// Fetch announcements
$stmt=$pdo->prepare("SELECT a.*, ad.full_name FROM announcements a JOIN admins ad ON a.created_by=ad.id ORDER BY a.created_at DESC");
$stmt->execute(); $announcements=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Announcements | Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f5f7fa;color:#1e3a5f;min-height:100vh;display:flex;flex-direction:column;}
.top-nav{display:flex;gap:15px;padding:10px 20px;background:#e5f4fc;}
.top-nav a{text-decoration:none;color:#007cc7;font-weight:600;padding:8px 12px;border-radius:6px;transition:.3s;}
.top-nav a:hover,.top-nav a.active{background:#007cc7;color:white;}
.container{max-width:1200px;margin:20px auto;padding:0 20px;display:flex;flex-direction:column;gap:30px;}

/* Center Add Form */
form#addForm{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);width:100%;max-width:600px;margin:0 auto;text-align:center;}
form#addForm input, form#addForm textarea{width:100%;padding:8px;margin-bottom:10px;border-radius:5px;border:1px solid #007cc7;}
form#addForm button{padding:8px 12px;background:#007cc7;color:white;border:none;border-radius:5px;cursor:pointer;}
form#addForm button:hover{background:#005fa3;}

/* Success message */
.success-msg{display:none;background:#4caf50;color:white;padding:10px;border-radius:5px;text-align:center;margin-bottom:10px;}

/* Table styling */
table{width:100%;border-collapse:collapse;background:white;border:1px solid #007cc7;border-radius:8px;overflow:hidden;}
th,td{padding:10px;text-align:center;border-bottom:1px solid #ddd;}
thead{background:#007cc7;color:white;}
tbody tr:nth-child(even){background:#e5f4fc;}
tbody tr:hover{background:#d0ebfc;}
button.edit-btn,button.delete-btn,button.save-btn,button.cancel-btn{border:none;padding:5px 8px;border-radius:5px;cursor:pointer;color:white;}
.edit-btn, .save-btn{background:#007cc7;} .edit-btn:hover, .save-btn:hover{background:#005fa3;}
.delete-btn{background:#e53935;} .delete-btn:hover{background:#b71c1c;}
.cancel-btn{background:#4caf50;} .cancel-btn:hover{background:#388e3c;}
input.edit-input, textarea.edit-textarea{width:100%;padding:6px;border:1px solid #007cc7;border-radius:5px;}
@media(max-width:768px){table,thead,tbody,tr,th,td{display:block;}thead{display:none;}td{display:flex;justify-content:space-between;padding:10px;border:none;border-bottom:1px solid #ddd;}td::before{content:attr(data-label);font-weight:bold;color:#007cc7;flex-basis:40%;}}
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
  <a href="found-items.php">Found Items</a>
  <a href="lost-items.php">Lost Items</a>
  <a href="announcements.php" class="active">Announcements</a>
</nav>

<div class="container">
    <form id="addForm">
        <h3>Add Announcement</h3>
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="content" placeholder="Content" rows="4" required></textarea>
        <button type="submit">Add</button>
    </form>

    <div class="success-msg" id="successMsg"></div>

    <h3 style="text-align:center;">Recent Announcements</h3>
    <input type="text" id="searchInput" placeholder="Search announcements..." style="padding:6px 10px;border:1px solid #007cc7;border-radius:5px;margin-bottom:10px;max-width:400px;display:block;margin-left:auto;margin-right:auto;">

    <table id="announcementsTable">
        <thead>
            <tr>
                <th>SL</th>
                <th>Title</th>
                <th>Content</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sl=1; foreach($announcements as $a): ?>
            <tr id="announcement-<?= $a['announcement_id'] ?>">
                <td data-label="SL"><?= $sl++ ?></td>
                <td data-label="Title" class="title"><?= htmlspecialchars($a['title']) ?></td>
                <td data-label="Content" class="content"><?= nl2br(htmlspecialchars($a['content'])) ?></td>
                <td data-label="Created By"><?= htmlspecialchars($a['full_name']) ?></td>
                <td data-label="Created At"><?= date('d M Y, h:i A',strtotime($a['created_at'])) ?></td>
                <td data-label="Actions">
                    <button class="edit-btn" onclick="editAnnouncement(<?= $a['announcement_id'] ?>)">Edit</button>
                    <button class="delete-btn" onclick="deleteAnnouncement(<?= $a['announcement_id'] ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<footer class="footer">&copy; 2025 Campus Connect | IUB</footer>

<script>
function showSuccess(msg){
    const el=document.getElementById('successMsg');
    el.textContent=msg; el.style.display='block';
    setTimeout(()=>{el.style.display='none';},3000);
}

document.getElementById('addForm').addEventListener('submit',function(e){
    e.preventDefault();
    const formData=new FormData(this); formData.append('action','add');
    fetch('announcements.php',{method:'POST',body:new URLSearchParams(formData)})
    .then(res=>res.json()).then(data=>{
        if(data.success){ showSuccess(data.message); setTimeout(()=>{location.reload();},500);}
        else showSuccess(data.message);
    });
});

// Search filter
document.getElementById("searchInput").addEventListener("keyup", function () {
    let filter=this.value.toLowerCase();
    let rows=document.querySelectorAll("#announcementsTable tbody tr");
    rows.forEach(row=>{ row.style.display=row.innerText.toLowerCase().includes(filter)?'':'none'; });
});

// Delete
function deleteAnnouncement(id){
    fetch('announcements.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=delete&announcement_id='+id})
    .then(res=>res.json()).then(data=>{
        if(data.success){ document.getElementById('announcement-'+id).remove(); showSuccess(data.message);}
        else showSuccess('Failed to delete');
    });
}

// Edit inline
function editAnnouncement(id){
    const row=document.getElementById('announcement-'+id);
    const titleEl=row.querySelector('.title');
    const contentEl=row.querySelector('.content');
    const oldTitle=titleEl.textContent;
    const oldContent=contentEl.textContent;

    titleEl.innerHTML=`<input class="edit-input" value="${oldTitle}">`;
    contentEl.innerHTML=`<textarea class="edit-textarea" rows="3">${oldContent}</textarea>`;
    row.querySelector('td:last-child').innerHTML=`<button class="save-btn">Save</button> <button class="cancel-btn">Cancel</button>`;

    row.querySelector('.cancel-btn').onclick=()=>{ titleEl.textContent=oldTitle; contentEl.textContent=oldContent; resetActions(row,id); }
    row.querySelector('.save-btn').onclick=()=>{
        const newTitle=titleEl.querySelector('input').value;
        const newContent=contentEl.querySelector('textarea').value;
        fetch('announcements.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=edit&announcement_id=${id}&title=${encodeURIComponent(newTitle)}&content=${encodeURIComponent(newContent)}`
        }).then(res=>res.json()).then(data=>{
            if(data.success){ titleEl.textContent=newTitle; contentEl.textContent=newContent; showSuccess(data.message); resetActions(row,id);}
            else showSuccess('Failed to update');
        });
    }
}

function resetActions(row,id){
    row.querySelector('td:last-child').innerHTML=`<button class="edit-btn" onclick="editAnnouncement(${id})">Edit</button>
    <button class="delete-btn" onclick="deleteAnnouncement(${id})">Delete</button>`;
}
</script>
</body>
</html>
