<?php
require_once 'includes/header.php';

$page_title = 'My Profile';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=profile.php');
}

$user_id = $_SESSION['user_id'];
$tab = sanitizeInput($_GET['tab'] ?? 'profile');

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            
            if (empty($first_name) || empty($last_name)) {
                $error_message = 'First name and last name are required.';
            } else {
                $db = Database::getInstance();
                $result = $db->execute(
                    "UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE user_id = ?",
                    [$first_name, $last_name, $phone, $user_id]
                );
                
                if ($result) {
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $success_message = 'Profile updated successfully!';
                } else {
                    $error_message = 'Failed to update profile.';
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = 'All password fields are required.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'New password must be at least 6 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match.';
            } else {
                $db = Database::getInstance();
                $user = $db->fetchOne("SELECT password FROM users WHERE user_id = ?", [$user_id]);
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $result = $db->execute(
                        "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                        [$hashed_password, $user_id]
                    );
                    
                    if ($result) {
                        $success_message = 'Password changed successfully!';
                    } else {
                        $error_message = 'Failed to change password.';
                    }
                } else {
                    $error_message = 'Current password is incorrect.';
                }
            }
            break;
            
        case 'upload_profile_picture':
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).';
                } elseif ($file['size'] > $max_size) {
                    $error_message = 'File size must be less than 5MB.';
                } else {
                    $uploads_dir = 'uploads/profile_pictures';
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $uploads_dir . '/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $db = Database::getInstance();
                        
                        // Get old profile picture to delete it
                        $old_picture = $db->fetchOne("SELECT profile_picture FROM users WHERE user_id = ?", [$user_id]);
                        
                        // Update database
                        $result = $db->execute(
                            "UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?",
                            [$upload_path, $user_id]
                        );
                        
                        if ($result) {
                            // Delete old profile picture if it exists
                            if ($old_picture && $old_picture['profile_picture'] && file_exists($old_picture['profile_picture'])) {
                                unlink($old_picture['profile_picture']);
                            }
                            $success_message = 'Profile picture updated successfully!';
                        } else {
                            // Delete uploaded file if database update failed
                            unlink($upload_path);
                            $error_message = 'Failed to update profile picture.';
                        }
                    } else {
                        $error_message = 'Failed to upload file.';
                    }
                }
            } else {
                $error_message = 'Please select a valid image file.';
            }
            break;
            
        case 'add_address':
            $address_type = sanitizeInput($_POST['address_type'] ?? 'shipping');
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $address_line1 = sanitizeInput($_POST['address_line1'] ?? '');
            $address_line2 = sanitizeInput($_POST['address_line2'] ?? '');
            $barangay = sanitizeInput($_POST['barangay'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $state = sanitizeInput($_POST['state'] ?? '');
            $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
            $country = 'PH'; // Fixed to Philippines
            $province = sanitizeInput($_POST['province'] ?? '');
            $city_dropdown = sanitizeInput($_POST['city_dropdown'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            // Use dropdown city if selected, otherwise use manual city input
            if (!empty($city_dropdown)) {
                $city = $city_dropdown;
            }
            
            if (empty($first_name) || empty($last_name) || empty($address_line1) || empty($city) || empty($postal_code) || empty($province)) {
                $error_message = 'Please fill in all required fields including province/region.';
            } else {
                $db = Database::getInstance();
                
                // If this is set as default, unset other defaults
                if ($is_default) {
                    $db->execute("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND address_type = ?", 
                                [$user_id, $address_type]);
                }
                
                $result = $db->insert(
                    "INSERT INTO addresses (user_id, address_type, first_name, last_name, 
                     address_line1, address_line2, barangay, city, state, province, postal_code, country, phone, latitude, longitude, is_default) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$user_id, $address_type, $first_name, $last_name, $address_line1, 
                     $address_line2, $barangay, $city, $state, $province, $postal_code, $country, $phone, $latitude, $longitude, $is_default]
                );
                
                if ($result) {
                    $success_message = 'Address added successfully!';
                    $tab = 'addresses';
                } else {
                    $error_message = 'Failed to add address.';
                }
            }
            break;
            
        case 'update_address':
            $address_id = (int)($_POST['address_id'] ?? 0);
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $address_line1 = sanitizeInput($_POST['address_line1'] ?? '');
            $address_line2 = sanitizeInput($_POST['address_line2'] ?? '');
            $barangay = sanitizeInput($_POST['barangay'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $state = sanitizeInput($_POST['state'] ?? '');
            $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
            $country = sanitizeInput($_POST['country'] ?? 'PH');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if (empty($first_name) || empty($last_name) || empty($address_line1) || empty($city) || empty($postal_code)) {
                $error_message = 'Please fill in all required fields.';
            } else {
                $db = Database::getInstance();
                
                // If this is set as default, unset other defaults
                if ($is_default) {
                    $db->execute("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND address_id != ?", 
                                [$user_id, $address_id]);
                }
                
                $result = $db->execute(
                    "UPDATE addresses SET first_name = ?, last_name = ?, 
                     address_line1 = ?, address_line2 = ?, barangay = ?, city = ?, state = ?, 
                     postal_code = ?, country = ?, phone = ?, latitude = ?, longitude = ?, is_default = ? 
                     WHERE address_id = ? AND user_id = ?",
                    [$first_name, $last_name, $address_line1, $address_line2, $barangay,
                     $city, $state, $postal_code, $country, $phone, $latitude, $longitude, $is_default, $address_id, $user_id]
                );
                
                if ($result) {
                    $success_message = 'Address updated successfully!';
                    $tab = 'addresses';
                } else {
                    $error_message = 'Failed to update address.';
                }
            }
            break;
            
        case 'delete_address':
            $address_id = (int)($_POST['address_id'] ?? 0);
            
            $db = Database::getInstance();
            $result = $db->execute("DELETE FROM addresses WHERE address_id = ? AND user_id = ?", 
                                  [$address_id, $user_id]);
            
            if ($result) {
                $success_message = 'Address deleted successfully!';
                $tab = 'addresses';
            } else {
                $error_message = 'Failed to delete address.';
            }
            break;
    }
}

// Get user data
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE user_id = ?", [$user_id]);

// Get addresses
$addresses = $db->fetchAll("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC", [$user_id]);

// Get recent orders
$recent_orders = $db->fetchAll(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5",
    [$user_id]
);

// Get notifications
$notifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user_id]
);
?>

