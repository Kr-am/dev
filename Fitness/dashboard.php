<?php
include 'config.php';

// Check if logged in and coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'coach') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$profile_pic = null;
$username = '';

$profileStmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
if ($profileResult->num_rows === 1) {
    $userData = $profileResult->fetch_assoc();
    $profile_pic = $userData['profile_pic'];
    $username = $userData['username'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['upload_profile'])) {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $upload_dir = 'uploads/profile/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = $user_id . '_' . basename($_FILES['profile_pic']['name']);
            $file_path = $upload_dir . $filename;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $file_path);

            $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $updateStmt->bind_param("si", $file_path, $user_id);
            $updateStmt->execute();
        }
        header('Location: dashboard.php');
        exit();
    }

    if (isset($_POST['save_bio'])) {
        $bio_text = trim($_POST['bio'] ?? '');
        $bioColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
        if ($bioColumn && $bioColumn->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
        }
        $updateBioStmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $updateBioStmt->bind_param("si", $bio_text, $user_id);
        $updateBioStmt->execute();
        header('Location: dashboard.php');
        exit();
    }
}

$bio = '';
$bioColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
if ($bioColumn && $bioColumn->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
}
$bioStmt = $conn->prepare("SELECT bio FROM users WHERE id = ?");
$bioStmt->bind_param("i", $user_id);
$bioStmt->execute();
$bioResult = $bioStmt->get_result();
if ($bioResult && $bioResult->num_rows > 0) {
    $bioRow = $bioResult->fetch_assoc();
    $bio = $bioRow['bio'] ?? '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TrackMy Bite</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #e8f5e8;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #4caf50;
            color: white;
            padding: 10px;
            text-align: center;
        }
        .profile,
        .post-form,
        .feed {
            background-color: white;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .profile-display {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profile-display img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #4caf50;
        }
        .profile-display .info {
            flex: 1;
        }
        .profile-display .info h3 {
            margin: 0 0 10px;
        }
        .profile-display .info p {
            margin: 0;
            color: #555;
        }
        .profile form {
            margin-top: 15px;
        }
        .post,
        .comment-box {
            background-color: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        textarea,
        input[type="file"],
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            resize: vertical;
            box-sizing: border-box;
            margin-bottom: 12px;
        }
        button {
            background-color: #4caf50;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .meal-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .meal-box {
            background: #edf7ee;
            border: 1px solid #d7edd8;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .meal-box strong {
            display: block;
            margin-top: 8px;
            font-size: 1.2rem;
        }
        .meal-card {
            border: 1px solid #e2ede4;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 16px;
            align-items: center;
        }
        .meal-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 14px;
        }
        .meal-details strong {
            display: block;
            margin-bottom: 8px;
        }
        .meal-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .meal-actions button {
            background-color: #e53935;
            padding: 6px 14px;
            font-size: 0.9rem;
        }
        .meal-actions button:hover {
            background-color: #c62828;
        }
        button:hover {
            background-color: #45a049;
        }
        .nav {
            background-color: #4caf50;
            color: white;
            padding: 10px;
            text-align: center;
        }
        .nav a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Coach Dashboard</h1>
        <a href="logout.php">Logout</a>
    </div>
    <div class="nav">
        <a href="dashboard.php">Profile</a>
        <a href="clients.php">Clients</a>
        <a href="newsfeed.php">Messages</a>
        <a href="coach_meal_tracker.php">Meal Planner</a>
    </div>
    <div class="profile">
        <h2>Profile</h2>
        <div class="profile-display">
            <?php if ($profile_pic): ?>
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile picture">
            <?php else: ?>
                <div style="width:120px;height:120px;background:#f0f0f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#777;">No photo</div>
            <?php endif; ?>
            <div class="info">
                <h3><?php echo htmlspecialchars($username); ?></h3>
                <p>Upload or change your profile picture anytime.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="profile_pic" accept="image/*" required>
                    <button type="submit" name="upload_profile">Upload Profile Photo</button>
                </form>
            </div>
        </div>
    </div>
    <div class="post-form">
        <h2>Coach Bio</h2>
        <p>Describe your coaching focus, what body goals this plan supports, and the benefits trainees can expect.</p>
        <form method="POST">
            <textarea name="bio" rows="6" placeholder="Write your coach bio and plan focus..."><?php echo htmlspecialchars($bio); ?></textarea>
            <button type="submit" name="save_bio">Save Bio</button>
        </form>
    </div>
</body>
</html>