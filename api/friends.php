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
        $action = $_GET['action'] ?? 'list';
        $userId = $_GET['user_id'] ?? $currentUserId;
        
        switch ($action) {
            case 'list':
                // Get friends list
                $query = "SELECT u.id, u.username, u.display_name, u.avatar, u.status, u.last_active,
                                f.status as friendship_status, f.created_at as friends_since
                         FROM friends f
                         JOIN users u ON (CASE WHEN f.user_id = :user_id THEN f.friend_id ELSE f.user_id END) = u.id
                         WHERE (f.user_id = :user_id OR f.friend_id = :user_id) AND f.status = 'accepted'
                         ORDER BY u.username";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'friends' => $friends]);
                break;
                
            case 'requests':
                // Get friend requests (received)
                $query = "SELECT u.id, u.username, u.display_name, u.avatar, f.id as request_id, f.created_at
                         FROM friends f
                         JOIN users u ON f.user_id = u.id
                         WHERE f.friend_id = :user_id AND f.status = 'pending'
                         ORDER BY f.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $currentUserId);
                $stmt->execute();
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'requests' => $requests]);
                break;
                
            case 'sent':
                // Get sent friend requests
                $query = "SELECT u.id, u.username, u.display_name, u.avatar, f.id as request_id, f.created_at
                         FROM friends f
                         JOIN users u ON f.friend_id = u.id
                         WHERE f.user_id = :user_id AND f.status = 'pending'
                         ORDER BY f.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $currentUserId);
                $stmt->execute();
                $sent = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'sent_requests' => $sent]);
                break;
                
            case 'mutual':
                $targetUserId = $_GET['target_user_id'] ?? null;
                if (!$targetUserId) {
                    echo json_encode(['success' => false, 'message' => 'Target user ID required']);
                    exit;
                }
                
                // Get mutual friends
                $query = "SELECT DISTINCT u.id, u.username, u.display_name, u.avatar
                         FROM friends f1
                         JOIN friends f2 ON (
                             (f1.user_id = f2.user_id OR f1.user_id = f2.friend_id OR f1.friend_id = f2.user_id OR f1.friend_id = f2.friend_id)
                             AND f1.id != f2.id
                         )
                         JOIN users u ON (
                             CASE 
                                 WHEN f1.user_id = :current_user THEN f1.friend_id
                                 WHEN f1.friend_id = :current_user THEN f1.user_id
                             END = u.id
                         )
                         WHERE ((f1.user_id = :current_user OR f1.friend_id = :current_user) AND f1.status = 'accepted')
                           AND ((f2.user_id = :target_user OR f2.friend_id = :target_user) AND f2.status = 'accepted')
                           AND u.id != :current_user AND u.id != :target_user";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':current_user', $currentUserId);
                $stmt->bindParam(':target_user', $targetUserId);
                $stmt->execute();
                $mutualFriends = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'mutual_friends' => $mutualFriends]);
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
        
        switch ($action) {
            case 'send_request':
                // Check if request already exists
                $checkQuery = "SELECT id FROM friends 
                              WHERE ((user_id = :current_user AND friend_id = :target_user) 
                                  OR (user_id = :target_user AND friend_id = :current_user))";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':current_user', $currentUserId);
                $checkStmt->bindParam(':target_user', $targetUserId);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Friend request already exists']);
                    exit;
                }
                
                // Send friend request
                $insertQuery = "INSERT INTO friends (user_id, friend_id, status) VALUES (:user_id, :friend_id, 'pending')";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':user_id', $currentUserId);
                $insertStmt->bindParam(':friend_id', $targetUserId);
                
                if ($insertStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Friend request sent']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send friend request']);
                }
                break;
                
            case 'accept_request':
                $requestId = $data['request_id'] ?? null;
                if (!$requestId) {
                    echo json_encode(['success' => false, 'message' => 'Request ID required']);
                    exit;
                }
                
                $updateQuery = "UPDATE friends SET status = 'accepted' WHERE id = :id AND friend_id = :current_user";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $requestId);
                $updateStmt->bindParam(':current_user', $currentUserId);
                
                if ($updateStmt->execute() && $updateStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Friend request accepted']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to accept friend request']);
                }
                break;
                
            case 'reject_request':
                $requestId = $data['request_id'] ?? null;
                if (!$requestId) {
                    echo json_encode(['success' => false, 'message' => 'Request ID required']);
                    exit;
                }
                
                $deleteQuery = "DELETE FROM friends WHERE id = :id AND friend_id = :current_user";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':id', $requestId);
                $deleteStmt->bindParam(':current_user', $currentUserId);
                
                if ($deleteStmt->execute() && $deleteStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Friend request rejected']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reject friend request']);
                }
                break;
                
            case 'remove_friend':
                $deleteQuery = "DELETE FROM friends 
                               WHERE ((user_id = :current_user AND friend_id = :target_user) 
                                   OR (user_id = :target_user AND friend_id = :current_user))
                               AND status = 'accepted'";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':current_user', $currentUserId);
                $deleteStmt->bindParam(':target_user', $targetUserId);
                
                if ($deleteStmt->execute() && $deleteStmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Friend removed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to remove friend']);
                }
                break;
        }
        break;
}
?>