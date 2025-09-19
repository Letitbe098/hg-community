<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasPermission('moderate_users')) {
    header('Location: index.php');
    exit;
}

$user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - HG Community</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .manage-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #2b2d31;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .manage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #3f4147;
        }
        
        .manage-header h1 {
            color: #ffffff;
            font-size: 1.8rem;
            margin: 0;
        }
        
        .back-button {
            background: #4f545c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-button:hover {
            background: #5d6269;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: #383a40;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #4f545c;
        }
        
        .users-table th {
            background: #4f545c;
            color: #ffffff;
            font-weight: 600;
        }
        
        .users-table td {
            color: #dbdee1;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: #ffffff;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: #949ba4;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active { background: #23a559; color: white; }
        .status-banned { background: #ed4245; color: white; }
        .status-muted { background: #f0b232; color: white; }
        .status-restricted { background: #5865f2; color: white; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .btn-ban { background: #ed4245; color: white; }
        .btn-unban { background: #23a559; color: white; }
        .btn-mute { background: #f0b232; color: white; }
        .btn-unmute { background: #23a559; color: white; }
        .btn-restrict { background: #5865f2; color: white; }
        .btn-unrestrict { background: #23a559; color: white; }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #949ba4;
        }
        
        @media (max-width: 768px) {
            .users-table {
                font-size: 0.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .manage-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-content" style="width: 100%; max-width: none;">
            <div class="manage-container">
                <div class="manage-header">
                    <h1>Manage Users</h1>
                    <a href="index.php" class="back-button">← Back to Community</a>
                </div>
                
                <div id="users-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading users...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const currentUserRole = '<?php echo $user['role']; ?>';
        
        async function loadUsers() {
            try {
                const response = await fetch('api/users.php');
                const data = await response.json();
                
                if (data.success) {
                    renderUsers(data.users);
                } else {
                    document.getElementById('users-container').innerHTML = 
                        '<div class="loading">Failed to load users: ' + data.message + '</div>';
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('users-container').innerHTML = 
                    '<div class="loading">Error loading users</div>';
            }
        }
        
        function renderUsers(users) {
            const container = document.getElementById('users-container');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="loading">No users found</div>';
                return;
            }
            
            let tableHTML = `
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            users.forEach(user => {
                const avatarSrc = user.avatar || 'assets/images/default-avatar.png';
                const lastActive = user.last_active ? new Date(user.last_active).toLocaleDateString() : 'Never';
                
                tableHTML += `
                    <tr>
                        <td>
                            <div class="user-info">
                                <img src="${avatarSrc}" alt="Avatar" class="user-avatar">
                                <div class="user-details">
                                    <div class="user-name">${user.username}</div>
                                    <div class="user-email">${user.email}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge role-${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                        </td>
                        <td>
                            <span class="status-badge status-${user.status}">${user.status}</span>
                        </td>
                        <td>${lastActive}</td>
                        <td>
                            <div class="action-buttons">
                                ${generateActionButtons(user)}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += '</tbody></table>';
            container.innerHTML = tableHTML;
        }
        
        function generateActionButtons(user) {
            // Don't show actions for current user or if user is admin and current user is not admin
            if (user.role === 'admin' && currentUserRole !== 'admin') {
                return '<span style="color: #949ba4;">No actions available</span>';
            }
            
            let buttons = '';
            
            switch (user.status) {
                case 'active':
                    buttons += `<button class="action-btn btn-ban" onclick="moderateUser(${user.id}, 'ban')">Ban</button>`;
                    buttons += `<button class="action-btn btn-mute" onclick="moderateUser(${user.id}, 'mute')">Mute</button>`;
                    buttons += `<button class="action-btn btn-restrict" onclick="moderateUser(${user.id}, 'restrict')">Restrict</button>`;
                    break;
                case 'banned':
                    buttons += `<button class="action-btn btn-unban" onclick="moderateUser(${user.id}, 'unban')">Unban</button>`;
                    break;
                case 'muted':
                    buttons += `<button class="action-btn btn-unmute" onclick="moderateUser(${user.id}, 'unmute')">Unmute</button>`;
                    buttons += `<button class="action-btn btn-ban" onclick="moderateUser(${user.id}, 'ban')">Ban</button>`;
                    break;
                case 'restricted':
                    buttons += `<button class="action-btn btn-unrestrict" onclick="moderateUser(${user.id}, 'unrestrict')">Unrestrict</button>`;
                    buttons += `<button class="action-btn btn-ban" onclick="moderateUser(${user.id}, 'ban')">Ban</button>`;
                    break;
            }
            
            return buttons || '<span style="color: #949ba4;">No actions available</span>';
        }
        
        async function moderateUser(userId, action) {
            const actionText = action.charAt(0).toUpperCase() + action.slice(1);
            
            if (!confirm(`Are you sure you want to ${action} this user?`)) {
                return;
            }
            
            try {
                const response = await fetch('api/users.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        action: action
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(`User ${action}ned successfully!`, 'success');
                    loadUsers(); // Reload users list
                } else {
                    showNotification('Failed to ' + action + ' user: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error performing action', 'error');
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }
        
        // Load users on page load
        loadUsers();
    </script>
</body>
</html>