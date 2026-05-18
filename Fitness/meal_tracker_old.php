<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = '';

if ($role === 'coach') {
    header('Location: dashboard.php');
    exit();
}

$columnCheck = $conn->query("SHOW COLUMNS FROM meal_entries LIKE 'uploaded_by'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE meal_entries ADD COLUMN uploaded_by ENUM('coach','trainee') NOT NULL DEFAULT 'trainee'");
}

function estimateNutrition($meal_name, $image_name = '') {
    $name = strtolower($meal_name);
    $keywords = [
        'salad' => ['calories' => 180, 'note' => 'Light salad estimate'],
        'chicken' => ['calories' => 320, 'note' => 'Grilled chicken estimate'],
        'rice' => ['calories' => 260, 'note' => 'Cooked rice estimate'],
        'pasta' => ['calories' => 350, 'note' => 'Pasta estimate'],
        'burger' => ['calories' => 600, 'note' => 'Burger estimate'],
        'pizza' => ['calories' => 520, 'note' => 'Pizza slice estimate'],
        'smoothie' => ['calories' => 220, 'note' => 'Smoothie estimate'],
        'fruit' => ['calories' => 90, 'note' => 'Fruit bowl estimate'],
        'egg' => ['calories' => 75, 'note' => 'Egg-based meal estimate'],
        'fish' => ['calories' => 280, 'note' => 'Fish estimate'],
        'steak' => ['calories' => 450, 'note' => 'Steak estimate'],
    ];
    foreach ($keywords as $word => $data) {
        if (strpos($name, $word) !== false) {
            return $data;
        }
    }
    if ($image_name) {
        $image = strtolower($image_name);
        foreach ($keywords as $word => $data) {
            if (strpos($image, $word) !== false) {
                return $data;
            }
        }
    }
    return ['calories' => 250, 'note' => 'Default meal estimate'];
}

$selected_user_id = $user_id;
$assigned_coach_id = null;
if ($role === 'coach') {
    $selected_user_id = intval($_GET['trainee_id'] ?? $_POST['trainee_id'] ?? 0);
}

$assignedStmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'trainee' AND assigned_coach_id = ?");
$assignedStmt->bind_param("i", $user_id);
$assignedStmt->execute();
$assignedResult = $assignedStmt->get_result();
$assignedTrainees = [];
while ($row = $assignedResult->fetch_assoc()) {
    $assignedTrainees[] = $row;
}

if ($role === 'coach' && !$selected_user_id && count($assignedTrainees) > 0) {
    $selected_user_id = $assignedTrainees[0]['id'];
}

if ($role === 'coach' && $selected_user_id) {
    $assignedCheckStmt = $conn->prepare("SELECT assigned_coach_id, username FROM users WHERE id = ? AND role = 'trainee'");
    $assignedCheckStmt->bind_param("i", $selected_user_id);
    $assignedCheckStmt->execute();
    $assignedCheck = $assignedCheckStmt->get_result()->fetch_assoc();
    if (!$assignedCheck || $assignedCheck['assigned_coach_id'] !== $user_id) {
        $selected_user_id = 0;
    }
}

