<?php
// Enhanced notification component with real-time updates, sounds, and popups

// Only include if not already included
if (!defined('NOTIFICATION_COMPONENT_LOADED')) {
    define('NOTIFICATION_COMPONENT_LOADED', true);
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $unread_count = 0;
    $notifications = [];

    if ($user_id && function_exists('getUnreadNotificationCount')) {
        try {
            $unread_count = getUnreadNotificationCount($user_id);
            $notifications = getNotifications($user_id, 10, false);
            
            // Sort notifications to show unread first
            if (is_array($notifications)) {
                usort($notifications, function($a, $b) {
                    if ($a['is_read'] == $b['is_read']) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    }
                    return $a['is_read'] ? 1 : -1;
                });
            }
            
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            $notifications = [];
        }
    }
}
?>

<style>
.notification-container {
    position: relative;
    display: inline-block;
}

.notification-bell {
    background: none;
    border: none;
    cursor: pointer;
    position: relative;
    color: #333;
    font-size: 20px;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.notification-bell:hover {
    background: #f8f9fa;
    color: #e74c3c;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.notification-message {
    color: #666;
    font-size: 14px;
    margin-bottom: 4px;
}

.notification-time {
    color: #999;
    font-size: 12px;
}

.notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.notification-actions button {
    background: none;
    border: 1px solid #ddd;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.notification-actions .mark-read {
    color: #28a745;
    border-color: #28a745;
}

.notification-actions .mark-read:hover {
    background: #28a745;
    color: white;
}

.notification-actions .delete {
    color: #dc3545;
    border-color: #dc3545;
}

.notification-actions .delete:hover {
    background: #dc3545;
    color: white;
}

/* Toast notification styles */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    max-width: 350px;
    animation: slideIn 0.3s ease;
}

.notification-toast.warning {
    background: #ffc107;
    color: #212529;
}

.notification-toast.error {
    background: #dc3545;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-toast .toast-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.notification-toast .toast-message {
    font-size: 14px;
    opacity: 0.9;
}

.notification-toast .toast-close {
    position: absolute;
    top: 8px;
    right: 12px;
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    font-size: 18px;
    opacity: 0.7;
}

.notification-toast .toast-close:hover {
    opacity: 1;
}
</style>

<!-- Notification Bell Icon -->
<div class="notification-container">
    <button id="notification-bell" class="notification-bell" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
        <span class="notification-badge" id="notification-badge">
            <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
        </span>
        <?php endif; ?>
    </button>
    
    <!-- Notification Dropdown -->
    <div id="notification-dropdown" class="notification-dropdown">
        <div style="padding: 16px; border-bottom: 1px solid #f0f0f0; background: #f8f9fa;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; color: #333;">Notifications</h4>
                <div style="display: flex; gap: 8px;">
                    <button id="refresh-notifications" style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Refresh Notifications">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button id="mark-all-read" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        Mark All Read
                    </button>
                </div>
            </div>
        </div>
        
        <div id="notifications-list">
            <?php if (empty($notifications)): ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 8px; opacity: 0.5;"></i>
                    <p style="margin: 0;">No notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                    <div class="notification-time">
                        <i class="fas fa-clock"></i> <?php echo timeAgo($notification['created_at']); ?>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                        <button class="mark-read" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                            <i class="fas fa-check"></i> Mark Read
                        </button>
                        <?php endif; ?>
                        <button class="delete" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Notification sound
