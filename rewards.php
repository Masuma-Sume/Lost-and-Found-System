<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Get user information
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT User_ID, Email, Name, Contact_No 
            FROM user 
            WHERE User_ID = ?";
    
    $stmt = executeQuery($conn, $sql, [$user_id], 's');
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $user = $result->fetch_assoc();

    // Fetch user's total coins
    $total_points = 0;
    $points_sql = "SELECT Total_Points FROM user_rewards WHERE User_ID = ?";
    $stmt_points = $conn->prepare($points_sql);
    if ($stmt_points) {
        $stmt_points->bind_param('s', $user_id);
        $stmt_points->execute();
        $result_points = $stmt_points->get_result();
        if ($row = $result_points->fetch_assoc()) {
            $total_points = $row['Total_Points'];
        }
    }

    // Fetch all rewards for the user
    $all_rewards_sql = "SELECT r.*, i.Item_Name, i.Location, i.Date_Reported, b.Badge_Name FROM rewards r
                        LEFT JOIN items i ON r.Item_ID = i.Item_ID
                        LEFT JOIN badges b ON r.Badge_ID = b.Badge_ID
                        WHERE r.User_ID = ? ORDER BY r.Created_At DESC";
    $stmt_all = $conn->prepare($all_rewards_sql);
    $all_rewards = false;
    if ($stmt_all) {
        $stmt_all->bind_param('s', $user_id);
        $stmt_all->execute();
        $all_rewards = $stmt_all->get_result();
    }

} catch (Exception $e) {
    error_log("Rewards page error: " . $e->getMessage());
    $error_message = "An error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Lost & Found Rewards</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009688;
            --primary-dark: #00796B;
            --secondary-color: #ffffff;
            --text-color: #222;
            --text-light: #666;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Merriweather', serif;
            background: url('image2.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-color);
            line-height: 1.6;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: inherit;
            filter: blur(8px) brightness(0.5);
            z-index: 0;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 1;
        }

        .rewards-header {
            background: linear-gradient(135deg, rgba(0,150,136,0.95), rgba(0,121,107,0.95));
            color: #fff;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .rewards-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .rewards-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .reward-card {
            background-color: rgba(255,255,255,0.98);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: transform 0.3s ease;
        }

        .reward-card:hover {
            transform: translateY(-5px);
        }

        .reward-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reward-details {
            margin-top: 1rem;
        }

        .reward-details p {
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }

        .reward-details strong {
            color: var(--text-color);
        }

        .trophy-icon {
            color: #FFD700;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .no-rewards {
            text-align: center;
            padding: 3rem;
            background-color: rgba(255,255,255,0.98);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .no-rewards i {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .no-rewards p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
            transition: background-color 0.3s ease;
            margin-top: 1rem;
        }

        .back-btn:hover {
            background-color: var(--primary-dark);
        }

        .item-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="rewards-header">
            <h1><i class="fas fa-trophy"></i> Your Rewards</h1>
            <p>Thank you for helping others find their lost items!</p>
            <div style="margin-top:1.5rem;font-size:1.3rem;font-weight:700;">
                <i class="fas fa-coins"></i> Total Coins: <span style="color:#FFD700;"> <?php echo $total_points; ?> </span>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <?php if ($all_rewards && $all_rewards->num_rows > 0): ?>
                <div class="rewards-grid">
                    <?php while($reward = $all_rewards->fetch_assoc()): ?>
                        <div class="reward-card">
                            <div class="trophy-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($reward['Item_Name']); ?></h3>
                            <div class="reward-details">
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($reward['Location']); ?></p>
                                <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($reward['Date_Reported'])); ?></p>
                                <p><strong>Points:</strong> <span style="color:#FFD700;font-weight:700;">+<?php echo $reward['Points']; ?></span></p>
                                <?php if (!empty($reward['Badge_Name'])): ?>
                                    <p><strong>Badge:</strong> <span style="color:#009688;font-weight:700;">🏅 <?php echo htmlspecialchars($reward['Badge_Name']); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-rewards">
                    <i class="fas fa-trophy"></i>
                    <p>You haven't earned any rewards yet.</p>
                    <p>Report found items to start earning rewards!</p>
                    <a href="report_found.php" class="back-btn">
                        <i class="fas fa-plus-circle"></i> Report Found Item
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html> 