<?php
require_once 'config.php';

echo "<h2>Categories Table Test</h2>";

try {
    // Check if categories table exists
    $result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($result->num_rows > 0) {
        echo "✅ Categories table exists<br>";
        
        // Check if categories table has data
        $count_result = $conn->query("SELECT COUNT(*) as count FROM categories");
        if ($count_result) {
            $row = $count_result->fetch_assoc();
            echo "✅ Categories table has " . $row['count'] . " records<br>";
            
            if ($row['count'] == 0) {
                echo "❌ Categories table is empty! This is the problem.<br>";
                echo "Let's insert the default categories...<br>";
                
                // Insert default categories
                $insert_sql = "INSERT INTO categories (Category_Name) VALUES 
                ('Electronics'), ('Books'), ('Accessories'), ('Documents'), ('Clothing'), ('Others')";
                
                if ($conn->query($insert_sql)) {
                    echo "✅ Default categories inserted successfully!<br>";
                    
                    // Verify insertion
                    $verify_result = $conn->query("SELECT COUNT(*) as count FROM categories");
                    $verify_row = $verify_result->fetch_assoc();
                    echo "✅ Now categories table has " . $verify_row['count'] . " records<br>";
                    
                    // Show all categories
                    $categories_result = $conn->query("SELECT * FROM categories ORDER BY Category_Name");
                    echo "<h3>Available Categories:</h3>";
                    echo "<ul>";
                    while ($cat = $categories_result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($cat['Category_Name']) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "❌ Failed to insert categories: " . $conn->error . "<br>";
                }
            } else {
                // Show existing categories
                $categories_result = $conn->query("SELECT * FROM categories ORDER BY Category_Name");
                echo "<h3>Available Categories:</h3>";
                echo "<ul>";
                while ($cat = $categories_result->fetch_assoc()) {
                    echo "<li>" . htmlspecialchars($cat['Category_Name']) . "</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "❌ Error counting categories: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Categories table does not exist!<br>";
        echo "This means the database setup didn't complete properly.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='index.php'>Go to Home</a> | <a href='report_lost.php'>Report Lost Item</a> | <a href='report_found.php'>Report Found Item</a>";
?>
