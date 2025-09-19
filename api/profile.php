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
        
        $query = "SELECT id, username, email, phone, bio, avatar, role, status, created_at, last_active FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;
        
    case 'POST':
        // Handle profile picture upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $uploadDir = '../uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['avatar']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit;
            }
            
            if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) { // 5MB limit
                echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.']);
                exit;
            }
            
            $fileName = $_SESSION['user_id'] . '_' . time() . '_' . $_FILES['avatar']['name'];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filePath)) {
                $avatarPath = 'uploads/avatars/' . $fileName;
                
                // Update user avatar in database
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
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $username = $data['username'] ?? null;
        $bio = $data['bio'] ?? null;
        $phone = $data['phone'] ?? null;
        
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
        
        $updateQuery = "UPDATE users SET username = :username, bio = :bio, phone = :phone WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':username', $username);
        $updateStmt->bindParam(':bio', $bio);
        $updateStmt->bindParam(':phone', $phone);
        $updateStmt->bindParam(':id', $userId);
        
        if ($updateStmt->execute()) {
            // Update session username
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        break;
}
?>