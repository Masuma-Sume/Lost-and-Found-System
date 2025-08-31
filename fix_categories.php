<?php
require_once 'config.php';

echo "<h2>Fixing Categories Table</h2>";

try {
    // Check if categories table exists
    $result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($result->num_rows > 0) {
        echo "✅ Categories table exists<br>";
        
        // Check current count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM categories");
        $row = $count_result->fetch_assoc();
        echo "Current categories count: " . $row['count'] . "<br>";
        
        if ($row['count'] == 0) {
            echo "❌ Categories table is empty! Inserting default categories...<br>";
            
            // Insert default categories
            $categories = [
                'Electronics',
                'Books', 
                'Accessories',
                'Documents',
                'Clothing',
                'Others'
            ];
            
            $inserted = 0;
            foreach ($categories as $category) {
                $sql = "INSERT INTO categories (Category_Name) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $category);
                
                if ($stmt->execute()) {
                    $inserted++;
                    echo "✅ Inserted: " . htmlspecialchars($category) . "<br>";
                } else {
                    echo "❌ Failed to insert: " . htmlspecialchars($category) . " - " . $stmt->error . "<br>";
                }
            }
            
            echo "<br>✅ Successfully inserted $inserted categories!<br>";
            
        } else {
            echo "✅ Categories table already has data<br>";
        }
        
        // Show all categories
        echo "<h3>All Available Categories:</h3>";
        $categories_result = $conn->query("SELECT * FROM categories ORDER BY Category_Name");
        echo "<ul>";
        while ($cat = $categories_result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($cat['Category_Name']) . "</li>";
        }
        echo "</ul>";
        
    } else {
        echo "❌ Categories table does not exist!<br>";
        echo "Please run the database setup again.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='index.php'>Go to Home</a> | <a href='report_lost.php'>Report Lost Item</a> | <a href='report_found.php'>Report Found Item</a>";
?>
