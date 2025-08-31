<?php
require_once 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$search_results = null;
$search_query = "";
$item_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$category_id = isset($_GET['category']) ? $_GET['category'] : 'all';
$suggested_category = null;

// Get all categories for the dropdown
try {
    $categories_sql = "SELECT Category_ID, Category_Name FROM categories ORDER BY Category_Name ASC";
    $stmt = executeQuery($conn, $categories_sql, [], '');
    $categories = fetchAll($stmt);
} catch (Exception $e) {
    $categories = [];
}

// AI Smart Category Suggestion Function
function suggestCategory($search_query) {
    $query_lower = strtolower($search_query);
    
    // Define keyword mappings for categories
    $category_keywords = [
        'Electronics' => ['phone', 'laptop', 'computer', 'charger', 'headphone', 'earphone', 'tablet', 'ipad', 'macbook', 'keyboard', 'mouse', 'cable', 'wire', 'battery', 'power', 'electronic', 'device', 'gadget'],
        'Books' => ['book', 'textbook', 'notebook', 'diary', 'journal', 'magazine', 'novel', 'dictionary', 'manual', 'guide', 'paper', 'document', 'folder', 'binder'],
        'Accessories' => ['bag', 'backpack', 'wallet', 'purse', 'watch', 'bracelet', 'necklace', 'ring', 'earring', 'sunglasses', 'glasses', 'belt', 'scarf', 'hat', 'cap', 'umbrella', 'key', 'keychain'],
        'Documents' => ['id', 'card', 'license', 'passport', 'certificate', 'degree', 'transcript', 'receipt', 'ticket', 'voucher', 'coupon', 'letter', 'envelope', 'stamp'],
        'Clothing' => ['shirt', 'pant', 'dress', 'jacket', 'coat', 'sweater', 'hoodie', 't-shirt', 'jeans', 'skirt', 'sock', 'shoe', 'boot', 'sneaker', 'sandal', 'tie', 'scarf', 'glove', 'mitten'],
        'Others' => ['water', 'bottle', 'cup', 'mug', 'lunch', 'food', 'snack', 'pen', 'pencil', 'marker', 'highlighter', 'eraser', 'ruler', 'calculator', 'compass', 'protractor']
    ];
    
    // Count matches for each category
    $category_scores = [];
    foreach ($category_keywords as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                $score++;
            }
        }
        $category_scores[$category] = $score;
    }
    
    // Return the category with the highest score
    $max_score = max($category_scores);
    if ($max_score > 0) {
        $suggested_category = array_search($max_score, $category_scores);
        return $suggested_category;
    }
    
    return null;
}

