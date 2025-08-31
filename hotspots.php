<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$hotspots_data = [];
$recent_activity = [];

try {
    // Get hotspots with detailed statistics
    $hotspots_sql = "SELECT 
                        Location,
                        COUNT(*) as total_items,
                        SUM(CASE WHEN Item_Type = 'lost' THEN 1 ELSE 0 END) as lost_items,
                        SUM(CASE WHEN Item_Type = 'found' THEN 1 ELSE 0 END) as found_items,
                        SUM(CASE WHEN Status = 'claimed' THEN 1 ELSE 0 END) as resolved_items,
                        MAX(Date_Reported) as last_activity
                     FROM items 
                     GROUP BY Location 
                     ORDER BY total_items DESC, last_activity DESC 
                     LIMIT 15";
    
    $stmt = executeQuery($conn, $hotspots_sql, [], '');
    $hotspots_data = fetchAll($stmt);

    // Get recent activity for each hotspot
    $activity_sql = "SELECT i.*, u.Name as Reporter_Name, c.Category_Name
                     FROM items i
                     LEFT JOIN user u ON i.User_ID = u.User_ID
                     LEFT JOIN categories c ON i.Category_ID = c.Category_ID
                     ORDER BY i.Date_Reported DESC
                     LIMIT 20";
    
    $stmt = executeQuery($conn, $activity_sql, [], '');
    $recent_activity = fetchAll($stmt);

    // Get category distribution for hotspots
    $category_sql = "SELECT c.Category_Name, COUNT(*) as count
                     FROM items i
                     LEFT JOIN categories c ON i.Category_ID = c.Category_ID
                     GROUP BY c.Category_ID, c.Category_Name
                     ORDER BY count DESC";
    
    $stmt = executeQuery($conn, $category_sql, [], '');
    $category_data = fetchAll($stmt);

} catch (Exception $e) {
    error_log("Hotspots error: " . $e->getMessage());
    $error_message = "An error occurred while fetching hotspot data. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Location Hotspots</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --gradient-primary: linear-gradient(135deg, #009688 0%, #00796B 100%);
            --gradient-secondary: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Merriweather', serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .top-bar {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 1rem;
        }

        .nav-item {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transition: left 0.3s ease;
        }

        .nav-item:hover::before {
            left: 0;
        }

        .nav-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeInUp 0.8s ease;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hotspots-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .hotspots-list {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hotspot-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            background: var(--gradient-secondary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .hotspot-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .hotspot-item:hover {
            transform: translateX(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hotspot-rank {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-right: 1.5rem;
            min-width: 50px;
            text-align: center;
        }

        .hotspot-info {
            flex: 1;
        }

        .hotspot-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .hotspot-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hotspot-stat {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .hotspot-stat.lost {
            color: var(--danger-color);
            background: #ffebee;
        }

        .hotspot-stat.found {
            color: var(--success-color);
            background: #e8f5e9;
        }

        .hotspot-stat.resolved {
            color: var(--info-color);
            background: #e3f2fd;
        }

        .hotspot-total {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .activity-feed {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            max-height: 600px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: white;
        }

        .activity-icon.lost {
            background: var(--danger-color);
        }

        .activity-icon.found {
            background: var(--success-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .activity-details {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 2rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @media (max-width: 768px) {
            .hotspots-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-fire"></i> BRAC UNIVERSITY LOST & FOUND
        </div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-fire pulse"></i> Location Hotspots
                </h1>
                <p class="page-subtitle">
                    Discover the most active areas where items are frequently lost and found on campus
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo count($hotspots_data); ?></div>
                    <div class="stat-label">Active Hotspots</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo array_sum(array_column($hotspots_data, 'total_items')); ?>
                    </div>
                    <div class="stat-label">Total Items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo array_sum(array_column($hotspots_data, 'resolved_items')); ?>
                    </div>
                    <div class="stat-label">Resolved Cases</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $total = array_sum(array_column($hotspots_data, 'total_items'));
                        $resolved = array_sum(array_column($hotspots_data, 'resolved_items'));
                        echo $total > 0 ? round(($resolved / $total) * 100) : 0;
                        ?>%
                    </div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>

            <!-- Hotspots and Activity -->
            <div class="hotspots-grid">
                <div class="hotspots-list">
                    <h2 class="section-title">
                        <i class="fas fa-fire"></i> Top Hotspots
                    </h2>
                    
                    <?php if ($hotspots_data): ?>
                        <?php foreach($hotspots_data as $index => $hotspot): ?>
                            <div class="hotspot-item">
                                <div class="hotspot-rank">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="hotspot-info">
                                    <div class="hotspot-name">
                                        <?php echo htmlspecialchars($hotspot['Location']); ?>
                                    </div>
                                    <div class="hotspot-stats">
                                        <div class="hotspot-stat lost">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo $hotspot['lost_items']; ?> Lost
                                        </div>
                                        <div class="hotspot-stat found">
                                            <i class="fas fa-hand-holding"></i>
                                            <?php echo $hotspot['found_items']; ?> Found
                                        </div>
                                        <div class="hotspot-stat resolved">
                                            <i class="fas fa-check"></i>
                                            <?php echo $hotspot['resolved_items']; ?> Resolved
                                        </div>
                                        <div class="hotspot-total">
                                            <?php echo $hotspot['total_items']; ?> Total
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-light); padding: 2rem;">
                            No hotspot data available yet.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="activity-feed">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i> Recent Activity
                    </h2>
                    
                    <?php if ($recent_activity): ?>
                        <?php foreach($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['Item_Type']; ?>">
                                    <i class="fas fa-<?php echo $activity['Item_Type'] === 'lost' ? 'exclamation-triangle' : 'hand-holding'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($activity['Item_Name']); ?>
                                    </div>
                                    <div class="activity-details">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($activity['Location']); ?><br>
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($activity['Reporter_Name']); ?><br>
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M d, Y', strtotime($activity['Date_Reported'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-light); padding: 2rem;">
                            No recent activity.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i> Category Distribution
                    </h2>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i> Top 5 Hotspots
                    </h2>
                    <div class="chart-container">
                        <canvas id="hotspotsChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($category_data, 'Category_Name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_data, 'count')); ?>,
                    backgroundColor: [
                        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
                        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
                    ],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#333'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#333',
                        borderWidth: 1
                    }
                }
            }
        });

        // Top Hotspots Chart
        const hotspotsCtx = document.getElementById('hotspotsChart').getContext('2d');
        const top5Hotspots = <?php echo json_encode(array_slice($hotspots_data, 0, 5)); ?>;
        
        new Chart(hotspotsCtx, {
            type: 'bar',
            data: {
                labels: top5Hotspots.map(h => h.Location),
                datasets: [{
                    label: 'Total Items',
                    data: top5Hotspots.map(h => h.total_items),
                    backgroundColor: [
                        'rgba(255, 107, 107, 0.9)',
                        'rgba(78, 205, 196, 0.9)',
                        'rgba(69, 183, 209, 0.9)',
                        'rgba(150, 206, 180, 0.9)',
                        'rgba(255, 234, 167, 0.9)'
                    ],
                    borderColor: [
                        '#FF6B6B',
                        '#4ECDC4',
                        '#45B7D1',
                        '#96CEB4',
                        '#FFEAA7'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: [
                        'rgba(255, 107, 107, 1)',
                        'rgba(78, 205, 196, 1)',
                        'rgba(69, 183, 209, 1)',
                        'rgba(150, 206, 180, 1)',
                        'rgba(255, 234, 167, 1)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)',
                            lineWidth: 1
                        },
                        ticks: {
                            color: '#333',
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#333',
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#333',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                }
            }
        });

        // Add smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html> 