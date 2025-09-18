<?php
session_start();
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        $query = "SELECT id, username, email, password, role, status FROM users WHERE username = :username OR email = :username";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row['status'] == 'banned') {
                return ['success' => false, 'message' => 'Your account has been banned.'];
            }
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                
                // Update last active
                $updateQuery = "UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = :id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $row['id']);
                $updateStmt->execute();
                
                return ['success' => true, 'user' => $row];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials.'];
    }
    
    public function register($username, $email, $phone, $password, $inviteCode = null) {
        // Check if invite code is valid
        if ($inviteCode) {
            $inviteQuery = "SELECT id, role FROM invites WHERE invite_code = :code AND expires_at > NOW() AND used_at IS NULL";
            $inviteStmt = $this->db->prepare($inviteQuery);
            $inviteStmt->bindParam(':code', $inviteCode);
            $inviteStmt->execute();
            
            if ($inviteStmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Invalid or expired invite code.'];
            }
            
            $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
            $role = $invite['role'];
        } else {
            $role = 'member';
        }
        
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertQuery = "INSERT INTO users (username, email, phone, password, role) VALUES (:username, :email, :phone, :password, :role)";
        $insertStmt = $this->db->prepare($insertQuery);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':phone', $phone);
        $insertStmt->bindParam(':password', $hashedPassword);
        $insertStmt->bindParam(':role', $role);
        
        if ($insertStmt->execute()) {
            $userId = $this->db->lastInsertId();
            
            // Mark invite as used
            if ($inviteCode) {
                $updateInviteQuery = "UPDATE invites SET used_at = NOW(), used_by = :user_id WHERE invite_code = :code";
                $updateInviteStmt = $this->db->prepare($updateInviteQuery);
                $updateInviteStmt->bindParam(':user_id', $userId);
                $updateInviteStmt->bindParam(':code', $inviteCode);
                $updateInviteStmt->execute();
            }
            
            return ['success' => true, 'message' => 'Account created successfully.'];
        }
        
        return ['success' => false, 'message' => 'Registration failed.'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $query = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function hasPermission($permission, $channelId = null) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['role'];
        
        // Admin has all permissions
        if ($role == 'admin') {
            return true;
        }
        
        // Role-based permissions
        switch ($permission) {
            case 'create_announcement':
                return in_array($role, ['admin']);
            case 'moderate_users':
                return in_array($role, ['admin', 'moderator']);
            case 'manage_channels':
                return in_array($role, ['admin']);
            case 'send_message':
                return in_array($role, ['admin', 'moderator', 'member']);
            default:
                return false;
        }
    }
}
?>