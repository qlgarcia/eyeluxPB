<?php
// Notification Management Module for Admin Panel
require_once 'includes/config.php';
require_once 'includes/database.php';

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-complete.php');
    exit;
}

$db = Database::getInstance();

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'send_notification':
                $notification_type = $_POST['notification_type'];
                $title = $_POST['title'];
                $message = $_POST['message'];
                $recipient_type = $_POST['recipient_type'];
                $recipient_id = $_POST['recipient_id'] ?? null;
                
                $result = $db->insert(
                    "INSERT INTO notifications (notification_type, title, message, recipient_type, recipient_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$notification_type, $title, $message, $recipient_type, $recipient_id]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'create', 'notifications', ?, ?)",
                        [$_SESSION['admin_id'], $result, json_encode(['title' => $title, 'recipient_type' => $recipient_type])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
                }
                break;
                
            case 'mark_as_read':
                $notification_id = intval($_POST['notification_id']);
                
                $result = $db->query(
                    "UPDATE notifications SET is_read = 1 WHERE notification_id = ?",
                    [$notification_id]
                );
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
                }
                break;
                
            case 'delete_notification':
                $notification_id = intval($_POST['notification_id']);
                
                $result = $db->query("DELETE FROM notifications WHERE notification_id = ?", [$notification_id]);
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id) VALUES (?, 'delete', 'notifications', ?)",
                        [$_SESSION['admin_id'], $notification_id]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
                }
                break;
                
            case 'get_notifications':
                $limit = intval($_POST['limit'] ?? 50);
                $offset = intval($_POST['offset'] ?? 0);
                
                $notifications = $db->fetchAll("
                    SELECT * FROM notifications 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?
                ", [$limit, $offset]);
                
                echo json_encode(['success' => true, 'data' => $notifications]);
                break;
                
            case 'create_email_template':
                $template_name = $_POST['template_name'];
                $template_subject = $_POST['template_subject'];
                $template_body = $_POST['template_body'];
                $template_variables = $_POST['template_variables'] ?? '';
                
                $result = $db->insert(
                    "INSERT INTO email_templates (template_name, template_subject, template_body, template_variables, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$template_name, $template_subject, $template_body, $template_variables]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'create', 'email_templates', ?, ?)",
                        [$_SESSION['admin_id'], $result, json_encode(['template_name' => $template_name])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Email template created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create email template']);
                }
                break;
                
            case 'update_email_template':
                $template_id = intval($_POST['template_id']);
                $template_name = $_POST['template_name'];
                $template_subject = $_POST['template_subject'];
                $template_body = $_POST['template_body'];
                $template_variables = $_POST['template_variables'] ?? '';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $result = $db->query(
                    "UPDATE email_templates SET template_name = ?, template_subject = ?, template_body = ?, template_variables = ?, is_active = ?, updated_at = NOW() WHERE template_id = ?",
                    [$template_name, $template_subject, $template_body, $template_variables, $is_active, $template_id]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'email_templates', ?, ?)",
                        [$_SESSION['admin_id'], $template_id, json_encode(['template_name' => $template_name, 'is_active' => $is_active])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Email template updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update email template']);
                }
                break;
                
            case 'delete_email_template':
                $template_id = intval($_POST['template_id']);
                
                $result = $db->query("DELETE FROM email_templates WHERE template_id = ?", [$template_id]);
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id) VALUES (?, 'delete', 'email_templates', ?)",
                        [$_SESSION['admin_id'], $template_id]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Email template deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete email template']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get notification data
$notifications = $db->fetchAll("
    SELECT * FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 20
");

$email_templates = $db->fetchAll("SELECT * FROM email_templates ORDER BY created_at DESC");

// Get users for recipient selection
$users = $db->fetchAll("SELECT user_id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY first_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management - EyeLux Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        .back-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .back-btn:hover {
            background: #5a6fd8;
        }
        .notification-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .notification-tab {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #e9ecef;
            color: #495057;
        }
        .notification-tab.active {
            background: #667eea;
            color: white;
        }
        .notification-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin: 2px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-read {
            background: #d4edda;
            color: #155724;
        }
        .status-unread {
            background: #fff3cd;
            color: #856404;
        }
        .notification-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .notification-item.unread {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        .notification-item h4 {
            margin-bottom: 5px;
            color: #333;
        }
        .notification-item p {
            color: #666;
            margin-bottom: 10px;
        }
        .notification-meta {
            font-size: 0.8rem;
            color: #999;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .modal-body {
            padding: 20px;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 3000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            background: #28a745;
        }
        .notification.error {
            background: #dc3545;
        }
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .template-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .template-item h4 {
            margin-bottom: 10px;
            color: #333;
        }
        .template-item p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .template-controls {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-bell"></i> Notification Management</h1>
            <a href="admin.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>
        
        <!-- Notification Tabs -->
        <div class="notification-tabs">
            <button class="notification-tab active" onclick="showNotificationTab('notifications')">
                <i class="fas fa-bell"></i> Notifications
            </button>
            <button class="notification-tab" onclick="showNotificationTab('send')">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
            <button class="notification-tab" onclick="showNotificationTab('templates')">
                <i class="fas fa-envelope"></i> Email Templates
            </button>
        </div>
        
        <!-- Notifications List -->
        <div id="notifications-content" class="notification-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                <button class="btn btn-primary" onclick="loadNotifications()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
            
            <div id="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                    <h4><?= htmlspecialchars($notification['title']) ?></h4>
                    <p><?= htmlspecialchars($notification['message']) ?></p>
                    <div class="notification-meta">
                        <span>
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($notification['notification_type']) ?> |
                            <i class="fas fa-user"></i> <?= htmlspecialchars($notification['recipient_type']) ?> |
                            <i class="fas fa-clock"></i> <?= date('M j, Y H:i', strtotime($notification['created_at'])) ?>
                        </span>
                        <div>
                            <?php if (!$notification['is_read']): ?>
                                <button class="btn btn-info" onclick="markAsRead(<?= $notification['notification_id'] ?>)">
                                    <i class="fas fa-check"></i> Mark Read
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger" onclick="deleteNotification(<?= $notification['notification_id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Send Notification -->
        <div id="send-content" class="notification-content" style="display: none;">
            <h3><i class="fas fa-paper-plane"></i> Send Notification</h3>
            <p style="color: #666; margin-bottom: 20px;">Send notifications to users or administrators.</p>
            
            <form id="send-notification-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="notification_type">Notification Type *</label>
                        <select id="notification_type" name="notification_type" required>
                            <option value="">Select Type</option>
                            <option value="order">Order</option>
                            <option value="inventory">Inventory</option>
                            <option value="user">User</option>
                            <option value="system">System</option>
                            <option value="promotion">Promotion</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipient_type">Recipient Type *</label>
                        <select id="recipient_type" name="recipient_type" required onchange="toggleRecipientSelection()">
                            <option value="">Select Recipient</option>
                            <option value="all">All Users</option>
                            <option value="admin">All Admins</option>
                            <option value="user">Specific User</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="user-selection" style="display: none;">
                    <label for="recipient_id">Select User</label>
                    <select id="recipient_id" name="recipient_id">
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['user_id'] ?>">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-primary" onclick="sendNotification()">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Email Templates -->
        <div id="templates-content" class="notification-content" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="fas fa-envelope"></i> Email Templates</h3>
                <button class="btn btn-primary" onclick="openTemplateModal()">
                    <i class="fas fa-plus"></i> Add Template
                </button>
            </div>
            
            <div class="template-grid">
                <?php foreach ($email_templates as $template): ?>
                <div class="template-item">
                    <h4><?= htmlspecialchars($template['template_name']) ?></h4>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($template['template_subject']) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge <?= $template['is_active'] ? 'status-read' : 'status-unread' ?>">
                            <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                    <p><strong>Variables:</strong> <?= htmlspecialchars($template['template_variables']) ?></p>
                    <div class="template-controls">
                        <button class="btn btn-info" onclick="editTemplate(<?= $template['template_id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger" onclick="deleteTemplate(<?= $template['template_id'] ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Template Modal -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="template-modal-title"><i class="fas fa-plus"></i> Add Email Template</h3>
                <button class="close" onclick="closeModal('templateModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="template-form">
                    <input type="hidden" id="template_id" name="template_id" value="0">
                    
                    <div class="form-group">
                        <label for="template_name">Template Name *</label>
                        <input type="text" id="template_name" name="template_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_subject">Subject *</label>
                        <input type="text" id="template_subject" name="template_subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_body">Email Body *</label>
                        <textarea id="template_body" name="template_body" required></textarea>
                        <small style="color: #666;">Use variables like #{customer_name}, #{order_number}, etc.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_variables">Available Variables</label>
                        <input type="text" id="template_variables" name="template_variables" placeholder="customer_name, order_number, order_total">
                        <small style="color: #666;">Comma-separated list of available variables</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is_active" name="is_active" checked> Active Template
                        </label>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('templateModal')" style="margin-right: 10px;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveTemplate()">
                            <i class="fas fa-save"></i> Save Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notification-container"></div>
    
    <script>
        // Show notification tab
        function showNotificationTab(tabName) {
            // Hide all content
            document.querySelectorAll('.notification-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.notification-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected content
            document.getElementById(tabName + '-content').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Toggle recipient selection
        function toggleRecipientSelection() {
            const recipientType = document.getElementById('recipient_type').value;
            const userSelection = document.getElementById('user-selection');
            
            if (recipientType === 'user') {
                userSelection.style.display = 'block';
                document.getElementById('recipient_id').required = true;
            } else {
                userSelection.style.display = 'none';
                document.getElementById('recipient_id').required = false;
            }
        }
        
        // Send notification
        function sendNotification() {
            const formData = new FormData();
            formData.append('action', 'send_notification');
            formData.append('notification_type', document.getElementById('notification_type').value);
            formData.append('title', document.getElementById('title').value);
            formData.append('message', document.getElementById('message').value);
            formData.append('recipient_type', document.getElementById('recipient_type').value);
            formData.append('recipient_id', document.getElementById('recipient_id').value);
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('send-notification-form').reset();
                    document.getElementById('user-selection').style.display = 'none';
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
        }
        
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_as_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
        }
        
        // Delete notification
        function deleteNotification(notificationId) {
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_notification&notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                });
            }
        }
        
        // Load notifications
        function loadNotifications() {
            showNotification('Refreshing notifications...', 'success');
            setTimeout(() => location.reload(), 1000);
        }
        
        // Template functions
        function openTemplateModal(templateId = 0) {
            document.getElementById('template_id').value = templateId;
            document.getElementById('template-modal-title').innerHTML = templateId > 0 ? '<i class="fas fa-edit"></i> Edit Template' : '<i class="fas fa-plus"></i> Add Email Template';
            document.getElementById('templateModal').style.display = 'block';
            
            if (templateId > 0) {
                // Load template data for editing
                // This would need to be implemented with a get_template action
            } else {
                document.getElementById('template-form').reset();
                document.getElementById('template_id').value = 0;
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function saveTemplate() {
            const formData = new FormData();
            formData.append('action', document.getElementById('template_id').value > 0 ? 'update_email_template' : 'create_email_template');
            formData.append('template_id', document.getElementById('template_id').value);
            formData.append('template_name', document.getElementById('template_name').value);
            formData.append('template_subject', document.getElementById('template_subject').value);
            formData.append('template_body', document.getElementById('template_body').value);
            formData.append('template_variables', document.getElementById('template_variables').value);
            formData.append('is_active', document.getElementById('is_active').checked ? '1' : '0');
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('templateModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
        }
        
        function editTemplate(templateId) {
            openTemplateModal(templateId);
        }
        
        function deleteTemplate(templateId) {
            if (confirm('Are you sure you want to delete this template?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_email_template&template_id=${templateId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                });
            }
        }
        
        // Notification system
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => container.removeChild(notification), 300);
            }, 3000);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