// Handle search
if (isset($_GET['q']) || isset($_GET['type']) || isset($_GET['category'])) {
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : "";
    
    // Get AI category suggestion if search query is provided
    if (!empty($search_query)) {
        $suggested_category = suggestCategory($search_query);
    }
    
    $sql = "SELECT i.*, u.Name as Reporter_Name, c.Category_Name,
                   (SELECT COUNT(*) FROM claims ic WHERE ic.Item_ID = i.Item_ID) as claim_count
            FROM items i
            LEFT JOIN user u ON i.User_ID = u.User_ID
            LEFT JOIN categories c ON i.Category_ID = c.Category_ID
            WHERE 1=1";
    
    $params = array();
    $types = "";
    
    if (!empty($search_query)) {
        $sql .= " AND i.Item_Name LIKE ?";
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $types .= "s";
    }
    
    if ($item_type !== 'all') {
        $sql .= " AND i.Item_Type = ?";
        $params[] = $item_type;
        $types .= "s";
    }
    
    if ($category_id !== 'all') {
        $sql .= " AND i.Category_ID = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY i.Date_Reported DESC";
    
    try {
        $stmt = executeQuery($conn, $sql, $params, $types);
        $search_results = fetchAll($stmt);
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $search_results = [];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BRAC UNIVERSITY - Advanced Search</title>
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
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --gradient-primary: linear-gradient(135deg, #009688 0%, #00796B 100%);
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
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .search-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .search-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .search-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .search-subtitle {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .search-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .search-label {
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .search-input {
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            color: #222;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
            background: #fff;
        }

        .search-select {
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .search-btn {
            padding: 1rem 2rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 150, 136, 0.3);
        }

        .ai-suggestion {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.5s ease;
        }

        .ai-suggestion.hidden {
            display: none;
        }

        .ai-icon {
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }

        .ai-text {
            flex: 1;
        }

        .ai-title {
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .ai-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .apply-suggestion {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .apply-suggestion:hover {
            background: rgba(255,255,255,0.3);
        }

        .results-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .results-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .results-count {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
        }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .item-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #e0e0e0;
        }

        .item-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1rem;
            border: 1px solid #e0e0e0;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .item-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }

        .item-type {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .item-type.lost {
            background: #ffebee;
            color: var(--danger-color);
        }

        .item-type.found {
            background: #e8f5e9;
            color: var(--success-color);
        }

        .item-category {
            background: #e3f2fd;
            color: var(--info-color);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .item-details {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .item-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .item-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .no-results {
            text-align: center;
            color: var(--text-light);
            padding: 3rem;
            font-size: 1.1rem;
        }

        .claim-badge {
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .item-grid {
                grid-template-columns: 1fr;
            }
            
            .search-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-search"></i> BRAC UNIVERSITY LOST & FOUND
        </div>
        <div class="nav-menu">
            <a href="home.php" class="nav-item">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="search.php" class="nav-item">
                <i class="fas fa-search"></i> Basic Search
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
            <div class="search-header">
                <h1 class="search-title">
                    <i class="fas fa-robot"></i> Advanced Search
                </h1>
                <p class="search-subtitle">Find items with AI-powered category suggestions and advanced filters</p>
            </div>

            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <label class="search-label">Search Query</label>
                    <input type="text" name="q" class="search-input" 
                           placeholder="Enter item name, description, or location..."
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           id="searchInput">
                </div>
                
                <div class="search-group">
                    <label class="search-label">Item Type</label>
                    <select name="type" class="search-select">
                        <option value="all" <?php echo $item_type === 'all' ? 'selected' : ''; ?>>All Items</option>
                        <option value="lost" <?php echo $item_type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $item_type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                
                <div class="search-group">
                    <label class="search-label">Category</label>
                    <select name="category" class="search-select" id="categorySelect">
                        <option value="all" <?php echo $category_id === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['Category_ID']; ?>" 
                                    <?php echo $category_id == $category['Category_ID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['Category_Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>

            <?php if ($suggested_category): ?>
                <div class="ai-suggestion" id="aiSuggestion">
                    <div class="ai-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-text">
                        <div class="ai-title">🤖 AI Category Suggestion</div>
                        <div class="ai-description">
                            Based on your search, we suggest filtering by "<strong><?php echo htmlspecialchars($suggested_category); ?></strong>" category for better results.
                        </div>
                    </div>
                    <button type="button" class="apply-suggestion" onclick="applyCategorySuggestion('<?php echo htmlspecialchars($suggested_category); ?>')">
                        Apply Suggestion
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($search_results !== null): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2 class="results-title">Search Results</h2>
                    <div class="results-count">
                        <i class="fas fa-list"></i> <?php echo count($search_results); ?> items found
                    </div>
                </div>

                <?php if (count($search_results) > 0): ?>
                    <div class="item-grid">
                        <?php foreach($search_results as $item): ?>
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
                                
                                <?php if ($item['Category_Name']): ?>
                                    <div class="item-category">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['Category_Name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <div class="item-detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($item['Location']); ?></span>
                                    </div>
                                    <div class="item-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($item['Date_Reported'])); ?></span>
                                    </div>
                                    <div class="item-detail">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($item['Reporter_Name']); ?></span>
                                    </div>
                                    <?php if ($item['claim_count'] > 0): ?>
                                        <div class="item-detail">
                                            <i class="fas fa-hand-holding"></i>
                                            <span><?php echo $item['claim_count']; ?> claim(s) <span class="claim-badge"><?php echo $item['claim_count']; ?></span></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['Description']): ?>
                                        <div class="item-detail">
                                            <i class="fas fa-info-circle"></i>
                                            <span><?php echo htmlspecialchars(substr($item['Description'], 0, 100)) . (strlen($item['Description']) > 100 ? '...' : ''); ?></span>
                                        </div>
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
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                        <p>No items found matching your search criteria.</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Try adjusting your search terms or filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Function to apply AI category suggestion
        function applyCategorySuggestion(categoryName) {
            // Find the category ID based on the category name
            const categorySelect = document.getElementById('categorySelect');
            const options = categorySelect.options;
            
            for (let i = 0; i < options.length; i++) {
                if (options[i].text === categoryName) {
                    categorySelect.value = options[i].value;
                    break;
                }
            }
            
            // Hide the suggestion
            document.getElementById('aiSuggestion').classList.add('hidden');
            
            // Submit the form
            categorySelect.form.submit();
        }

        // Real-time AI suggestion (optional enhancement)
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length > 2) {
                // You could add AJAX call here to get real-time suggestions
                // For now, we'll just show the suggestion when the form is submitted
            }
        });

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate search form on load
            const searchForm = document.querySelector('.search-form');
            searchForm.style.opacity = '0';
            searchForm.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                searchForm.style.transition = 'all 0.6s ease';
                searchForm.style.opacity = '1';
                searchForm.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html> 