<main>
    <div class="container">
        <div class="profile-page">
            <div class="page-header">
                <h1>My Account</h1>
                <p>Manage your account settings and preferences</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-layout">
                <!-- Sidebar Navigation -->
                <div class="profile-sidebar">
                    <nav class="profile-nav">
                        <a href="?tab=profile" class="nav-item <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        
                        <a href="?tab=addresses" class="nav-item <?php echo $tab === 'addresses' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Addresses</span>
                        </a>
                        
                        <a href="?tab=orders" class="nav-item <?php echo $tab === 'orders' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Orders</span>
                        </a>
                        
                        <a href="refund-request.php" class="nav-item">
                            <i class="fas fa-undo"></i>
                            <span>Refund Requests</span>
                        </a>
                        
                        <a href="?tab=notifications" class="nav-item <?php echo $tab === 'notifications' ? 'active' : ''; ?>">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        
                        <a href="?tab=security" class="nav-item <?php echo $tab === 'security' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span>Security</span>
                        </a>
                    </nav>
                </div>
                
                <!-- Main Content -->
                <div class="profile-content">
                    <?php if ($tab === 'profile'): ?>
                        <!-- Profile Information -->
                        <div class="profile-section">
                            <h2>Personal Information</h2>
                            
                            <form method="POST" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    <small>Email cannot be changed. Contact support if needed.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Account Created</label>
                                    <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                        
                        <!-- Profile Picture Section -->
                        <div class="profile-section">
                            <h2>Profile Picture</h2>
                            
                            <div class="profile-picture-container">
                                <div class="current-picture">
                                    <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                             alt="Profile Picture" class="profile-image">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" class="profile-picture-form">
                                    <input type="hidden" name="action" value="upload_profile_picture">
                                    
                                    <div class="form-group">
                                        <label for="profile_picture">Upload New Picture</label>
                                        <input type="file" id="profile_picture" name="profile_picture" 
                                               accept="image/jpeg,image/png,image/gif,image/webp" required>
                                        <small>Supported formats: JPEG, PNG, GIF, WebP. Max size: 5MB</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload Picture
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                    <?php elseif ($tab === 'addresses'): ?>
                        <!-- Address Management -->
                        <div class="addresses-section">
                            <div class="section-header">
                                <h2>Addresses</h2>
                                <button class="btn btn-primary" onclick="showAddAddressForm()">
                                    <i class="fas fa-plus"></i> Add New Address
                                </button>
                            </div>
                            
                            <?php if (empty($addresses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                    <h3>No addresses saved</h3>
                                    <p>Add your first address to make checkout faster.</p>
                                    <button class="btn btn-primary" onclick="showAddAddressForm()">Add Address</button>
                                </div>
                            <?php else: ?>
                                <div class="addresses-grid">
                                    <?php foreach ($addresses as $address): ?>
                                    <div class="address-card">
                                        <div class="address-header">
                                            <h3><?php echo htmlspecialchars($address['first_name'] . ' ' . $address['last_name']); ?></h3>
                                            <?php if ($address['is_default']): ?>
                                                <span class="default-badge">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="address-details">
                                            <p><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                            <?php if ($address['address_line2']): ?>
                                                <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                                            <?php endif; ?>
                                            <p><?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']); ?></p>
                                            <p><?php echo htmlspecialchars($address['country']); ?></p>
                                            <?php if ($address['phone']): ?>
                                                <p><?php echo htmlspecialchars($address['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="address-actions">
                                            <button class="btn btn-outline" onclick="editAddress(<?php echo $address['address_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this address?')">
                                                <input type="hidden" name="action" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                                <button type="submit" class="btn btn-secondary">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($tab === 'orders'): ?>
                        <!-- Recent Orders -->
                        <div class="orders-section">
                            <div class="section-header">
                                <h2>Recent Orders</h2>
                                <a href="orders.php" class="btn btn-outline">View All Orders</a>
                            </div>
                            
                            <?php if (empty($recent_orders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-bag" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                    <h3>No orders yet</h3>
                                    <p>Start shopping to see your orders here.</p>
                                    <a href="products.php" class="btn btn-primary">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <div class="orders-list">
                                    <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-info">
                                            <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                            <p><?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                                        </div>
                                        
                                        <div class="order-status">
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="order-total">
                                            <?php echo formatPrice($order['total_amount']); ?>
                                        </div>
                                        
                                        <div class="order-actions">
                                            <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($tab === 'notifications'): ?>
                        <!-- Notifications -->
                        <div class="notifications-section">
                            <h2>Notifications</h2>
                            
                            <?php if (empty($notifications)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                    <h3>No notifications</h3>
                                    <p>You'll see important updates here.</p>
                                </div>
                            <?php else: ?>
                                <div class="notifications-list">
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-<?php echo $notification['type'] === 'order' ? 'shopping-bag' : 'info-circle'; ?>"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <span class="notification-date">
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($tab === 'security'): ?>
                        <!-- Security Settings -->
                        <div class="security-section">
                            <h2>Security Settings</h2>
                            
                            <form method="POST" class="security-form">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label for="current_password">Current Password *</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="6">
                                    <small>Password must be at least 6 characters long.</small>
                                    <div id="password-strength" class="password-strength"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                    <div id="password-match" class="password-match"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add/Edit Address Modal -->
<div id="addressModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Address</h3>
            <button class="modal-close" onclick="closeAddressModal()">&times;</button>
        </div>
        
        <form method="POST" id="addressForm">
            <input type="hidden" name="action" value="add_address">
            <input type="hidden" name="address_id" id="address_id">
            <input type="hidden" name="latitude" id="latitude" value="">
            <input type="hidden" name="longitude" id="longitude" value="">
            
            <div class="form-group">
                <label for="address_type">Address Type</label>
                <select name="address_type" id="address_type">
                    <option value="shipping">Shipping</option>
                    <option value="billing">Billing</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="modal_first_name">First Name *</label>
                    <input type="text" id="modal_first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_last_name">Last Name *</label>
                    <input type="text" id="modal_last_name" name="last_name" required>
                </div>
            </div>
            
            
            <div class="form-group">
                <label for="modal_address_line1">House Number and Street *</label>
                <input type="text" id="modal_address_line1" name="address_line1" required placeholder="123 Main Street">
            </div>
            
            <div class="form-group">
                <label for="modal_barangay">Barangay</label>
                <input type="text" id="modal_barangay" name="barangay" placeholder="Barangay Name">
            </div>
            
            <div class="form-group">
                <label for="modal_address_line2">Address Line 2</label>
                <input type="text" id="modal_address_line2" name="address_line2" placeholder="Unit, Building, Subdivision (Optional)">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="modal_city">City / Municipality *</label>
                    <input type="text" id="modal_city" name="city" required placeholder="City or Municipality">
                </div>
                
                <div class="form-group">
                    <label for="modal_state">State</label>
                    <input type="text" id="modal_state" name="state" placeholder="State (if applicable)">
                </div>
                
                <div class="form-group">
                    <label for="modal_postal_code">ZIP Code *</label>
                    <input type="text" id="modal_postal_code" name="postal_code" required placeholder="1234">
                </div>
            </div>
            
            <div class="form-group">
                <label for="modal_country">Country</label>
                <select name="country" id="modal_country" disabled>
                    <option value="PH" selected>Philippines</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="modal_province">Province/Region *</label>
                <select name="province" id="modal_province" required>
                    <option value="">Select Province/Region</option>
                    <option value="NCR">National Capital Region (NCR)</option>
                    <option value="Ilocos">Ilocos Region</option>
                    <option value="Cagayan Valley">Cagayan Valley</option>
                    <option value="Central Luzon">Central Luzon</option>
                    <option value="CALABARZON">CALABARZON</option>
                    <option value="MIMAROPA">MIMAROPA</option>
                    <option value="Bicol">Bicol Region</option>
                    <option value="Western Visayas">Western Visayas</option>
                    <option value="Central Visayas">Central Visayas</option>
                    <option value="Eastern Visayas">Eastern Visayas</option>
                    <option value="Zamboanga Peninsula">Zamboanga Peninsula</option>
                    <option value="Northern Mindanao">Northern Mindanao</option>
                    <option value="Davao">Davao Region</option>
                    <option value="SOCCSKSARGEN">SOCCSKSARGEN</option>
                    <option value="Caraga">Caraga</option>
                    <option value="Bangsamoro">Bangsamoro</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="modal_city_dropdown">City/Municipality *</label>
                <select name="city_dropdown" id="modal_city_dropdown" required>
                    <option value="">Select City/Municipality</option>
                </select>
                <small class="form-help">Or enter manually below</small>
            </div>
            
            <div class="form-group">
                <label for="map_selection">Select Location on Map</label>
                <div id="map-container" style="height: 300px; width: 100%; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; position: relative;">
                    <div id="map" style="height: 100%; width: 100%;"></div>
                    <div id="map-overlay" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">
                        <button type="button" id="use_current_location" class="btn btn-outline" style="margin-bottom: 5px;">
                            <i class="fas fa-location-arrow"></i> Use Current Location
                        </button>
                    </div>
                </div>
                <small class="form-help">Click on the map to select your exact location</small>
                <div id="geolocation-notice" style="display: none; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 4px; padding: 8px; margin-top: 5px; font-size: 12px;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Current location feature requires HTTPS. For HTTP sites, please use the map to manually select your location.
                </div>
            </div>
            
            <div class="form-group">
                <label for="modal_phone">Phone Number</label>
                <input type="tel" id="modal_phone" name="phone" placeholder="+63 9XX XXX XXXX">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" id="is_default">
                    <span>Set as default address</span>
                </label>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddressModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Address</button>
            </div>
        </form>
    </div>
</div>

<style>
.profile-page {
    padding: 20px 0;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.page-header p {
    color: #666;
    font-size: 16px;
}

.profile-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 30px;
}

.profile-sidebar {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    height: fit-content;
}

.profile-nav {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #666;
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.3s;
}

.nav-item:hover {
    background: #f8f9fa;
    color: #2c3e50;
}

.nav-item.active {
    background: #e74c3c;
    color: white;
}

.nav-item i {
    width: 20px;
    text-align: center;
}

.profile-content {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.profile-section h2,
.section-header h2,
.notifications-section h2,
.security-section h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    font-size: 24px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.profile-form,
.security-form {
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #e74c3c;
}

.form-group small {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state h3 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.empty-state p {
    margin-bottom: 30px;
}

.addresses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.address-card {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.address-card:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.address-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.address-header h3 {
    color: #2c3e50;
    margin: 0;
}

.default-badge {
    background: #e74c3c;
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.address-details p {
    margin-bottom: 5px;
    color: #666;
}

.address-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-card {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 20px;
    align-items: center;
    padding: 20px;
    border: 1px solid #eee;
    border-radius: 8px;
}

.order-info h3 {
    color: #2c3e50;
    margin-bottom: 5px;
}

.order-info p {
    color: #666;
    font-size: 14px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #d1ecf1; color: #0c5460; }
.status-shipped { background: #e2e3e5; color: #383d41; }
.status-delivered { background: #d4edda; color: #155724; }

.order-total {
    font-weight: bold;
    color: #e74c3c;
    font-size: 16px;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-item {
    display: flex;
    gap: 15px;
    padding: 20px;
    border: 1px solid #eee;
    border-radius: 8px;
    transition: background 0.3s;
}

.notification-item.unread {
    background: #f8f9fa;
    border-left: 4px solid #e74c3c;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e74c3c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.notification-content h4 {
    color: #2c3e50;
    margin-bottom: 5px;
}

.notification-content p {
    color: #666;
    margin-bottom: 10px;
}

.notification-date {
    color: #999;
    font-size: 12px;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
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
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    color: #2c3e50;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-close:hover {
    color: #e74c3c;
}

#addressForm {
    padding: 25px;
}

.modal-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

@media (max-width: 768px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar {
        order: 2;
    }
    
    .profile-content {
        order: 1;
    }
    
    .profile-nav {
        flex-direction: row;
        overflow-x: auto;
    }
    
    .nav-item {
        white-space: nowrap;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .order-card {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .addresses-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        margin: 10% auto;
        width: 95%;
    }
}

/* Profile Picture Styles */
.profile-picture-container {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
}

.current-picture {
    flex-shrink: 0;
}

.profile-image {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e74c3c;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.default-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    border: 4px solid #e74c3c;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.profile-picture-form {
    flex: 1;
}

.profile-picture-form .form-group {
    margin-bottom: 15px;
}

.profile-picture-form input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s ease;
}

.profile-picture-form input[type="file"]:hover {
    border-color: #e74c3c;
    background: #fff5f5;
}

.profile-picture-form small {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.profile-picture-form .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Responsive Profile Picture */
@media (max-width: 768px) {
    .profile-picture-container {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .profile-image,
    .default-avatar {
        width: 100px;
        height: 100px;
    }
    
    .default-avatar {
        font-size: 40px;
    }
}

/* Password Strength Indicator */
.password-strength {
    margin-top: 8px;
    height: 4px;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.password-strength.weak {
    background: linear-gradient(to right, #e74c3c 0%, #e74c3c 33%, #ecf0f1 33%, #ecf0f1 100%);
}

.password-strength.medium {
    background: linear-gradient(to right, #f39c12 0%, #f39c12 66%, #ecf0f1 66%, #ecf0f1 100%);
}

.password-strength.strong {
    background: #27ae60;
}

.password-match {
    margin-top: 5px;
    font-size: 12px;
    font-weight: 500;
}

.password-match.match {
    color: #27ae60;
}

.password-match.no-match {
    color: #e74c3c;
}

/* Map and Address Form Styles */
#map {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.form-help {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--sage);
    color: var(--sage);
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-outline:hover {
    background: var(--sage);
    color: white;
}

#modal_province, #modal_city_dropdown {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

#modal_province:focus, #modal_city_dropdown:focus {
    outline: none;
    border-color: var(--sage);
    box-shadow: 0 0 0 3px rgba(134, 142, 150, 0.1);
}

#modal_country {
    background-color: #f8f9fa;
    color: #6c757d;
    cursor: not-allowed;
}

/* Map container styling */
.map-container {
    position: relative;
    margin-bottom: 15px;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
}

/* Responsive design for mobile */
@media (max-width: 768px) {
    #map {
        height: 250px;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-row .form-group {
        width: 100%;
        margin-right: 0;
    }
}
</style>

<script>
function showAddAddressForm() {
    document.getElementById('modalTitle').textContent = 'Add New Address';
    document.getElementById('addressForm').reset();
    document.querySelector('input[name="action"]').value = 'add_address';
    document.getElementById('address_id').value = '';
    document.getElementById('addressModal').style.display = 'block';
}

function editAddress(addressId) {
    // Find the address data from the current page
    const addressData = getAddressData(addressId);
    
    if (addressData) {
        // Update modal title and action
        document.getElementById('modalTitle').textContent = 'Edit Address';
        document.querySelector('input[name="action"]').value = 'update_address';
        document.getElementById('address_id').value = addressId;
        
        // Populate all form fields with existing data
        document.getElementById('address_type').value = addressData.address_type || 'shipping';
        document.getElementById('modal_first_name').value = addressData.first_name || '';
        document.getElementById('modal_last_name').value = addressData.last_name || '';
        document.getElementById('modal_address_line1').value = addressData.address_line1 || '';
        document.getElementById('modal_address_line2').value = addressData.address_line2 || '';
        document.getElementById('modal_barangay').value = addressData.barangay || '';
        document.getElementById('modal_city').value = addressData.city || '';
        document.getElementById('modal_state').value = addressData.state || '';
        document.getElementById('modal_postal_code').value = addressData.postal_code || '';
        document.getElementById('modal_province').value = addressData.province || '';
        document.getElementById('modal_phone').value = addressData.phone || '';
        
        // Populate coordinate fields
        document.getElementById('latitude').value = addressData.latitude || '';
        document.getElementById('longitude').value = addressData.longitude || '';
        
        // Check if this is the default address
        const isDefaultCheckbox = document.getElementById('is_default');
        if (isDefaultCheckbox) {
            isDefaultCheckbox.checked = addressData.is_default == 1;
        }
        
        // Update city dropdown if province is set
        if (addressData.province) {
            updateCityDropdownForProvince(addressData.province);
            // Then set the city dropdown
            setTimeout(() => {
                updateCityDropdown(addressData.city);
            }, 100);
        }
        
        // If we have address coordinates, place a marker there
        if (addressData.latitude && addressData.longitude) {
            setTimeout(() => {
                if (map && isMapInitialized) {
                    const coordinates = [parseFloat(addressData.longitude), parseFloat(addressData.latitude)];
                    placeMarker(coordinates);
                    map.flyTo({
                        center: coordinates,
                        zoom: 15
                    });
                }
            }, 500);
        } else {
            // If no coordinates, try to geocode the address to get coordinates
            if (addressData.address_line1 && addressData.city) {
                setTimeout(() => {
                    geocodeAddress(addressData);
                }, 500);
            }
        }
        
        console.log('Edit address data loaded:', addressData);
    } else {
        console.error('Address data not found for ID:', addressId);
        alert('Address data not found. Please refresh the page and try again.');
        return;
    }
    
    document.getElementById('addressModal').style.display = 'block';
    
    // Initialize map after modal is shown
    setTimeout(() => {
        if (typeof mapboxgl !== 'undefined') {
            // Check if map is already initialized
            if (!isMapInitialized) {
                initMap();
            } else if (map) {
                // Map exists but might need to be resized
                setTimeout(() => {
                    map.resize();
                }, 100);
            }
        } else {
            // If Mapbox is not loaded, show fallback
            handleMapError();
        }
    }, 200);
}

// Address data for editing (embedded from PHP)
const addressData = <?php echo json_encode($addresses); ?>;

function getAddressData(addressId) {
    // Find address data from the embedded JSON
    return addressData.find(addr => addr.address_id == addressId) || null;
}

function inferProvinceFromCity(city) {
    // Map major cities to their provinces
    const cityToProvince = {
        'Manila': 'NCR',
        'Quezon City': 'NCR',
        'Makati': 'NCR',
        'Taguig': 'NCR',
        'Pasig': 'NCR',
        'Mandaluyong': 'NCR',
        'San Juan': 'NCR',
        'Marikina': 'NCR',
        'Caloocan': 'NCR',
        'Las Piñas': 'NCR',
        'Malabon': 'NCR',
        'Muntinlupa': 'NCR',
        'Navotas': 'NCR',
        'Parañaque': 'NCR',
        'Pasay': 'NCR',
        'Valenzuela': 'NCR',
        'Pateros': 'NCR',
        'Cebu City': 'Cebu',
        'Davao City': 'Davao del Sur',
        'Zamboanga City': 'Zamboanga del Sur',
        'Antipolo': 'Rizal',
        'Cagayan de Oro': 'Misamis Oriental',
        'Bacolod': 'Negros Occidental',
        'Iloilo City': 'Iloilo',
        'Iligan': 'Lanao del Norte',
        'Calamba': 'Laguna',
        'Cabuyao': 'Laguna',
        'Santa Rosa': 'Laguna'
    };
    
    return cityToProvince[city] || '';
}

function closeAddressModal() {
    document.getElementById('addressModal').style.display = 'none';
    
    // Clear any existing markers when closing
    if (marker) {
        marker.remove();
        marker = null;
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addressModal');
    if (event.target === modal) {
        closeAddressModal();
    }
}

// Password validation functions
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrengthDiv = document.getElementById('password-strength');
    const passwordMatchDiv = document.getElementById('password-match');
    
    if (newPasswordInput && passwordStrengthDiv) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            
            passwordStrengthDiv.className = 'password-strength ' + strength.level;
        });
    }
    
    if (confirmPasswordInput && passwordMatchDiv) {
        confirmPasswordInput.addEventListener('input', function() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword === '') {
                passwordMatchDiv.textContent = '';
                passwordMatchDiv.className = 'password-match';
            } else if (newPassword === confirmPassword) {
                passwordMatchDiv.textContent = '✓ Passwords match';
                passwordMatchDiv.className = 'password-match match';
            } else {
                passwordMatchDiv.textContent = '✗ Passwords do not match';
                passwordMatchDiv.className = 'password-match no-match';
            }
        });
    }
});

function calculatePasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 6) score++;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    if (score < 3) return { level: 'weak' };
    if (score < 5) return { level: 'medium' };
    return { level: 'strong' };
}

// Mapbox integration for address selection
let map;
let marker;
let isMapInitialized = false;

function initMap() {
    try {
        // Check if Mapbox is loaded
        if (typeof mapboxgl === 'undefined') {
            throw new Error('Mapbox not loaded');
        }
        
        // Set the access token
        mapboxgl.accessToken = getMapboxAccessToken();
        
        // Check if map container exists
        const mapContainer = document.getElementById('map');
        if (!mapContainer) {
            throw new Error('Map container not found');
        }
        
        // If map already exists, destroy it first
        if (map) {
            map.remove();
            map = null;
            marker = null;
            isMapInitialized = false;
        }
        
        // Default to Manila, Philippines
        const manila = [120.9842, 14.5995]; // [lng, lat] for Mapbox
        
        map = new mapboxgl.Map({
            container: 'map',
            style: MAPBOX_CONFIG.style,
            center: manila,
            zoom: MAPBOX_CONFIG.defaultZoom
        });
        
        // Add navigation controls
        map.addControl(new mapboxgl.NavigationControl());
        
        isMapInitialized = true;
        
        // Add click listener to map
        map.on('click', (event) => {
            placeMarker([event.lngLat.lng, event.lngLat.lat]);
            reverseGeocode([event.lngLat.lng, event.lngLat.lat]);
        });
        
                // Use current location button - enhanced with automation
        const currentLocationBtn = document.getElementById('use_current_location');
        if (currentLocationBtn) {
            currentLocationBtn.addEventListener('click', async () => {
                // Check if geolocation is supported
                if (!navigator.geolocation) {
                    showGeolocationError('Geolocation is not supported by this browser.');
                    return;
                }

                // Check if we're in a secure context (HTTPS or localhost)
                if (!window.isSecureContext) {
                    showGeolocationError('Geolocation requires a secure connection (HTTPS). Please use the map to manually select your location.');
                    return;
                }

                // Show loading state
                currentLocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto-Filling Address...';
                currentLocationBtn.disabled = true;

                try {
                    // Use the automation agent to fill the entire address
                    const result = await addressAutomation.automateAddressFill();
                    
                    if (result.success) {
                        // If automation succeeded, also update the map
                        const { coordinates } = result;
                        const pos = [coordinates.longitude, coordinates.latitude];
                        
                        if (map && isMapInitialized) {
                            placeMarker(pos);
                            map.flyTo({
                                center: pos,
                                zoom: 15
                            });
                        }
                        
                        console.log('✅ Address automation completed successfully:', result);
                    }
                    
                } catch (error) {
                    console.error('Address automation failed:', error);
                    showGeolocationError(`Address automation failed: ${error.message}. Please fill the form manually.`);
                } finally {
                    // Reset button
                    currentLocationBtn.innerHTML = '<i class="fas fa-location-arrow"></i> Use Current Location';
                    currentLocationBtn.disabled = false;
                }
            });
        }
        
        console.log('Mapbox initialized successfully');
    } catch (error) {
        console.error('Error initializing Mapbox:', error);
        handleMapError();
    }
}

function placeMarker(location) {
    if (marker) {
        marker.setLngLat(location);
    } else {
        // Create a new marker
        marker = new mapboxgl.Marker({
            draggable: true
        })
        .setLngLat(location)
        .addTo(map);
        
        marker.on('dragend', () => {
            const lngLat = marker.getLngLat();
            reverseGeocode([lngLat.lng, lngLat.lat]);
            saveCoordinates(lngLat.lat, lngLat.lng);
        });
    }
    
    // Save coordinates to hidden fields
    saveCoordinates(location[1], location[0]); // location is [lng, lat], we need [lat, lng]
}

function saveCoordinates(latitude, longitude) {
    document.getElementById('latitude').value = latitude;
    document.getElementById('longitude').value = longitude;
    console.log('Coordinates saved:', latitude, longitude);
}

function geocodeAddress(addressData) {
    // Create a search query from the address data
    const query = `${addressData.address_line1}, ${addressData.city}, ${addressData.province}, Philippines`;
    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${getMapboxAccessToken()}&country=PH&limit=1`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Geocoding result:', data);
            if (data.features && data.features.length > 0) {
                const feature = data.features[0];
                const [lng, lat] = feature.center;
                
                if (map && isMapInitialized) {
                    const coordinates = [lng, lat];
                    placeMarker(coordinates);
                    map.flyTo({
                        center: coordinates,
                        zoom: 15
                    });
                }
                
                // Save the coordinates
                saveCoordinates(lat, lng);
            }
        })
        .catch(error => {
            console.error('Geocoding error:', error);
        });
}

function reverseGeocode(coordinates) {
    // Use Mapbox Geocoding API with more detailed parameters
    const [lng, lat] = coordinates;
    const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${getMapboxAccessToken()}&types=address,poi,place,locality,neighborhood,region,postcode&country=PH&limit=1`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Geocoding response:', data);
            if (data.features && data.features.length > 0) {
                updateAddressFields(data.features[0]);
            } else {
                console.log('No address found for this location');
                showAddressFillMessage('No detailed address found for this location. Please fill the form manually.');
            }
        })
        .catch(error => {
            console.error('Geocoding error:', error);
            showAddressFillMessage('Error getting address information. Please fill the form manually.');
        });
}

function updateAddressFields(feature) {
    // Enhanced Philippine address parsing for Mapbox geocoding response
    const context = feature.context || [];
    const placeName = feature.place_name || '';
    const text = feature.text || '';
    const properties = feature.properties || {};
    
    // Initialize address components
    let street = '';
    let barangay = '';
    let city = '';
    let province = '';
    let postalCode = '';
    let country = 'Philippines';
    
    // Log the full response for debugging
    console.log('Full Mapbox geocoding response:', feature);
    
    // Extract information from Mapbox context array
    context.forEach(item => {
        const id = item.id;
        const itemText = item.text;
        
        console.log('Processing context item:', { id, text: itemText });
        
        if (id.includes('place')) {
            city = itemText;
        } else if (id.includes('region')) {
            province = itemText;
        } else if (id.includes('postcode')) {
            postalCode = itemText;
        } else if (id.includes('district')) {
            // In Philippines, district might be barangay or municipality
            if (!barangay) {
                barangay = itemText;
            }
        } else if (id.includes('neighborhood')) {
            // Neighborhood in Philippines is often barangay
            if (!barangay) {
                barangay = itemText;
            }
        } else if (id.includes('locality')) {
            // Locality might be barangay or city
            if (!city) {
                city = itemText;
            } else if (!barangay) {
                barangay = itemText;
            }
        }
    });
    
    // If no city found in context, use the main text
    if (!city && text) {
        city = text;
    }
    
    // Extract street address from place_name
    if (placeName) {
        const parts = placeName.split(',');
        if (parts.length > 0) {
            // First part is usually the street address
            street = parts[0].trim();
            
            // Try to extract more specific components from place_name
            const placeParts = placeName.split(',').map(part => part.trim());
            
            // Look for barangay indicators
            for (let part of placeParts) {
                if (part.toLowerCase().includes('barangay') || part.toLowerCase().includes('brgy')) {
                    barangay = part.replace(/barangay|brgy/gi, '').trim();
                    break;
                }
            }
        }
    }
    
    // Enhanced barangay detection using Philippine patterns
    if (!barangay && placeName) {
        const barangayPatterns = [
            /barangay\s+([^,]+)/i,
            /brgy\.?\s+([^,]+)/i,
            /bgy\.?\s+([^,]+)/i
        ];
        
        for (let pattern of barangayPatterns) {
            const match = placeName.match(pattern);
            if (match && match[1]) {
                barangay = match[1].trim();
                break;
            }
        }
    }
    
    // Log extracted components for debugging
    console.log('Extracted Philippine address components:', {
        street, barangay, city, province, postalCode, country
    });
    
    // Track missing fields for logging
    const missingFields = [];
    
    // Update form fields with validation
    updateFieldWithLog('modal_address_line1', street, 'House Number and Street', missingFields);
    updateFieldWithLog('modal_barangay', barangay, 'Barangay', missingFields);
    updateFieldWithLog('modal_city', city, 'City/Municipality', missingFields);
    updateFieldWithLog('modal_postal_code', postalCode, 'ZIP Code', missingFields);
    
    // Set country to Philippines
    const countryField = document.getElementById('modal_country');
    if (countryField) {
        countryField.value = 'PH';
    }
    
    // Update province dropdown
    if (province) {
        updateProvinceDropdown(province);
    } else {
        missingFields.push('Province');
    }
    
    // Update city dropdown if city is found
    if (city) {
        updateCityDropdown(city);
    }
    
    // Log missing fields
    if (missingFields.length > 0) {
        console.log('Missing address fields from geocoding:', missingFields);
    }
    
    // Show success message with details
    const filledFields = ['House Number and Street', 'Barangay', 'City/Municipality', 'Province', 'ZIP Code']
        .filter(field => !missingFields.includes(field));
    
    let message = `Address information automatically filled: ${filledFields.join(', ')}.`;
    if (missingFields.length > 0) {
        message += ` Please manually fill: ${missingFields.join(', ')}.`;
    }
    
    showAddressFillMessage(message);
}

// Helper function to update form fields with logging
function updateFieldWithLog(fieldId, value, fieldName, missingFields) {
    const field = document.getElementById(fieldId);
    if (field && value) {
        field.value = value;
        console.log(`✓ Filled ${fieldName}: ${value}`);
    } else if (field && !value) {
        console.log(`⚠ Missing ${fieldName} from geocoding response`);
        missingFields.push(fieldName);
    }
}

function updateCityDropdown(selectedCity) {
    const cityDropdown = document.getElementById('modal_city_dropdown');
    if (!cityDropdown) return;
    
    const options = cityDropdown.options;
    
    for (let i = 0; i < options.length; i++) {
        if (options[i].text.toLowerCase().includes(selectedCity.toLowerCase())) {
            options[i].selected = true;
            break;
        }
    }
}

function updateCityDropdownForProvince(selectedProvince) {
    const provinceDropdown = document.getElementById('modal_province');
    const cityDropdown = document.getElementById('modal_city_dropdown');
    
    if (!provinceDropdown || !cityDropdown) return;
    
    // Set the province dropdown
    const provinceOptions = provinceDropdown.options;
    for (let i = 0; i < provinceOptions.length; i++) {
        if (provinceOptions[i].value === selectedProvince) {
            provinceOptions[i].selected = true;
            break;
        }
    }
    
    // Update city dropdown based on selected province
    updateCityDropdownOptions(selectedProvince);
}

function updateCityDropdownOptions(province) {
    const cityDropdown = document.getElementById('modal_city_dropdown');
    if (!cityDropdown) return;
    
    // Clear existing options except the first one
    cityDropdown.innerHTML = '<option value="">Select City/Municipality</option>';
    
    // Add cities based on province
    if (philippinesCities[province]) {
        philippinesCities[province].forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            cityDropdown.appendChild(option);
        });
    }
}

function updateProvinceDropdown(selectedProvince) {
    const provinceDropdown = document.getElementById('modal_province');
    if (!provinceDropdown) return;
    
    const options = provinceDropdown.options;
    
    for (let i = 0; i < options.length; i++) {
        const optionText = options[i].text.toLowerCase();
        const selectedText = selectedProvince.toLowerCase();
        
        // Check if the province matches or contains the selected text
        if (optionText.includes(selectedText) || selectedText.includes(optionText)) {
            options[i].selected = true;
            break;
        }
    }
}

function showAddressFillMessage(message) {
    // Create or update a success message
    let messageDiv = document.getElementById('address-fill-message');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'address-fill-message';
        messageDiv.style.cssText = `
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-size: 14px;
            display: none;
        `;
        
        // Insert after the map container
        const mapContainer = document.getElementById('map');
        if (mapContainer && mapContainer.parentNode) {
            mapContainer.parentNode.insertBefore(messageDiv, mapContainer.nextSibling);
        }
    }
    
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    // Hide the message after 3 seconds
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 3000);
}

function showGeolocationError(message) {
    // Create or update an error message
    let errorDiv = document.getElementById('geolocation-error-message');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'geolocation-error-message';
        errorDiv.style.cssText = `
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
            font-size: 14px;
            display: none;
            position: relative;
        `;
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = `
            position: absolute;
            top: 5px;
            right: 10px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #721c24;
        `;
        closeBtn.onclick = () => {
            errorDiv.style.display = 'none';
        };
        errorDiv.appendChild(closeBtn);
        
        // Insert after the map container
        const mapContainer = document.getElementById('map');
        if (mapContainer && mapContainer.parentNode) {
            mapContainer.parentNode.insertBefore(errorDiv, mapContainer.nextSibling);
        }
    }
    
    // Set the message content (excluding the close button)
    const messageSpan = document.createElement('span');
    messageSpan.textContent = message;
    errorDiv.innerHTML = '';
    errorDiv.appendChild(messageSpan);
    
    // Add close button back
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = `
        position: absolute;
        top: 5px;
        right: 10px;
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #721c24;
    `;
    closeBtn.onclick = () => {
        errorDiv.style.display = 'none';
    };
    errorDiv.appendChild(closeBtn);
    
    errorDiv.style.display = 'block';
    
    // Hide the message after 8 seconds (longer for error messages)
    setTimeout(() => {
        errorDiv.style.display = 'none';
    }, 8000);
}

// Philippines cities data
const philippinesCities = {
    'NCR': [
        'Manila', 'Quezon City', 'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 
        'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig', 'Pateros', 
        'San Juan', 'Taguig', 'Valenzuela'
    ],
    'Central Luzon': [
        'Angeles City', 'Bataan', 'Bulacan', 'Nueva Ecija', 'Pampanga', 'Tarlac', 'Zambales'
    ],
    'CALABARZON': [
        'Batangas', 'Cavite', 'Laguna', 'Quezon', 'Rizal'
    ],
    'Central Visayas': [
        'Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Bohol', 'Negros Oriental', 'Siquijor'
    ],
    'Davao': [
        'Davao City', 'Davao del Norte', 'Davao del Sur', 'Davao Occidental', 'Davao Oriental'
    ]
    // Add more provinces and cities as needed
};

// Update city dropdown when province changes
document.getElementById('modal_province').addEventListener('change', function() {
    const cityDropdown = document.getElementById('modal_city_dropdown');
    const selectedProvince = this.value;
    
    // Clear existing options
    cityDropdown.innerHTML = '<option value="">Select City/Municipality</option>';
    
    if (philippinesCities[selectedProvince]) {
        philippinesCities[selectedProvince].forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            cityDropdown.appendChild(option);
        });
    }
});

