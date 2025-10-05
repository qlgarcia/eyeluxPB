<?php
// Admin order status management
require_once 'includes/header.php';

$page_title = 'Order Status Management';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$db = Database::getInstance();
$success_message = '';
$error_message = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = sanitizeInput($_POST['status'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    if ($order_id > 0 && !empty($new_status)) {
        $result = updateOrderStatus($order_id, $new_status, $message);
        
        if ($result) {
            $success_message = 'Order status updated successfully!';
        } else {
            $error_message = 'Failed to update order status.';
        }
    } else {
        $error_message = 'Please provide valid order ID and status.';
    }
}

// Get all orders with their current status
$orders = $db->fetchAll("
    SELECT o.*, u.first_name, u.last_name, u.email,
           COUNT(os.id) as status_count
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_status os ON o.order_id = os.order_id
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");

// Available order statuses
$statuses = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded'
];
?>

<style>
.order-status-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.status-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.form-group textarea {
    height: 80px;
    resize: vertical;
}

.btn {
    background: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn:hover {
    background: #0056b3;
}

.orders-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.orders-table table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th,
.orders-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.orders-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d1ecf1; color: #0c5460; }
.status-processing { background: #d4edda; color: #155724; }
.status-shipped { background: #cce5ff; color: #004085; }
.status-delivered { background: #d1f2eb; color: #0f5132; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-refunded { background: #e2e3e5; color: #383d41; }

.order-actions {
    display: flex;
    gap: 10px;
}

.btn-small {
    padding: 5px 10px;
    font-size: 12px;
}

.btn-success { background: #28a745; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-danger { background: #dc3545; }

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
</style>

<div class="order-status-container">
    <h1>📦 Order Status Management</h1>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="status-form">
        <h3>Update Order Status</h3>
        <form method="POST">
            <div class="form-group">
                <label for="order_id">Select Order:</label>
                <select name="order_id" id="order_id" required>
                    <option value="">Choose an order...</option>
                    <?php foreach ($orders as $order): ?>
                    <option value="<?php echo $order['order_id']; ?>">
                        #<?php echo htmlspecialchars($order['order_number']); ?> - 
                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?> - 
                        $<?php echo number_format($order['total_amount'], 2); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status">New Status:</label>
                <select name="status" id="status" required>
                    <option value="">Select status...</option>
                    <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="message">Additional Message (Optional):</label>
                <textarea name="message" id="message" placeholder="Add any additional information for the customer..."></textarea>
            </div>
            
            <button type="submit" name="update_status" class="btn">Update Status</button>
        </form>
    </div>
    
    <div class="orders-table">
        <h3 style="padding: 20px 20px 0; margin: 0;">All Orders</h3>
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Current Status</th>
                    <th>Order Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($order['email']); ?></small>
                    </td>
                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></td>
                    <td>
                        <div class="order-actions">
                            <button class="btn btn-small btn-success" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'confirmed')">
                                Confirm
                            </button>
                            <button class="btn btn-small btn-warning" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'shipped')">
                                Ship
                            </button>
                            <button class="btn btn-small btn-danger" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'cancelled')">
                                Cancel
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateOrderStatus(orderId, status) {
    if (confirm('Are you sure you want to update this order status to ' + status + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="status" value="${status}">
            <input type="hidden" name="update_status" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-refresh page every 30 seconds to show new orders
setInterval(function() {
    // Only refresh if no form is being filled
    if (!document.querySelector('form input:focus')) {
        location.reload();
    }
}, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>





