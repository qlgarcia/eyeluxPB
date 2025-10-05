<?php
// Ultra-clean notification display component
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress any potential output
ob_start();

$user_id = $_SESSION['user_id'] ?? null;
$unread_count = 0;
$notifications = [];

if ($user_id) {
    try {
        $unread_count = getUnreadNotificationCount($user_id);
        $notifications = getNotifications($user_id, 5, true); // Get 5 unread notifications
        
        // Clean notifications array - remove any problematic entries
        $notifications = array_filter($notifications, function($notification) {
            return !empty($notification['id']) && 
                   !empty($notification['title']) && 
                   !empty($notification['message']) &&
                   !str_contains($notification['message'], 'C:\\laragon') &&
                   !str_contains($notification['message'], 'style=') &&
                   !str_contains($notification['message'], 'onmouseover');
        });
        
    } catch (Exception $e) {
        // Silently handle errors
        error_log("Notification error: " . $e->getMessage());
        $notifications = [];
    }
}

// Clear any output buffer
ob_end_clean();
?>

<style>
.notification-item:hover {
    background-color: #f8f9fa !important;
}
</style>

<!-- Notification Bell Icon -->
<div class="notification-container" style="position: relative; display: inline-block;">
    <button id="notification-bell" class="notification-bell" style="background: none; border: none; cursor: pointer; position: relative;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
        </svg>
        
        <?php if ($unread_count > 0): ?>
        <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold;">
            <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
        </span>
        <?php endif; ?>
    </button>
    
    <!-- Notification Dropdown -->
    <div id="notification-dropdown" class="notification-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 350px; max-height: 400px; overflow-y: auto; z-index: 1000;">
        <div class="notification-header" style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; color: #333;">Notifications</h3>
            <?php if ($unread_count > 0): ?>
            <button id="mark-all-read" style="background: none; border: none; color: #007bff; cursor: pointer; font-size: 14px;">Mark all read</button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
            <div class="no-notifications" style="padding: 20px; text-align: center; color: #666;">
                <p style="margin: 0;">No new notifications</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <?php if (!empty($notification['id']) && !empty($notification['title']) && !empty($notification['message'])): ?>
                <div class="notification-item" data-id="<?php echo htmlspecialchars($notification['id']); ?>" style="padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background-color 0.2s;">
                    <div style="display: flex; align-items: flex-start;">
                        <div class="notification-icon" style="margin-right: 10px; margin-top: 2px;">
                            <?php
                            $icon = '🔔'; // Default icon
                            switch ($notification['type'] ?? '') {
                                case 'order_status':
                                    $icon = '📦';
                                    break;
                                case 'promotion':
                                    $icon = '🎉';
                                    break;
                                case 'system':
                                    $icon = '⚙️';
                                    break;
                                default:
                                    $icon = '🔔';
                            }
                            ?>
                            <span style="font-size: 18px;"><?php echo $icon; ?></span>
                        </div>
                        
                        <div class="notification-content" style="flex: 1;">
                            <div class="notification-title" style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-message" style="color: #666; font-size: 14px; line-height: 1.4;">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-time" style="color: #999; font-size: 12px; margin-top: 4px;">
                                <?php 
                                // Use a simple time display to avoid function conflicts
                                $time = $notification['created_at'] ?? date('Y-m-d H:i:s');
                                $now = new DateTime();
                                $date = new DateTime($time);
                                $diff = $now->diff($date);
                                
                                if ($diff->days > 0) {
                                    echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                } elseif ($diff->h > 0) {
                                    echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                } elseif ($diff->i > 0) {
                                    echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                } else {
                                    echo 'Just now';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!($notification['is_read'] ?? false)): ?>
                        <div class="unread-indicator" style="width: 8px; height: 8px; background: #e74c3c; border-radius: 50%; margin-left: 10px; margin-top: 8px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer" style="padding: 15px; border-top: 1px solid #eee; text-align: center;">
            <a href="user-notifications.php" style="color: #007bff; text-decoration: none; font-size: 14px;">View all notifications</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    const markAllRead = document.getElementById('mark-all-read');
    
    // Toggle dropdown
    if (bell) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (bell && dropdown && !bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    // Mark individual notification as read
    document.querySelectorAll('.notification-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            if (notificationId) {
                markNotificationAsRead(notificationId);
            }
        });
    });
    
    // Mark all notifications as read
    if (markAllRead) {
        markAllRead.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        refreshNotifications();
    }, 30000);
});

function markNotificationAsRead(notificationId) {
    fetch('ajax-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_read&notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread indicator
            const item = document.querySelector(`[data-id="${notificationId}"]`);
            if (item) {
                const indicator = item.querySelector('.unread-indicator');
                if (indicator) {
                    indicator.remove();
                }
            }
            
            // Update badge count
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('ajax-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove all unread indicators
            document.querySelectorAll('.unread-indicator').forEach(function(indicator) {
                indicator.remove();
            });
            
            // Update badge count
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function refreshNotifications() {
    fetch('ajax-notifications.php?action=get_count')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationBadge(data.count);
        }
    })
    .catch(error => {
        console.error('Error refreshing notifications:', error);
    });
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (count === 0) {
        if (badge) {
            badge.remove();
        }
    } else {
        if (!badge) {
            const bell = document.getElementById('notification-bell');
            if (bell) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge';
                newBadge.style.cssText = 'position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold;';
                bell.appendChild(newBadge);
            }
        }
        const badgeElement = document.querySelector('.notification-badge');
        if (badgeElement) {
            badgeElement.textContent = count > 99 ? '99+' : count;
        }
    }
}

function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    } else {
        const days = Math.floor(diffInSeconds / 86400);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    }
}
</script>
