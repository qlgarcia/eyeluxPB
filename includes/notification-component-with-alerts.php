<?php
// Enhanced notification component with alerts
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$unread_count = 0;
$notifications = [];

if ($user_id) {
    try {
        $unread_count = getUnreadNotificationCount($user_id);
        $notifications = getNotifications($user_id, 10, false); // Get 10 notifications (both read and unread)
        
        // Sort notifications to show unread first
        usort($notifications, function($a, $b) {
            if ($a['is_read'] == $b['is_read']) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            }
            return $a['is_read'] ? 1 : -1;
        });
        
        // Basic filtering - only remove truly problematic entries
        $notifications = array_filter($notifications, function($notification) {
            return !empty($notification['id']) && 
                   !empty($notification['title']) && 
                   !empty($notification['message']);
        });
        
    } catch (Exception $e) {
        // Silently handle errors
        error_log("Notification error: " . $e->getMessage());
        $notifications = [];
    }
}
?>

<style>
.notification-item:hover {
    background-color: #f8f9fa !important;
}

/* Toast notification styles */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 15px;
    max-width: 350px;
    z-index: 10000;
    transform: translateX(100%);
    transition: transform 0.3s ease-in-out;
    cursor: pointer;
}

.notification-toast.show {
    transform: translateX(0);
}

.notification-toast-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.notification-toast-icon {
    font-size: 20px;
    margin-right: 10px;
}

.notification-toast-title {
    font-weight: 600;
    color: #333;
    flex: 1;
}

.notification-toast-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-toast-message {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 8px;
}

.notification-toast-time {
    color: #999;
    font-size: 12px;
}

/* Notification sound indicator */
.notification-sound-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 10001;
    display: none;
}

.notification-sound-indicator.show {
    display: block;
    animation: fadeInOut 2s ease-in-out;
}

@keyframes fadeInOut {
    0% { opacity: 0; }
    20% { opacity: 1; }
    80% { opacity: 1; }
    100% { opacity: 0; }
}

/* Bell animation for new notifications */
.notification-bell.new-notification {
    animation: bellShake 0.5s ease-in-out;
}

