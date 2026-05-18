<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainee') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle meal addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meal'])) {
    $meal_name = trim($_POST['meal_name'] ?? '');
    $portion = !empty($_POST['portion']) ? floatval($_POST['portion']) : 1;
    $calories_per_portion = !empty($_POST['calories_per_portion']) ? intval($_POST['calories_per_portion']) : 0;
    $nutrition_note = trim($_POST['nutrition_note'] ?? '');
    $meal_date = $_POST['meal_date'] ?? $selected_date;
    
    $total_calories = (int)round($portion * $calories_per_portion);
    
    // FIXED: Wrapped column names in backticks to prevent MariaDB reserved keyword conflicts
    $stmt = $conn->prepare("INSERT INTO meal_entries (`user_id`, `coach_id`, `meal_name`, `portion`, `calories_per_portion`, `total_calories`, `nutrition_note`, `uploaded_by`, `meal_date`) VALUES (?, 0, ?, ?, ?, ?, ?, 'trainee', ?)");
    $stmt->bind_param("isidiss", $user_id, $meal_name, $portion, $calories_per_portion, $total_calories, $nutrition_note, $meal_date);
    
    if ($stmt->execute()) {
        $message = 'Meal added successfully!';
    }
}

// Handle meal deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal'])) {
    $meal_id = intval($_POST['delete_meal']);
    $stmt = $conn->prepare("DELETE FROM meal_entries WHERE id = ? AND user_id = ? AND uploaded_by = 'trainee'");
    $stmt->bind_param("ii", $meal_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Meal deleted successfully!';
    }
}

// Get all meals for the user
$mealsStmt = $conn->prepare("SELECT * FROM meal_entries WHERE user_id = ? AND uploaded_by = 'trainee' ORDER BY meal_date DESC, created_at DESC");
$mealsStmt->bind_param("i", $user_id);
$mealsStmt->execute();
$allMeals = $mealsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group meals by date
$mealsByDate = [];
foreach ($allMeals as $meal) {
    if (!isset($mealsByDate[$meal['meal_date']])) {
        $mealsByDate[$meal['meal_date']] = [];
    }
    $mealsByDate[$meal['meal_date']][] = $meal;
}

// Get meals for selected date
$selectedDateMeals = $mealsByDate[$selected_date] ?? [];
$totalCaloriesForDay = array_sum(array_column($selectedDateMeals, 'total_calories'));

