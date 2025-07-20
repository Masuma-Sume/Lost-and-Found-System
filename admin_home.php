<?php
require_once 'config.php';
session_start();

// Redirect to login if not admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch dashboard stats
$total_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
$total_lost = $conn->query("SELECT COUNT(*) as count FROM items WHERE Item_Type = 'lost'")->fetch_assoc()['count'];
$total_found = $conn->query("SELECT COUNT(*) as count FROM items WHERE Item_Type = 'found'")->fetch_assoc()['count'];
$open_cases = $conn->query("SELECT COUNT(*) as count FROM items WHERE Status = 'open'")->fetch_assoc()['count'];
$closed_cases = $conn->query("SELECT COUNT(*) as count FROM items WHERE Status = 'closed'")->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - BRAC UNIVERSITY LOST & FOUND</title>
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
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Merriweather', serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .top-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 1px;
        }
        .nav-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Merriweather', serif;
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .card {
            background-color: var(--secondary-color);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 2rem 1.5rem;
            text-align: center;
        }
        .card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .card .stat {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .quick-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .quick-link {
            background: var(--primary-color);
            color: white;
            padding: 1.2rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            box-shadow: var(--shadow);
            transition: background 0.3s;
        }
        .quick-link:hover {
            background: var(--primary-dark);
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 0 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-user-shield"></i> Admin Dashboard
        </div>
        <div class="nav-menu">
            <a href="admin_home.php" class="nav-item"><i class="fas fa-home"></i> Home</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <h2 style="color: var(--primary-color); margin-bottom: 2rem;">Welcome, Admin!</h2>
        <div class="dashboard-grid">
            <div class="card">
                <h3><i class="fas fa-users"></i> Total Users</h3>
                <div class="stat"><?php echo $total_users; ?></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-search"></i> Lost Items</h3>
                <div class="stat"><?php echo $total_lost; ?></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-box-open"></i> Found Items</h3>
                <div class="stat"><?php echo $total_found; ?></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-folder-open"></i> Open Cases</h3>
                <div class="stat"><?php echo $open_cases; ?></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-check-circle"></i> Closed Cases</h3>
                <div class="stat"><?php echo $closed_cases; ?></div>
            </div>
        </div>
        <div class="quick-links">
            <a href="manage_users.php" class="quick-link"><i class="fas fa-users-cog"></i> Manage Users</a>
            <a href="all_reports.php" class="quick-link"><i class="fas fa-list"></i> View All Reports</a>
            <a href="monthly_reports.php" class="quick-link"><i class="fas fa-chart-line"></i> Monthly Reports</a>
        </div>
    </div>
</body>
</html> 