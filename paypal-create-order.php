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
$session_id = session_id();
$txStarted = false;

try {
    $db = Database::getInstance();

    $cart_items = getCartItems($user_id, $session_id);
    if (empty($cart_items)) {
        throw new Exception('Cart is empty');
    }

    $cart_total = calculateCartTotal($cart_items);
    $tax_rate = 0.08;
    $tax_amount = $cart_total * $tax_rate;
    $shipping_amount = $cart_total >= 50 ? 0 : 9.99;
    $final_total = round($cart_total + $tax_amount + $shipping_amount, 2);

    $shipping_address_id = (int)($_POST['shipping_address'] ?? 0);
    $billing_address_id = (int)($_POST['billing_address'] ?? 0);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if (!$shipping_address_id || !$billing_address_id) {
        throw new Exception('Shipping and billing addresses are required');
    }

    $db->beginTransaction();
    $txStarted = true;

    $order_number = generateOrderNumber();
    $order_id = $db->insert(
        "INSERT INTO orders (user_id, order_number, total_amount, tax_amount, shipping_amount, 
                 shipping_address_id, billing_address_id, payment_method, payment_status, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$user_id, $order_number, $final_total, $tax_amount, $shipping_amount,
         $shipping_address_id, $billing_address_id, 'paypal', 'pending', $notes]
    );

    foreach ($cart_items as $item) {
        $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
        $db->insert(
            "INSERT INTO order_items (order_id, product_id, product_name, product_sku, 
                     quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$order_id, $item['product_id'], $item['product_name'], $item['sku'] ?? 'N/A',
             $item['quantity'], $price, $price * $item['quantity']]
        );
    }

    // Create PayPal order
    $access_token = getPayPalAccessToken();
    $orderPayload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string)$order_id,
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($final_total, 2, '.', '')
            ]
        ]],
        'application_context' => [
            'user_action' => 'PAY_NOW',
        ]
    ];

    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('PayPal create order failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $pp = json_decode($response, true);
    if ($status >= 400 || empty($pp['id'])) {
        throw new Exception('PayPal error: ' . ($pp['message'] ?? 'Unknown'));
    }

    // Store PayPal order ID temporarily on order
    $db->execute("UPDATE orders SET notes = CONCAT(COALESCE(notes,''), '\nPayPalOrderID: ', ?) WHERE order_id = ?", [$pp['id'], $order_id]);

    $db->commit();

    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['id' => $pp['id'], 'order_id' => $order_id]);
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


