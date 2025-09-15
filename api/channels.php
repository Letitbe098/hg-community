<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

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
        $query = "SELECT c.*, u.username as created_by_name FROM channels c 
                 LEFT JOIN users u ON c.created_by = u.id 
                 ORDER BY c.type, c.name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'channels' => $channels]);
        break;
        
    case 'POST':
        if (!$auth->hasPermission('manage_channels')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'];
        $description = $data['description'];
        $type = $data['type'];
        $teamName = $data['team_name'] ?? null;
        
        $insertQuery = "INSERT INTO channels (name, description, type, team_name, created_by) VALUES (:name, :description, :type, :team_name, :created_by)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':name', $name);
        $insertStmt->bindParam(':description', $description);
        $insertStmt->bindParam(':type', $type);
        $insertStmt->bindParam(':team_name', $teamName);
        $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
        
        if ($insertStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Channel created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create channel']);
        }
        break;
}
?>