<?php

/**
 * Paychangu Hosted Checkout Helper
 * Handles initialization and verification of Hosted Payment Page sessions.
 */

if (!defined('PAYCHANGU_SECRET_KEY')) {
    define('PAYCHANGU_SECRET_KEY', 'SEC-tbEk8HH1ZsOXWxjWhSEIJ9EBGZbaL1zd');
}

/**
 * Calculate total amount including processing fees.
 * Paychangu typically charges around 1.5% - 2.5% depending on the channel.
 * For this implementation, we will add a 2% processing fee.
 * 
 * @param float $baseAmount The original cost of the item.
 * @return float The total amount to be charged to the user.
 */
function calculateTotalWithFees(float $baseAmount): float {
    $feePercent = 0.02; // 2% fee
    return round($baseAmount * (1 + $feePercent));
}

/**
 * Initialize a Paychangu Hosted Checkout session.
 * 
 * @param float  $amount       Amount in MWK
 * @param string $email        Customer email
 * @param string $firstName    Customer first name
 * @param string $lastName     Customer last name
 * @param string $txRef        Unique transaction reference
 * @param string $callbackUrl  URL to redirect to after successful payment
 * 
 * @return array ['success' => bool, 'checkout_url' => string, 'message' => string]
 */
function initializePaychanguCheckout($amount, $email, $firstName, $lastName, $txRef, $callbackUrl) {
    $curl = curl_init();

    $payload = json_encode([
        'amount'      => round($amount),
        'currency'    => 'MWK',
        'email'       => $email,
        'first_name'  => $firstName,
        'last_name'   => $lastName,
        'callback_url'=> $callbackUrl, // Return URL after payment
        'return_url'  => $callbackUrl, // Fallback return URL
        'tx_ref'      => $txRef,
        'customization' => [
            'title'       => 'Agrilink Stock Purchase',
            'description' => 'Payment for supplier inventory stock.'
        ]
    ]);

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paychangu.com/payment",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . PAYCHANGU_SECRET_KEY,
            "Accept: application/json",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return [
            'success' => false,
            'message' => "cURL Error: " . $err
        ];
    }

    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] === 'success' && isset($result['data']['checkout_url'])) {
        return [
            'success'      => true,
            'checkout_url' => $result['data']['checkout_url'],
            'message'      => 'Checkout session initialized.'
        ];
    }

    return [
        'success' => false,
        'message' => $result['message'] ?? 'Unable to initialize checkout session.'
    ];
}

/**
 * Verify a Paychangu transaction status.
 * 
 * @param string $txRef The unique transaction reference or transaction ID.
 * @return array Transaction details from Paychangu.
 */
function verifyPaychanguTransaction($txRef) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paychangu.com/verify-payment/" . $txRef,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . PAYCHANGU_SECRET_KEY,
            "Accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['status' => 'error', 'message' => $err];
    }

    return json_decode($response, true);
}
