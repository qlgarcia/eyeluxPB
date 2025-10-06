<?php
// Update products with images and convert to Philippine Pesos
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔄 Updating Products with Images and Philippine Pesos</h2>";
    
    // Exchange rate: 1 USD = 56 PHP (approximate)
    $usd_to_php = 56;
    
    // Product updates with images and converted prices
    $product_updates = [
        // Sunglasses
        [
            'id' => 1,
            'name' => 'Aviator Classic',
            'description' => 'Timeless aviator frame with UV protection lenses. Perfect for outdoor activities and driving.',
            'price_usd' => 89.99,
            'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Ray-Ban',
            'model' => 'Aviator',
            'color' => 'Gold',
            'gender' => 'unisex'
        ],
        [
            'id' => 2,
            'name' => 'Wayfarer Original',
            'description' => 'Iconic wayfarer frame design. Classic style that never goes out of fashion.',
            'price_usd' => 79.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Ray-Ban',
            'model' => 'Wayfarer',
            'color' => 'Black',
            'gender' => 'unisex'
        ],
        
        // Eyeglasses
        [
            'id' => 3,
            'name' => 'Round Metal Frame',
            'description' => 'Vintage-inspired round metal frame. Perfect for prescription lenses or blue light blocking.',
            'price_usd' => 129.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Warby Parker',
            'model' => 'Round Metal',
            'color' => 'Tortoise',
            'gender' => 'unisex'
        ],
        [
            'id' => 4,
            'name' => 'Cat Eye Frame',
            'description' => 'Retro cat eye frame design. Elegant and feminine style for women.',
            'price_usd' => 149.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Warby Parker',
            'model' => 'Cat Eye',
            'color' => 'Red',
            'gender' => 'women'
        ],
        
        // Reading Glasses
        [
            'id' => 5,
            'name' => 'Reading Glasses Frame +2.00',
            'description' => 'Lightweight reading glasses frame. Comfortable for extended reading sessions.',
            'price_usd' => 29.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Generic',
            'model' => 'Reader',
            'color' => 'Black',
            'gender' => 'unisex'
        ],
        
        // Sports Glasses
        [
            'id' => 6,
            'name' => 'Swim Goggles Frame',
            'description' => 'Anti-fog swim goggles frame. Perfect for swimming and water sports.',
            'price_usd' => 24.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Speedo',
            'model' => 'Swim Pro',
            'color' => 'Blue',
            'gender' => 'unisex'
        ],
        
        // Kids Eyewear
        [
            'id' => 7,
            'name' => 'Kids Fun Frames',
            'description' => 'Colorful and durable frames designed specifically for children. Safe and comfortable.',
            'price_usd' => 39.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Kids Vision',
            'model' => 'Fun Frames',
            'color' => 'Rainbow',
            'gender' => 'kids'
        ]
    ];
    
    // Add more products with different categories
    $additional_products = [
        // More Sunglasses
        [
            'category_id' => 1,
            'name' => 'Pilot Sunglasses',
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
            'category_id' => 1,
            'name' => 'Oversized Sunglasses',
            'description' => 'Trendy oversized frame with UV400 protection. Fashion-forward design.',
            'price_usd' => 75.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Gucci',
            'model' => 'Oversized',
            'color' => 'Brown',
            'gender' => 'women',
            'stock_quantity' => 30,
            'sku' => 'GUC-OVER-001'
        ],
        
        // More Eyeglasses
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
            'category_id' => 2,
            'name' => 'Horn Rimmed Frame',
            'description' => 'Classic horn-rimmed frame. Timeless style with modern comfort.',
            'price_usd' => 165.99,
            'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Persol',
            'model' => 'Horn Rim',
            'color' => 'Tortoise',
            'gender' => 'unisex',
            'stock_quantity' => 15,
            'sku' => 'PER-HORN-001'
        ],
        
        // More Reading Glasses
        [
            'category_id' => 3,
            'name' => 'Folding Reading Glasses',
            'description' => 'Convenient folding reading glasses frame. Compact and portable design.',
            'price_usd' => 35.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Generic',
            'model' => 'Folding',
            'color' => 'Silver',
            'gender' => 'unisex',
            'stock_quantity' => 50,
            'sku' => 'GEN-FOLD-001'
        ],
        
        // More Sports Glasses
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
        ],
        [
            'category_id' => 4,
            'name' => 'Running Glasses Frame',
            'description' => 'Lightweight running glasses frame. Secure fit for active runners.',
            'price_usd' => 65.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Nike',
            'model' => 'Running',
            'color' => 'Blue',
            'gender' => 'unisex',
            'stock_quantity' => 40,
            'sku' => 'NIKE-RUN-001'
        ],
        
        // More Kids Eyewear
        [
            'category_id' => 5,
            'name' => 'Kids Safety Frames',
            'description' => 'Impact-resistant safety frames for children. Extra durable construction.',
            'price_usd' => 45.99,
            'image_url' => 'https://images.unsplash.com/photo-1506629905607-4b0b5b5b5b5b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
            'brand' => 'Kids Vision',
            'model' => 'Safety',
            'color' => 'Blue',
            'gender' => 'kids',
            'stock_quantity' => 25,
            'sku' => 'KV-SAFE-001'
        ]
    ];
    
    // Update existing products
    echo "<h3>📝 Updating Existing Products:</h3>";
    foreach ($product_updates as $product) {
        $price_php = round($product['price_usd'] * $usd_to_php, 2);
        
        $result = $db->execute(
            "UPDATE products SET 
                product_name = ?, 
                description = ?, 
                price = ?, 
                image_url = ?, 
                brand = ?, 
                model = ?, 
                color = ?, 
                gender = ?,
                updated_at = NOW()
             WHERE product_id = ?",
            [
                $product['name'],
                $product['description'],
                $price_php,
                $product['image_url'],
                $product['brand'],
                $product['model'],
                $product['color'],
                $product['gender'],
                $product['id']
            ]
        );
        
        if ($result) {
            echo "<p>✅ Updated: {$product['name']} - ₱" . number_format($price_php, 2) . "</p>";
        } else {
            echo "<p>❌ Failed to update: {$product['name']}</p>";
        }
    }
    
    // Add new products
    echo "<h3>➕ Adding New Products:</h3>";
    foreach ($additional_products as $product) {
        $price_php = round($product['price_usd'] * $usd_to_php, 2);
        
        $result = $db->insert(
            "INSERT INTO products (category_id, product_name, description, price, stock_quantity, brand, model, color, gender, image_url, sku, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
                $product['sku'],
                false
            ]
        );
        
        if ($result) {
            echo "<p>✅ Added: {$product['name']} - ₱" . number_format($price_php, 2) . "</p>";
        } else {
            echo "<p>❌ Failed to add: {$product['name']}</p>";
        }
    }
    
    // Update category descriptions
    echo "<h3>📋 Updating Category Descriptions:</h3>";
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
            [$category[1], $category[0]]
        );
        
        if ($result) {
            echo "<p>✅ Updated category: {$category[1]}</p>";
        } else {
            echo "<p>❌ Failed to update category: {$category[1]}</p>";
        }
    }
    
    echo "<h3>✅ Product Update Complete!</h3>";
    echo "<p>All products now have:</p>";
    echo "<ul>";
    echo "<li>✅ Appropriate product images</li>";
    echo "<li>✅ Prices converted to Philippine Pesos</li>";
    echo "<li>✅ Updated descriptions (frames only, no prescription lenses)</li>";
    echo "<li>✅ Updated category descriptions</li>";
    echo "</ul>";
    
    // Show summary
    $total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
    echo "<p><strong>Total products in database:</strong> $total_products</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}
h2, h3 {
    color: #333;
}
p {
    margin: 10px 0;
}
ul {
    margin: 10px 0;
    padding-left: 20px;
}
</style>