// Handle map loading errors
function handleMapError() {
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center;">
                <div style="font-size: 48px; color: #6c757d; margin-bottom: 15px;">🗺️</div>
                <h4 style="color: #495057; margin-bottom: 10px;">Map Not Available</h4>
                <p style="color: #6c757d; margin-bottom: 15px; font-size: 14px;">Please enter your address manually using the form fields below.</p>
                <small style="color: #adb5bd;">To enable map selection, add your Mapbox access token to the code.</small>
            </div>
        `;
    }
    
    // Hide the "Use Current Location" button
    const currentLocationBtn = document.getElementById('use_current_location');
    if (currentLocationBtn) {
        currentLocationBtn.style.display = 'none';
    }
}

// Override the existing showAddAddressForm function
function showAddAddressForm() {
    document.getElementById('modalTitle').textContent = 'Add New Address';
    document.getElementById('addressForm').reset();
    document.querySelector('input[name="action"]').value = 'add_address';
    document.getElementById('address_id').value = '';
    document.getElementById('addressModal').style.display = 'block';
    
    // Reset map container
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        mapContainer.innerHTML = '<div style="height: 100%; width: 100%; display: flex; align-items: center; justify-content: center; color: #666;">Loading map...</div>';
    }
    
    // Show current location button
    const currentLocationBtn = document.getElementById('use_current_location');
    if (currentLocationBtn) {
        currentLocationBtn.style.display = 'block';
    }
    
    // Initialize map after modal is shown
    setTimeout(() => {
        if (typeof mapboxgl !== 'undefined') {
            initMap();
        } else {
            // If Mapbox is not loaded, show fallback
            handleMapError();
        }
    }, 200);
}
</script>

<!-- Mapbox Configuration -->
<script src="google_maps_config.js"></script>

<!-- Mapbox API -->
<script>
// Load Mapbox API dynamically
function loadMapbox() {
    if (isMapboxTokenSet()) {
        // Load Mapbox CSS
        const link = document.createElement('link');
        link.href = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
        
        // Load Mapbox JS
        const script = document.createElement('script');
        script.src = 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js';
        script.async = true;
        script.defer = true;
        script.onerror = handleMapError;
        script.onload = () => {
            console.log('Mapbox loaded successfully');
        };
        document.head.appendChild(script);
    } else {
        console.log('Mapbox access token not set. Using fallback mode.');
        handleMapError();
    }
}

// Address Automation Agent for Philippines
class PhilippineAddressAutomation {
    constructor() {
        this.isProcessing = false;
        this.logs = [];
    }

    // Main automation function - retrieves geolocation and fills address modal
    async automateAddressFill() {
        if (this.isProcessing) {
            console.log('Address automation already in progress');
            return;
        }

        this.isProcessing = true;
        this.logs = [];
        
        try {
            console.log('🚀 Starting Philippine Address Automation');
            this.log('Starting address automation process');

            // Step 1: Get user's current geolocation
            const coordinates = await this.getCurrentLocation();
            if (!coordinates) {
                throw new Error('Unable to retrieve current location');
            }

            this.log(`Retrieved coordinates: ${coordinates.latitude}, ${coordinates.longitude}`);

            // Step 2: Perform reverse geocoding
            const addressData = await this.performReverseGeocoding(coordinates);
            if (!addressData) {
                throw new Error('Reverse geocoding failed');
            }

            this.log('Reverse geocoding completed successfully');

            // Step 3: Map results to modal fields
            const mappingResult = this.mapAddressToModal(addressData);
            this.log(`Address mapping completed. Filled: ${mappingResult.filledFields.length}, Missing: ${mappingResult.missingFields.length}`);

            // Step 4: Validate and confirm modal population
            const validationResult = this.validateModalPopulation();
            this.log(`Modal validation: ${validationResult.isComplete ? 'Complete' : 'Incomplete'}`);

            // Step 5: Show results to user
            this.showAutomationResults(mappingResult, validationResult);

            return {
                success: true,
                coordinates,
                addressData,
                mappingResult,
                validationResult,
                logs: this.logs
            };

        } catch (error) {
            console.error('Address automation failed:', error);
            this.log(`❌ Automation failed: ${error.message}`);
            this.showAutomationError(error.message);
            return {
                success: false,
                error: error.message,
                logs: this.logs
            };
        } finally {
            this.isProcessing = false;
        }
    }

    // Step 1: Retrieve user's current geolocation
    async getCurrentLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported by browser'));
                return;
            }

            if (!window.isSecureContext) {
                reject(new Error('Geolocation requires HTTPS connection'));
                return;
            }

            this.log('Requesting current location...');
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const coords = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };
                    this.log(`✅ Location retrieved: ${coords.latitude}, ${coords.longitude}`);
                    resolve(coords);
                },
                (error) => {
                    let errorMessage = 'Failed to get location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Permission denied';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Position unavailable';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Request timeout';
                            break;
                        default:
                            errorMessage += 'Unknown error';
                            break;
                    }
                    reject(new Error(errorMessage));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        });
    }

    // Step 2: Perform reverse geocoding
    async performReverseGeocoding(coordinates) {
        const { latitude, longitude } = coordinates;
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${longitude},${latitude}.json?access_token=${getMapboxAccessToken()}&types=address,poi,place,locality,neighborhood,region,postcode&country=PH&limit=1`;
        
        this.log('Performing reverse geocoding...');
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            if (!data.features || data.features.length === 0) {
                throw new Error('No address found for this location');
            }

            const feature = data.features[0];
            this.log('✅ Reverse geocoding successful');
            return feature;
        } catch (error) {
            this.log(`❌ Reverse geocoding failed: ${error.message}`);
            throw error;
        }
    }

    // Step 3: Map address data to modal fields
    mapAddressToModal(addressData) {
        this.log('Mapping address data to modal fields...');
        
        const filledFields = [];
        const missingFields = [];
        
        // Extract Philippine address components
        const addressComponents = this.extractPhilippineAddress(addressData);
        
        // Map to modal fields
        const fieldMappings = [
            { 
                fieldId: 'modal_address_line1', 
                value: addressComponents.street, 
                name: 'House Number and Street',
                required: true
            },
            { 
                fieldId: 'modal_barangay', 
                value: addressComponents.barangay, 
                name: 'Barangay',
                required: false
            },
            { 
                fieldId: 'modal_city', 
                value: addressComponents.city, 
                name: 'City/Municipality',
                required: true
            },
            { 
                fieldId: 'modal_postal_code', 
                value: addressComponents.postalCode, 
                name: 'ZIP Code',
                required: true
            }
        ];

        // Update form fields
        fieldMappings.forEach(mapping => {
            const field = document.getElementById(mapping.fieldId);
            if (field && mapping.value) {
                field.value = mapping.value;
                filledFields.push(mapping.name);
                this.log(`✅ Filled ${mapping.name}: ${mapping.value}`);
            } else {
                if (mapping.required) {
                    missingFields.push(mapping.name);
                }
                this.log(`⚠️ ${mapping.required ? 'Missing' : 'Optional'} ${mapping.name}`);
            }
        });

        // Set country to Philippines
        const countryField = document.getElementById('modal_country');
        if (countryField) {
            countryField.value = 'PH';
            filledFields.push('Country');
            this.log('✅ Set Country: Philippines');
        }

        // Update province
        if (addressComponents.province) {
            updateProvinceDropdown(addressComponents.province);
            filledFields.push('Province');
            this.log(`✅ Set Province: ${addressComponents.province}`);
        } else {
            missingFields.push('Province');
        }

        // Update city dropdown
        if (addressComponents.city) {
            updateCityDropdown(addressComponents.city);
        }

        return { filledFields, missingFields, addressComponents };
    }

    // Extract Philippine address components from Mapbox response
    extractPhilippineAddress(feature) {
        const context = feature.context || [];
        const placeName = feature.place_name || '';
        const text = feature.text || '';
        
        let street = '';
        let barangay = '';
        let city = '';
        let province = '';
        let postalCode = '';
        
        // Extract from context
        context.forEach(item => {
            const id = item.id;
            const itemText = item.text;
            
            if (id.includes('place')) {
                city = itemText;
            } else if (id.includes('region')) {
                province = itemText;
            } else if (id.includes('postcode')) {
                postalCode = itemText;
            } else if (id.includes('district') || id.includes('neighborhood') || id.includes('locality')) {
                if (!barangay) {
                    barangay = itemText;
                }
            }
        });
        
        // Extract street from place_name
        if (placeName) {
            const parts = placeName.split(',');
            if (parts.length > 0) {
                street = parts[0].trim();
            }
        }
        
        // Enhanced barangay detection
        if (!barangay && placeName) {
            const barangayPatterns = [
                /barangay\s+([^,]+)/i,
                /brgy\.?\s+([^,]+)/i,
                /bgy\.?\s+([^,]+)/i
            ];
            
            for (let pattern of barangayPatterns) {
                const match = placeName.match(pattern);
                if (match && match[1]) {
                    barangay = match[1].trim();
                    break;
                }
            }
        }
        
        return { street, barangay, city, province, postalCode };
    }

    // Step 4: Validate modal population
    validateModalPopulation() {
        this.log('Validating modal population...');
        
        const requiredFields = [
            'modal_address_line1',
            'modal_city', 
            'modal_postal_code'
        ];
        
        const missingRequired = [];
        const filledFields = [];
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.value.trim()) {
                filledFields.push(fieldId);
            } else {
                missingRequired.push(fieldId);
            }
        });
        
        const isComplete = missingRequired.length === 0;
        this.log(`✅ Modal validation: ${filledFields.length}/${requiredFields.length} required fields filled`);
        
        return {
            isComplete,
            filledFields: filledFields.length,
            totalRequired: requiredFields.length,
            missingRequired
        };
    }

    // Show automation results to user
    showAutomationResults(mappingResult, validationResult) {
        const { filledFields, missingFields } = mappingResult;
        
        let message = `🎉 Address automation completed! `;
        message += `Filled: ${filledFields.join(', ')}. `;
        
        if (missingFields.length > 0) {
            message += `Please manually fill: ${missingFields.join(', ')}. `;
        }
        
        if (validationResult.isComplete) {
            message += '✅ All required fields are complete.';
        } else {
            message += '⚠️ Some required fields need attention.';
        }
        
        showAddressFillMessage(message);
        this.log(`✅ Automation completed: ${message}`);
    }

    // Show automation error
    showAutomationError(errorMessage) {
        showGeolocationError(`Address automation failed: ${errorMessage}. Please fill the form manually.`);
    }

    // Logging function
    log(message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${message}`;
        console.log(logEntry);
        this.logs.push(logEntry);
    }
}

// Initialize automation agent
const addressAutomation = new PhilippineAddressAutomation();

// Load Mapbox when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadMapbox();
    
    // Show HTTPS notice if not in secure context
    if (!window.isSecureContext) {
        const notice = document.getElementById('geolocation-notice');
        if (notice) {
            notice.style.display = 'block';
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>


