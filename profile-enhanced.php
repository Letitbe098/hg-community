<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$profileUserId = $_GET['user_id'] ?? $currentUser['id'];
$isOwnProfile = ($profileUserId == $currentUser['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - HG Community</title>
    <link rel="stylesheet" href="assets/css/profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="profile-wrapper">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <img id="cover-image" class="cover-image" src="" alt="Cover" style="display: none;">
                
                <a href="index.php" class="back-button">← Back to Community</a>
                
                <?php if ($isOwnProfile): ?>
                <button class="cover-upload tooltip" onclick="document.getElementById('cover-input').click()">
                    📷 Change Cover
                    <span class="tooltiptext">Upload a cover image to personalize your profile</span>
                </button>
                <input type="file" id="cover-input" accept="image/*" style="display: none;">
                <?php endif; ?>
                
                <!-- Avatar Section -->
                <div class="avatar-section">
                    <div class="avatar-container">
                        <img id="profile-avatar" class="profile-avatar" src="assets/images/default-avatar.png" alt="Profile Picture">
                        <div id="status-indicator" class="status-indicator offline"></div>
                        
                        <?php if ($isOwnProfile): ?>
                        <button class="avatar-upload tooltip" onclick="document.getElementById('avatar-input').click()">
                            📷
                            <span class="tooltiptext">Upload a new profile picture</span>
                        </button>
                        <input type="file" id="avatar-input" accept="image/*" style="display: none;">
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-basic-info">
                        <h1 id="profile-name" class="profile-name">Loading...</h1>
                        <p id="profile-username" class="profile-username">@loading</p>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <span id="friends-count" class="stat-number">0</span>
                                <span class="stat-label">Friends</span>
                            </div>
                            <div class="stat-item">
                                <span id="followers-count" class="stat-number">0</span>
                                <span class="stat-label">Followers</span>
                            </div>
                            <div class="stat-item">
                                <span id="following-count" class="stat-number">0</span>
                                <span class="stat-label">Following</span>
                            </div>
                            <div class="stat-item">
                                <span id="posts-count" class="stat-number">0</span>
                                <span class="stat-label">Posts</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="profile-actions" id="profile-actions">
                    <!-- Buttons will be populated by JavaScript -->
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <!-- Tabs -->
                <div class="profile-tabs">
                    <button class="tab-button active" data-tab="about">About</button>
                    <button class="tab-button" data-tab="friends">Friends</button>
                    <button class="tab-button" data-tab="followers">Followers</button>
                    <button class="tab-button" data-tab="posts">Posts</button>
                    <button class="tab-button" data-tab="gallery">Gallery</button>
                </div>
                
                <!-- About Tab -->
                <div id="about-tab" class="tab-content active">
                    <div class="about-section">
                        <div class="about-main">
                            <h3>📝 About</h3>
                            <p id="bio-text" class="bio-text">No bio available.</p>
                            
                            <h3 style="margin-top: 30px;">🔗 Social Links</h3>
                            <div id="social-links" class="social-links">
                                <!-- Social links will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="about-sidebar">
                            <div class="info-card">
                                <h3>ℹ️ Information</h3>
                                <div class="info-item">
                                    <span class="info-icon">👤</span>
                                    <span>Role: <span id="user-role">Member</span></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-icon">📅</span>
                                    <span>Joined: <span id="join-date">Unknown</span></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-icon">🕒</span>
                                    <span>Last active: <span id="last-active">Unknown</span></span>
                                </div>
                                <div class="info-item" id="phone-info" style="display: none;">
                                    <span class="info-icon">📱</span>
                                    <span>Phone: <span id="user-phone">Not provided</span></span>
                                </div>
                            </div>
                            
                            <div class="info-card" id="mutual-friends-card" style="display: none;">
                                <h3>👥 Mutual Friends</h3>
                                <div id="mutual-friends-list">
                                    <!-- Mutual friends will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Friends Tab -->
                <div id="friends-tab" class="tab-content">
                    <div class="loading" id="friends-loading">
                        <div class="spinner"></div>
                        Loading friends...
                    </div>
                    <div id="friends-content" class="friends-grid" style="display: none;">
                        <!-- Friends will be populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Followers Tab -->
                <div id="followers-tab" class="tab-content">
                    <div class="loading" id="followers-loading">
                        <div class="spinner"></div>
                        Loading followers...
                    </div>
                    <div id="followers-content" class="friends-grid" style="display: none;">
                        <!-- Followers will be populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Posts Tab -->
                <div id="posts-tab" class="tab-content">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading posts...
                    </div>
                </div>
                
                <!-- Gallery Tab -->
                <div id="gallery-tab" class="tab-content">
                    <div class="gallery-grid">
                        <!-- Gallery items will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div id="edit-profile-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <span class="close">&times;</span>
            </div>
            
            <form id="edit-profile-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-username">Username</label>
                        <input type="text" id="edit-username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-display-name">Display Name</label>
                        <input type="text" id="edit-display-name" name="display_name">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="edit-bio">Bio / About</label>
                    <textarea id="edit-bio" name="bio" placeholder="Tell us about yourself..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-phone">Phone Number</label>
                    <input type="tel" id="edit-phone" name="phone">
                    <div class="privacy-note">
                        📱 Phone number is only visible to admins and trusted friends
                    </div>
                </div>
                
                <h3 style="margin: 25px 0 15px 0; color: #ffffff;">🔗 Social Links</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-snapchat">Snapchat</label>
                        <input type="url" id="edit-snapchat" name="snapchat" placeholder="https://snapchat.com/add/username">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-instagram">Instagram</label>
                        <input type="url" id="edit-instagram" name="instagram" placeholder="https://instagram.com/username">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-facebook">Facebook</label>
                        <input type="url" id="edit-facebook" name="facebook" placeholder="https://facebook.com/username">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-email-link">Email</label>
                        <input type="email" id="edit-email-link" name="email" placeholder="your.email@example.com">
                    </div>
                </div>
                
                <h3 style="margin: 25px 0 15px 0; color: #ffffff;">🎨 Theme Settings</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-theme">Theme</label>
                        <select id="edit-theme" name="theme">
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-accent-color">Accent Color</label>
                        <input type="color" id="edit-accent-color" name="accent_color" value="#5865f2">
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="modal-btn secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="modal-btn primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const currentUser = <?php echo json_encode($currentUser); ?>;
        const profileUserId = <?php echo json_encode($profileUserId); ?>;
        const isOwnProfile = <?php echo json_encode($isOwnProfile); ?>;
    </script>
    <script src="assets/js/profile-enhanced.js"></script>
</body>
</html>