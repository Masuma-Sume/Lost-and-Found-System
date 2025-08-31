<?php
require_once 'config.php';
session_start();

// Redirect to login if not admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Admin data
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// Fetch comprehensive dashboard stats
try {
    // Basic counts
    $total_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
    $total_lost = $conn->query("SELECT COUNT(*) as count FROM items WHERE Item_Type = 'lost'")->fetch_assoc()['count'];
    $total_found = $conn->query("SELECT COUNT(*) as count FROM items WHERE Item_Type = 'found'")->fetch_assoc()['count'];
    $open_cases = $conn->query("SELECT COUNT(*) as count FROM items WHERE Status = 'open'")->fetch_assoc()['count'];
    $closed_cases = $conn->query("SELECT COUNT(*) as count FROM items WHERE Status = 'closed'")->fetch_assoc()['count'];
    
    // Approval status counts
    $pending_items_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE COALESCE(Approval_Status,'pending') = 'pending'")->fetch_assoc()['c'];
    $approved_items_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE COALESCE(Approval_Status,'pending') = 'approved'")->fetch_assoc()['c'];
    $rejected_items_count = $conn->query("SELECT COUNT(*) as c FROM items WHERE COALESCE(Approval_Status,'pending') = 'rejected'")->fetch_assoc()['c'];
    
    // Claims statistics
    $pending_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE Claim_Status = 'pending'")->fetch_assoc()['c'];
    $approved_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE Claim_Status = 'approved'")->fetch_assoc()['c'];
    $rejected_claims = $conn->query("SELECT COUNT(*) as c FROM claims WHERE Claim_Status = 'rejected'")->fetch_assoc()['c'];
    
    // Returned metrics
    $returned_total = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE Claim_Status = 'approved'")->fetch_assoc()['c'];
    $returned_today = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE Claim_Status = 'approved' AND DATE(Updated_At) = CURDATE()")->fetch_assoc()['c'];
    $returned_this_month = $conn->query("SELECT COUNT(*) AS c FROM claims WHERE Claim_Status = 'approved' AND YEAR(Updated_At) = YEAR(CURDATE()) AND MONTH(Updated_At) = MONTH(CURDATE())")->fetch_assoc()['c'];
    
    // Recent activity
    $recent_items = $conn->query("SELECT i.*, u.Name as Reporter_Name, c.Category_Name 
                                  FROM items i 
                                  LEFT JOIN user u ON i.User_ID = u.User_ID 
                                  LEFT JOIN categories c ON i.Category_ID = c.Category_ID 
                                  ORDER BY i.Date_Reported DESC LIMIT 5");
    
    $recent_claims = $conn->query("SELECT c.*, i.Item_Name, u.Name as Claimant_Name 
                                   FROM claims c 
                                   LEFT JOIN items i ON c.Item_ID = i.Item_ID 
                                   LEFT JOIN user u ON c.Claimant_ID = u.User_ID 
                                   ORDER BY c.Created_At DESC LIMIT 5");
    
    // Monthly returns chart data
    $month_rows = $conn->query("SELECT DATE(Updated_At) AS d, COUNT(*) AS c
                                FROM claims
                                WHERE Claim_Status = 'approved'
                                  AND YEAR(Updated_At) = YEAR(CURDATE())
                                  AND MONTH(Updated_At) = MONTH(CURDATE())
                                GROUP BY DATE(Updated_At)
                                ORDER BY DATE(Updated_At)");
    $returns_chart_labels = [];
    $returns_chart_values = [];
    if ($month_rows) {
        while ($r = $month_rows->fetch_assoc()) {
            $returns_chart_labels[] = $r['d'];
            $returns_chart_values[] = (int)$r['c'];
        }
    }
    
    // Top users by posts
    $top_posters = $conn->query("SELECT u.User_ID, u.Name, COUNT(i.Item_ID) AS posts
                                 FROM user u
                                 LEFT JOIN items i ON i.User_ID = u.User_ID
                                 WHERE u.Role = 'user'
                                 GROUP BY u.User_ID, u.Name
                                 ORDER BY posts DESC
                                 LIMIT 10");
    
    // Category distribution
    $category_stats = $conn->query("SELECT c.Category_Name, COUNT(i.Item_ID) as count
                                    FROM categories c
                                    LEFT JOIN items i ON c.Category_ID = i.Category_ID
                                    GROUP BY c.Category_ID, c.Category_Name
                                    ORDER BY count DESC");
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Set default values
    $total_users = $total_lost = $total_found = $open_cases = $closed_cases = 0;
    $pending_items_count = $approved_items_count = $rejected_items_count = 0;
    $pending_claims = $approved_claims = $rejected_claims = 0;
    $returned_total = $returned_today = $returned_this_month = 0;
    $returns_chart_labels = $returns_chart_values = [];
    $recent_items = $recent_claims = $top_posters = $category_stats = null;
}
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
            --gradient-primary: linear-gradient(135deg, #009688 0%, #00796B 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Merriweather', serif;
        }
        
        body {
            background: #f5f5f5;
            color: var(--text-color);
            line-height: 1.6;
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
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .welcome-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
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
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.users { color: var(--primary-color); }
        .stat-icon.lost { color: var(--danger-color); }
        .stat-icon.found { color: var(--success-color); }
        .stat-icon.pending { color: var(--warning-color); }
        .stat-icon.approved { color: var(--success-color); }
        .stat-icon.rejected { color: var(--danger-color); }
        .stat-icon.returned { color: var(--info-color); }
        
        .stat-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: 300px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .chart-container {
            flex: 1;
            position: relative;
            min-height: 200px;
        }
        
        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .activity-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .activity-icon.lost { background: var(--danger-color); }
        .activity-icon.found { background: var(--success-color); }
        .activity-icon.claim { background: var(--info-color); }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title-text {
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }
        
        .activity-meta {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            background: var(--gradient-primary);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 2rem;
        }
        
        .badge {
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                height: 250px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .container {
                padding: 0 1rem;
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
            <a href="admin_home.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_review_items.php" class="nav-item">
                <i class="fas fa-clipboard-check"></i> Review Items
                <?php if ($pending_items_count > 0): ?>
                    <span class="badge"><?php echo $pending_items_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_review_claims.php" class="nav-item">
                <i class="fas fa-hand-paper"></i> Review Claims
                <?php if ($pending_claims > 0): ?>
                    <span class="badge"><?php echo $pending_claims; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with the Lost & Found system today</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-title">Total Users</div>
                <div class="stat-value"><?php echo number_format($total_users); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon lost">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-title">Lost Items</div>
                <div class="stat-value"><?php echo number_format($total_lost); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon found">
                    <i class="fas fa-box-open"></i>
                </div>
                <div class="stat-title">Found Items</div>
                <div class="stat-value"><?php echo number_format($total_found); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-title">Pending Approval</div>
                <div class="stat-value"><?php echo number_format($pending_items_count); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="stat-title">Approved Items</div>
                <div class="stat-value"><?php echo number_format($approved_items_count); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon returned">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="stat-title">Items Returned</div>
                <div class="stat-value"><?php echo number_format($returned_total); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-title">Pending Claims</div>
                <div class="stat-value"><?php echo number_format($pending_claims); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon returned">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-title">Returned Today</div>
                <div class="stat-value"><?php echo number_format($returned_today); ?></div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i> Returns This Month
                </h3>
                <div class="chart-container">
                    <canvas id="returnsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i> Category Distribution
                </h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="recent-activity">
            <h3 class="activity-title">
                <i class="fas fa-history"></i> Recent Activity
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Recent Items</h4>
                    <?php if ($recent_items && $recent_items->num_rows > 0): ?>
                        <?php while($item = $recent_items->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $item['Item_Type']; ?>">
                                    <i class="fas fa-<?php echo $item['Item_Type'] === 'lost' ? 'search' : 'box-open'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title-text"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($item['Reporter_Name'] ?? 'Anonymous'); ?> • 
                                        <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-light); text-align: center; padding: 1rem;">No recent items</p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Recent Claims</h4>
                    <?php if ($recent_claims && $recent_claims->num_rows > 0): ?>
                        <?php while($claim = $recent_claims->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon claim">
                                    <i class="fas fa-hand-holding"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title-text"><?php echo htmlspecialchars($claim['Item_Name']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($claim['Claimant_Name']); ?> • 
                                        <?php echo date('M d, Y', strtotime($claim['Created_At'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-light); text-align: center; padding: 1rem;">No recent claims</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="admin_review_items.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div>Review Items</div>
                <?php if ($pending_items_count > 0): ?>
                    <small><?php echo $pending_items_count; ?> pending</small>
                <?php endif; ?>
            </a>
            
            <a href="admin_review_claims.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-hand-paper"></i>
                </div>
                <div>Review Claims</div>
                <?php if ($pending_claims > 0): ?>
                    <small><?php echo $pending_claims; ?> pending</small>
                <?php endif; ?>
            </a>
            
            <a href="reports.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>View Reports</div>
            </a>
            
            <a href="admin_users.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div>Manage Users</div>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Returns Chart
        const returnsCtx = document.getElementById('returnsChart');
        if (returnsCtx) {
            new Chart(returnsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($returns_chart_labels); ?>,
                    datasets: [{
                        label: 'Returns',
                        data: <?php echo json_encode($returns_chart_values); ?>,
                        borderColor: '#009688',
                        backgroundColor: 'rgba(0,150,136,0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                                 options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     scales: {
                         x: { 
                             title: { display: false },
                             grid: { display: false },
                             ticks: { maxTicksLimit: 7 }
                         },
                         y: { 
                             beginAtZero: true, 
                             title: { display: false },
                             precision: 0,
                             ticks: { maxTicksLimit: 5 }
                         }
                     },
                     plugins: {
                         legend: { display: false }
                     }
                 }
            });
        }

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            const categoryData = <?php 
                $cat_labels = [];
                $cat_values = [];
                if ($category_stats) {
                    while ($cat = $category_stats->fetch_assoc()) {
                        $cat_labels[] = $cat['Category_Name'];
                        $cat_values[] = (int)$cat['count'];
                    }
                }
                echo json_encode(['labels' => $cat_labels, 'values' => $cat_values]);
            ?>;
            
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryData.labels,
                    datasets: [{
                        data: categoryData.values,
                        backgroundColor: [
                            '#009688', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                                 options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     plugins: {
                         legend: {
                             position: 'bottom',
                             labels: {
                                 padding: 10,
                                 usePointStyle: true,
                                 font: { size: 11 }
                             }
                         }
                     }
                 }
            });
        }
    </script>
</body>
</html> 