<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        $currentUserId = $_SESSION['user_id'];
        
        // Get user profile with enhanced data
        $query = "SELECT u.*, 
                         (SELECT COUNT(*) FROM friends WHERE (user_id = u.id OR friend_id = u.id) AND status = 'accepted') as friends_count,
                         (SELECT COUNT(*) FROM followers WHERE following_id = u.id) as followers_count,
                         (SELECT COUNT(*) FROM followers WHERE follower_id = u.id) as following_count,
                         (SELECT COUNT(*) FROM messages WHERE user_id = u.id) as posts_count
                  FROM users u 
                  WHERE u.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check relationship status if viewing someone else's profile
            $relationshipStatus = null;
            $friendRequestId = null;
            $isFollowing = false;
            
            if ($userId != $currentUserId) {
                // Check friendship status
                $friendQuery = "SELECT id, status FROM friends 
                               WHERE ((user_id = :current_user AND friend_id = :target_user) 
                                   OR (user_id = :target_user AND friend_id = :current_user))";
                $friendStmt = $db->prepare($friendQuery);
                $friendStmt->bindParam(':current_user', $currentUserId);
                $friendStmt->bindParam(':target_user', $userId);
                $friendStmt->execute();
                
                if ($friendStmt->rowCount() > 0) {
                    $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
                    $relationshipStatus = $friendship['status'];
                    $friendRequestId = $friendship['id'];
                }
                
                // Check following status
                $followQuery = "SELECT id FROM followers WHERE follower_id = :current_user AND following_id = :target_user";
                $followStmt = $db->prepare($followQuery);
                $followStmt->bindParam(':current_user', $currentUserId);
                $followStmt->bindParam(':target_user', $userId);
                $followStmt->execute();
                $isFollowing = $followStmt->rowCount() > 0;
            }
            
            // Get social links
            $socialQuery = "SELECT platform, url FROM user_social_links WHERE user_id = :user_id";
            $socialStmt = $db->prepare($socialQuery);
            $socialStmt->bindParam(':user_id', $userId);
            $socialStmt->execute();
            $socialLinks = $socialStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Privacy check for phone number
            $canViewPhone = false;
            if ($userId == $currentUserId || $_SESSION['role'] == 'admin') {
                $canViewPhone = true;
            } else {
                // Check if current user is a trusted friend
                $trustedQuery = "SELECT id FROM friends 
                                WHERE ((user_id = :current_user AND friend_id = :target_user) 
                                    OR (user_id = :target_user AND friend_id = :current_user))
                                AND status = 'accepted' AND is_trusted = 1";
                $trustedStmt = $db->prepare($trustedQuery);
                $trustedStmt->bindParam(':current_user', $currentUserId);
                $trustedStmt->bindParam(':target_user', $userId);
                $trustedStmt->execute();
                $canViewPhone = $trustedStmt->rowCount() > 0;
            }
            
            if (!$canViewPhone) {
                $user['phone'] = null;
            }
            
            echo json_encode([
                'success' => true, 
                'user' => $user,
                'social_links' => $socialLinks,
                'relationship_status' => $relationshipStatus,
                'friend_request_id' => $friendRequestId,
                'is_following' => $isFollowing,
                'can_view_phone' => $canViewPhone
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;
        
    case 'POST':
        // Handle file uploads (avatar, cover, gallery)
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $uploadDir = '../uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['avatar']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.']);
                exit;
            }
            
            if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.']);
                exit;
            }
            
            $fileName = $_SESSION['user_id'] . '_' . time() . '_' . $_FILES['avatar']['name'];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filePath)) {
                $avatarPath = 'uploads/avatars/' . $fileName;
                
                $updateQuery = "UPDATE users SET avatar = :avatar WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':avatar', $avatarPath);
                $updateStmt->bindParam(':id', $_SESSION['user_id']);
                
                if ($updateStmt->execute()) {
                    echo json_encode(['success' => true, 'avatar' => $avatarPath]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update avatar']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        }
        
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
            $uploadDir = '../uploads/covers/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['cover']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.']);
                exit;
            }
            
            if ($_FILES['cover']['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 10MB allowed.']);
                exit;
            }
            
            $fileName = $_SESSION['user_id'] . '_cover_' . time() . '_' . $_FILES['cover']['name'];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $filePath)) {
                $coverPath = 'uploads/covers/' . $fileName;
                
                $updateQuery = "UPDATE users SET cover_image = :cover WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':cover', $coverPath);
                $updateStmt->bindParam(':id', $_SESSION['user_id']);
                
                if ($updateStmt->execute()) {
                    echo json_encode(['success' => true, 'cover' => $coverPath]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update cover']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $username = $data['username'] ?? null;
        $displayName = $data['display_name'] ?? null;
        $bio = $data['bio'] ?? null;
        $phone = $data['phone'] ?? null;
        $theme = $data['theme'] ?? 'dark';
        $accentColor = $data['accent_color'] ?? '#5865f2';
        
        if (!$username) {
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            exit;
        }
        
        // Check if username already exists (excluding current user)
        $checkQuery = "SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':id', $userId);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        $updateQuery = "UPDATE users SET username = :username, display_name = :display_name, bio = :bio, phone = :phone, theme = :theme, accent_color = :accent_color WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':username', $username);
        $updateStmt->bindParam(':display_name', $displayName);
        $updateStmt->bindParam(':bio', $bio);
        $updateStmt->bindParam(':phone', $phone);
        $updateStmt->bindParam(':theme', $theme);
        $updateStmt->bindParam(':accent_color', $accentColor);
        $updateStmt->bindParam(':id', $userId);
        
        if ($updateStmt->execute()) {
            // Update social links
            if (isset($data['social_links'])) {
                // Delete existing social links
                $deleteQuery = "DELETE FROM user_social_links WHERE user_id = :user_id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':user_id', $userId);
                $deleteStmt->execute();
                
                // Insert new social links
                foreach ($data['social_links'] as $platform => $url) {
                    if (!empty($url)) {
                        $insertQuery = "INSERT INTO user_social_links (user_id, platform, url) VALUES (:user_id, :platform, :url)";
                        $insertStmt = $db->prepare($insertQuery);
                        $insertStmt->bindParam(':user_id', $userId);
                        $insertStmt->bindParam(':platform', $platform);
                        $insertStmt->bindParam(':url', $url);
                        $insertStmt->execute();
                    }
                }
            }
            
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        break;
}
?>