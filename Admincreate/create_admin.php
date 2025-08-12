<?php
// DB connection details
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

$message = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Admin credentials to insert
    $username_val = "2221134";                  // login username
    $full_name = "Jubair Islam Labib";          // admin full name
    $email = "2221134@iub.edu.bd";              // admin email
    $plain_password = "102019";                  // plain password
    
    // Hash the password
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    // Prepare insert
    $stmt = $pdo->prepare("INSERT INTO admins (uid, username, full_name, email, password, role) 
                           VALUES (UUID(), ?, ?, ?, ?, 'admin')");

    $stmt->execute([$username_val, $full_name, $email, $hashed_password]);

    $message = "✅ Admin user <strong>$full_name</strong> with username <strong>$username_val</strong> inserted successfully! You can now login.";
} catch (PDOException $e) {
    $message = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Insert Admin - Campus Connect</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background-color: #e5f4fc;
    margin: 0; padding: 0;
    display: flex; align-items: center; justify-content: center;
    height: 100vh;
  }
  .message-box {
    background: white;
    padding: 25px 30px;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0, 124, 199, 0.3);
    max-width: 400px;
    text-align: center;
  }
  .message-box p {
    font-size: 1.2em;
    color: #007cc7;
    margin: 0;
  }
  .message-box p strong {
    color: #004a80;
  }
</style>
</head>
<body>
  <div class="message-box">
    <p><?= $message ?></p>
  </div>
</body>
</html>
