<?php
include 'config.php';

// Check if logged in and trainee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainee') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Create applications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS trainee_coach_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainee_id INT NOT NULL,
    coach_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (trainee_id, coach_id),
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle apply to coach
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_coach_id'])) {
    $coach_id = intval($_POST['apply_coach_id']);
    
    // Check if coach exists and is actually a coach
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'coach'");
    $checkStmt->bind_param("i", $coach_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        // Try to insert or update application
        $stmt = $conn->prepare("INSERT INTO trainee_coach_applications (trainee_id, coach_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending'");
        $stmt->bind_param("ii", $user_id, $coach_id);
        if ($stmt->execute()) {
            $message = 'Application sent successfully!';
        }
    }
}

// Get all coaches (with fallback check against the current trainee's assigned_coach_id)
$coachesStmt = $conn->prepare("    SELECT u.id, u.username, u.bio, u.profile_pic,
           CASE 
               WHEN current_trainee.assigned_coach_id = u.id THEN 'accepted'
               ELSE COALESCE(a.status, 'none')
           END as application_status
    FROM users u
    CROSS JOIN (SELECT assigned_coach_id FROM users WHERE id = ?) current_trainee
    LEFT JOIN trainee_coach_applications a ON u.id = a.coach_id AND a.trainee_id = ?
    WHERE u.role = 'coach'
    ORDER BY u.username ASC
");
$coachesStmt->bind_param("ii", $user_id, $user_id);
$coachesStmt->execute();
$coachesResult = $coachesStmt->get_result();
$coaches = [];
while ($row = $coachesResult->fetch_assoc()) {
    $coaches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Coaches - TrackMy Bite</title>
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
        .header h1 {
            margin-bottom: 5px;
        }
        .logout-btn {
            position: absolute;
            right: 15px;
            top: 15px;
            background-color: #e53935;
            color: white;
            border: none;
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
            max-width: 1000px;
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
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .coach-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .coach-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .coach-pic {
            width: 100%;
            height: 200px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #999;
            overflow: hidden;
        }
        .coach-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .coach-info {
            padding: 20px;
        }
        .coach-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .coach-bio {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            max-height: 100px;
            overflow: hidden;
        }
        .coach-actions {
            display: flex;
            gap: 10px;
        }
        .apply-btn {
            flex: 1;
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .apply-btn:hover {
            background-color: #45a049;
        }
        .apply-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .pending-badge {
            background-color: #ff9800;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            flex: 1;
        }
        .accepted-badge {
            background-color: #4caf50;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            flex: 1;
        }
        .no-coaches {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Find Your Coach</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="nav">
        <a href="trainee.php">Coaches</a>
        <a href="newsfeed.php">Messages</a>
        <a href="trainee_profile.php">My Profile</a>
        <a href="meal_tracker.php">Meal Tracker</a>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <h2>Browse Available Coaches</h2>
        
        <?php if (count($coaches) > 0): ?>
            <div class="coaches-grid">
                <?php foreach ($coaches as $coach): ?>
                    <div class="coach-card">
                        <div class="coach-pic">
                            <?php if ($coach['profile_pic'] && file_exists($coach['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($coach['profile_pic']); ?>" alt="<?php echo htmlspecialchars($coach['username']); ?>">
                            <?php else: ?>
                                👤
                            <?php endif; ?>
                        </div>
                        <div class="coach-info">
                            <div class="coach-name"><?php echo htmlspecialchars($coach['username']); ?></div>
                            <div class="coach-bio">
                                <?php if ($coach['bio']): ?>
                                    <?php echo htmlspecialchars(substr($coach['bio'], 0, 100)); ?>
                                    <?php if (strlen($coach['bio']) > 100): ?>...<?php endif; ?>
                                <?php else: ?>
                                    <em>No bio available</em>
                                <?php endif; ?>
                            </div>
                            <div class="coach-actions">
                                <?php if ($coach['application_status'] === 'pending'): ?>
                                    <div class="pending-badge">⏳ Pending</div>
                                <?php elseif ($coach['application_status'] === 'accepted'): ?>
                                    <div class="accepted-badge">✓ Accepted</div>
                                <?php else: ?>
                                    <form method="POST" style="width: 100%;">
                                        <input type="hidden" name="apply_coach_id" value="<?php echo $coach['id']; ?>">
                                        <button type="submit" class="apply-btn">Apply</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-coaches">
                <p>No coaches available at the moment. Please try again later.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>