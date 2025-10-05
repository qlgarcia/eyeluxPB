<?php
// Safe notification component with real-time updates
// This version has comprehensive error handling to prevent website crashes

// Prevent multiple inclusions
if (defined('SAFE_NOTIFICATION_LOADED')) {
    return;
}
define('SAFE_NOTIFICATION_LOADED', true);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$user_id = null;
$unread_count = 0;
$notifications = [];

// Safely get user ID
try {
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
} catch (Exception $e) {
    error_log("Session error in notification component: " . $e->getMessage());
    $user_id = null;
}

// Safely get notification data
if ($user_id && $user_id > 0) {
    try {
        // Check if functions exist before calling them
        if (function_exists('getUnreadNotificationCount') && function_exists('getNotifications')) {
            $unread_count = @getUnreadNotificationCount($user_id);
            $notifications = @getNotifications($user_id, 10, false);
            
            // Ensure we have valid data
            if (!is_numeric($unread_count)) {
                $unread_count = 0;
            }
            if (!is_array($notifications)) {
                $notifications = [];
            }
            
            // Sort notifications safely
            if (!empty($notifications)) {
                @usort($notifications, function($a, $b) {
                    try {
                        if (!isset($a['is_read']) || !isset($b['is_read'])) {
                            return 0;
                        }
                        if ($a['is_read'] == $b['is_read']) {
                            $time_a = isset($a['created_at']) ? @strtotime($a['created_at']) : 0;
                            $time_b = isset($b['created_at']) ? @strtotime($b['created_at']) : 0;
                            return $time_b - $time_a;
                        }
                        return $a['is_read'] ? 1 : -1;
                    } catch (Exception $e) {
                        return 0;
                    }
                });
            }
        }
    } catch (Exception $e) {
        error_log("Notification data error: " . $e->getMessage());
        $unread_count = 0;
        $notifications = [];
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
                <div class="notification-item <?php echo isset($notification['is_read']) && !$notification['is_read'] ? 'unread' : ''; ?>" data-id="<?php echo isset($notification['id']) ? $notification['id'] : ''; ?>">
                    <div class="notification-title"><?php echo isset($notification['title']) ? htmlspecialchars($notification['title']) : 'Notification'; ?></div>
                    <div class="notification-message"><?php echo isset($notification['message']) ? htmlspecialchars($notification['message']) : ''; ?></div>
                    <div class="notification-time">
                        <i class="fas fa-clock"></i> <?php echo isset($notification['created_at']) ? timeAgo($notification['created_at']) : 'Unknown time'; ?>
                    </div>
                    <div class="notification-actions">
                        <?php if (isset($notification['is_read']) && !$notification['is_read']): ?>
                        <button class="mark-read" onclick="markAsRead(<?php echo isset($notification['id']) ? $notification['id'] : 0; ?>)">
                            <i class="fas fa-check"></i> Mark Read
                        </button>
                        <?php endif; ?>
                        <button class="delete" onclick="deleteNotification(<?php echo isset($notification['id']) ? $notification['id'] : 0; ?>)">
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
// Safe notification functions with comprehensive error handling
(function() {
    'use strict';
    
    // Check if required elements exist
    if (!document.getElementById('notification-bell')) {
        console.warn('Notification bell not found');
        return;
    }
    
    // Safe notification sound
    function playNotificationSound() {
        try {
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
    
    // Safe toast notification
    function showToastNotification(title, message, type = 'success') {
        try {
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
        } catch (e) {
            console.error('Error showing toast:', e);
        }
    }
    
    // Safe AJAX request
    function safeAjax(url, data, callback) {
        try {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(callback)
            .catch(error => {
                console.error('AJAX error:', error);
                showToastNotification('Error', 'Failed to process request', 'error');
            });
        } catch (e) {
            console.error('AJAX setup error:', e);
            showToastNotification('Error', 'Failed to process request', 'error');
        }
    }
    
    // Mark notification as read
    window.markAsRead = function(notificationId) {
        if (!notificationId || notificationId <= 0) {
            showToastNotification('Error', 'Invalid notification ID', 'error');
            return;
        }
        
        safeAjax('user-notifications.php', `action=mark_read&notification_id=${notificationId}`, function(data) {
            if (data && data.success) {
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
        });
    };
    
    // Delete notification
    window.deleteNotification = function(notificationId) {
        if (!notificationId || notificationId <= 0) {
            showToastNotification('Error', 'Invalid notification ID', 'error');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this notification?')) {
            return;
        }
        
        safeAjax('user-notifications.php', `action=delete&notification_id=${notificationId}`, function(data) {
            if (data && data.success) {
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
        });
    };
    
    // Mark all notifications as read
    function markAllAsRead() {
        safeAjax('user-notifications.php', 'action=mark_all_read', function(data) {
            if (data && data.success) {
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
        });
    }
    
    // Update notification badge
    function updateNotificationBadge() {
        safeAjax('user-notifications.php?action=get_count', '', function(data) {
            if (data && typeof data.count !== 'undefined') {
                const badge = document.getElementById('notification-badge');
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                    } else {
                        // Create badge if it doesn't exist
                        const bell = document.getElementById('notification-bell');
                        if (bell) {
                            const newBadge = document.createElement('span');
                            newBadge.id = 'notification-badge';
                            newBadge.className = 'notification-badge';
                            newBadge.textContent = data.count > 99 ? '99+' : data.count;
                            bell.appendChild(newBadge);
                        }
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            }
        });
    }
    
    // Load notifications
    function loadNotifications() {
        safeAjax('user-notifications.php?action=get_notifications', '', function(data) {
            if (data && data.success && data.html) {
                const notificationsList = document.getElementById('notifications-list');
                if (notificationsList) {
                    notificationsList.innerHTML = data.html;
                }
            }
        });
    }
    
    // Check for new notifications
    function checkForNewNotifications() {
        safeAjax('user-notifications.php?action=get_count', '', function(data) {
            if (data && typeof data.count !== 'undefined') {
                const currentBadge = document.getElementById('notification-badge');
                const currentCount = currentBadge ? parseInt(currentBadge.textContent) || 0 : 0;
                
                if (data.count > currentCount) {
                    // New notification received
                    console.log('New notification detected! Count:', data.count, 'Previous:', currentCount);
                    playNotificationSound();
                    showToastNotification('New Notification', 'You have received a new notification', 'success');
                    updateNotificationBadge();
                    loadNotifications();
                }
            }
        });
    }
    
    // Event listeners
    try {
        // Toggle notification dropdown
        const bell = document.getElementById('notification-bell');
        if (bell) {
            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = document.getElementById('notification-dropdown');
                if (dropdown) {
                    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                }
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notification-dropdown');
            const bell = document.getElementById('notification-bell');
            if (dropdown && bell && !bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Mark all as read button
        const markAllBtn = document.getElementById('mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                markAllAsRead();
            });
        }
        
        // Refresh notifications button
        const refreshBtn = document.getElementById('refresh-notifications');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                loadNotifications();
                updateNotificationBadge();
                showToastNotification('Refreshed', 'Notifications refreshed', 'success');
            });
        }
        
        // Check for notifications every 5 seconds
        setInterval(checkForNewNotifications, 5000);
        
        // Check when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                checkForNewNotifications();
            }
        });
        
        // Check when window gains focus
        window.addEventListener('focus', checkForNewNotifications);
        
    } catch (e) {
        console.error('Error setting up notification event listeners:', e);
    }
    
    // Time ago function
    window.timeAgo = function(dateString) {
        try {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
            return Math.floor(diffInSeconds / 86400) + ' days ago';
        } catch (e) {
            return 'Unknown time';
        }
    };
    
})();
</script>






