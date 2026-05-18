<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = '';
$selected_trainee_id = isset($_GET['trainee_id']) ? intval($_GET['trainee_id']) : 0;
$selected_coach_id = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
$coach_id = null;
$chatMessages = [];
$trainees = [];
$coaches = [];
$coachInfo = null;
$selectedTrainee = null;
$selectedCoach = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $messageText = trim($_POST['message'] ?? '');

    if ($messageText !== '') {
        if ($role === 'trainee') {
            $selected_coach_id = intval($_POST['coach_id'] ?? 0);
            
            // Validate the trainee is actually assigned to this coach
            $coachStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'coach' AND (id = (SELECT assigned_coach_id FROM users WHERE id = ?))");
            $coachStmt->bind_param("ii", $selected_coach_id, $user_id);
            $coachStmt->execute();
            $coachResult = $coachStmt->get_result();
            if ($coachResult && $coachResult->num_rows > 0) {
                $insertStmt = $conn->prepare("INSERT INTO messages (coach_id, trainee_id, sender_id, content) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("iiis", $selected_coach_id, $user_id, $user_id, $messageText);
                $insertStmt->execute();
            }
        } elseif ($role === 'coach') {
            $selected_trainee_id = intval($_POST['trainee_id'] ?? 0);
            $checkTrainee = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'trainee' AND assigned_coach_id = ?");
            $checkTrainee->bind_param("ii", $selected_trainee_id, $user_id);
            $checkTrainee->execute();
            $checkResult = $checkTrainee->get_result();
            if ($checkResult && $checkResult->num_rows > 0) {
                $insertStmt = $conn->prepare("INSERT INTO messages (coach_id, trainee_id, sender_id, content) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("iiis", $user_id, $selected_trainee_id, $user_id, $messageText);
                $insertStmt->execute();
            }
        }
    }

    $redirectUrl = 'newsfeed.php';
    if ($role === 'coach' && $selected_trainee_id) {
        $redirectUrl .= '?trainee_id=' . $selected_trainee_id;
    } elseif ($role === 'trainee' && $selected_coach_id) {
        $redirectUrl .= '?coach_id=' . $selected_coach_id;
    }
    header('Location: ' . $redirectUrl);
    exit();
}

if ($role === 'trainee') {
    // Get all accepted coaches for this trainee
    $coachesStmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'coach' AND (id = (SELECT assigned_coach_id FROM users WHERE id = ?)) ORDER BY username ASC");
    $coachesStmt->bind_param("i", $user_id);
    $coachesStmt->execute();
    $coachesResult = $coachesStmt->get_result();
    while ($row = $coachesResult->fetch_assoc()) {
        $coaches[] = $row;
    }

    if ($selected_coach_id) {
        foreach ($coaches as $c) {
            if ($c['id'] == $selected_coach_id) {
                $selectedCoach = $c;
                break;
            }
        }
        if (!$selectedCoach && count($coaches) > 0) {
            $selectedCoach = $coaches[0];
            $selected_coach_id = $selectedCoach['id'];
        }
    } elseif (count($coaches) > 0) {
        $selectedCoach = $coaches[0];
        $selected_coach_id = $selectedCoach['id'];
    }

    if ($selected_coach_id) {
        $coach_id = $selected_coach_id;
        $messagesStmt = $conn->prepare(
            "SELECT m.*, u.username FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.coach_id = ? AND m.trainee_id = ? ORDER BY m.created_at ASC"
        );
        $messagesStmt->bind_param("ii", $selected_coach_id, $user_id);
        $messagesStmt->execute();
        $messagesResult = $messagesStmt->get_result();
        while ($row = $messagesResult->fetch_assoc()) {
            $chatMessages[] = $row;
        }
    }
} else {
    $traineeStmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'trainee' AND assigned_coach_id = ? ORDER BY username ASC");
    $traineeStmt->bind_param("i", $user_id);
    $traineeStmt->execute();
    $traineeResult = $traineeStmt->get_result();
    while ($row = $traineeResult->fetch_assoc()) {
        $trainees[] = $row;
    }

    if ($selected_trainee_id) {
        foreach ($trainees as $t) {
            if ($t['id'] == $selected_trainee_id) {
                $selectedTrainee = $t;
                break;
            }
        }
        if (!$selectedTrainee && count($trainees) > 0) {
            $selectedTrainee = $trainees[0];
            $selected_trainee_id = $selectedTrainee['id'];
        }
    } elseif (count($trainees) > 0) {
        $selectedTrainee = $trainees[0];
        $selected_trainee_id = $selectedTrainee['id'];
    }

    if ($selected_trainee_id) {
        $messagesStmt = $conn->prepare(
            "SELECT m.*, u.username FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.coach_id = ? AND m.trainee_id = ? ORDER BY m.created_at ASC"
        );
        $messagesStmt->bind_param("ii", $user_id, $selected_trainee_id);
        $messagesStmt->execute();
        $messagesResult = $messagesStmt->get_result();
        while ($row = $messagesResult->fetch_assoc()) {
            $chatMessages[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - TrackMy Bite</title>
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
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px;
        }
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .panel.full {
            flex: 1 1 700px;
            min-width: 320px;
        }
        .panel.sidebar {
            width: 280px;
            min-width: 280px;
        }
        .sidebar h2,
        .full h2 {
            margin-top: 0;
        }
        .trainee-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .trainee-list li {
            margin-bottom: 10px;
        }
        .trainee-list a {
            display: block;
            padding: 10px;
            background: #f7f7f7;
            border-radius: 6px;
            color: #333;
            text-decoration: none;
        }
        .trainee-list a.active {
            background: #4caf50;
            color: white;
        }
        .chat-box {
            max-height: 620px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .message-row {
            display: flex;
            margin-bottom: 14px;
        }
        .message-bubble {
            padding: 12px 16px;
            border-radius: 16px;
            max-width: 80%;
            line-height: 1.4;
        }
        .sent {
            margin-left: auto;
            background-color: #dcedc8;
            border-bottom-right-radius: 4px;
        }
        .received {
            margin-right: auto;
            background-color: #fff;
            border-bottom-left-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .message-meta {
            font-size: 0.85rem;
            color: #666;
            margin-top: 4px;
        }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            resize: vertical;
            box-sizing: border-box;
            margin-bottom: 12px;
        }
        button {
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background-color: #45a049;
        }
        .notice {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Messages</h1>
        <a href="logout.php" style="color: white;">Logout</a>
    </div>
    <div class="nav">
        <a href="<?php echo $role === 'coach' ? 'dashboard.php' : 'trainee.php'; ?>">Profile</a>
        <?php if ($role === 'coach'): ?>
            <a href="clients.php">Clients</a>
        <?php endif; ?>
        <a href="newsfeed.php">Messages</a>
        <?php if ($role === 'coach'): ?>
            <a href="coach_meal_tracker.php">Meal Planner</a>
        <?php else: ?>
            <a href="meal_tracker.php">Meal Tracker</a>
        <?php endif; ?>
    </div>
    <div class="container">
        <?php if ($role === 'coach'): ?>
            <div class="panel sidebar">
                <h2>Your Trainees</h2>
                <?php if (count($trainees) === 0): ?>
                    <p>No trainees assigned yet.</p>
                <?php else: ?>
                    <ul class="trainee-list">
                        <?php foreach ($trainees as $trainee): ?>
                            <li>
                                <a href="newsfeed.php?trainee_id=<?php echo $trainee['id']; ?>" class="<?php echo $selected_trainee_id == $trainee['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($trainee['username']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="panel full">
                <h2>Coach-Trainee Chat</h2>
                <?php if (!$selected_trainee_id): ?>
                    <div class="notice">Choose a trainee to open the private chat.</div>
                <?php else: ?>
                    <p>Chat with <strong><?php echo htmlspecialchars($selectedTrainee['username']); ?></strong>.</p>
                    <div class="chat-box">
                        <?php if (count($chatMessages) === 0): ?>
                            <p>No messages yet. Start the conversation below.</p>
                        <?php else: ?>
                            <?php foreach ($chatMessages as $chat): ?>
                                <div class="message-row">
                                    <div class="message-bubble <?php echo $chat['sender_id'] === $user_id ? 'sent' : 'received'; ?>">
                                        <?php echo nl2br(htmlspecialchars($chat['content'])); ?>
                                        <div class="message-meta"><?php echo htmlspecialchars($chat['username']); ?> • <?php echo $chat['created_at']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <textarea name="message" placeholder="Write your message..." required></textarea>
                        <input type="hidden" name="trainee_id" value="<?php echo $selected_trainee_id; ?>">
                        <button type="submit" name="send_message">Send Message</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="panel sidebar">
                <h2>Your Coaches</h2>
                <?php if (count($coaches) === 0): ?>
                    <p>No coaches assigned yet.</p>
                <?php else: ?>
                    <ul class="trainee-list">
                        <?php foreach ($coaches as $coach_item): ?>
                            <li>
                                <a href="newsfeed.php?coach_id=<?php echo $coach_item['id']; ?>" class="<?php echo $selected_coach_id == $coach_item['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($coach_item['username']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="panel full">
                <h2>Private Chat with Your Coach</h2>
                <?php if (!$selected_coach_id): ?>
                    <div class="notice">You do not have an accepted coach yet. This chat opens once a coach accepts your request.</div>
                <?php else: ?>
                    <p>Chat with <strong><?php echo htmlspecialchars($selectedCoach['username']); ?></strong>.</p>
                    <div class="chat-box">
                        <?php if (count($chatMessages) === 0): ?>
                            <p>No messages yet. Send a message to your coach.</p>
                        <?php else: ?>
                            <?php foreach ($chatMessages as $chat): ?>
                                <div class="message-row">
                                    <div class="message-bubble <?php echo $chat['sender_id'] === $user_id ? 'sent' : 'received'; ?>">
                                        <?php echo nl2br(htmlspecialchars($chat['content'])); ?>
                                        <div class="message-meta"><?php echo htmlspecialchars($chat['username']); ?> • <?php echo $chat['created_at']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <textarea name="message" placeholder="Write your message..." required></textarea>
                        <input type="hidden" name="coach_id" value="<?php echo $selected_coach_id; ?>">
                        <button type="submit" name="send_message">Send Message</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>