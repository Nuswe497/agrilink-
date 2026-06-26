<?php
session_start();
require 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            // Generate token
            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

            // Save token
            $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
            $ins->bind_param("iss", $user_id, $token, $expires);
            $ins->execute();
            $ins->close();

            // Build reset link
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $link = "http://{$host}{$path}/reset_password.php?token={$token}";

            $subject = 'Agrilink password reset';
            $message = "We received a password-reset request for your account.<br><br>" .
                       "If you requested this, click the link below to reset your password (expires in 1 hour):<br><br>" .
                       "<a href='{$link}'>{$link}</a><br><br>" .
                       "If you did not request a reset, you can ignore this email.";

            // Send email via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'busbooking28@gmail.com'; // your Gmail
                $mail->Password   = 'zghyrcfafjqsnuzh';   // Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('mwahimbaalinuswe@gmail.com', 'Agrilink');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                $mail->AltBody = strip_tags($message);

                $mail->send();
            } catch (Exception $e) {
                $error = "Mailer Error: {$mail->ErrorInfo}";
            }
        }

        $stmt->close();
        $conn->close();

        if ($error === '') {
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Forgot password</title>
  <style>
    body{font-family:Segoe UI, sans-serif;/* replaced *//* replaced */
      position: relative;
      overflow-x: hidden;
      display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{background:#fff;padding:24px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.08);max-width:440px;width:100%}
    input{width:100%;padding:10px;margin-top:6px;border:1px solid #ccc;border-radius:6px}
    button{width:100%;padding:10px;margin-top:12px;background: #f4a261;color:#fff;border:none;border-radius:6px}
    .alert{color:red;margin-bottom:10px}
  </style>
</head>
<body>
<?php include 'glow_bg.php'; ?>
  <div class="box">
    <h2>Forgot password</h2>
    <?php if ($error): ?>
      <div class="alert"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($sent): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Sent!',
                text: 'A password reset link has been sent to your email.',
                confirmButtonColor: '#2a9d8f'
            });
        });
      </script>
      <p style="color:green; font-weight:600;">A password reset link has been sent to your email.</p>
      <p><a href="login.php" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#f4a261; color:#fff; border-radius:6px; text-decoration:none;">Return to login</a></p>
    <?php else: ?>
      <form method="post">
        <label for="email">Enter your email address</label>
        <input id="email" type="email" name="email" required />
        <button type="submit">Send reset link</button>
      </form>
      <p style="margin-top:12px"><a href="login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
