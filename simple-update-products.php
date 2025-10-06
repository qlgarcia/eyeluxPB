<?php
// Simple product update script
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔄 Updating Products</h2>";
    
    // Exchange rate: 1 USD = 56 PHP
    $usd_to_php = 56;
    
    // First, let's see what products exist
    $existing_products = $db->fetchAll("SELECT * FROM products ORDER BY product_id");
    
    echo "<h3>📋 Current Products:</h3>";
    foreach ($existing_products as $product) {
        echo "<p>ID: {$product['product_id']} - {$product['product_name']} - $" . number_format($product['price'], 2) . "</p>";
    }
    
    // Update each existing product
    echo "<h3>📝 Updating Products:</h3>";
    
    $updates = [
        [
            'id' => 1,
            'name' => 'Aviator Classic Frame',
            'description' => 'Timeless aviator frame with UV protection lenses. Perfect for outdoor activities and driving.',
            'price_usd' => 89.99,
            'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 2,
            'name' => 'Wayfarer Original Frame',
            'description' => 'Iconic wayfarer frame design. Classic style that never goes out of fashion.',
            'price_usd' => 79.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 3,
            'name' => 'Round Metal Frame',
            'description' => 'Vintage-inspired round metal frame. Perfect for prescription lenses or blue light blocking.',
            'price_usd' => 129.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 4,
            'name' => 'Cat Eye Frame',
            'description' => 'Retro cat eye frame design. Elegant and feminine style for women.',
            'price_usd' => 149.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 5,
            'name' => 'Reading Glasses Frame',
            'description' => 'Lightweight reading glasses frame. Comfortable for extended reading sessions.',
            'price_usd' => 29.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 6,
            'name' => 'Swim Goggles Frame',
            'description' => 'Anti-fog swim goggles frame. Perfect for swimming and water sports.',
            'price_usd' => 24.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ],
        [
            'id' => 7,
            'name' => 'Kids Fun Frames',
            'description' => 'Colorful and durable frames designed specifically for children. Safe and comfortable.',
            'price_usd' => 39.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
        ]
    ];
    
    foreach ($updates as $update) {
        $price_php = round($update['price_usd'] * $usd_to_php, 2);
        
        $result = $db->execute(
            "UPDATE products SET 
                product_name = ?, 
                description = ?, 
                price = ?, 
                image_url = ?
             WHERE product_id = ?",
            [
                $update['name'],
                $update['description'],
                $price_php,
                $update['image_url'],
                $update['id']
            ]
        );
        
        if ($result) {
            echo "<p>✅ Updated: {$update['name']} - ₱" . number_format($price_php, 2) . "</p>";
        } else {
            echo "<p>❌ Failed to update: {$update['name']}</p>";
        }
    }
    
    // Add some new products
    echo "<h3>➕ Adding New Products:</h3>";
    
    $new_products = [
        [
            'category_id' => 1,
            'name' => 'Pilot Sunglasses Frame',
            'description' => 'Classic pilot-style frame with gradient lenses. Perfect for aviation enthusiasts.',
            'price_usd' => 95.99,
            'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Oakley',
            'model' => 'Pilot',
            'color' => 'Silver',
            'gender' => 'men',
            'stock_quantity' => 25,
            'sku' => 'OAK-PILOT-001'
        ],
        [
            'category_id' => 2,
            'name' => 'Square Metal Frame',
            'description' => 'Modern square metal frame. Clean lines and contemporary design.',
            'price_usd' => 135.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Tom Ford',
            'model' => 'Square Metal',
            'color' => 'Black',
            'gender' => 'men',
            'stock_quantity' => 20,
            'sku' => 'TF-SQ-001'
        ],
        [
            'category_id' => 4,
            'name' => 'Cycling Glasses Frame',
            'description' => 'Aerodynamic cycling glasses frame. Designed for high-speed cycling.',
            'price_usd' => 85.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Oakley',
            'model' => 'Cycling',
            'color' => 'Red',
            'gender' => 'unisex',
            'stock_quantity' => 35,
            'sku' => 'OAK-CYC-001'
        ]
    ];
    
    foreach ($new_products as $product) {
        $price_php = round($product['price_usd'] * $usd_to_php, 2);
        
        $result = $db->insert(
            "INSERT INTO products (category_id, product_name, description, price, stock_quantity, brand, model, color, gender, image_url, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $product['category_id'],
                $product['name'],
                $product['description'],
                $price_php,
                $product['stock_quantity'],
                $product['brand'],
                $product['model'],
                $product['color'],
                $product['gender'],
                $product['image_url'],
                $product['sku']
            ]
        );
        
        if ($result) {
            echo "<p>✅ Added: {$product['name']} - ₱" . number_format($price_php, 2) . "</p>";
        } else {
            echo "<p>❌ Failed to add: {$product['name']}</p>";
        }
    }
    
    // Update category descriptions
    echo "<h3>📋 Updating Categories:</h3>";
    
    $category_updates = [
        [1, 'Sunglasses', 'Protective eyewear frames for outdoor activities. UV protection lenses available separately.'],
        [2, 'Eyeglasses', 'Prescription and non-prescription frame options. Perfect for everyday wear and style.'],
        [3, 'Reading Glasses', 'Magnifying frame options for reading and close work. Various magnification strengths available.'],
        [4, 'Sports Glasses', 'Performance eyewear frames for athletes and active lifestyles. Impact-resistant options available.'],
        [5, 'Kids Eyewear', 'Fun and protective frame options designed specifically for children. Safety-tested materials.']
    ];
    
    foreach ($category_updates as $category) {
        $result = $db->execute(
            "UPDATE categories SET description = ? WHERE category_id = ?",
            [$category[2], $category[0]]
        );
        
        if ($result) {
            echo "<p>✅ Updated category: {$category[1]}</p>";
        } else {
            echo "<p>❌ Failed to update category: {$category[1]}</p>";
        }
    }
    
    echo "<h3>✅ Update Complete!</h3>";
    
    // Show final count
    $total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
    echo "<p><strong>Total products:</strong> $total_products</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
h2, h3 { color: #333; }
p { margin: 10px 0; }
</style>




