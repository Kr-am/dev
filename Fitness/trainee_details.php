<?php
include 'config.php';

// Check if logged in and coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'coach') {
    header('Location: index.php');
    exit();
}

$coach_id = $_SESSION['user_id'];
$trainee_id = intval($_GET['id'] ?? 0);
$message = '';

// Verify this trainee is assigned to this coach
$checkStmt = $conn->prepare("SELECT assigned_coach_id FROM users WHERE id = ? AND role = 'trainee'");
$checkStmt->bind_param("i", $trainee_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0 || !($row = $checkResult->fetch_assoc())) {
    header('Location: clients.php');
    exit();
}

if ($row['assigned_coach_id'] != $coach_id) {
    header('Location: clients.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_details'])) {
    $name = trim($_POST['name'] ?? '');
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $sex = $_POST['sex'] ?? null;
    $starting_weight = !empty($_POST['starting_weight']) ? floatval($_POST['starting_weight']) : null;
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $availability = trim($_POST['availability'] ?? '');
    $meal_type_preference = trim($_POST['meal_type_preference'] ?? '');

    $infoStmt = $conn->prepare("SELECT user_id FROM trainee_info WHERE user_id = ?");
    $infoStmt->bind_param("i", $trainee_id);
    $infoStmt->execute();
    $infoExists = $infoStmt->get_result()->num_rows > 0;

    if ($infoExists) {
        $updateStmt = $conn->prepare(
            "UPDATE trainee_info SET name = ?, age = ?, sex = ?, starting_weight = ?, height = ?, start_date = ?, end_date = ?, availability = ?, meal_type_preference = ? WHERE user_id = ?"
        );
        $updateStmt->bind_param(
            "sisddssssi",
            $name,
            $age,
            $sex,
            $starting_weight,
            $height,
            $start_date,
            $end_date,
            $availability,
            $meal_type_preference,
            $trainee_id
        );
        $updateStmt->execute();
    } else {
        $insertStmt = $conn->prepare(
            "INSERT INTO trainee_info (user_id, name, age, sex, starting_weight, height, start_date, end_date, availability, meal_type_preference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertStmt->bind_param(
            "isisddssss",
            $trainee_id,
            $name,
            $age,
            $sex,
            $starting_weight,
            $height,
            $start_date,
            $end_date,
            $availability,
            $meal_type_preference
        );
        $insertStmt->execute();
    }

    $message = 'Trainee details saved successfully.';
}

// Get trainee info
$traineeStmt = $conn->prepare("SELECT u.username, u.email, t.* FROM users u LEFT JOIN trainee_info t ON u.id = t.user_id WHERE u.id = ?");
$traineeStmt->bind_param("i", $trainee_id);
$traineeStmt->execute();
$traineeResult = $traineeStmt->get_result();
$trainee = $traineeResult->fetch_assoc();

// Get trainee goals
$goalsStmt = $conn->prepare("SELECT * FROM trainee_goals WHERE user_id = ? ORDER BY created_at DESC");
$goalsStmt->bind_param("i", $trainee_id);
$goalsStmt->execute();
$goalsResult = $goalsStmt->get_result();
$goals = [];
while ($row = $goalsResult->fetch_assoc()) {
    $goals[] = $row;
}

$bmi = null;
if (!empty($trainee['starting_weight']) && !empty($trainee['height'])) {
    $height_m = $trainee['height'] / 100;
    if ($height_m > 0) {
        $bmi = round($trainee['starting_weight'] / ($height_m * $height_m), 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainee Details - TrackMy Bite</title>
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
        .container {
            margin: 20px;
            max-width: 600px;
        }
        .card {
            background-color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #333;
        }
        .value {
            color: #666;
        }
        .value.empty {
            font-style: italic;
            color: #888;
        }
        .goal-item {
            background-color: #f0f0f0;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .bmi-display {
            font-size: 1.2rem;
            font-weight: bold;
            color: #4caf50;
        }
        .message {
            background-color: #dcedc8;
            border: 1px solid #c5e1a5;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Trainee Details</h1>
        <a href="logout.php">Logout</a>
    </div>
    <div class="nav">
        <a href="dashboard.php">Home</a>
        <a href="clients.php">Clients</a>
        <a href="newsfeed.php">Messages</a>
        <a href="coach_meal_tracker.php">Meal Planner</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Personal Information</h2>
            
            <div class="info-row">
                <span class="label">Username:</span>
                <span class="value"><?php echo htmlspecialchars($trainee['username']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">Email:</span>
                <span class="value"><?php echo htmlspecialchars($trainee['email']); ?></span>
            </div>

            <div class="info-row">
                <span class="label">Name:</span>
                <?php if (!empty($trainee['name'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['name']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">Age:</span>
                <?php if (!empty($trainee['age'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['age']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">Sex:</span>
                <?php if (!empty($trainee['sex'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['sex']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">Starting Weight:</span>
                <?php if (!empty($trainee['starting_weight'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['starting_weight']); ?> kg</span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">Height:</span>
                <?php if (!empty($trainee['height'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['height']); ?> cm</span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <?php if ($bmi): ?>
                <div class="info-row">
                    <span class="label">BMI:</span>
                    <span class="value bmi-display"><?php echo $bmi; ?></span>
                </div>
            <?php endif; ?>

            <div class="info-row">
                <span class="label">Start Date:</span>
                <?php if (!empty($trainee['start_date'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['start_date']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">End Date:</span>
                <?php if (!empty($trainee['end_date'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['end_date']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                <span class="label">Availability:</span>
                <?php if (!empty($trainee['availability'])): ?>
                    <span class="value" style="margin-top: 4px;"><?php echo nl2br(htmlspecialchars($trainee['availability'])); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <span class="label">Meal Type Preference:</span>
                <?php if (!empty($trainee['meal_type_preference'])): ?>
                    <span class="value"><?php echo htmlspecialchars($trainee['meal_type_preference']); ?></span>
                <?php else: ?>
                    <span class="value empty">no details yet</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($goals) > 0): ?>
            <div class="card">
                <h2>Goals</h2>
                <?php foreach ($goals as $goal): ?>
                    <div class="goal-item">
                        <?php echo htmlspecialchars($goal['goal']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>