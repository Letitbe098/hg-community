<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HG Community - Hackers Gurukul</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <div class="app-container">
        <!-- Left Sidebar - Channels -->
        <div class="sidebar-left">
            <div class="server-header">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    
                <img src="assets/images/logo.jpeg" 
     alt="Hackers Gurukul Logo" 
     style="
        width: 40px;
        height: 40px;
        border-radius: 50%;                /* round edges */
        background-color: rgba(255,255,255,0.6); /* faint white background */
        border: 0.5px solid rgba(255,255,255,0.7); /* ultra-thin light border */
        padding: 2px;                      /* very thin space around logo */
        box-shadow: 0 0 2px rgba(255,255,255,0.4); /* subtle light glow */
     ">
<h2 style="display:inline-block; margin-left:8px;">HG Community</h2>

                </div>
                <div class="user-info">
                    <div class="avatar">
                        <img src="<?php echo $user['avatar'] ? htmlspecialchars($user['avatar']) : 'assets/images/default-avatar.png'; ?>" alt="Avatar">
                        <div class="status-indicator online"></div>
                    </div>
                    <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                    <span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                </div>
            </div>
            
            <div class="channels-container">
                <div class="channel-category">
                    <h3>📢 Announcements</h3>
                    <div id="announcement-channels"></div>
                </div>
                
                <div class="channel-category">
                    <h3>👥 Teams</h3>
                    <div id="team-channels"></div>
                </div>
                
                <div class="channel-category">
                    <h3>💻 Technical</h3>
                    <div id="technical-channels"></div>
                </div>
                
                <div class="channel-category">
                    <h3>💬 General</h3>
                    <div id="general-channels"></div>
                </div>
            </div>
            
            <?php if ($user['role'] == 'admin'): ?>
            <div class="admin-controls">
                <button id="create-channel-btn" class="control-btn">+ New Channel</button>
                <button id="create-invite-btn" class="control-btn">Create Invite</button>
                <button id="manage-users-btn" class="control-btn" onclick="window.location.href='manage-users.php'">Manage Users</button>
            </div>
            <?php elseif ($user['role'] == 'moderator'): ?>
            <div class="admin-controls">
                <button id="manage-users-btn" class="control-btn" onclick="window.location.href='manage-users.php'">Manage Users</button>
            </div>
            <?php endif; ?>
            
            <div class="user-controls">
                <button id="settings-btn" class="control-btn" onclick="window.location.href='profile.php'">⚙️ Profile</button>
                <button id="logout-btn" class="control-btn">🚪 Logout</button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="chat-header">
                <h3 id="current-channel">Select a channel</h3>
                <div class="channel-actions">
                    <button id="upload-file-btn" class="action-btn">📎 Upload</button>
                </div>
            </div>
            
            <div class="messages-container" id="messages-container">
                <div class="welcome-message">
                    <h2>Welcome to HG Community! 👋</h2>
                    <p>Select a channel from the sidebar to start chatting with your fellow Hackers Gurukul students.</p>
                </div>
            </div>
            
            <div class="message-input-container" style="display: none;">
                <form id="message-form" enctype="multipart/form-data">
                    <input type="hidden" id="current-channel-id" name="channel_id">
                    <div class="file-preview" id="file-preview" style="display: none;"></div>
                    <div class="input-row">
                        <input type="text" id="message-input" name="content" placeholder="Type a message..." autocomplete="off">
                        <input type="file" id="file-input" name="file" style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                        <button type="button" id="file-btn" class="file-button">📎</button>
                        <button type="submit" class="send-button">Send</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Right Sidebar - Online Members -->
        <div class="sidebar-right">
            <div class="members-header">
                <h3>Online Members</h3>
                <span id="online-count">0 online</span>
            </div>
            
            <div class="members-list" id="members-list">
                <!-- Members will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="create-channel-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Create New Channel</h2>
            <form id="create-channel-form">
                <div class="form-group">
                    <label for="channel-name">Channel Name</label>
                    <input type="text" id="channel-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="channel-description">Description</label>
                    <textarea id="channel-description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="channel-type">Type</label>
                    <select id="channel-type" name="type" required>
                        <option value="general">General</option>
                        <option value="team">Team</option>
                        <option value="technical">Technical</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </div>
                <div class="form-group" id="team-name-group" style="display: none;">
                    <label for="team-name">Team Name</label>
                    <input type="text" id="team-name" name="team_name">
                </div>
                <button type="submit">Create Channel</button>
            </form>
        </div>
    </div>
    
    <div id="create-invite-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Create Invite Link</h2>
            <form id="create-invite-form">
                <div class="form-group">
                    <label for="invite-email">Email (Required)</label>
                    <input type="email" id="invite-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="invite-phone">Phone (Optional)</label>
                    <input type="tel" id="invite-phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="invite-role">Role</label>
                    <select id="invite-role" name="role">
                        <option value="member">Member</option>
                        <option value="moderator">Moderator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expiry-hours">Expires in (hours)</label>
                    <input type="number" id="expiry-hours" name="expiry_hours" value="24" min="1" max="168">
                </div>
                <button type="submit">Create Invite</button>
            </form>
            <div id="invite-result" style="display: none;">
                <h3>Invite Created!</h3>
                <div class="invite-info">
                    <label>Invite Link:</label>
                    <input type="text" id="invite-url" readonly>
                    <button onclick="copyInviteLink()">Copy</button>
                </div>
                <div class="invite-info">
                    <label>Invite Code:</label>
                    <input type="text" id="invite-code" readonly>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const currentUser = <?php echo json_encode($user); ?>;
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>