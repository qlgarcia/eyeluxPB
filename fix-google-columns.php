<?php
// Add missing Google OAuth columns to users table
require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h2>🔧 Adding Google OAuth Columns</h2>";

try {
    $db = Database::getInstance();
    
    // Check current columns
    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    $existingColumns = array_column($columns, 'Field');
    
    echo "<h3>Current columns:</h3>";
    foreach ($existingColumns as $column) {
        echo "<p>✅ $column</p>";
    }
    
    // Add missing columns
    $missingColumns = [
        'google_id' => 'VARCHAR(255) NULL',
        'auth_provider' => "ENUM('local', 'google') DEFAULT 'local'",
        'profile_picture_url' => 'VARCHAR(500) NULL'
    ];
    
    echo "<h3>Adding missing columns:</h3>";
    
    foreach ($missingColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $existingColumns)) {
            $sql = "ALTER TABLE users ADD COLUMN $columnName $columnDef";
            $result = $db->execute($sql);
            
            if ($result) {
                echo "<p>✅ Added column: $columnName</p>";
            } else {
                echo "<p>❌ Failed to add column: $columnName</p>";
            }
        } else {
            echo "<p>✅ Column already exists: $columnName</p>";
        }
    }
    
    // Verify final columns
    echo "<h3>Final column check:</h3>";
    $finalColumns = $db->fetchAll("SHOW COLUMNS FROM users");
    $finalColumnNames = array_column($finalColumns, 'Field');
    
    $requiredColumns = ['google_id', 'auth_provider', 'email_verified', 'profile_picture_url'];
    
    foreach ($requiredColumns as $column) {
        if (in_array($column, $finalColumnNames)) {
            echo "<p>✅ Column '$column' exists</p>";
        } else {
            echo "<p>❌ Column '$column' missing</p>";
        }
    }
    
    echo "<h3>✅ Database update complete!</h3>";
    echo "<p><a href='test-db-oauth.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Test Database Again</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>





