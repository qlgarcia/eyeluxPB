<?php
// Stripe Webhook Handler
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Stripe configuration
define('STRIPE_SECRET_KEY', 'sk_test_your_secret_key_here');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret_here');

// Get the raw POST data
$payload = @file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $signature,
        STRIPE_WEBHOOK_SECRET
    );
} catch (Exception $e) {
    http_response_code(400);
    error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
    exit('Webhook signature verification failed');
}

// Handle the event
$db = Database::getInstance();

switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        handlePaymentSuccess($paymentIntent, $db);
        break;
        
    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        handlePaymentFailure($paymentIntent, $db);
        break;
        
    case 'payment_method.attached':
        $paymentMethod = $event->data->object;
        handlePaymentMethodAttached($paymentMethod, $db);
        break;
        
    case 'customer.created':
        $customer = $event->data->object;
        handleCustomerCreated($customer, $db);
        break;
        
    case 'charge.dispute.created':
        $dispute = $event->data->object;
        handleDisputeCreated($dispute, $db);
        break;
        
    default:
        error_log('Unhandled Stripe webhook event: ' . $event->type);
}

http_response_code(200);
echo 'Webhook handled successfully';

function handlePaymentSuccess($paymentIntent, $db) {
    $order_id = $paymentIntent->metadata->order_id ?? null;
    
    if ($order_id) {
        // Update order status
        $db->execute(
            "UPDATE orders SET 
                status = 'paid', 
                payment_intent_id = ?, 
                payment_method = 'stripe',
                paid_at = NOW(),
                updated_at = NOW() 
             WHERE order_id = ?",
            [$paymentIntent->id, $order_id]
        );
        
        // Create notification
        $db->execute(
            "INSERT INTO notifications (user_id, type, title, message, created_at) 
             SELECT user_id, 'order_paid', 'Payment Successful', 
                    CONCAT('Your order #', order_number, ' has been paid successfully!'), 
                    NOW()
             FROM orders WHERE order_id = ?",
            [$order_id]
        );
        
        // Send confirmation email
        sendOrderConfirmationEmail($order_id, $db);
        
        error_log("Payment succeeded for order: $order_id");
    }
}

function handlePaymentFailure($paymentIntent, $db) {
    $order_id = $paymentIntent->metadata->order_id ?? null;
    
    if ($order_id) {
        // Update order status
        $db->execute(
            "UPDATE orders SET 
                status = 'payment_failed', 
                payment_intent_id = ?,
                updated_at = NOW() 
             WHERE order_id = ?",
            [$paymentIntent->id, $order_id]
        );
        
        // Create notification
        $db->execute(
            "INSERT INTO notifications (user_id, type, title, message, created_at) 
             SELECT user_id, 'payment_failed', 'Payment Failed', 
                    CONCAT('Payment failed for order #', order_number, '. Please try again.'), 
                    NOW()
             FROM orders WHERE order_id = ?",
            [$order_id]
        );
        
        error_log("Payment failed for order: $order_id");
    }
}

function handlePaymentMethodAttached($paymentMethod, $db) {
    $customer_id = $paymentMethod->customer;
    $user_id = $paymentMethod->metadata->user_id ?? null;
    
    if ($user_id) {
        // Store payment method for future use
        $db->execute(
            "INSERT INTO user_payment_methods (user_id, stripe_customer_id, stripe_payment_method_id, type, created_at) 
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             stripe_payment_method_id = VALUES(stripe_payment_method_id),
             updated_at = NOW()",
            [$user_id, $customer_id, $paymentMethod->id, $paymentMethod->type]
        );
        
        error_log("Payment method attached for user: $user_id");
    }
}

function handleCustomerCreated($customer, $db) {
    $user_id = $customer->metadata->user_id ?? null;
    
    if ($user_id) {
        // Store Stripe customer ID
        $db->execute(
            "UPDATE users SET stripe_customer_id = ?, updated_at = NOW() WHERE user_id = ?",
            [$customer->id, $user_id]
        );
        
        error_log("Stripe customer created for user: $user_id");
    }
}

function handleDisputeCreated($dispute, $db) {
    $charge_id = $dispute->charge;
    
    // Find order by charge ID
    $order = $db->fetchOne(
        "SELECT * FROM orders WHERE payment_intent_id IN (
            SELECT id FROM stripe_charges WHERE charge_id = ?
        )",
        [$charge_id]
    );
    
    if ($order) {
        // Update order status
        $db->execute(
            "UPDATE orders SET status = 'disputed', updated_at = NOW() WHERE order_id = ?",
            [$order['order_id']]
        );
        
        // Create notification
        $db->execute(
            "INSERT INTO notifications (user_id, type, title, message, created_at) 
             VALUES (?, 'dispute', 'Payment Disputed', 
                    CONCAT('A dispute has been created for order #', ?, '. Amount: $', ?), 
                    NOW())",
            [$order['user_id'], $order['order_number'], $dispute->amount / 100]
        );
        
        error_log("Dispute created for order: " . $order['order_id']);
    }
}

function sendOrderConfirmationEmail($order_id, $db) {
    // Get order details
    $order = $db->fetchOne(
        "SELECT o.*, u.email, u.first_name, u.last_name 
         FROM orders o 
         JOIN users u ON o.user_id = u.user_id 
         WHERE o.order_id = ?",
        [$order_id]
    );
    
    if ($order) {
        // Send email using your existing email service
        require_once 'includes/email_service.php';
        $emailService = new EmailService();
        
        $subject = "Order Confirmation - #" . $order['order_number'];
        $message = "Dear " . $order['first_name'] . ",\n\n";
        $message .= "Thank you for your order! Your payment has been processed successfully.\n\n";
        $message .= "Order Number: #" . $order['order_number'] . "\n";
        $message .= "Total Amount: $" . number_format($order['total_amount'], 2) . "\n\n";
        $message .= "We'll send you another email when your order ships.\n\n";
        $message .= "Best regards,\nEyeLux Team";
        
        $emailService->sendEmail($order['email'], $subject, $message);
    }
}
?>





