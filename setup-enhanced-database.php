<?php
// Enhanced database setup script for profile features
// Access: http://localhost/hg-community/setup-enhanced-database.php

require_once 'config/database.php';

echo "<h2>HG Community - Enhanced Profile Database Setup</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";
    
    // Enhanced tables for profile features
    $enhancedTables = [
        "users_enhanced" => "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS display_name VARCHAR(100),
            ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255),
            ADD COLUMN IF NOT EXISTS theme ENUM('light', 'dark') DEFAULT 'dark',
            ADD COLUMN IF NOT EXISTS accent_color VARCHAR(7) DEFAULT '#5865f2'",
        
        "friends" => "CREATE TABLE IF NOT EXISTS friends (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            friend_id INT NOT NULL,
            status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
            is_trusted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_friendship (user_id, friend_id)
        )",
        
        "followers" => "CREATE TABLE IF NOT EXISTS followers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            following_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_follow (follower_id, following_id)
        )",
        
        "user_social_links" => "CREATE TABLE IF NOT EXISTS user_social_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            platform ENUM('snapchat', 'instagram', 'facebook', 'email', 'twitter', 'linkedin', 'github') NOT NULL,
            url VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_platform (user_id, platform)
        )",
        
        "user_gallery" => "CREATE TABLE IF NOT EXISTS user_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            caption TEXT,
            is_profile_pic BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "user_posts" => "CREATE TABLE IF NOT EXISTS user_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            image_path VARCHAR(255),
            likes_count INT DEFAULT 0,
            comments_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "post_likes" => "CREATE TABLE IF NOT EXISTS post_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES user_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_like (post_id, user_id)
        )",
        
        "post_comments" => "CREATE TABLE IF NOT EXISTS post_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES user_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "user_activity" => "CREATE TABLE IF NOT EXISTS user_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type ENUM('login', 'post', 'comment', 'like', 'friend_request', 'follow') NOT NULL,
            target_id INT,
            target_type ENUM('user', 'post', 'comment', 'channel'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    echo "<h3>Creating Enhanced Tables:</h3>";
    foreach ($enhancedTables as $tableName => $sql) {
        try {
            $db->exec($sql);
            echo "<p style='color: green;'>✅ Table/Enhancement '$tableName' created successfully</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error with '$tableName': " . $e->getMessage() . "</p>";
        }
    }
    
    // Create upload directories
    echo "<h3>Creating Upload Directories:</h3>";
    $directories = [
        '../uploads/avatars/',
        '../uploads/covers/',
        '../uploads/gallery/',
        '../uploads/posts/'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0777, true)) {
                echo "<p style='color: green;'>✅ Directory '$dir' created</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to create directory '$dir'</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Directory '$dir' already exists</p>";
        }
    }
    
    // Insert sample social links for admin user
    echo "<h3>Setting up Sample Data:</h3>";
    try {
        $adminQuery = "SELECT id FROM users WHERE role = 'admin' LIMIT 1";
        $adminStmt = $db->prepare($adminQuery);
        $adminStmt->execute();
        
        if ($adminStmt->rowCount() > 0) {
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            $adminId = $admin['id'];
            
            // Add sample social links for admin
            $sampleLinks = [
                ['snapchat', 'https://snapchat.com/add/hgcommunity'],
                ['instagram', 'https://instagram.com/hackersgurukulofficial'],
                ['facebook', 'https://facebook.com/hackersgurukulofficial'],
                ['email', 'admin@hackersgurukulcommunity.com']
            ];
            
            foreach ($sampleLinks as $link) {
                $checkLinkQuery = "SELECT id FROM user_social_links WHERE user_id = ? AND platform = ?";
                $checkLinkStmt = $db->prepare($checkLinkQuery);
                $checkLinkStmt->execute([$adminId, $link[0]]);
                
                if ($checkLinkStmt->rowCount() == 0) {
                    $insertLinkQuery = "INSERT INTO user_social_links (user_id, platform, url) VALUES (?, ?, ?)";
                    $insertLinkStmt = $db->prepare($insertLinkQuery);
                    $insertLinkStmt->execute([$adminId, $link[0], $link[1]]);
                    echo "<p style='color: green;'>✅ Added sample {$link[0]} link for admin</p>";
                }
            }
            
            // Update admin with enhanced profile data
            $updateAdminQuery = "UPDATE users SET 
                display_name = 'HG Community Admin',
                bio = 'Welcome to Hackers Gurukul Community! This is the official admin account.',
                theme = 'dark',
                accent_color = '#5865f2'
                WHERE id = ?";
            $updateAdminStmt = $db->prepare($updateAdminQuery);
            $updateAdminStmt->execute([$adminId]);
            echo "<p style='color: green;'>✅ Enhanced admin profile data</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Sample data setup: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>Enhanced Profile Setup Complete! 🎉</h3>";
    echo "<p><strong>New Features Available:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Enhanced profile with display names and cover images</li>";
    echo "<li>✅ Friends system with requests and mutual friends</li>";
    echo "<li>✅ Followers system</li>";
    echo "<li>✅ Social links (Snapchat, Instagram, Facebook, Email)</li>";
    echo "<li>✅ Profile themes and accent colors</li>";
    echo "<li>✅ Privacy controls for phone numbers</li>";
    echo "<li>✅ Gallery and posts system (structure ready)</li>";
    echo "<li>✅ Activity tracking system</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='profile-enhanced.php'>Test Enhanced Profile Page</a></li>";
    echo "<li><a href='login.php'>Login and Update Your Profile</a></li>";
    echo "<li><a href='index.php'>Return to Community</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Setup Error: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL server is running</li>";
    echo "<li>Check database credentials in config/database.php</li>";
    echo "<li>Ensure database 'hg_community' exists</li>";
    echo "<li>Run basic setup first: <a href='setup-database.php'>setup-database.php</a></li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
h2, h3 { color: #333; }
ol, ul { margin-left: 20px; }
a { color: #667eea; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>