// Get unique dates with meals
$uniqueDates = array_keys($mealsByDate);
rsort($uniqueDates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Tracker - TrackMyBite</title>
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
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        .message {
            grid-column: 1 / -1;
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        .sidebar {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            height: fit-content;
        }
        .sidebar h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .date-list {
            list-style: none;
        }
        .date-list li {
            margin-bottom: 8px;
        }
        .date-list a {
            display: block;
            padding: 10px;
            background-color: #f7f7f7;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .date-list a:hover,
        .date-list a.active {
            background-color: #4caf50;
            color: white;
        }
        .main {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .date-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4caf50;
        }
        .date-header h2 {
            color: #333;
        }
        .total-calories {
            background-color: #4caf50;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 18px;
        }
        .add-meal-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-grid.full {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: Arial, sans-serif;
        }
        textarea {
            resize: vertical;
            min-height: 60px;
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
        .meals-list {
            margin-top: 20px;
        }
        .meal-item {
            background-color: #f9f9f9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .meal-details {
            flex: 1;
        }
        .meal-name {
            font-weight: bold;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .meal-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .meal-calories {
            font-weight: bold;
            color: #4caf50;
            font-size: 16px;
        }
        .meal-actions {
            display: flex;
            gap: 10px;
        }
        .delete-btn {
            background-color: #e53935;
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        .delete-btn:hover {
            background-color: #c62828;
        }
        .no-meals {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .api-status {
            font-size: 12px;
            color: #2e7d32;
            margin-top: 4px;
            font-style: italic;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Meal Tracker</h1>
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

        <div class="sidebar">
            <h3>📅 Your Meal Dates</h3>
            <?php if (count($uniqueDates) > 0): ?>
                <ul class="date-list">
                    <?php foreach ($uniqueDates as $date): ?>
                        <li>
                            <a href="meal_tracker.php?date=<?php echo $date; ?>" class="<?php echo $date === $selected_date ? 'active' : ''; ?>">
                                <?php echo date('M d, Y', strtotime($date)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: #999;">No meals logged yet</p>
            <?php endif; ?>
        </div>

        <div class="main">
            <div class="date-header">
                <h2><?php echo date('l, F j, Y', strtotime($selected_date)); ?></h2>
                <div class="total-calories">
                    Total: <?php echo $totalCaloriesForDay; ?> kcal
                </div>
            </div>

            <div class="add-meal-form">
                <h3>Add New Meal</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div>
                            <label>Meal Name *</label>
                            <input type="text" id="meal_name" name="meal_name" placeholder="e.g., Chicken Adobo, Pork Sinigang, Gulay..." autocomplete="off" required>
                            <div id="search_status" class="api-status">Smart-calculating active...</div>
                        </div>
                        <div>
                            <label>Portion (servings) *</label>
                            <input type="number" name="portion" step="0.1" min="0.1" value="1" placeholder="1" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div>
                            <label>Calories per Portion *</label>
                            <input type="number" id="calories_per_portion" name="calories_per_portion" min="0" placeholder="Type a meal name..." required>
                        </div>
                        <div>
                            <label>Date *</label>
                            <input type="date" name="meal_date" value="<?php echo $selected_date; ?>" required>
                        </div>
                    </div>
                    <div class="form-grid full">
                        <div>
                            <label>Nutrition Notes (optional)</label>
                            <textarea name="nutrition_note" placeholder="Add any notes about this meal..."></textarea>
                        </div>
                    </div>
                    <button type="submit" name="add_meal">Add Meal</button>
                </form>
            </div>

            <div class="meals-list">
                <h3>Meals for this day</h3>
                <?php if (count($selectedDateMeals) > 0): ?>
                    <?php foreach ($selectedDateMeals as $meal): ?>
                        <div class="meal-item">
                            <div class="meal-details">
                                <div class="meal-name"><?php echo htmlspecialchars($meal['meal_name']); ?></div>
                                <div class="meal-info">
                                    Portion: <?php echo $meal['portion']; ?> × <?php echo $meal['calories_per_portion']; ?> kcal/portion
                                </div>
                                <?php if ($meal['nutrition_note']): ?>
                                    <div class="meal-info">📝 <?php echo htmlspecialchars($meal['nutrition_note']); ?></div>
                                <?php endif; ?>
                                <div class="meal-calories">Total: <?php echo $meal['total_calories']; ?> kcal</div>
                            </div>
                            <div class="meal-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="delete_meal" value="<?php echo $meal['id']; ?>">
                                    <button type="submit" class="delete-btn" onclick="return confirm('Delete this meal?');">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-meals">
                        <p>No meals logged for this date yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('meal_name').addEventListener('input', function() {
            const inputStr = this.value.toLowerCase().trim();
            const calorieInput = document.getElementById('calories_per_portion');
            const statusDiv = document.getElementById('search_status');

            if (!inputStr) {
                calorieInput.value = '';
                statusDiv.innerText = "Smart-calculating active...";
                return;
            }

            let estimatedCals = 0;
            let matchedKeyword = "";

            if (inputStr.includes("adobo")) {
                estimatedCals = inputStr.includes("chicken") ? 280 : 350;
                matchedKeyword = "Adobo standard serving";
            } else if (inputStr.includes("sinigang")) {
                estimatedCals = inputStr.includes("pork") ? 310 : 190;
                matchedKeyword = "Sinigang standard bowl";
            } else if (inputStr.includes("gulay") || inputStr.includes("vegetable") || inputStr.includes("pinakbet") || inputStr.includes("chopsuey")) {
                estimatedCals = 120;
                matchedKeyword = "Mixed Vegetables / Gulay side";
            } else if (inputStr.includes("tinola")) {
                estimatedCals = 220;
                matchedKeyword = "Chicken Tinola bowl";
            } else if (inputStr.includes("bicol express")) {
                estimatedCals = 420;
                matchedKeyword = "Bicol Express serving";
            } else if (inputStr.includes("nilaga")) {
                estimatedCals = inputStr.includes("beef") ? 340 : 260;
                matchedKeyword = "Nilaga soup serving";
            } else if (inputStr.includes("rice")) {
                estimatedCals = inputStr.includes("brown") ? 215 : 205;
                matchedKeyword = "1 Cup of Rice";
            } else if (inputStr.includes("chicken breast")) {
                estimatedCals = 165;
                matchedKeyword = "Chicken Breast (100g)";
            } else if (inputStr.includes("egg")) {
                estimatedCals = inputStr.includes("fried") ? 90 : 78;
                matchedKeyword = "1 Whole Egg";
            } else if (inputStr.includes("tuna")) {
                estimatedCals = 120;
                matchedKeyword = "Canned Tuna serving";
            } else if (inputStr.includes("banana")) {
                estimatedCals = 105;
                matchedKeyword = "1 Medium Banana";
            } else if (inputStr.includes("shake") || inputStr.includes("protein")) {
                estimatedCals = 140;
                matchedKeyword = "Protein Supplement Serving";
            }

            if (estimatedCals > 0) {
                calorieInput.value = estimatedCals;
                statusDiv.innerText = `Auto-calculated based on: ${matchedKeyword}`;
            } else {
                statusDiv.innerText = "Analyzing query... (or enter custom calories manually)";
            }
        });
    </script>
</body>
</html>