if ($role === 'trainee') {
    $coachStmt = $conn->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
    $coachStmt->bind_param("i", $user_id);
    $coachStmt->execute();
    $coachInfo = $coachStmt->get_result()->fetch_assoc();
    $assigned_coach_id = $coachInfo['assigned_coach_id'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_goal']) && $role === 'coach' && $selected_user_id) {
        $daily_goal = intval($_POST['daily_goal']);
        if ($daily_goal > 0) {
            $stmt = $conn->prepare("INSERT INTO calorie_goals (user_id, coach_id, daily_goal) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE daily_goal = VALUES(daily_goal), coach_id = VALUES(coach_id)");
            $stmt->bind_param("iii", $selected_user_id, $user_id, $daily_goal);
            $stmt->execute();
            $message = 'Calorie goal updated.';
        }
    }

    if (isset($_POST['add_meal'])) {
        $target_user_id = $role === 'coach' ? $selected_user_id : $user_id;
        if ($target_user_id) {
            $meal_name = trim($_POST['meal_name'] ?? 'Meal');
            $portion = !empty($_POST['portion']) ? floatval($_POST['portion']) : 1;
            $calories_per_portion = !empty($_POST['calories_per_portion']) ? intval($_POST['calories_per_portion']) : 0;
            $nutrition_note = trim($_POST['nutrition_note'] ?? '');
            $meal_date = !empty($_POST['meal_date']) ? $_POST['meal_date'] : date('Y-m-d');
            $image_path = null;
            
            if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === 0) {
                $upload_dir = 'uploads/meals/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $filename = $target_user_id . '_' . time() . '_' . basename($_FILES['meal_image']['name']);
                $image_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['meal_image']['tmp_name'], $image_path);
            }
            
            if ($calories_per_portion <= 0) {
                $estimate = estimateNutrition($meal_name, $image_path ? basename($image_path) : '');
                $calories_per_portion = $estimate['calories'];
                if (!$nutrition_note) {
                    $nutrition_note = $estimate['note'];
                }
            }
            $total_calories = (int) round($portion * $calories_per_portion);
            $coach_id = $role === 'coach' ? $user_id : ($assigned_coach_id ?: 0);
            $uploaded_by = $role === 'coach' ? 'coach' : 'trainee';
            
            $insert = $conn->prepare("INSERT INTO meal_entries (user_id, coach_id, meal_name, image_path, portion, calories_per_portion, total_calories, nutrition_note, uploaded_by, meal_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("iissddisss", $target_user_id, $coach_id, $meal_name, $image_path, $portion, $calories_per_portion, $total_calories, $nutrition_note, $uploaded_by, $meal_date);
            $insert->execute();
            $message = 'Meal added to the tracker.';
        }
    }

    if (isset($_POST['delete_meal'])) {
        $delete_id = intval($_POST['delete_meal']);
        $allow_delete = false;
        $check_user_id = $role === 'coach' ? $selected_user_id : $user_id; // FIXED PARSE ERROR HERE
        if ($role === 'coach' && $selected_user_id) {
            $checkStmt = $conn->prepare("SELECT user_id FROM meal_entries WHERE id = ? AND user_id = ?");
            $checkStmt->bind_param("ii", $delete_id, $check_user_id);
            $checkStmt->execute();
            $allow_delete = $checkStmt->get_result()->num_rows > 0;
        }
        if ($role === 'trainee') {
            $checkStmt = $conn->prepare("SELECT user_id FROM meal_entries WHERE id = ? AND user_id = ?");
            $checkStmt->bind_param("ii", $delete_id, $check_user_id);
            $checkStmt->execute();
            $allow_delete = $checkStmt->get_result()->num_rows > 0;
        }
        if ($allow_delete) {
            $deleteStmt = $conn->prepare("DELETE FROM meal_entries WHERE id = ?");
            $deleteStmt->bind_param("i", $delete_id);
            $deleteStmt->execute();
            $message = 'Meal removed.';
        }
    }

    header('Location: meal_tracker.php' . ($role === 'coach' && $selected_user_id ? '?trainee_id=' . $selected_user_id : ''));
    exit();
}

$selectedTrainee = null;
$daily_goal = 0;
$goalRow = null;
if ($role === 'coach') {
    if ($selected_user_id) {
        $goalStmt = $conn->prepare("SELECT daily_goal FROM calorie_goals WHERE user_id = ?");
        $goalStmt->bind_param("i", $selected_user_id);
        $goalStmt->execute();
        $goalRow = $goalStmt->get_result()->fetch_assoc();
        $daily_goal = $goalRow['daily_goal'] ?? 0;
        $traineeRowStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $traineeRowStmt->bind_param("i", $selected_user_id);
        $traineeRowStmt->execute();
        $selectedTrainee = $traineeRowStmt->get_result()->fetch_assoc();
    }
} else {
    $goalStmt = $conn->prepare("SELECT daily_goal FROM calorie_goals WHERE user_id = ?");
    $goalStmt->bind_param("i", $user_id);
    $goalStmt->execute();
    $goalRow = $goalStmt->get_result()->fetch_assoc();
    $daily_goal = $goalRow['daily_goal'] ?? 0;
}

$target_id = $role === 'coach' ? $selected_user_id : $user_id;
$meals = [];
$calories_today = 0;
$planStartDate = null;
if ($target_id) {
    $startDateStmt = $conn->prepare("SELECT start_date FROM trainee_info WHERE user_id = ?");
    $startDateStmt->bind_param("i", $target_id);
    $startDateStmt->execute();
    $startDateRow = $startDateStmt->get_result()->fetch_assoc();
    $planStartDate = $startDateRow['start_date'] ?? null;

    $mealsStmt = $conn->prepare("SELECT * FROM meal_entries WHERE user_id = ? ORDER BY COALESCE(meal_date, DATE(created_at)) DESC, created_at DESC");
    $mealsStmt->bind_param("i", $target_id);
    $mealsStmt->execute();
    $meals = $mealsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($planStartDate) {
        foreach ($meals as &$meal) {
            $meal['day_label'] = '';
            if ($meal['uploaded_by'] === 'coach') {
                $createdAt = strtotime($meal['created_at']);
                $startAt = strtotime($planStartDate);
                if ($createdAt !== false && $startAt !== false && $createdAt >= $startAt) {
                    $dayNumber = (int) floor(($createdAt - $startAt) / 86400) + 1;
                    if ($dayNumber > 0) {
                        $meal['day_label'] = 'Meal Day ' . $dayNumber;
                    }
                }
            }
        }
        unset($meal);
    }

    $todayStmt = $conn->prepare("SELECT COALESCE(SUM(total_calories),0) AS total FROM meal_entries WHERE user_id = ? AND (meal_date = CURDATE() OR (meal_date IS NULL AND DATE(created_at) = CURDATE()))");
    $todayStmt->bind_param("i", $target_id);
    $todayStmt->execute();
    $todayRow = $todayStmt->get_result()->fetch_assoc();
    $calories_today = $todayRow['total'] ?? 0;
}
$remaining_calories = $daily_goal > 0 ? max(0, $daily_goal - $calories_today) : 0;

