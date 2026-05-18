<?php
include 'config.php';

// Check if logged in and coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'coach') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['trainee_id'])) {
    $trainee_id = intval($_POST['trainee_id']);
    $action = $_POST['action'] ?? 'assign';
    
    if ($action === 'assign') {
        $stmt = $conn->prepare("UPDATE users SET assigned_coach_id = ? WHERE id = ? AND role = 'trainee' AND assigned_coach_id IS NULL");
        $stmt->bind_param("ii", $user_id, $trainee_id);
        $stmt->execute();
    } elseif ($action === 'remove' || $action === 'decline') {
        $stmt = $conn->prepare("UPDATE users SET assigned_coach_id = NULL WHERE id = ? AND role = 'trainee' AND (assigned_coach_id = ? OR assigned_coach_id IS NULL)");
        $stmt->bind_param("ii", $trainee_id, $user_id);
        $stmt->execute();
    }
    
    header('Location: clients.php');
    exit();
}

// FIXED SQL: Only fetch trainees where they are assigned to this coach, OR where they have specifically applied to this coach ID
$stmt = $conn->prepare("SELECT t.id, t.username, t.email, t.created_at, t.assigned_coach_id, c.username AS coach_name FROM users t LEFT JOIN users c ON t.assigned_coach_id = c.id WHERE t.role = 'trainee' AND t.assigned_coach_id = ? ORDER BY t.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - TrackMy Bite</title>
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
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4caf50;
            color: white;
        }
        .status {
            padding: 6px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .available {
            background-color: #dcedc8;
            color: #33691e;
        }
        .assigned {
            background-color: #c8e6c9;
            color: #1b5e20;
        }
        .choose-button {
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            margin-right: 6px;
        }
        .choose-button:hover {
            background-color: #45a049;
        }
        .remove-button {
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .remove-button:hover {
            background-color: #c62828;
        }
        .action-cell {
            display: flex;
            gap: 8px;
        }
        .action-cell form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Clients</h1>
        <a href="logout.php">Logout</a>
    </div>
    <div class="nav">
        <a href="dashboard.php">Profile</a>
        <a href="clients.php">Clients</a>
        <a href="newsfeed.php">Messages</a>
        <a href="coach_meal_tracker.php">Meal Planner</a>
    </div>
    <div class="container">
        <h2>Trainees</h2>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while ($user = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                    <td>
                        <?php if ($user['assigned_coach_id'] == $user_id): ?>
                            <span class="status assigned">Active Client</span>
                        <?php else: ?>
                            <span class="status available">Applied / New Request</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-cell">
                            <?php if ($user['assigned_coach_id'] == $user_id): ?>
                                <a href="trainee_details.php?id=<?php echo $user['id']; ?>" class="choose-button" style="text-decoration: none;">Client Details</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="trainee_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="remove-button" onclick="return confirm('Remove this trainee due to inactivity?');">Remove</button>
                                </form>
                            <?php elseif (!$user['assigned_coach_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="trainee_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="assign">
                                    <button type="submit" class="choose-button">Accept Request</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="trainee_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button type="submit" class="remove-button" onclick="return confirm('Decline this application request?');">No</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>