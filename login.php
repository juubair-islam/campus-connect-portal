<?php
session_start();

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // DB connection
    $host = "localhost";
    $dbname = "campus_connect_portal";
    $username = "root";
    $password = "";

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => "Database connection failed: " . $e->getMessage()]);
        exit;
    }

    $role = $_POST['role'] ?? '';
    $loginId = trim($_POST['loginId'] ?? '');
    $passwordInput = $_POST['loginPassword'] ?? '';

    if (!$role || !$loginId || !$passwordInput) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing login credentials']);
        exit;
    }

    // Admin login (from database)
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$loginId]);
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminUser || !password_verify($passwordInput, $adminUser['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid Admin ID or password.']);
            exit;
        }

        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['role'] = 'admin';
        $_SESSION['name'] = $adminUser['full_name'];

        echo json_encode(['success' => true, 'redirect' => 'admin-dashboard.php']);
        exit;
    }

    // Student login
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE iub_id = ?");
        $stmt->execute([$loginId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($passwordInput, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid student ID or password.']);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'student';
        $_SESSION['name'] = $user['name'];

        echo json_encode(['success' => true, 'redirect' => 'student-dashboard.php']);
        exit;
    }

    // Administrative staff login
    if ($role === 'administrative_staff') {
        $stmt = $pdo->prepare("SELECT * FROM administrative_staff WHERE employee_id = ?");
        $stmt->execute([$loginId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($passwordInput, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid staff ID or password.']);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'administrative_staff';
        $_SESSION['name'] = $user['full_name'];

        echo json_encode(['success' => true, 'redirect' => 'staff-dashboard.php']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Campus Connect - Login</title>
  <link rel="stylesheet" href="css/signup.css" />
  <link rel="stylesheet" href="css/login.css" />
  <style>
    /* Select Role Dropdown */
    select {
      width: 100%;
      padding: 10px 12px;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      background-color: #fff;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      cursor: pointer;
    }
    select:focus {
      outline: none;
      border-color: #007cc7;
      box-shadow: 0 0 6px rgba(0, 124, 199, 0.4);
      background-color: #e5f4fc;
    }

    /* Forgot Password Section */
    .forgot-section {
      margin-top: 12px;
      text-align: right;
    }
    .forgot-password a {
      color: #007cc7;
      font-weight: 500;
      text-decoration: none;
      font-size: 0.95em;
      transition: color 0.3s ease;
    }
    .forgot-password a:hover,
    .forgot-password a:focus {
      color: #005fa3;
      text-decoration: underline;
    }

    /* Remove the Here I Am button styling */

  </style>
</head>
<body>
<header class="header">
  <a href="index.html" class="logo-title">
    <img src="images/logo.png" alt="Campus Connect Logo" class="logo" />
    <div class="header-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </a>
  <a href="signup.html" class="back-button">🡺 Sign Up</a>
</header>

<main>
  <form id="loginForm" method="POST" novalidate>
    <h2>Login</h2>

    <!-- Dropdown for Role Selection -->
    <div class="form-group">
      <select name="role" id="role" required>
        <option value="">Select Your Role</option>
        <option value="student">Student</option>
        <option value="administrative_staff">Administrative Staff</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <div class="form-group">
      <input type="text" id="loginId" name="loginId" placeholder="Enter your ID" required />
    </div>

    <div class="form-group">
      <input type="password" id="loginPassword" name="loginPassword" placeholder="Enter Password" required />
    </div>

    <button type="submit">Login</button>

    <!-- Forgot Password only -->
    <div class="forgot-section">
      <p class="forgot-password">
        <a href="forgot-password.html">Forgot Password?</a>
      </p>
    </div>

    <div id="loginStatus" class="validation-msg"></div>
  </form>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginStatus = document.getElementById("loginStatus");

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    loginStatus.textContent = "";

    const formData = new FormData(loginForm);

    try {
      const res = await fetch("login.php", {
        method: "POST",
        body: formData
      });
      const result = await res.json();

      if (res.ok && result.success) {
        loginStatus.style.color = "green";
        loginStatus.textContent = "Login successful! Redirecting...";
        setTimeout(() => {
          window.location.href = result.redirect;
        }, 1000);
      } else {
        loginStatus.style.color = "crimson";
        loginStatus.textContent = result.error || "Login failed. Check credentials.";
      }
    } catch (err) {
      loginStatus.style.color = "crimson";
      loginStatus.textContent = "Error: " + err.message;
    }
  });
});
</script>
</body>
</html>
