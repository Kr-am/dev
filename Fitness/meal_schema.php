<?php
$mysqli = new mysqli('localhost', 'root', 'Number_606', 'trackmybite');
if ($mysqli->connect_error) {
    echo 'Connect error: ' . $mysqli->connect_error . "\n";
    exit(1);
}
$queries = [
    "CREATE TABLE IF NOT EXISTS calorie_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        coach_id INT NOT NULL,
        daily_goal INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_goal (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS meal_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        coach_id INT NOT NULL,
        meal_name VARCHAR(255) NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        portion DECIMAL(5,2) NOT NULL DEFAULT 1,
        calories_per_portion INT NOT NULL DEFAULT 0,
        total_calories INT NOT NULL DEFAULT 0,
        nutrition_note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS coach_meal_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coach_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        plan_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
];
foreach ($queries as $query) {
    if (!$mysqli->query($query)) {
        echo 'Error: ' . $mysqli->error . "\n";
        exit(1);
    }
}
echo "Meal tracker schema created.\n";