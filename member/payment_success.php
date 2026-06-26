<?php
session_start();
require 'db.php';

/**
 * Payment Success Page
 * User is redirected here after completing payment on Paychangu
 * This is NOT where we verify payment - the webhook does that
 * This page just shows a success message while webhook confirms in background
 */

// Clear any previous errors
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Processing - Agrilink</title>
  <link rel="stylesheet" href="theme.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #fffbe6, #fef6d3);
      margin: 0;
      padding: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .payment-status {
      max-width: 500px;
      padding: 40px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
      text-align: center;
    }
    .success-icon {
      font-size: 60px;
      color: #2a9d8f;
      margin-bottom: 20px;
    }
    h2 {
      color: #2a9d8f;
      margin-top: 0;
    }
    .message {
      color: #555;
      line-height: 1.6;
      margin: 20px 0;
    }
    .loader {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #f4a261;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .next-step {
      background: #f4a261;
      color: white;
      padding: 12px 30px;
      border-radius: 6px;
      text-decoration: none;
      display: inline-block;
      margin-top: 20px;
      cursor: pointer;
      border: none;
      font-size: 16px;
      font-weight: bold;
    }
    .next-step:hover {
      background: #e89c4f;
    }
    .info {
      background: #f0f8f7;
      padding: 15px;
      border-left: 4px solid #2a9d8f;
      margin: 20px 0;
      text-align: left;
      font-size: 14px;
      color: #333;
    }
  </style>
</head>
<body>
  <div class="payment-status">
    <div class="success-icon">✓</div>
    <h2>Payment Received!</h2>
    
    <div class="message">
      <p>Thank you for your registration payment. Your account is being activated...</p>
      <div class="loader"></div>
    </div>

    <div class="info">
      <strong>What's next?</strong><br>
      Our system is processing your payment. You will receive a confirmation email shortly with your login credentials.
    </div>

    <p>
      <a href="login.php" class="next-step">Go to Login</a>
    </p>

    <p style="font-size: 12px; color: #999; margin-top: 30px;">
      If you don't receive a confirmation email within 5 minutes, please contact support.
    </p>
  </div>

  <script>
    // Auto-redirect to login after 5 seconds
    setTimeout(function() {
      window.location.href = 'login.php';
    }, 5000);
  </script>
</body>
</html>
