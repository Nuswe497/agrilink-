<?php

/**
 * Paychangu Transfer Helper
 * Handles mobile money payouts via Paychangu API.
 */

if (!defined('PAYCHANGU_SECRET_KEY')) {
    define('PAYCHANGU_SECRET_KEY', 'SEC-tbEk8HH1ZsOXWxjWhSEIJ9EBGZbaL1zd');
}

// Operator reference IDs
if (!defined('AIRTEL_REF_ID')) {
    define('AIRTEL_REF_ID', '20be6c20-adeb-4b5b-a7ba-0769820df4fb');
}
if (!defined('TNM_REF_ID')) {
    define('TNM_REF_ID', '27494cb5-ba9e-437f-a114-4e7a7686bcca');
}

/**
 * Dynamically fetch supported mobile money operators from Paychangu
 * @return array Associative array mapping short_code (tnm, airtel) to ref_id
 */
function getPaychanguOperators(): array {
    static $operatorCache = null;

    if ($operatorCache !== null) {
        return $operatorCache;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://api.paychangu.com/mobile-money/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'Authorization: Bearer ' . PAYCHANGU_SECRET_KEY,
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $operatorCache = [];
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['status']) && strtolower($data['status']) === 'success' && isset($data['data'])) {
            foreach ($data['data'] as $op) {
                if (isset($op['short_code']) && isset($op['ref_id'])) {
                    $operatorCache[strtolower($op['short_code'])] = $op['ref_id'];
                }
            }
        }
    }
    
    return $operatorCache;
}

/**
 * Send a mobile money payout via Paychangu.
 *
 * @param string $email      Member email (for logging/reference)
 * @param float  $amount     Amount in MWK
 * @param string $phone      Recipient phone number
 * @param string $operator   'airtel' or 'tnm'
 *
 * @return array ['success' => bool, 'transaction_id' => string, 'message' => string]
 */
function sendPaychanguTransfer(string $email, float $amount, string $phone, string $operator): array {
    // Clean phone number — strip spaces, leading zeros, country code mess
    $phone = preg_replace('/\s+/', '', $phone);

    // Fetch operators dynamically via GET endpoint
    $operators_list = getPaychanguOperators();
    $operator_key   = strtolower($operator);
    
    // Resolve operator ref ID (use dynamic list if available, otherwise fallback to hardcoded definitions)
    if (isset($operators_list[$operator_key])) {
        $operator_ref_id = $operators_list[$operator_key];
    } else {
        $operator_ref_id = ($operator_key === 'tnm') ? TNM_REF_ID : AIRTEL_REF_ID;
    }

    // Unique charge ID for this transaction
    $charge_id = 'PC-' . strtoupper(uniqid());

    $payload = json_encode([
        'mobile_money_operator_ref_id' => $operator_ref_id,
        'mobile'                       => $phone,
        'amount'                       => (string) round($amount),
        'charge_id'                    => $charge_id,
    ]);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://api.paychangu.com/mobile-money/payouts/initialize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'Authorization: Bearer ' . PAYCHANGU_SECRET_KEY,
        ],
    ]);

    $response  = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($curl);
    curl_close($curl);

    // cURL-level failure
    if ($curl_err) {
        return [
            'success'        => false,
            'transaction_id' => $charge_id,
            'message'        => 'cURL error: ' . $curl_err,
        ];
    }

    $data = json_decode($response, true);

    // Paychangu returns status:'success' on HTTP 200
    if ($http_code === 200 && isset($data['status']) && strtolower($data['status']) === 'success') {
        // Use their transaction string 'trans_id' or 'ref_id' if available, otherwise our charge_id
        $txn_id = $data['data']['trans_id'] ?? $data['data']['ref_id'] ?? $charge_id;
        return [
            'success'        => true,
            'transaction_id' => $txn_id,
            'message'        => $data['message'] ?? 'Transfer initiated.',
        ];
    }

    // API returned an error
    $err_msg = $data['message'] ?? ('HTTP ' . $http_code . ': ' . $response);
    return [
        'success'        => false,
        'transaction_id' => $charge_id,
        'message'        => $err_msg,
    ];
}

/**
 * Record a transfer attempt in the transfer_history table.
 */
function recordTransfer(
    mysqli $conn,
    int    $user_id,
    float  $amount,
    string $transaction_id,
    string $status,
    string $payment_method,
    string $error_msg = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO transfer_history
            (user_id, amount, transaction_id, status, payment_method, error_message)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status            = VALUES(status),
            confirmation_date = IF(VALUES(status) = 'completed', NOW(), NULL),
            error_message     = VALUES(error_message)
    ");
    $stmt->bind_param('idssss', $user_id, $amount, $transaction_id, $status, $payment_method, $error_msg);
    $stmt->execute();
    $stmt->close();
}