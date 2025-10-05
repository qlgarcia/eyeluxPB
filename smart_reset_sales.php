<?php
// Smart reset - only clear fake test data, preserve real sales
require_once 'includes/database.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Smart Reset Sales Data</title></head><body>";
echo "<h1>Smart Reset Sales Data</h1>";

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // First, let's see what we have before reset
    echo "<h2>Before Reset:</h2>";
    $before_products = $db->fetchAll("SELECT product_name, sales_count, review_count FROM products WHERE is_active = 1 AND sales_count > 0 ORDER BY sales_count DESC");
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Product Name</th><th>Sales Count</th><th>Review Count</th><th>Status</th></tr>";
    
    foreach ($before_products as $product) {
        $sales = $product['sales_count'] ?? 0;
        $reviews = $product['review_count'] ?? 0;
        
        // If it has reviews, it's likely a real purchase
        $status = $reviews > 0 ? "REAL PURCHASE" : "FAKE TEST DATA";
        $style = $reviews > 0 ? "color: green; font-weight: bold;" : "color: red;";
        
        echo "<tr>";
        echo "<td style='$style'>" . htmlspecialchars($product['product_name']) . "</td>";
        echo "<td style='$style'>$sales</td>";
        echo "<td style='$style'>$reviews</td>";
        echo "<td style='$style'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Reset only products with 0 reviews (fake test data)
    echo "<h2>Resetting Fake Test Data:</h2>";
    
    $result = $db->execute("UPDATE products SET sales_count = 0 WHERE is_active = 1 AND review_count = 0 AND sales_count > 0");
    
    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✓ Reset fake test data (products with 0 reviews)</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to reset fake test data</p>";
    }
    
    // Show what we have after reset
    echo "<h2>After Reset:</h2>";
    $after_products = $db->fetchAll("SELECT product_name, sales_count, review_count FROM products WHERE is_active = 1 AND sales_count > 0 ORDER BY sales_count DESC");
    
    if (count($after_products) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Product Name</th><th>Sales Count</th><th>Review Count</th><th>Status</th></tr>";
        
        foreach ($after_products as $product) {
            $sales = $product['sales_count'] ?? 0;
            $reviews = $product['review_count'] ?? 0;
            $status = "REAL PURCHASE";
            $style = "color: green; font-weight: bold;";
            
            echo "<tr>";
            echo "<td style='$style'>" . htmlspecialchars($product['product_name']) . "</td>";
            echo "<td style='$style'>$sales</td>";
            echo "<td style='$style'>$reviews</td>";
            echo "<td style='$style'>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p style='color: green; font-weight: bold;'>✓ These are your real purchases that will show in Best Sellers!</p>";
    } else {
        echo "<p style='color: blue;'>No products with real sales found</p>";
    }
    
    // Check if Aviator Classic still has its sales
    $aviator = $db->fetchOne("SELECT product_name, sales_count, review_count FROM products WHERE product_name LIKE '%Aviator Classic%' AND is_active = 1");
    
    if ($aviator) {
        echo "<h2>Aviator Classic Status:</h2>";
        echo "<p>Product: " . htmlspecialchars($aviator['product_name']) . "</p>";
        echo "<p>Sales Count: " . ($aviator['sales_count'] ?? 0) . "</p>";
        echo "<p>Review Count: " . ($aviator['review_count'] ?? 0) . "</p>";
        
        if (($aviator['sales_count'] ?? 0) > 0) {
            echo "<p style='color: green; font-weight: bold;'>✓ Aviator Classic will appear in Best Sellers!</p>";
        } else {
            echo "<p style='color: red;'>✗ Aviator Classic sales were reset - this shouldn't happen</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>