$bgImage = 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1400&q=80';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Tracker - TrackMy Bite</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f0f4f3;
        }
        .hero {
            background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url('<?php echo $bgImage; ?>') center/cover no-repeat;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .hero h1 {
            margin: 0;
            font-size: 2.8rem;
        }
        .hero p {
            margin: 10px auto 0;
            max-width: 700px;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .nav {
            background-color: #4caf50;
            color: white;
            text-align: center;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .nav a {
            color: white;
            text-decoration: none;
            margin: 0 16px;
            font-weight: bold;
            font-size: 1.05rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        .nav a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            gap: 20px;
            grid-template-columns: 1.2fr 0.8fr;
        }
        @media (max-width: 850px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            padding: 24px;
            height: fit-content;
        }
        h2 {
            margin-top: 0;
            color: #224d24;
            border-bottom: 2px solid #edf7ee;
            padding-bottom: 8px;
        }
        .info-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #edf7ee;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid #d7edd8;
        }
        .info-box strong {
            display: block;
            font-size: 1.3rem;
            margin-top: 8px;
            color: #224d24;
        }
        
        form label {
            display: block;
            width: 100%;
            margin-bottom: 6px;
            font-weight: bold;
            color: #444;
        }
        form input[type="text"],
        form input[type="number"],
        form input[type="file"],
        form input[type="date"],
        form select,
        form textarea {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #d7ddd8;
            border-radius: 10px;
            outline: none;
            background: #f8faf8;
            font-size: 1rem;
        }
        
        .coach-select-form {
            display: block;
            margin-bottom: 20px;
        }
        
        button {
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }
        button:hover {
            background-color: #3b8b3d;
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
            background: #fafcfb;
        }
        .meal-card img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 14px;
        }
        .meal-details strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1.15rem;
            color: #224d24;
        }
        .meal-details p {
            margin: 4px 0;
            color: #555;
            font-size: 0.95rem;
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
            font-size: 0.85rem;
        }
        .meal-actions button:hover {
            background-color: #c62828;
        }
        .goal-card {
            background: linear-gradient(180deg, #ffffff 0%, #f0f8f0 100%);
            border: 1px solid #d7edd8;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .message {
            padding: 14px 18px;
            background: #dff3dc;
            border-radius: 12px;
            margin-bottom: 16px;
            color: #24562c;
            border-left: 5px solid #4caf50;
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Meal Tracker</h1>
        <p>Log meals, upload pictures, and track calories against your daily goal. Coaches can set targets for clients and follow progress in real time.</p>
    </div>
    <div class="nav">
        <a href="<?php echo $role === 'coach' ? 'dashboard.php' : 'trainee.php'; ?>">Home / Profile</a>
        <?php if ($role === 'coach'): ?>
            <a href="clients.php">Clients</a>
        <?php endif; ?>
        <a href="newsfeed.php">Messages</a>
        <a href="meal_tracker.php">Meal Tracker</a>
    </div>
    <div class="container">
        <div class="card">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($role === 'coach'): ?>
                <h2>Coach Meal Manager</h2>
                <form method="GET" class="coach-select-form">
                    <label>Select Trainee</label>
                    <select name="trainee_id" onchange="this.form.submit()">
                        <option value="">-- Choose a Trainee --</option>
                        <?php foreach ($assignedTrainees as $trainee): ?>
                            <option value="<?php echo $trainee['id']; ?>" <?php echo $selected_user_id == $trainee['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($trainee['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selected_user_id && $selectedTrainee): ?>
                    <div class="goal-card">
                        <h3>Daily Calorie Goal for <?php echo htmlspecialchars($selectedTrainee['username']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="trainee_id" value="<?php echo $selected_user_id; ?>">
                            <label>Set Calorie Target</label>
                            <input type="number" name="daily_goal" min="0" value="<?php echo $daily_goal; ?>" required>
                            <button type="submit" name="set_goal">Update Goal</button>
                        </form>
                        <div class="info-bar" style="margin-top:16px;">
                            <div class="info-box">
                                <span>Goal</span>
                                <strong><?php echo $daily_goal ? $daily_goal . ' kcal' : 'Not set'; ?></strong>
                            </div>
                            <div class="info-box">
                                <span>Today</span>
                                <strong><?php echo $calories_today; ?> kcal</strong>
                            </div>
                            <div class="info-box">
                                <span>Remaining</span>
                                <strong><?php echo $daily_goal ? $remaining_calories . ' kcal' : '-'; ?></strong>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="message">Select a trainee to manage meal goals and entries.</div>
                <?php endif; ?>
            <?php else: ?>
                <h2>Your Daily Nutrition</h2>
                <div class="goal-card">
                    <div class="info-bar">
                        <div class="info-box">
                            <span>Your Goal</span>
                            <strong><?php echo $daily_goal ? $daily_goal . ' kcal' : 'Not set'; ?></strong>
                        </div>
                        <div class="info-box">
                            <span>Consumed Today</span>
                            <strong><?php echo $calories_today; ?> kcal</strong>
                        </div>
                        <div class="info-box">
                            <span>Remaining</span>
                            <strong><?php echo $daily_goal ? $remaining_calories . ' kcal' : '-'; ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($role === 'coach' && $selected_user_id && $selectedTrainee) || $role === 'trainee'): ?>
                <h2>Log a Meal</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($role === 'coach'): ?>
                        <input type="hidden" name="trainee_id" value="<?php echo $selected_user_id; ?>">
                    <?php endif; ?>
                    <label>Meal Name *</label>
                    <input type="text" name="meal_name" placeholder="e.g. Chicken Salad" required>
                    
                    <label>Portion (servings) *</label>
                    <input type="number" step="0.25" name="portion" value="1" required>
                    
                    <label>Calories per Portion</label>
                    <input type="number" name="calories_per_portion" placeholder="Leave blank to estimate" min="0">
                    
                    <label>Date *</label>
                    <input type="date" name="meal_date" value="<?php echo date('Y-m-d'); ?>" required>
                    
                    <label>Upload Meal Photo</label>
                    <input type="file" name="meal_image" accept="image/*">
                    
                    <label>Nutrition Notes (optional)</label>
                    <textarea name="nutrition_note" rows="3" placeholder="e.g. chicken breast, veggies, dressing"></textarea>
                    
                    <button type="submit" name="add_meal">Add Meal</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Meal Log</h2>
            <?php if ($target_id): ?>
                <?php if (count($meals) === 0): ?>
                    <p style="color: #666;">No meals logged yet.</p>
                <?php endif; ?>
                <?php foreach ($meals as $meal): ?>
                    <div class="meal-card">
                        <?php if ($meal['image_path'] && file_exists($meal['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($meal['image_path']); ?>" alt="Meal image">
                        <?php else: ?>
                            <div style="width:100px;height:100px;border-radius:14px;background:#eef7ef;display:flex;align-items:center;justify-content:center;color:#6f8a6d;font-size:0.85rem;font-weight:bold;text-align:center;border:1px dashed #cedecf;">No Image</div>
                        <?php endif; ?>
                        <div class="meal-details">
                            <strong><?php echo htmlspecialchars($meal['meal_name']); ?></strong>
                            <?php if (!empty($meal['day_label'])): ?>
                                <p><strong><?php echo htmlspecialchars($meal['day_label']); ?></strong></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($meal['nutrition_note'] ?: 'Nutrition info provided by client'); ?></p>
                            <p>Portion: <?php echo htmlspecialchars($meal['portion']); ?></p>
                            <p>Calories/portion: <?php echo htmlspecialchars($meal['calories_per_portion']); ?> kcal</p>
                            <p><strong>Total: <?php echo htmlspecialchars($meal['total_calories']); ?> kcal</strong></p>
                            <p style="margin:6px 0 0;font-size:0.95rem;color:#4a6c4a;"><strong><?php echo $meal['uploaded_by'] === 'coach' ? 'Uploaded by coach' : 'Uploaded by client'; ?></strong></p>
                            <small style="color:#888; display:block; margin-top:4px;">Logged Date: <?php echo htmlspecialchars($meal['meal_date'] ?: date('Y-m-d', strtotime($meal['created_at']))); ?></small>
                            <div class="meal-actions">
                                <form method="POST" style="display:inline-block; margin:0; padding:0;">
                                    <button type="submit" name="delete_meal" value="<?php echo $meal['id']; ?>">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>