<?php
include 'config.php';

// Check if logged in and trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainee') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Get user profile info including personal details and gender
$userStmt = $conn->prepare("SELECT username, profile_pic, bio, age, gender, height, weight, target_goal FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

// Handle profile picture upload and updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_bio'])) {
        $bio = trim($_POST['bio'] ?? '');
        $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $stmt->bind_param("si", $bio, $user_id);
        if ($stmt->execute()) {
            $message = 'Bio saved successfully!';
            $userData['bio'] = $bio;
        }
    }

    if (isset($_POST['save_details'])) {
        $age = intval($_POST['age'] ?? 0);
        $gender = trim($_POST['gender'] ?? '');
        $height = floatval($_POST['height'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $target_goal = trim($_POST['target_goal'] ?? '');

        $stmt = $conn->prepare("UPDATE users SET age = ?, gender = ?, height = ?, weight = ?, target_goal = ? WHERE id = ?");
        $stmt->bind_param("idsdsi", $age, $gender, $height, $weight, $target_goal, $user_id);
        if ($stmt->execute()) {
            $message = 'Personal details saved successfully!';
            $userData['age'] = $age;
            $userData['gender'] = $gender;
            $userData['height'] = $height;
            $userData['weight'] = $weight;
            $userData['target_goal'] = $target_goal;
        }
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $upload_dir = 'uploads/profile/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old profile picture if it exists
        if ($userData['profile_pic'] && file_exists($userData['profile_pic'])) {
            unlink($userData['profile_pic']);
        }
        
        $filename = $user_id . '_' . time() . '_' . basename($_FILES['profile_pic']['name']);
        $image_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $image_path)) {
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $image_path, $user_id);
            if ($stmt->execute()) {
                $message = 'Profile picture updated successfully!';
                $userData['profile_pic'] = $image_path;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TrackMy Bite</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #e8f5e8;
        }
        .header {
            background-color: #4caf50;
            color: white;
            padding: 15px;
            text-align: center;
            position: relative;
        }
        .logout-btn {
            position: absolute;
            right: 15px;
            top: 15px;
            background-color: #e53935;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }
        .logout-btn:hover {
            background-color: #c62828;
        }
        .nav {
            background-color: #4caf50;
            color: white;
            padding: 10px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }
        .nav a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        .card {
            background-color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .profile-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #4caf50;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .profile-pic-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin-bottom: 15px;
            margin-left: auto;
            margin-right: auto;
            border: 4px solid #4caf50;
        }
        .username {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        label {
            display: block;
            font-weight: bold;
            color: #333;
            margin-top: 12px;
            margin-bottom: 5px;
        }
        input[type="file"],
        input[type="number"],
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 12px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            resize: vertical;
        }
        button {
            background-color: #4caf50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>My Profile</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="nav">
        <a href="trainee.php">Home</a>
        <a href="newsfeed.php">Messages</a>
        <a href="trainee_profile.php">My Profile</a>
        <a href="meal_tracker.php">Meal Tracker</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="profile-section">
                <?php if ($userData['profile_pic'] && file_exists($userData['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($userData['profile_pic']); ?>" alt="Profile" class="profile-pic">
                <?php else: ?>
                    <div class="profile-pic-placeholder">👤</div>
                <?php endif; ?>
                <div class="username"><?php echo htmlspecialchars($userData['username']); ?></div>
            </div>

            <h2>Profile Picture</h2>
            <form method="POST" enctype="multipart/form-data">
                <label>Upload Profile Picture</label>
                <input type="file" name="profile_pic" accept="image/*">
                <button type="submit">Upload Picture</button>
            </form>
        </div>

        <div class="card">
            <h2>Personal Physical Details</h2>
            <form method="POST">
                <label>Age</label>
                <input type="number" name="age" value="<?php echo htmlspecialchars($userData['age'] ?? ''); ?>" placeholder="e.g. 20" min="1">

                <label>Gender</label>
                <select name="gender">
                    <option value="" disabled <?php echo empty($userData['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                    <option value="male" <?php echo ($userData['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($userData['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>

                <label>Height (cm)</label>
                <input type="number" name="height" step="0.1" value="<?php echo htmlspecialchars($userData['height'] ?? ''); ?>" placeholder="e.g. 160">

                <label>Weight (kg)</label>
                <input type="number" name="weight" step="0.1" value="<?php echo htmlspecialchars($userData['weight'] ?? ''); ?>" placeholder="e.g. 60">

                <label>Target Goal / Focus</label>
                <input type="text" name="target_goal" value="<?php echo htmlspecialchars($userData['target_goal'] ?? ''); ?>" placeholder="e.g. Calisthenics, Weight Loss, Muscle Gain">

                <button type="submit" name="save_details">Save Details</button>
            </form>
        </div>

        <div class="card">
            <h2>Bio</h2>
            <form method="POST">
                <label>Tell us about yourself</label>
                <textarea name="bio" rows="5" placeholder="Write your bio here..."><?php echo htmlspecialchars($userData['bio'] ?? ''); ?></textarea>
                <button type="submit" name="save_bio">Save Bio</button>
            </form>
        </div>
    </div>
</body>
</html>