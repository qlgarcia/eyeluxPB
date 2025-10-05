<?php
// User notifications page
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Helper function for time ago
if (!function_exists('timeAgo')) {
    function timeAgo($dateString) {
        try {
            $now = new DateTime();
            $date = new DateTime($dateString);
            $diff = $now->diff($date);
            
            if ($diff->days > 0) {
                return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
            } elseif ($diff->h > 0) {
                return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            } elseif ($diff->i > 0) {
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            } else {
                return 'Just now';
            }
        } catch (Exception $e) {
            return 'Just now';
        }
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$db = Database::getInstance();

// Handle AJAX requests
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? $_GET['action'];
        switch ($action) {
            case 'mark_read':
                $notification_id = (int)($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    $result = markNotificationAsRead($notification_id, $user_id);
                    echo json_encode(['success' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                }
                break;
                
            case 'mark_all_read':
                try {
                    $result = markAllNotificationsAsRead($user_id);
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update notifications in database']);
                    }
                } catch (Exception $e) {
                    error_log("Mark all read error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                break;
                
            case 'delete':
                $notification_id = (int)($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    $result = $db->execute(
                        "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?",
                        [$notification_id, $user_id]
                    );
                    echo json_encode(['success' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                }
                break;
                
            case 'get_count':
                $unread_count = getUnreadNotificationCount($user_id);
                echo json_encode(['count' => $unread_count]);
                break;
                
            case 'get_notifications':
                $notifications = getNotifications($user_id, 10, false);
                $html = '';
                
                if (empty($notifications)) {
                    $html = '<div style="padding: 20px; text-align: center; color: #666;"><i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 8px; opacity: 0.5;"></i><p style="margin: 0;">No notifications</p></div>';
                } else {
                    foreach ($notifications as $notification) {
                        $unreadClass = $notification['is_read'] ? '' : 'unread';
                        $markReadBtn = $notification['is_read'] ? '' : '<button class="mark-read" onclick="markAsRead(' . $notification['id'] . ')"><i class="fas fa-check"></i> Mark Read</button>';
                        
                        $html .= '<div class="notification-item ' . $unreadClass . '" data-id="' . $notification['id'] . '">';
                        $html .= '<div class="notification-title">' . htmlspecialchars($notification['title']) . '</div>';
                        $html .= '<div class="notification-message">' . htmlspecialchars($notification['message']) . '</div>';
                        $html .= '<div class="notification-time"><i class="fas fa-clock"></i> ' . timeAgo($notification['created_at']) . '</div>';
                        $html .= '<div class="notification-actions">' . $markReadBtn . '<button class="delete" onclick="deleteNotification(' . $notification['id'] . ')"><i class="fas fa-trash"></i> Delete</button></div>';
                        $html .= '</div>';
                    }
                }
                
                echo json_encode(['success' => true, 'html' => $html]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// Get notifications for the user
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Force refresh to avoid caching issues
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Use the simple query that works - user_id is now properly cast to int
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT notification_id as id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_notifications = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?",
        [$user_id]
    )['count'];
    
    $unread_count = getUnreadNotificationCount($user_id);
    
} catch (Exception $e) {
    $notifications = [];
    $total_notifications = 0;
    $unread_count = 0;
    error_log("Error fetching notifications: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container" style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <div class="notifications-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="margin: 0; color: #333;">Notifications</h1>
        <div class="notification-actions">
            <?php if ($unread_count > 0): ?>
            <button id="mark-all-read" class="btn btn-primary" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                Mark All Read
            </button>
            <?php endif; ?>
            <span class="notification-count" style="color: #666; font-size: 14px;">
                <?php echo $unread_count; ?> unread of <?php echo $total_notifications; ?> total
            </span>
        </div>
    </div>
    
    <?php if (count($notifications) == 0): ?>
    <div class="no-notifications" style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 8px;">
        <div style="font-size: 48px; margin-bottom: 20px;">🔔</div>
        <h3 style="color: #666; margin-bottom: 10px;">No notifications yet</h3>
        <p style="color: #999; margin: 0;">You'll see order updates, promotions, and other important updates here.</p>
        
    </div>
    <?php else: ?>
    <div class="notifications-list">
        <?php foreach ($notifications as $notification): ?>
                <div class="notification-item" data-id="<?php echo $notification['id']; ?>" style="background: white; border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; padding: 20px; transition: all 0.2s; <?php echo !$notification['is_read'] ? 'border-left: 4px solid #007bff;' : ''; ?>">
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <div class="notification-icon" style="margin-right: 12px;">
                            <?php
                            $icon = '🔔';
                            $iconColor = '#007bff';
                            switch ($notification['type']) {
                                case 'order_status':
                                    $icon = '📦';
                                    $iconColor = '#28a745';
                                    break;
                                case 'warning':
                                    $icon = '⚠️';
                                    $iconColor = '#ffc107';
                                    break;
                                case 'ban':
                                    $icon = '🚫';
                                    $iconColor = '#dc3545';
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
                            <span style="font-size: 24px;"><?php echo $icon; ?></span>
                        </div>
                        
                        <div style="flex: 1;">
                            <h3 style="margin: 0; color: #333; font-size: 16px; font-weight: 600;">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </h3>
                            <p style="margin: 8px 0 0 0; color: #666; line-height: 1.5;">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: #999; font-size: 14px;">
                                    <?php echo timeAgo($notification['created_at']); ?>
                                </span>
                                
                                <div class="notification-actions" style="display: flex; gap: 10px;">
                                    <?php if (!$notification['is_read']): ?>
                                            <button onclick="markAsRead(<?php echo $notification['id']; ?>)" class="btn btn-sm btn-outline-primary" style="background: none; border: 1px solid #007bff; color: #007bff; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                Mark Read
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // Show review button for delivered orders
                                            if ($notification['type'] == 'order_status' && 
                                                (strpos($notification['title'], 'Delivered') !== false || 
                                                 strpos($notification['message'], 'delivered successfully') !== false)) {
                                                
                                                // Try to extract order number from message
                                                $order_id = null;
                                                if (preg_match('/order #(\d+)/i', $notification['message'], $matches)) {
                                                    $order_number = $matches[1];
                                                    // Get order ID from order number
                                                    try {
                                                        $db = Database::getInstance();
                                                        $order = $db->fetchOne("SELECT order_id FROM orders WHERE order_number = ?", [$order_number]);
                                                        $order_id = $order['order_id'] ?? null;
                                                    } catch (Exception $e) {
                                                        $order_id = null;
                                                    }
                                                }
                                                
                                                if ($order_id): ?>
                                                    <button onclick="goToReviews(<?php echo $order_id; ?>)" class="btn btn-sm btn-success" style="background: #28a745; border: 1px solid #28a745; color: white; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                        ⭐ Write Review
                                                    </button>
                                                <?php endif;
                                            } ?>
                                            
                                            <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" class="btn btn-sm btn-outline-danger" style="background: none; border: 1px solid #dc3545; color: #dc3545; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                                Delete
                                            </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!$notification['is_read']): ?>
                <div class="unread-indicator" style="width: 8px; height: 8px; background: #007bff; border-radius: 50%; margin-left: 15px; margin-top: 8px;"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_notifications > $limit): ?>
    <div class="pagination" style="text-align: center; margin-top: 30px;">
        <?php
        $total_pages = ceil($total_notifications / $limit);
        $current_page = $page;
        
        if ($current_page > 1): ?>
        <a href="?page=<?php echo $current_page - 1; ?>" style="display: inline-block; padding: 10px 15px; margin: 0 5px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px;">← Previous</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
        <a href="?page=<?php echo $i; ?>" style="display: inline-block; padding: 10px 15px; margin: 0 2px; background: <?php echo $i == $current_page ? '#007bff' : '#f8f9fa'; ?>; color: <?php echo $i == $current_page ? 'white' : '#333'; ?>; text-decoration: none; border-radius: 5px;"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($current_page < $total_pages): ?>
        <a href="?page=<?php echo $current_page + 1; ?>" style="display: inline-block; padding: 10px 15px; margin: 0 5px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 5px;">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function markAsRead(notificationId) {
    fetch('user-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_read&notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to mark notification as read');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking notification as read');
    });
}

function goToReviews(orderId) {
    // Redirect to the order details page where they can write reviews
    window.location.href = 'order-details.php?order_id=' + orderId;
    
    // Add a small delay and scroll to reviews section if it exists
    setTimeout(() => {
        const reviewsSection = document.querySelector('.order-items-section');
        if (reviewsSection) {
            reviewsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }, 500);
}

function deleteNotification(notificationId) {
    if (confirm('Are you sure you want to delete this notification?')) {
        fetch('user-notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete notification');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting notification');
        });
    }
}

document.getElementById('mark-all-read')?.addEventListener('click', function() {
    const button = this;
    const originalText = button.textContent;
    
    // Show loading state
    button.textContent = 'Marking...';
    button.disabled = true;
    
    fetch('user-notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Success - reload page to show updated state
            location.reload();
        } else {
            // Show specific error message if available
            const errorMsg = data.message || 'Failed to mark all notifications as read';
            alert(errorMsg);
            console.error('Mark all read failed:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
    })
    .finally(() => {
        // Restore button state
        button.textContent = originalText;
        button.disabled = false;
    });
});
</script>

<style>
.notification-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.btn {
    transition: all 0.2s;
}

.btn:hover {
    opacity: 0.8;
}

.container {
    font-family: Arial, sans-serif;
}
</style>

<?php
include 'includes/footer.php';
?>
