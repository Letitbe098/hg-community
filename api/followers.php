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
$currentUserId = $_SESSION['user_id'];

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'followers';
        $userId = $_GET['user_id'] ?? $currentUserId;
        
        switch ($action) {
            case 'followers':
                // Get followers list
                $query = "SELECT u.id, u.username, u.display_name, u.avatar, u.status, f.created_at as followed_since
                         FROM followers f
                         JOIN users u ON f.follower_id = u.id
                         WHERE f.following_id = :user_id
                         ORDER BY f.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'followers' => $followers]);
                break;
                
            case 'following':
                // Get following list
                $query = "SELECT u.id, u.username, u.display_name, u.avatar, u.status, f.created_at as followed_since
                         FROM followers f
                         JOIN users u ON f.following_id = u.id
                         WHERE f.follower_id = :user_id
                         ORDER BY f.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $following = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'following' => $following]);
                break;
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;
        $targetUserId = $data['user_id'] ?? null;
        
        if (!$targetUserId) {
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit;
        }
        
        if ($targetUserId == $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Cannot follow yourself']);
            exit;
        }
        
        switch ($action) {
            case 'follow':
                // Check if already following
                $checkQuery = "SELECT id FROM followers WHERE follower_id = :follower AND following_id = :following";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':follower', $currentUserId);
                $checkStmt->bindParam(':following', $targetUserId);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Already following this user']);
                    exit;
                }
                
                // Follow user
                $insertQuery = "INSERT INTO followers (follower_id, following_id) VALUES (:follower, :following)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':follower', $currentUserId);
                $insertStmt->bindParam(':following', $targetUserId);
                
                if ($insertStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'User followed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to follow user']);
                }
                break;
                
            case 'unfollow':
                $deleteQuery = "DELETE FROM followers WHERE follower_id = :follower AND following_id = :following";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':follower', $currentUserId);
                $deleteStmt->bindParam(':following', $targetUserId);
                
                if ($deleteStmt->execute() && $deleteStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'User unfollowed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to unfollow user']);
                }
                break;
        }
        break;
}
?>