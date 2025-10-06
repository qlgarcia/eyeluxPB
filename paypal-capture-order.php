<?php
// Ensure clean JSON output only
ob_start();
ini_set('display_errors', '0');

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/paypal_config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$txStarted = false;

$paypal_order_id = $_POST['paypal_order_id'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);

if (!$paypal_order_id || !$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $db = Database::getInstance();

    // Capture PayPal order
    $access_token = getPayPalAccessToken();
    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . urlencode($paypal_order_id) . '/capture');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('PayPal capture failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $pp = json_decode($response, true);
    if ($status >= 400 || empty($pp['status']) || $pp['status'] !== 'COMPLETED') {
        throw new Exception('PayPal capture error');
    }

    // Update order as paid, clear cart items, create notifications
    $db->beginTransaction();
    $txStarted = true;

    $db->execute(
        "UPDATE orders SET status = 'confirmed', payment_status = 'paid', payment_method = 'paypal', updated_at = NOW() WHERE order_id = ? AND user_id = ?",
        [$order_id, $user_id]
    );

    // Clear cart for this user
    $db->execute("DELETE FROM cart WHERE user_id = ?", [$user_id]);

    createOrderPlacedNotification($order_id);

    $db->commit();

    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => true, 'redirect' => 'order-confirmation.php?order_id=' . $order_id]);
    exit;
} catch (Exception $e) {
    if (isset($db) && $txStarted) {
        $db->rollback();
    }
    http_response_code(400);
    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}


