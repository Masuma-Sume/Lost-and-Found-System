<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: newlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$search_results = null;
$search_query = "";
$item_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Handle search
if (isset($_GET['q']) || isset($_GET['type'])) {
    $search_query = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : "";
    
    $sql = "SELECT i.*, u.Name as Reporter_Name,
                   (SELECT COUNT(*) FROM claims ic WHERE ic.Item_ID = i.Item_ID) as claim_count
            FROM items i
            LEFT JOIN user u ON i.User_ID = u.User_ID
            WHERE 1=1";
    
    $params = array();
    $types = "";
    
    if (!empty($search_query)) {
        $sql .= " AND (i.Item_Name LIKE ? OR i.Description LIKE ? OR i.Location LIKE ?)";
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    if ($item_type !== 'all') {
        $sql .= " AND i.Item_Type = ?";
        $params[] = $item_type;
        $types .= "s";
    }
    
    $sql .= " ORDER BY i.Date_Reported DESC";
    
    // Debug: check if SQL prepares successfully
    if (!$stmt = $conn->prepare($sql)) {
        die("Prepare failed: " . $conn->error . "<br>SQL: " . $sql);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $search_results = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Search Items</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #009688; /* Teal */
            --primary-dark: #00796B; /* Dark Teal */
            --secondary-color: #ffffff;
            --text-color: #222;
            --text-light: #666;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --danger-color: #dc3545;
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
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-menu {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .nav-item {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Merriweather', serif;
        }
        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .search-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .search-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            font-family: 'Merriweather', serif;
        }
        .search-type {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            font-family: 'Merriweather', serif;
            background-color: white;
        }
        .search-btn {
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .search-btn:hover {
            background-color: var(--primary-dark);
        }
        .results-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
        }
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .results-header h2 {
            color: var(--primary-color);
            margin: 0;
        }
        .results-count {
            color: #666;
            font-size: 0.9em;
        }
        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .item-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #eee;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #eee;
        }
        .item-image-placeholder {
            width: 100%;
            height: 200px;
            background-color: #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 14px;
            border: 1px solid #eee;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .item-name {
            font-weight: bold;
            color: var(--primary-color);
            margin: 0;
        }
        .item-type {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #e6f2ff;
            color: var(--primary-color);
        }
        .item-type.found {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .item-details {
            color: #666;
            font-size: 0.9em;
            margin: 10px 0;
        }
        .item-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: var(--primary-color);
            color: white;
            font-family: 'Merriweather', serif;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            background-color: var(--primary-dark);
        }
        .no-results {
            text-align: center;
            color: #666;
            padding: 30px;
            font-style: italic;
        }
        .claim-badge {
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">BRAC UNIVERSITY LOST & FOUND</div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="notifications.php" class="nav-item">
                <i class="fas fa-bell"></i> Notifications
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
        <div class="search-section">
            <form method="GET" action="" class="search-form">
                <input type="text" name="q" class="search-input" 
                       placeholder="Search by item name, description, or location..."
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <select name="type" class="search-type">
                    <option value="all" <?php echo $item_type === 'all' ? 'selected' : ''; ?>>All Items</option>
                    <option value="lost" <?php echo $item_type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                    <option value="found" <?php echo $item_type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                </select>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <?php if ($search_results !== null): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2>Search Results</h2>
                    <div class="results-count">
                        <?php echo $search_results->num_rows; ?> items found
                    </div>
                </div>

                <?php if ($search_results->num_rows > 0): ?>
                    <div class="item-grid">
                        <?php while($item = $search_results->fetch_assoc()): ?>
                            <div class="item-card">
                                <?php if (!empty($item['Image_URL'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['Image_URL']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" 
                                         class="item-image"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="item-image-placeholder" style="display: none;">
                                        <i class="fas fa-image"></i> No Image Available
                                    </div>
                                <?php else: ?>
                                    <div class="item-image-placeholder">
                                        <i class="fas fa-image"></i> No Image Available
                                    </div>
                                <?php endif; ?>
                                <div class="item-header">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                                    <span class="item-type <?php echo $item['Item_Type']; ?>">
                                        <?php echo ucfirst($item['Item_Type']); ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($item['Location']); ?></p>
                                    <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?></p>
                                    <p><strong>Reported by:</strong> <?php echo htmlspecialchars($item['Reporter_Name']); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($item['Description']); ?></p>
                                    <?php if ($item['claim_count'] > 0): ?>
                                        <p><strong>Claims:</strong> <span class="claim-badge"><?php echo $item['claim_count']; ?></span></p>
                                    <?php endif; ?>
                                </div>
                                <div class="item-actions">
                                    <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($item['Item_Type'] === 'found' && $item['User_ID'] !== $user_id): ?>
                                        <a href="claim_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-hand-holding"></i> Claim Item
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-results">No items found matching your search criteria.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 