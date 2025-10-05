<?php
// Script to recalculate all product statistics
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

echo "<h2>Recalculate Product Statistics</h2>";

try {
    $db = Database::getInstance();
    
    echo "<h3>Before Recalculation:</h3>";
    
    // Show current statistics
    $products = $db->fetchAll("
        SELECT product_id, product_name, sales_count, review_count, rating 
        FROM products 
        ORDER BY sales_count DESC, review_count DESC 
        LIMIT 10
    ");
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Product ID</th><th>Product Name</th><th>Sales Count</th><th>Review Count</th><th>Rating</th></tr>";
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>" . $product['product_id'] . "</td>";
        echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
        echo "<td>" . $product['sales_count'] . "</td>";
        echo "<td>" . $product['review_count'] . "</td>";
        echo "<td>" . $product['rating'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Recalculating Statistics...</h3>";
    
    // Recalculate all statistics
    $result = recalculateAllProductStatistics();
    
    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✓ Statistics recalculated successfully!</p>";
        
        echo "<h3>After Recalculation:</h3>";
        
        // Show updated statistics
        $updated_products = $db->fetchAll("
            SELECT product_id, product_name, sales_count, review_count, rating 
            FROM products 
            ORDER BY sales_count DESC, review_count DESC 
            LIMIT 10
        ");
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Product ID</th><th>Product Name</th><th>Sales Count</th><th>Review Count</th><th>Rating</th></tr>";
        foreach ($updated_products as $product) {
            echo "<tr>";
            echo "<td>" . $product['product_id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
            echo "<td>" . $product['sales_count'] . "</td>";
            echo "<td>" . $product['review_count'] . "</td>";
            echo "<td>" . $product['rating'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show summary
        $total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
        $total_sales = $db->fetchOne("SELECT SUM(sales_count) as total FROM products")['total'];
        $total_reviews = $db->fetchOne("SELECT SUM(review_count) as total FROM products")['total'];
        
        echo "<h3>Summary:</h3>";
        echo "<p><strong>Total Products:</strong> $total_products</p>";
        echo "<p><strong>Total Sales Count:</strong> $total_sales</p>";
        echo "<p><strong>Total Reviews:</strong> $total_reviews</p>";
        
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Failed to recalculate statistics</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    h3 { color: #333; margin-top: 30px; }
</style>







