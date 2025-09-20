class EnhancedProfile {
    constructor() {
        this.profileData = null;
        this.currentTab = 'about';
        
        this.init();
    }
    
    init() {
        this.loadProfile();
        this.setupEventListeners();
        this.setupTabs();
    }
    
    setupEventListeners() {
        // Avatar upload
        if (isOwnProfile) {
            document.getElementById('avatar-input').addEventListener('change', (e) => {
                this.handleAvatarUpload(e);
            });
            
            document.getElementById('cover-input').addEventListener('change', (e) => {
                this.handleCoverUpload(e);
            });
        }
        
        // Edit profile form
        const editForm = document.getElementById('edit-profile-form');
        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveProfile();
            });
        }
        
        // Modal controls
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                e.target.closest('.modal').style.display = 'none';
            });
        });
        
        // Click outside modal to close
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }
    
    setupTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const tabName = e.target.dataset.tab;
                this.switchTab(tabName);
            });
        });
    }
    
    switchTab(tabName) {
        // Update active tab button
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Update active tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');
        
        this.currentTab = tabName;
        
        // Load tab-specific content
        switch (tabName) {
            case 'friends':
                this.loadFriends();
                break;
            case 'followers':
                this.loadFollowers();
                break;
            case 'posts':
                this.loadPosts();
                break;
            case 'gallery':
                this.loadGallery();
                break;
        }
    }
    
    async loadProfile() {
        try {
            const response = await fetch(`api/profile-enhanced.php?user_id=${profileUserId}`);
            const data = await response.json();
            
            if (data.success) {
                this.profileData = data;
                this.renderProfile();
                
                if (!isOwnProfile) {
                    this.loadMutualFriends();
                }
            } else {
                this.showNotification('Failed to load profile: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            this.showNotification('Error loading profile', 'error');
        }
    }
    
    renderProfile() {
        const user = this.profileData.user;
        
        // Basic info
        document.getElementById('profile-name').textContent = user.display_name || user.username;
        document.getElementById('profile-username').textContent = '@' + user.username;
        
        // Avatar
        const avatarSrc = user.avatar && user.avatar !== 'default-avatar.png' ? user.avatar : 'assets/images/default-avatar.png';
        document.getElementById('profile-avatar').src = avatarSrc;
        
        // Cover image
        if (user.cover_image) {
            const coverImg = document.getElementById('cover-image');
            coverImg.src = user.cover_image;
            coverImg.style.display = 'block';
        }
        
        // Status indicator
        const statusIndicator = document.getElementById('status-indicator');
        const isOnline = new Date(user.last_active) > new Date(Date.now() - 5 * 60 * 1000); // 5 minutes
        statusIndicator.className = `status-indicator ${isOnline ? 'online' : 'offline'}`;
        
        // Stats
        document.getElementById('friends-count').textContent = user.friends_count || 0;
        document.getElementById('followers-count').textContent = user.followers_count || 0;
        document.getElementById('following-count').textContent = user.following_count || 0;
        document.getElementById('posts-count').textContent = user.posts_count || 0;
        
        // Bio
        document.getElementById('bio-text').textContent = user.bio || 'No bio available.';
        
        // User info
        document.getElementById('user-role').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
        document.getElementById('join-date').textContent = new Date(user.created_at).toLocaleDateString();
        document.getElementById('last-active').textContent = new Date(user.last_active).toLocaleDateString();
        
        // Phone (if visible)
        if (this.profileData.can_view_phone && user.phone) {
            document.getElementById('user-phone').textContent = user.phone;
            document.getElementById('phone-info').style.display = 'flex';
        }
        
        // Social links
        this.renderSocialLinks();
        
        // Action buttons
        this.renderActionButtons();
        
        // Apply theme if it's own profile
        if (isOwnProfile && user.theme) {
            this.applyTheme(user.theme, user.accent_color);
        }
    }
    
    renderSocialLinks() {
        const container = document.getElementById('social-links');
        container.innerHTML = '';
        
        if (this.profileData.social_links && this.profileData.social_links.length > 0) {
            this.profileData.social_links.forEach(link => {
                const linkEl = document.createElement('a');
                linkEl.href = link.url;
                linkEl.target = '_blank';
                linkEl.className = `social-link ${link.platform}`;
                
                let icon = '🔗';
                switch (link.platform) {
                    case 'snapchat': icon = '👻'; break;
                    case 'instagram': icon = '📷'; break;
                    case 'facebook': icon = '📘'; break;
                    case 'email': icon = '📧'; break;
                }
                
                linkEl.innerHTML = `${icon} ${link.platform.charAt(0).toUpperCase() + link.platform.slice(1)}`;
                container.appendChild(linkEl);
            });
        } else {
            container.innerHTML = '<p style="color: #949ba4;">No social links added.</p>';
        }
    }
    
    renderActionButtons() {
        const container = document.getElementById('profile-actions');
        container.innerHTML = '';
        
        if (isOwnProfile) {
            // Own profile buttons
            const editBtn = document.createElement('button');
            editBtn.className = 'action-btn btn-primary';
            editBtn.innerHTML = '✏️ Edit Profile';
            editBtn.onclick = () => this.openEditModal();
            container.appendChild(editBtn);
        } else {
            // Other user's profile buttons
            const relationshipStatus = this.profileData.relationship_status;
            const isFollowing = this.profileData.is_following;
            
            // Follow/Unfollow button
            const followBtn = document.createElement('button');
            followBtn.className = `action-btn ${isFollowing ? 'btn-secondary' : 'btn-primary'}`;
            followBtn.innerHTML = isFollowing ? '👤 Unfollow' : '➕ Follow';
            followBtn.onclick = () => this.toggleFollow();
            container.appendChild(followBtn);
            
            // Friend request buttons
            if (relationshipStatus === null) {
                const friendBtn = document.createElement('button');
                friendBtn.className = 'action-btn btn-success';
                friendBtn.innerHTML = '👋 Send Friend Request';
                friendBtn.onclick = () => this.sendFriendRequest();
                container.appendChild(friendBtn);
            } else if (relationshipStatus === 'pending') {
                const pendingBtn = document.createElement('button');
                pendingBtn.className = 'action-btn btn-secondary';
                pendingBtn.innerHTML = '⏳ Request Sent';
                pendingBtn.disabled = true;
                container.appendChild(pendingBtn);
            } else if (relationshipStatus === 'accepted') {
                const friendBtn = document.createElement('button');
                friendBtn.className = 'action-btn btn-danger';
                friendBtn.innerHTML = '❌ Remove Friend';
                friendBtn.onclick = () => this.removeFriend();
                container.appendChild(friendBtn);
            }
        }
    }
    
    openEditModal() {
        const modal = document.getElementById('edit-profile-modal');
        const user = this.profileData.user;
        
        // Populate form
        document.getElementById('edit-username').value = user.username;
        document.getElementById('edit-display-name').value = user.display_name || '';
        document.getElementById('edit-bio').value = user.bio || '';
        document.getElementById('edit-phone').value = user.phone || '';
        document.getElementById('edit-theme').value = user.theme || 'dark';
        document.getElementById('edit-accent-color').value = user.accent_color || '#5865f2';
        
        // Populate social links
        const socialLinks = {};
        if (this.profileData.social_links) {
            this.profileData.social_links.forEach(link => {
                socialLinks[link.platform] = link.url;
            });
        }
        
        document.getElementById('edit-snapchat').value = socialLinks.snapchat || '';
        document.getElementById('edit-instagram').value = socialLinks.instagram || '';
        document.getElementById('edit-facebook').value = socialLinks.facebook || '';
        document.getElementById('edit-email-link').value = socialLinks.email || '';
        
        modal.style.display = 'block';
    }
    
    async saveProfile() {
        const formData = new FormData(document.getElementById('edit-profile-form'));
        
        const profileData = {
            username: formData.get('username'),
            display_name: formData.get('display_name'),
            bio: formData.get('bio'),
            phone: formData.get('phone'),
            theme: formData.get('theme'),
            accent_color: formData.get('accent_color'),
            social_links: {
                snapchat: formData.get('snapchat'),
                instagram: formData.get('instagram'),
                facebook: formData.get('facebook'),
                email: formData.get('email')
            }
        };
        
        try {
            const response = await fetch('api/profile-enhanced.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(profileData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Profile updated successfully!', 'success');
                document.getElementById('edit-profile-modal').style.display = 'none';
                this.loadProfile(); // Reload profile data
            } else {
                this.showNotification('Failed to update profile: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error saving profile:', error);
            this.showNotification('Error saving profile', 'error');
        }
    }
    
    async handleAvatarUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            this.showNotification('Please select an image file', 'error');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            this.showNotification('File size must be less than 5MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('avatar', file);
        
        try {
            const response = await fetch('api/profile-enhanced.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('profile-avatar').src = data.avatar;
                this.showNotification('Profile picture updated successfully!', 'success');
            } else {
                this.showNotification('Failed to update profile picture: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error uploading avatar:', error);
            this.showNotification('Error uploading profile picture', 'error');
        }
    }
    
    async handleCoverUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            this.showNotification('Please select an image file', 'error');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            this.showNotification('File size must be less than 10MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('cover', file);
        
        try {
            const response = await fetch('api/profile-enhanced.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const coverImg = document.getElementById('cover-image');
                coverImg.src = data.cover;
                coverImg.style.display = 'block';
                this.showNotification('Cover image updated successfully!', 'success');
            } else {
                this.showNotification('Failed to update cover image: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error uploading cover:', error);
            this.showNotification('Error uploading cover image', 'error');
        }
    }
    
    async toggleFollow() {
        const isFollowing = this.profileData.is_following;
        const action = isFollowing ? 'unfollow' : 'follow';
        
        try {
            const response = await fetch('api/followers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    user_id: profileUserId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.loadProfile(); // Reload to update counts and button state
            } else {
                this.showNotification('Failed to ' + action + ': ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error toggling follow:', error);
            this.showNotification('Error updating follow status', 'error');
        }
    }
    
    async sendFriendRequest() {
        try {
            const response = await fetch('api/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_request',
                    user_id: profileUserId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.loadProfile(); // Reload to update button state
            } else {
                this.showNotification('Failed to send friend request: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error sending friend request:', error);
            this.showNotification('Error sending friend request', 'error');
        }
    }
    
    async removeFriend() {
        if (!confirm('Are you sure you want to remove this friend?')) {
            return;
        }
        
        try {
            const response = await fetch('api/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'remove_friend',
                    user_id: profileUserId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.loadProfile(); // Reload to update counts and button state
            } else {
                this.showNotification('Failed to remove friend: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error removing friend:', error);
            this.showNotification('Error removing friend', 'error');
        }
    }
    
    async loadFriends() {
        const loading = document.getElementById('friends-loading');
        const content = document.getElementById('friends-content');
        
        loading.style.display = 'flex';
        content.style.display = 'none';
        
        try {
            const response = await fetch(`api/friends.php?action=list&user_id=${profileUserId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderFriends(data.friends);
            } else {
                content.innerHTML = '<p style="color: #949ba4; text-align: center;">Failed to load friends</p>';
            }
        } catch (error) {
            console.error('Error loading friends:', error);
            content.innerHTML = '<p style="color: #949ba4; text-align: center;">Error loading friends</p>';
        } finally {
            loading.style.display = 'none';
            content.style.display = 'grid';
        }
    }
    
    renderFriends(friends) {
        const container = document.getElementById('friends-content');
        
        if (friends.length === 0) {
            container.innerHTML = '<p style="color: #949ba4; text-align: center; grid-column: 1 / -1;">No friends yet</p>';
            return;
        }
        
        container.innerHTML = '';
        
        friends.forEach(friend => {
            const friendCard = this.createFriendCard(friend);
            container.appendChild(friendCard);
        });
    }
    
    async loadFollowers() {
        const loading = document.getElementById('followers-loading');
        const content = document.getElementById('followers-content');
        
        loading.style.display = 'flex';
        content.style.display = 'none';
        
        try {
            const response = await fetch(`api/followers.php?action=followers&user_id=${profileUserId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderFollowers(data.followers);
            } else {
                content.innerHTML = '<p style="color: #949ba4; text-align: center;">Failed to load followers</p>';
            }
        } catch (error) {
            console.error('Error loading followers:', error);
            content.innerHTML = '<p style="color: #949ba4; text-align: center;">Error loading followers</p>';
        } finally {
            loading.style.display = 'none';
            content.style.display = 'grid';
        }
    }
    
    renderFollowers(followers) {
        const container = document.getElementById('followers-content');
        
        if (followers.length === 0) {
            container.innerHTML = '<p style="color: #949ba4; text-align: center; grid-column: 1 / -1;">No followers yet</p>';
            return;
        }
        
        container.innerHTML = '';
        
        followers.forEach(follower => {
            const followerCard = this.createFriendCard(follower, 'follower');
            container.appendChild(followerCard);
        });
    }
    
    createFriendCard(user, type = 'friend') {
        const card = document.createElement('div');
        card.className = 'friend-card';
        
        const avatarSrc = user.avatar && user.avatar !== 'default-avatar.png' ? user.avatar : 'assets/images/default-avatar.png';
        const displayName = user.display_name || user.username;
        
        card.innerHTML = `
            <img src="${avatarSrc}" alt="Avatar" class="friend-avatar">
            <div class="friend-name">${displayName}</div>
            <div class="friend-username">@${user.username}</div>
            <div class="friend-actions">
                <button class="friend-btn btn-primary" onclick="window.location.href='profile-enhanced.php?user_id=${user.id}'">
                    View Profile
                </button>
            </div>
        `;
        
        return card;
    }
    
    async loadMutualFriends() {
        try {
            const response = await fetch(`api/friends.php?action=mutual&target_user_id=${profileUserId}`);
            const data = await response.json();
            
            if (data.success && data.mutual_friends.length > 0) {
                this.renderMutualFriends(data.mutual_friends);
                document.getElementById('mutual-friends-card').style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading mutual friends:', error);
        }
    }
    
    renderMutualFriends(mutualFriends) {
        const container = document.getElementById('mutual-friends-list');
        container.innerHTML = '';
        
        mutualFriends.slice(0, 6).forEach(friend => { // Show max 6
            const avatarSrc = friend.avatar && friend.avatar !== 'default-avatar.png' ? friend.avatar : 'assets/images/default-avatar.png';
            
            const friendEl = document.createElement('div');
            friendEl.style.cssText = 'display: flex; align-items: center; gap: 10px; margin-bottom: 10px; cursor: pointer;';
            friendEl.innerHTML = `
                <img src="${avatarSrc}" alt="Avatar" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                <span style="color: #dbdee1; font-size: 0.9rem;">${friend.display_name || friend.username}</span>
            `;
            friendEl.onclick = () => window.location.href = `profile-enhanced.php?user_id=${friend.id}`;
            container.appendChild(friendEl);
        });
        
        if (mutualFriends.length > 6) {
            const moreEl = document.createElement('div');
            moreEl.style.cssText = 'color: #5865f2; font-size: 0.8rem; margin-top: 5px;';
            moreEl.textContent = `+${mutualFriends.length - 6} more mutual friends`;
            container.appendChild(moreEl);
        }
    }
    
    loadPosts() {
        // Placeholder for posts functionality
        const container = document.getElementById('posts-tab');
        container.innerHTML = '<p style="color: #949ba4; text-align: center; padding: 40px;">Posts feature coming soon!</p>';
    }
    
    loadGallery() {
        // Placeholder for gallery functionality
        const container = document.getElementById('gallery-tab');
        container.innerHTML = '<p style="color: #949ba4; text-align: center; padding: 40px;">Gallery feature coming soon!</p>';
    }
    
    applyTheme(theme, accentColor) {
        if (theme === 'light') {
            document.body.style.setProperty('--bg-primary', '#ffffff');
            document.body.style.setProperty('--bg-secondary', '#f8f9fa');
            document.body.style.setProperty('--text-primary', '#000000');
            document.body.style.setProperty('--text-secondary', '#6c757d');
        }
        
        if (accentColor) {
            document.body.style.setProperty('--accent-color', accentColor);
        }
    }
    
    showNotification(message, type = 'info') {
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
}

// Global functions for onclick handlers
function closeEditModal() {
    document.getElementById('edit-profile-modal').style.display = 'none';
}

// Initialize the enhanced profile
const enhancedProfile = new EnhancedProfile();