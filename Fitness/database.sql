-- ============================================================================
-- Database schema for TrackMy Bite - Fitness Coaching Platform
-- ============================================================================
-- 
-- FEATURES & REQUIRED TABLES:
-- 1. Trainee Home (Coach Selection):     users + trainee_coach_applications
-- 2. Trainee Profile (Pic & Bio):        users (profile_pic, bio, age, gender, height, weight, target_goal)
-- 3. Meal Tracker (Calendar & Calories): meal_entries (with auto-calculation)
-- 4. Messages (Private Chat):            messages + users(assigned_coach_id)
-- 5. Coach Dashboard (Client Manager):   users + trainee_coach_applications + meal_entries
--
-- ============================================================================

CREATE DATABASE IF NOT EXISTS trackmybite;
USE trackmybite;

-- ============================================================================
-- USERS TABLE - All coaches and trainees
-- ============================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('coach', 'trainee') NOT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    cover_pic VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    age INT DEFAULT NULL,
    gender ENUM('male', 'female') DEFAULT NULL,
    height DECIMAL(5,1) DEFAULT NULL,
    weight DECIMAL(5,1) DEFAULT NULL,
    target_goal VARCHAR(255) DEFAULT NULL,
    assigned_coach_id INT DEFAULT NULL, -- CRITICAL: Manages the active coach-trainee link
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================================
-- MEAL ENTRIES TABLE - Trainee meal logging with AUTO-CALCULATED calories
-- ============================================================================
CREATE TABLE IF NOT EXISTS `meal_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `coach_id` INT DEFAULT 0,
  `meal_name` VARCHAR(255) NOT NULL,
  `portion` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `calories_per_portion` INT NOT NULL DEFAULT 0,
  `total_calories` INT NOT NULL DEFAULT 0,
  `nutrition_note` TEXT DEFAULT NULL,
  `uploaded_by` ENUM('coach', 'trainee') NOT NULL DEFAULT 'trainee',
  `meal_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- CALORIE GOALS TABLE - Daily calorie targets per trainee
-- ============================================================================
CREATE TABLE IF NOT EXISTS calorie_goals (
    user_id INT PRIMARY KEY,
    coach_id INT NOT NULL,
    daily_goal INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- COACH MEAL PLANS TABLE - Meal plans created by coaches
-- ============================================================================
CREATE TABLE IF NOT EXISTS coach_meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    plan_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- MESSAGES TABLE - Private coach-trainee conversations
-- ============================================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    trainee_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TRAINEE COACH APPLICATIONS TABLE - Trainee applies to coaches
-- ============================================================================
CREATE TABLE IF NOT EXISTS trainee_coach_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainee_id INT NOT NULL,
    coach_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (trainee_id, coach_id),
    FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;