@keyframes bellShake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
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
                <p style="margin: 0;">No notifications</p>
                <?php if ($unread_count > 0): ?>
                <p style="margin: 5px 0; font-size: 12px; color: #999;">(But you have <?php echo $unread_count; ?> unread notifications)</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <?php if (!empty($notification['id']) && !empty($notification['title']) && !empty($notification['message'])): ?>
                <div class="notification-item" data-id="<?php echo htmlspecialchars($notification['id']); ?>" style="padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background-color 0.2s; <?php 
                    if (!($notification['is_read'] ?? false)) {
                        if (($notification['type'] ?? '') === 'order_status') {
                            echo 'background: #d4edda; border-left: 4px solid #28a745;';
                        } else {
                            echo 'background: #f8f9fa; border-left: 3px solid #007bff;';
                        }
                    }
                ?>">
                    <div style="display: flex; align-items: flex-start;">
                        <div class="notification-icon" style="margin-right: 10px; margin-top: 2px;">
                            <?php
                            $icon = '🔔'; // Default icon
                            $iconColor = '#007bff';
                            switch ($notification['type'] ?? '') {
                                case 'order_status':
                                    $icon = '📦';
                                    $iconColor = '#28a745';
                                    break;
                                case 'promotion':
                                    $icon = '🎉';
                                    $iconColor = '#ffc107';
                                    break;
                                case 'system':
                                    $icon = '⚙️';
                                    $iconColor = '#6c757d';
                                    break;
                                default:
                                    $icon = '🔔';
                                    $iconColor = '#007bff';
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

<!-- Toast notification container -->
<div id="notification-toast-container"></div>

<!-- Sound indicator -->
<div id="notification-sound-indicator" class="notification-sound-indicator">
    🔊 New notification!
</div>

<script>
// Notification alert system
class NotificationAlerts {
    constructor() {
        this.lastNotificationCount = <?php echo $unread_count; ?>;
        this.browserNotificationPermission = 'default';
        this.soundEnabled = true;
        this.toastEnabled = true;
        
        this.init();
    }
    
    init() {
        // Request browser notification permission
        this.requestNotificationPermission();
        
        // Set up periodic checking for new notifications
        this.startPeriodicCheck();
        
        // Set up bell click handler
        this.setupBellHandler();
        
        // Set up notification actions
        this.setupNotificationActions();
    }
    
    async requestNotificationPermission() {
        if ('Notification' in window) {
            try {
                this.browserNotificationPermission = await Notification.requestPermission();
                console.log('Notification permission:', this.browserNotificationPermission);
            } catch (error) {
                console.error('Error requesting notification permission:', error);
            }
        }
    }
    
    startPeriodicCheck() {
        // Check for new notifications every 10 seconds
        setInterval(() => {
            this.checkForNewNotifications();
        }, 10000);
    }
    
    async checkForNewNotifications() {
        try {
            const response = await fetch('ajax-notifications.php?action=get_count');
            const data = await response.json();
            
            if (data.success) {
                const currentCount = data.count;
                
                if (currentCount > this.lastNotificationCount) {
                    // New notifications detected!
                    this.handleNewNotifications(currentCount);
                }
                
                this.lastNotificationCount = currentCount;
                this.updateNotificationBadge(currentCount);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    handleNewNotifications(count) {
        const newCount = count - this.lastNotificationCount;
        
        // Show browser notification
        if (this.browserNotificationPermission === 'granted') {
            this.showBrowserNotification(newCount);
        }
        
        // Show toast notification
        if (this.toastEnabled) {
            this.showToastNotification(newCount);
        }
        
        // Play sound
        if (this.soundEnabled) {
            this.playNotificationSound();
        }
        
        // Animate bell
        this.animateBell();
        
        // Show sound indicator
        this.showSoundIndicator();
    }
    
    showBrowserNotification(count) {
        if ('Notification' in window && this.browserNotificationPermission === 'granted') {
            const notification = new Notification('🔔 New Order Update!', {
                body: `You have ${count} new notification${count > 1 ? 's' : ''} - Check your order status!`,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                tag: 'eyewear-notification',
                requireInteraction: true
            });
            
            notification.onclick = () => {
                window.focus();
                this.toggleDropdown();
                notification.close();
            };
            
            // Auto-close after 8 seconds (longer for order notifications)
            setTimeout(() => {
                notification.close();
            }, 8000);
        }
    }
    
    showToastNotification(count) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="notification-toast-header">
                <div class="notification-toast-icon">📦</div>
                <div class="notification-toast-title">Order Update!</div>
                <button class="notification-toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="notification-toast-message">You have ${count} new notification${count > 1 ? 's' : ''} - Check your order status!</div>
            <div class="notification-toast-time">Just now</div>
        `;
        
        toast.onclick = () => {
            this.toggleDropdown();
            toast.remove();
        };
        
        document.getElementById('notification-toast-container').appendChild(toast);
        
        // Show toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto-remove after 6 seconds (longer for order notifications)
        setTimeout(() => {
            toast.remove();
        }, 6000);
    }
    
    playNotificationSound() {
        // Create audio context for notification sound
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
        } catch (error) {
            console.log('Audio not supported or blocked');
        }
    }
    
    animateBell() {
        const bell = document.getElementById('notification-bell');
        bell.classList.add('new-notification');
        
        setTimeout(() => {
            bell.classList.remove('new-notification');
        }, 500);
    }
    
    showSoundIndicator() {
        const indicator = document.getElementById('notification-sound-indicator');
        indicator.classList.add('show');
        
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
    
    setupBellHandler() {
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        
        if (bell) {
            bell.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (bell && dropdown && !bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }
    
    toggleDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    setupNotificationActions() {
        // Mark individual notification as read
        document.querySelectorAll('.notification-item').forEach((item) => {
            item.addEventListener('click', () => {
                const notificationId = item.dataset.id;
                if (notificationId) {
                    this.markNotificationAsRead(notificationId);
                }
            });
        });
        
        // Mark all notifications as read
        const markAllRead = document.getElementById('mark-all-read');
        if (markAllRead) {
            markAllRead.addEventListener('click', (e) => {
                e.stopPropagation();
                this.markAllNotificationsAsRead();
            });
        }
    }
    
    async markNotificationAsRead(notificationId) {
        try {
            const response = await fetch('ajax-notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${notificationId}`
            });
            
            const data = await response.json();
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
                this.updateNotificationBadge();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllNotificationsAsRead() {
        try {
            const response = await fetch('ajax-notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            });
            
            const data = await response.json();
            if (data.success) {
                // Remove all unread indicators
                document.querySelectorAll('.unread-indicator').forEach((indicator) => {
                    indicator.remove();
                });
                
                // Update badge count
                this.updateNotificationBadge();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }
    
    updateNotificationBadge(count) {
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
}

// Initialize notification alerts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new NotificationAlerts();
});
</script>
