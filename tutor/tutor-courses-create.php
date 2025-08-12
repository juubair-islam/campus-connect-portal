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
$success = "";

$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $available_days_arr = $_POST['available_days'] ?? [];
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    // Validation
    if (!$course_code) $errors[] = "Course Code is required.";
    if (!$course_name) $errors[] = "Course Name is required.";
    if (!$description) $errors[] = "Description is required.";
    if (empty($available_days_arr)) $errors[] = "Select at least one Available Day.";
    if (!$start_time) $errors[] = "Start Time is required.";
    if (!$end_time) $errors[] = "End Time is required.";

    // Validate time constraints (7:00 - 19:00)
    $min_time = strtotime("07:00");
    $max_time = strtotime("19:00");
    $start_ts = strtotime($start_time);
    $end_ts = strtotime($end_time);

    if ($start_ts === false || $end_ts === false) {
        $errors[] = "Invalid start or end time.";
    } else {
        if ($start_ts < $min_time) {
            $errors[] = "Start time cannot be before 7:00 AM.";
        }
        if ($end_ts > $max_time) {
            $errors[] = "End time cannot be after 7:00 PM.";
        }
        if ($end_ts <= $start_ts) {
            $errors[] = "End time must be after start time.";
        }
    }

    // Prepare available_days string
    $available_days = implode(',', array_intersect($available_days_arr, $weekdays));

    // Check if course_code is unique
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
    $stmt->execute([$course_code]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Course Code already exists. Please use a different code.";
    }

    if (empty($errors)) {
        $insert = $pdo->prepare("INSERT INTO courses 
            (tutor_uid, course_code, course_name, description, available_days, start_time, end_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        try {
            $insert->execute([
                $student['uid'],
                $course_code,
                $course_name,
                $description,
                $available_days,
                $start_time,
                $end_time
            ]);
            $success = "Course created successfully!";
            $_POST = []; // clear form
        } catch (PDOException $e) {
            $errors[] = "Error creating course: " . $e->getMessage();
        }
    }
}

function isChecked($day) {
    return (isset($_POST['available_days']) && in_array($day, $_POST['available_days'])) ? "checked" : "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create Tutor Course - Campus Connect</title>
  <link rel="stylesheet" href="../css/student.css" />
  <style>
    main form {
      max-width: 700px;
      margin: 1em auto;
      background: #e5f4fc;
      padding: 1.5em;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,124,199,0.15);
    }
    form label {
      display: block;
      margin: 0.8em 0 0.3em;
      font-weight: 600;
    }
    form input[type=text],
    form textarea,
    form input[type=time] {
      width: 100%;
      padding: 0.5em;
      border: 1px solid #007cc7;
      border-radius: 4px;
      font-size: 1em;
      font-family: inherit;
      resize: vertical;
      box-sizing: border-box;
    }
    .checkbox-group {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    .checkbox-group label {
      font-weight: normal;
    }
    form button {
      margin-top: 1.2em;
      background-color: #007cc7;
      border: none;
      color: white;
      padding: 0.7em 1.5em;
      font-size: 1.1em;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    form button:hover {
      background-color: #005fa3;
    }
    .error-msg {
      color: crimson;
      margin-top: 1em;
    }
    .success-msg {
      color: green;
      margin-top: 1em;
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
    <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="../StudentProfile.php">👤 Profile</a>
  <a href="../lost-found.php">🏷️ Lost &amp; Found</a>
  <a href="../cctv-reporting.php">📹 CCTV Reporting</a>
  <a href="../event-booking.php">📅 Event Booking</a>
  <a href="../learner/learner-dashboard.php">🎓 Learner Panel</a>
  <a href="tutor-courses-list.php">📝 Tutor Courses</a>
</nav>

<main>
  <h2>Create New Tutor Course</h2>

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
    <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <label for="course_code">Course Code <sup style="color:red">*</sup></label>
    <input type="text" id="course_code" name="course_code" required value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>" />

    <label for="course_name">Course Name <sup style="color:red">*</sup></label>
    <input type="text" id="course_name" name="course_name" required value="<?php echo htmlspecialchars($_POST['course_name'] ?? ''); ?>" />

    <label>Available Days <sup style="color:red">*</sup></label>
    <div class="checkbox-group">
      <?php foreach ($weekdays as $day): ?>
        <label>
          <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" <?php echo isChecked($day); ?> />
          <?php echo $day; ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label for="start_time">From <sup style="color:red">*</sup></label>
    <input type="time" id="start_time" name="start_time" required min="07:00" max="19:00" value="<?php echo htmlspecialchars($_POST['start_time'] ?? '07:00'); ?>" />

    <label for="end_time">To <sup style="color:red">*</sup></label>
    <input type="time" id="end_time" name="end_time" required min="07:00" max="19:00" value="<?php echo htmlspecialchars($_POST['end_time'] ?? '19:00'); ?>" />

    <label for="description">Description <sup style="color:red">*</sup></label>
    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

    <button type="submit">Create Course</button>
  </form>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
