<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// AUTOMATIC MIGRATION: Check if image_path column exists in meal_entries table, create it if it doesn't
$imageColumnCheck = $conn->query("SHOW COLUMNS FROM meal_entries LIKE 'image_path'");
if ($imageColumnCheck && $imageColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE meal_entries ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER meal_name");
}

// BACKEND: Handle Deleting a Specific Meal Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal'])) {
    $delete_id = intval($_POST['meal_id']);
    $deleteStmt = $conn->prepare("DELETE FROM meal_entries WHERE id = ? AND user_id = ?");
    $deleteStmt->bind_param("ii", $delete_id, $user_id);
    if ($deleteStmt->execute()) {
        $message = 'Meal deleted successfully.';
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal_entry'])) {
    $meal_name = trim($_POST['meal_name'] ?? 'Meal');
    $portion = !empty($_POST['portion']) ? floatval($_POST['portion']) : 1;
    $calories_per_portion = !empty($_POST['calories_per_portion']) ? intval($_POST['calories_per_portion']) : 0;
    $nutrition_note = trim($_POST['nutrition_note'] ?? '');
    $custom_date = !empty($_POST['created_at']) ? trim($_POST['created_at']) : date('Y-m-d');
    $created_at_timestamp = $custom_date . ' ' . date('H:i:s');
    $image_path = null;

    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === 0) {
        $upload_dir = 'uploads/meals/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = $user_id . '_' . time() . '_' . basename($_FILES['meal_image']['name']);
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
    
    // UPDATED: Added target created_at handling for the custom timeline injection
    $insertMealStmt = $conn->prepare("INSERT INTO meal_entries (`user_id`, `coach_id`, `meal_name`, `image_path`, `portion`, `calories_per_portion`, `total_calories`, `nutrition_note`, `uploaded_by`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'coach', ?)");
    $insertMealStmt->bind_param("iissdiiss", $user_id, $user_id, $meal_name, $image_path, $portion, $calories_per_portion, $total_calories, $nutrition_note, $created_at_timestamp);
    $insertMealStmt->execute();

    $message = 'Meal logged successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bio'])) {
    $bio_text = trim($_POST['bio'] ?? '');
    $bioColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($bioColumn && $bioColumn->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
    }
    $updateBioStmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $updateBioStmt->bind_param("si", $bio_text, $user_id);
    $updateBioStmt->execute();
    $message = 'Plan focus updated successfully.';
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

$coachMeals = [];
$mealTotalCalories = 0;
$todayCalories = 0;
$mealStmt = $conn->prepare("SELECT * FROM meal_entries WHERE user_id = ? AND uploaded_by = 'coach' ORDER BY created_at DESC");
$mealStmt->bind_param("i", $user_id);
$mealStmt->execute();
$mealResult = $mealStmt->get_result();
while ($row = $mealResult->fetch_assoc()) {
    $coachMeals[] = $row;
    $mealTotalCalories += $row['total_calories'];
    if (date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d')) {
        $todayCalories += $row['total_calories'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Meal Planner - TrackMy Bite</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f0f4f3; }
        .header { background: #4caf50; color: white; padding: 16px; text-align: center; }
        .nav { background: #3d8d3d; text-align: center; padding: 12px; }
        .nav a { color: white; margin: 0 12px; text-decoration: none; font-weight: bold; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .card { background: white; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.08); padding: 24px; margin-bottom: 20px; }
        h1, h2, h3 { color: #224d24; }
        label, input, textarea { display: block; width: 100%; margin-bottom: 10px; }
        input, textarea { padding: 12px; border: 1px solid #d7ddd8; border-radius: 10px; background: #f8faf8; }
        button { background: #4caf50; color: white; padding: 12px 18px; border: none; border-radius: 10px; cursor: pointer; }
        button:hover { background: #3b8b3d; }
        .meal-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
        .meal-box { background: #edf7ee; border: 1px solid #d7edd8; border-radius: 12px; padding: 16px; text-align: center; }
        .meal-box strong { display: block; margin-top: 8px; font-size: 1.3rem; }
        .meal-card { display: grid; grid-template-columns: 100px 1fr; gap: 16px; padding: 18px; border: 1px solid #e2ede4; border-radius: 16px; margin-bottom: 16px; position: relative; }
        .meal-card img { width: 100%; height: 100px; object-fit: cover; border-radius: 14px; }
        .meal-details strong { display: block; margin-bottom: 8px; }
        .message { padding: 14px 18px; background: #dff3dc; border-radius: 12px; margin-bottom: 16px; color: #24562c; border-left: 5px solid #4caf50; }
        .btn-delete { background: #f44336; color: white; padding: 6px 12px; font-size: 0.85rem; border-radius: 6px; border: none; cursor: pointer; float: right; margin-top: -5px; }
        .btn-delete:hover { background: #d32f2f; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Coach Meal Planner</h1>
    </div>
    <div class="nav">
        <a href="dashboard.php">Profile</a>
        <a href="clients.php">Clients</a>
        <a href="newsfeed.php">Messages</a>
        <a href="coach_meal_tracker.php">Meal Planner</a>
    </div>
    <div class="container">
        <div class="card">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <h2>Plan Focus</h2>
            <p>Describe the physique goal, target body type, and meal benefits so trainees can see what your plan supports.</p>
            <form method="POST">
                <textarea name="bio" rows="5" placeholder="Add your coach plan focus or bio..."><?php echo htmlspecialchars($bio); ?></textarea>
                <button type="submit" name="save_bio">Save Plan Focus</button>
            </form>
        </div>
        <div class="card">
            <h2>Log a Meal</h2>
            <form method="POST" enctype="multipart/form-data">
                <label>Meal Name</label>
                <input type="text" name="meal_name" placeholder="e.g. Chicken Salad" required>
                
                <label>Log Date</label>
                <input type="date" name="created_at" value="<?php echo date('Y-m-d'); ?>" required>

                <label>Upload Meal Photo</label>
                <input type="file" name="meal_image" accept="image/*">
                <label>Portion</label>
                <input type="number" step="0.25" name="portion" value="1" required>
                <label>Calories per Portion</label>
                <input type="number" name="calories_per_portion" placeholder="Leave blank to estimate" min="0">
                <label>Nutrition Notes</label>
                <textarea name="nutrition_note" rows="3" placeholder="e.g. chicken breast, veggies, dressing"></textarea>
                <button type="submit" name="save_meal_entry">Add Meal</button>
            </form>
        </div>
        <div class="card">
            <h2>Summary</h2>
            <div class="meal-summary">
                <div class="meal-box">
                    <span>Today</span>
                    <strong><?php echo $todayCalories; ?> kcal</strong>
                </div>
                <div class="meal-box">
                    <span>Total Calories</span>
                    <strong><?php echo $mealTotalCalories; ?> kcal</strong>
                </div>
                <div class="meal-box">
                    <span>Meals Logged</span>
                    <strong><?php echo count($coachMeals); ?></strong>
                </div>
            </div>
            <?php if (count($coachMeals) === 0): ?>
                <p>No meals logged yet.</p>
            <?php else: ?>
                <?php foreach ($coachMeals as $meal): ?>
                    <div class="meal-card">
                        <?php if ($meal['image_path'] && file_exists($meal['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($meal['image_path']); ?>" alt="Meal image">
                        <?php else: ?>
                            <div style="width:100px;height:100px;border-radius:14px;background:#eef7ef;display:flex;align-items:center;justify-content:center;color:#6f8a6d;font-size:0.85rem;font-weight:bold;text-align:center;border:1px dashed #cedecf;">No Image</div>
                        <?php endif; ?>
                        <div class="meal-details">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this meal entry?');">
                                <input type="hidden" name="meal_id" value="<?php echo $meal['id']; ?>">
                                <button type="submit" name="delete_meal" class="btn-delete">Delete</button>
                            </form>
                            <strong><?php echo htmlspecialchars($meal['meal_name']); ?></strong>
                            <p><?php echo htmlspecialchars($meal['nutrition_note'] ?: 'No nutrition note'); ?></p>
                            <p>Portion: <?php echo htmlspecialchars($meal['portion']); ?></p>
                            <p>Calories/portion: <?php echo htmlspecialchars($meal['calories_per_portion']); ?> kcal</p>
                            <p><strong>Total: <?php echo htmlspecialchars($meal['total_calories']); ?> kcal</strong></p>
                            <small style="color:#888; display:block; margin-top:6px;">Logged: <?php echo date('F j, Y, g:i a', strtotime($meal['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>