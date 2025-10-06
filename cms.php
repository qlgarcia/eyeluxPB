<?php
// CMS Module for Admin Panel
require_once 'includes/config.php';
require_once 'includes/database.php';

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-complete.php');
    exit;
}

$db = new Database();

// Handle AJAX requests for CMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'save_page':
                $page_id = intval($_POST['page_id'] ?? 0);
                $page_title = $_POST['page_title'];
                $page_slug = $_POST['page_slug'];
                $page_content = $_POST['page_content'];
                $page_type = $_POST['page_type'] ?? 'static';
                $meta_title = $_POST['meta_title'] ?? '';
                $meta_description = $_POST['meta_description'] ?? '';
                $is_published = isset($_POST['is_published']) ? 1 : 0;
                
                if ($page_id > 0) {
                    // Update existing page
                    $result = $db->query(
                        "UPDATE cms_pages SET page_title = ?, page_slug = ?, page_content = ?, page_type = ?, meta_title = ?, meta_description = ?, is_published = ?, updated_at = NOW() WHERE page_id = ?",
                        [$page_title, $page_slug, $page_content, $page_type, $meta_title, $meta_description, $is_published, $page_id]
                    );
                    
                    if ($result) {
                        // Log the action
                        $db->query(
                            "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'cms_pages', ?, ?)",
                            [$_SESSION['admin_id'], $page_id, json_encode(['page_title' => $page_title, 'is_published' => $is_published])]
                        );
                        
                        echo json_encode(['success' => true, 'message' => 'Page updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update page']);
                    }
                } else {
                    // Create new page
                    $result = $db->insert(
                        "INSERT INTO cms_pages (page_title, page_slug, page_content, page_type, meta_title, meta_description, is_published, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$page_title, $page_slug, $page_content, $page_type, $meta_title, $meta_description, $is_published, $_SESSION['admin_id']]
                    );
                    
                    if ($result) {
                        // Log the action
                        $db->query(
                            "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'create', 'cms_pages', ?, ?)",
                            [$_SESSION['admin_id'], $result, json_encode(['page_title' => $page_title, 'is_published' => $is_published])]
                        );
                        
                        echo json_encode(['success' => true, 'message' => 'Page created successfully', 'page_id' => $result]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create page']);
                    }
                }
                break;
                
            case 'delete_page':
                $page_id = intval($_POST['page_id']);
                
                $result = $db->query("DELETE FROM cms_pages WHERE page_id = ?", [$page_id]);
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id) VALUES (?, 'delete', 'cms_pages', ?)",
                        [$_SESSION['admin_id'], $page_id]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Page deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete page']);
                }
                break;
                
            case 'get_page':
                $page_id = intval($_POST['page_id']);
                
                $page = $db->fetchOne("SELECT * FROM cms_pages WHERE page_id = ?", [$page_id]);
                
                if ($page) {
                    echo json_encode(['success' => true, 'data' => $page]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Page not found']);
                }
                break;
                
            case 'update_setting':
                $setting_key = $_POST['setting_key'];
                $setting_value = $_POST['setting_value'];
                
                $result = $db->query(
                    "UPDATE site_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?",
                    [$setting_value, $_SESSION['admin_id'], $setting_key]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'site_settings', ?, ?)",
                        [$_SESSION['admin_id'], $setting_key, json_encode(['setting_value' => $setting_value])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
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

// Get CMS data
$pages = $db->fetchAll("SELECT * FROM cms_pages ORDER BY created_at DESC");
$settings = $db->fetchAll("SELECT * FROM site_settings ORDER BY setting_key");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - EyeLux Admin</title>
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
        .cms-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .cms-tab {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #e9ecef;
            color: #495057;
        }
        .cms-tab.active {
            background: #667eea;
            color: white;
        }
        .cms-content {
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
            min-height: 200px;
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
        .status-published {
            background: #d4edda;
            color: #155724;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
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
            margin: 2% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
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
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .setting-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .setting-item h4 {
            margin-bottom: 10px;
            color: #333;
        }
        .setting-item p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .setting-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .setting-controls input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .setting-controls button {
            padding: 8px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-alt"></i> Content Management System</h1>
            <a href="admin.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>
        
        <!-- CMS Tabs -->
        <div class="cms-tabs">
            <button class="cms-tab active" onclick="showCMSTab('pages')">
                <i class="fas fa-file"></i> Pages
            </button>
            <button class="cms-tab" onclick="showCMSTab('settings')">
                <i class="fas fa-cog"></i> Site Settings
            </button>
        </div>
        
        <!-- Pages Management -->
        <div id="pages-content" class="cms-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="fas fa-file"></i> Manage Pages</h3>
                <button class="btn btn-primary" onclick="openPageModal()">
                    <i class="fas fa-plus"></i> Add New Page
                </button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?= htmlspecialchars($page['page_title']) ?></td>
                            <td><?= htmlspecialchars($page['page_slug']) ?></td>
                            <td><?= htmlspecialchars($page['page_type']) ?></td>
                            <td>
                                <span class="status-badge <?= $page['is_published'] ? 'status-published' : 'status-draft' ?>">
                                    <?= $page['is_published'] ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($page['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-info" onclick="editPage(<?= $page['page_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="deletePage(<?= $page['page_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Site Settings -->
        <div id="settings-content" class="cms-content" style="display: none;">
            <h3><i class="fas fa-cog"></i> Site Settings</h3>
            <p style="color: #666; margin-bottom: 20px;">Manage your website's global settings and configuration.</p>
            
            <div class="settings-grid">
                <?php foreach ($settings as $setting): ?>
                <div class="setting-item">
                    <h4><?= htmlspecialchars($setting['setting_key']) ?></h4>
                    <p><?= htmlspecialchars($setting['setting_description']) ?></p>
                    <div class="setting-controls">
                        <?php if ($setting['setting_type'] === 'boolean'): ?>
                            <select onchange="updateSetting('<?= $setting['setting_key'] ?>', this.value)">
                                <option value="1" <?= $setting['setting_value'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= $setting['setting_value'] == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        <?php else: ?>
                            <input type="<?= $setting['setting_type'] === 'number' ? 'number' : 'text' ?>" 
                                   value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                   onchange="updateSetting('<?= $setting['setting_key'] ?>', this.value)">
                        <?php endif; ?>
                        <button onclick="updateSetting('<?= $setting['setting_key'] ?>', document.querySelector('input[onchange*=\"<?= $setting['setting_key'] ?>\"]').value)">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Page Modal -->
    <div id="pageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><i class="fas fa-plus"></i> Add New Page</h3>
                <button class="close" onclick="closeModal('pageModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="page-form">
                    <input type="hidden" id="page_id" name="page_id" value="0">
                    
                    <div class="form-group">
                        <label for="page_title">Page Title *</label>
                        <input type="text" id="page_title" name="page_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="page_slug">Page Slug *</label>
                        <input type="text" id="page_slug" name="page_slug" required>
                        <small style="color: #666;">URL-friendly version of the title (e.g., about-us)</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="page_type">Page Type</label>
                            <select id="page_type" name="page_type">
                                <option value="static">Static Page</option>
                                <option value="dynamic">Dynamic Page</option>
                                <option value="custom">Custom Page</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="is_published" name="is_published" checked> Published
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_title">Meta Title</label>
                        <input type="text" id="meta_title" name="meta_title">
                        <small style="color: #666;">SEO title for search engines</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" rows="2"></textarea>
                        <small style="color: #666;">SEO description for search engines</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="page_content">Page Content *</label>
                        <textarea id="page_content" name="page_content" required></textarea>
                        <small style="color: #666;">HTML content for the page</small>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('pageModal')" style="margin-right: 10px;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="savePage()">
                            <i class="fas fa-save"></i> Save Page
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notification-container"></div>
    
    <script>
        // Show CMS tab
        function showCMSTab(tabName) {
            // Hide all content
            document.querySelectorAll('.cms-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.cms-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected content
            document.getElementById(tabName + '-content').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Modal functions
        function openPageModal(pageId = 0) {
            document.getElementById('page_id').value = pageId;
            document.getElementById('modal-title').innerHTML = pageId > 0 ? '<i class="fas fa-edit"></i> Edit Page' : '<i class="fas fa-plus"></i> Add New Page';
            document.getElementById('pageModal').style.display = 'block';
            
            if (pageId > 0) {
                loadPageData(pageId);
            } else {
                document.getElementById('page-form').reset();
                document.getElementById('page_id').value = 0;
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Load page data for editing
        function loadPageData(pageId) {
            fetch('cms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_page&page_id=${pageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const page = data.data;
                    document.getElementById('page_title').value = page.page_title;
                    document.getElementById('page_slug').value = page.page_slug;
                    document.getElementById('page_type').value = page.page_type;
                    document.getElementById('meta_title').value = page.meta_title || '';
                    document.getElementById('meta_description').value = page.meta_description || '';
                    document.getElementById('page_content').value = page.page_content;
                    document.getElementById('is_published').checked = page.is_published == 1;
                } else {
                    showNotification('Error loading page data: ' + data.message, 'error');
                }
            });
        }
        
        // Save page
        function savePage() {
            const formData = new FormData();
            formData.append('action', 'save_page');
            formData.append('page_id', document.getElementById('page_id').value);
            formData.append('page_title', document.getElementById('page_title').value);
            formData.append('page_slug', document.getElementById('page_slug').value);
            formData.append('page_type', document.getElementById('page_type').value);
            formData.append('meta_title', document.getElementById('meta_title').value);
            formData.append('meta_description', document.getElementById('meta_description').value);
            formData.append('page_content', document.getElementById('page_content').value);
            formData.append('is_published', document.getElementById('is_published').checked ? '1' : '0');
            
            fetch('cms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('pageModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
        }
        
        // Edit page
        function editPage(pageId) {
            openPageModal(pageId);
        }
        
        // Delete page
        function deletePage(pageId) {
            if (confirm('Are you sure you want to delete this page?')) {
                fetch('cms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_page&page_id=${pageId}`
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
        
        // Update setting
        function updateSetting(settingKey, settingValue) {
            fetch('cms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_setting&setting_key=${settingKey}&setting_value=${settingValue}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Setting updated successfully', 'success');
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
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
        
        // Auto-generate slug from title
        document.getElementById('page_title').addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('page_slug').value = slug;
        });
    </script>
</body>
</html>