function playNotificationSound() {
    try {
        // Create a simple notification sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.2);
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (e) {
        console.log('Could not play notification sound:', e);
    }
}

// Show toast notification
function showToastNotification(title, message, type = 'success') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.notification-toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `notification-toast ${type}`;
    toast.innerHTML = `
        <div class="toast-title">${title}</div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Mark notification as read
function markAsRead(notificationId) {
    fetch('user-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                const markReadBtn = notificationItem.querySelector('.mark-read');
                if (markReadBtn) {
                    markReadBtn.remove();
                }
            }
            
            // Update badge count
            updateNotificationBadge();
            showToastNotification('Success', 'Notification marked as read');
        } else {
            showToastNotification('Error', 'Failed to mark notification as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToastNotification('Error', 'Failed to mark notification as read', 'error');
    });
}

// Delete notification
function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification?')) {
        return;
    }
    
    fetch('user-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove from UI
            const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.remove();
            }
            
            // Update badge count
            updateNotificationBadge();
            showToastNotification('Success', 'Notification deleted');
        } else {
            showToastNotification('Error', 'Failed to delete notification', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToastNotification('Error', 'Failed to delete notification', 'error');
    });
}

// Mark all notifications as read
function markAllAsRead() {
    fetch('user-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const markReadBtn = item.querySelector('.mark-read');
                if (markReadBtn) {
                    markReadBtn.remove();
                }
            });
            
            // Update badge count
            updateNotificationBadge();
            showToastNotification('Success', 'All notifications marked as read');
        } else {
            showToastNotification('Error', 'Failed to mark all notifications as read', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToastNotification('Error', 'Failed to mark all notifications as read', 'error');
    });
}

// Update notification badge
function updateNotificationBadge() {
    fetch('user-notifications.php?action=get_count')
    .then(response => response.json())
    .then(data => {
        const badge = document.getElementById('notification-badge');
        if (data.count > 0) {
            if (badge) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
            } else {
                // Create badge if it doesn't exist
                const bell = document.getElementById('notification-bell');
                const newBadge = document.createElement('span');
                newBadge.id = 'notification-badge';
                newBadge.className = 'notification-badge';
                newBadge.textContent = data.count > 99 ? '99+' : data.count;
                bell.appendChild(newBadge);
            }
        } else {
            if (badge) {
                badge.remove();
            }
        }
    })
    .catch(error => console.error('Error updating badge:', error));
}

// Toggle notification dropdown
document.getElementById('notification-bell').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notification-dropdown');
    const bell = document.getElementById('notification-bell');
    if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// Mark all as read button
document.getElementById('mark-all-read').addEventListener('click', function(e) {
    e.stopPropagation();
    markAllAsRead();
});

// Refresh notifications button
document.getElementById('refresh-notifications').addEventListener('click', function(e) {
    e.stopPropagation();
    loadNotifications();
    updateNotificationBadge();
    showToastNotification('Refreshed', 'Notifications refreshed', 'success');
});

// Check for new notifications every 2 seconds for better real-time experience
setInterval(function() {
    checkForNewNotifications();
}, 2000);

// Also check every 10 seconds with a more thorough check
setInterval(function() {
    loadNotifications();
}, 10000);

// Also check immediately when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkForNewNotifications();
});

// Function to check for new notifications
function checkForNewNotifications() {
    // Add visual indicator that we're checking
    const refreshBtn = document.getElementById('refresh-notifications');
    if (refreshBtn) {
        refreshBtn.style.opacity = '0.6';
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    fetch('user-notifications.php?action=get_count&t=' + Date.now())
    .then(response => response.json())
    .then(data => {
        const currentBadge = document.getElementById('notification-badge');
        const currentCount = currentBadge ? parseInt(currentBadge.textContent) : 0;
        
        if (data.count > currentCount) {
            // New notification received
            console.log('New notification detected! Count:', data.count, 'Previous:', currentCount);
            playNotificationSound();
            showToastNotification('New Notification', 'You have received a new notification', 'success');
            updateNotificationBadge();
            loadNotifications(); // Refresh the notification list
        }
        
        // Reset refresh button
        if (refreshBtn) {
            refreshBtn.style.opacity = '1';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }
    })
    .catch(error => {
        console.error('Error checking notifications:', error);
        // Reset refresh button on error
        if (refreshBtn) {
            refreshBtn.style.opacity = '1';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }
    });
}

// More aggressive checking when user is active
let isUserActive = true;
let checkInterval = 2000; // Start with 2 seconds

// Adjust checking frequency based on user activity
function adjustCheckingFrequency() {
    if (isUserActive) {
        checkInterval = 2000; // Check every 2 seconds when active
    } else {
        checkInterval = 10000; // Check every 10 seconds when inactive
    }
}

// Track user activity
let lastActivity = Date.now();
document.addEventListener('mousemove', () => {
    lastActivity = Date.now();
    isUserActive = true;
    adjustCheckingFrequency();
});

document.addEventListener('keypress', () => {
    lastActivity = Date.now();
    isUserActive = true;
    adjustCheckingFrequency();
});

// Check if user is inactive
setInterval(() => {
    if (Date.now() - lastActivity > 30000) { // 30 seconds of inactivity
        isUserActive = false;
        adjustCheckingFrequency();
    }
}, 5000);

// Check for notifications when user becomes active (switches back to tab)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        checkForNewNotifications();
    }
});

// Check for notifications when user focuses on window
window.addEventListener('focus', function() {
    checkForNewNotifications();
});

// Load notifications (refresh the list)
function loadNotifications() {
    fetch('user-notifications.php?action=get_notifications')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationsList = document.getElementById('notifications-list');
            notificationsList.innerHTML = data.html;
        }
    })
    .catch(error => console.error('Error loading notifications:', error));
}

// Time ago function
function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}
</script>
