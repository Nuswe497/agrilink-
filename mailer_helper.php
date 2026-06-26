<?php
/**
 * Agrilink Mailer Helper
 * Provides reusable functions for sending emails via PHPMailer.
 * Requires PHPMailer to be at: member/PHPMailer/src/
 * 
 * Usage: require_once '/path/to/mailer_helper.php';
 *        sendAnnouncementToAllMembers($conn, $title, $message);
 *        sendInspectionEmail($conn, $user_id, $scheduled_date);
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── PHPMailer autoload ──────────────────────────────────────────────────────
// Locate PHPMailer relative to THIS file (project root)
$phpmailerBase = __DIR__ . '/member/PHPMailer/src/';
require_once $phpmailerBase . 'Exception.php';
require_once $phpmailerBase . 'PHPMailer.php';
require_once $phpmailerBase . 'SMTP.php';

// ── SMTP Configuration ──────────────────────────────────────────────────────
define('MAILER_HOST',       'smtp.gmail.com');
define('MAILER_USERNAME',   'busbooking28@gmail.com');   // SMTP login (app password account)
define('MAILER_PASSWORD',   'zghyrcfafjqsnuzh');         // Gmail App Password
define('MAILER_FROM_EMAIL', 'busbooking28@gmail.com');
define('MAILER_FROM_NAME',  'Agrilink Cooperative');
define('MAILER_REPLY_TO_EMAIL', 'livingstoniaagrilink@gmail.com');
define('MAILER_REPLY_TO_NAME',  'Livingstonia Agrilink Support');
define('MAILER_PORT',       587);

/**
 * Internal: create and configure a PHPMailer instance.
 */
function _createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAILER_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAILER_USERNAME;
    $mail->Password   = MAILER_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAILER_PORT;
    $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
    $mail->addReplyTo(MAILER_REPLY_TO_EMAIL, MAILER_REPLY_TO_NAME);
    $mail->isHTML(true);
    return $mail;
}

/**
 * Build a branded HTML email wrapper.
 */
function _wrapHtml(string $bodyContent): string {
    return "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #2a9d8f, #247b73); padding: 28px 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 1.6rem; letter-spacing: -0.5px; }
        .header p  { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 0.9rem; }
        .body { padding: 32px 36px; color: #2c3e50; line-height: 1.7; font-size: 1rem; }
        .body h2 { color: #2a9d8f; margin-top: 0; }
        .info-box { background: #f0faf9; border-left: 4px solid #2a9d8f; padding: 16px 20px; border-radius: 6px; margin: 20px 0; }
        .info-box p { margin: 0; }
        .footer { background: #2c3e50; color: rgba(255,255,255,0.7); text-align: center; padding: 18px 30px; font-size: 0.82rem; }
        .footer a { color: #f4a261; text-decoration: none; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>
          <h1>🐝 Agrilink Cooperative</h1>
          <p>Livingstonia Beekeeping Cooperative</p>
        </div>
        <div class='body'>
          {$bodyContent}
        </div>
        <div class='footer'>
          <p>&copy; " . date('Y') . " Agrilink Cooperative &nbsp;|&nbsp; <a href='mailto:livingstoniaagrilink@gmail.com'>livingstoniaagrilink@gmail.com</a></p>
          <p>This is an automated message — please do not reply directly to this email.</p>
        </div>
      </div>
    </body>
    </html>";
}

/**
 * Send an announcement email to ALL active members.
 *
 * @param mysqli  $conn      Active DB connection
 * @param string  $title     Announcement title
 * @param string  $message   Announcement message body
 * @return array  ['sent' => int, 'failed' => int, 'errors' => string[]]
 */
function sendAnnouncementToAllMembers(mysqli $conn, string $title, string $message): array {
    // Fetch all active members with emails
    $res = $conn->query("SELECT full_name, email FROM users WHERE role = 'member' AND status = 'active' AND email IS NOT NULL AND email != ''");
    if (!$res) {
        return ['sent' => 0, 'failed' => 0, 'errors' => ['DB error: ' . $conn->error]];
    }

    $members = $res->fetch_all(MYSQLI_ASSOC);
    $sent = 0; $failed = 0; $errors = [];

    $htmlBody = _wrapHtml("
        <h2>📢 " . htmlspecialchars($title) . "</h2>
        <p>Dear Member,</p>
        <p>We have an important announcement from the Agrilink Cooperative:</p>
        <div class='info-box'>
          <p>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
        <p>Please log in to your member portal to view this and other updates.</p>
        <p>Best regards,<br><strong>Agrilink Cooperative Management</strong></p>
    ");

    foreach ($members as $member) {
        try {
            $mail = _createMailer();
            $mail->addAddress($member['email'], $member['full_name'] ?? '');
            $mail->Subject = "📢 Agrilink Announcement: " . $title;
            $mail->Body    = $htmlBody;
            $mail->AltBody = "Announcement: {$title}\n\n{$message}\n\n-- Agrilink Cooperative";
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            $failed++;
            $errors[] = "Failed to send to {$member['email']}: " . $e->getMessage();
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
}

/**
 * Send an inspection schedule notification to a specific member.
 *
 * @param mysqli  $conn           Active DB connection
 * @param int     $user_id        Member's user_id
 * @param string  $scheduled_date Date string (Y-m-d)
 * @param string  $hive_id        Optional hive identifier
 * @return bool   True on success, false on failure
 * @param string  &$errorMsg      Error message if failure
 */
function sendInspectionEmail(mysqli $conn, int $user_id, string $scheduled_date, string $hive_id = '', string &$errorMsg = ''): bool {
    // Fetch member details
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    if (!$member || empty($member['email'])) {
        $errorMsg = "Member not found or has no email address.";
        return false;
    }

    $formattedDate = date('l, d F Y', strtotime($scheduled_date));
    $hiveInfo = !empty($hive_id) ? "<p><strong>Hive:</strong> " . htmlspecialchars($hive_id) . "</p>" : '';

    $htmlBody = _wrapHtml("
        <h2>🗓️ Hive Inspection Scheduled</h2>
        <p>Dear " . htmlspecialchars($member['full_name']) . ",</p>
        <p>We would like to inform you that a hive inspection has been scheduled for you.</p>
        <div class='info-box'>
          <p><strong>📅 Date:</strong> {$formattedDate}</p>
          {$hiveInfo}
          <p><strong>Status:</strong> Pending</p>
        </div>
        <p>Please ensure you are available on this date. If you have any questions or need to reschedule, please contact the cooperative office as soon as possible.</p>
        <p>You can view this inspection in your member portal under the <strong>Inspections</strong> section.</p>
        <p>Best regards,<br><strong>Agrilink Cooperative Management</strong></p>
    ");

    try {
        $mail = _createMailer();
        $mail->addAddress($member['email'], $member['full_name']);
        $mail->Subject = "🗓️ Agrilink: Inspection Scheduled for {$formattedDate}";
        $mail->Body    = $htmlBody;
        $mail->AltBody = "Dear {$member['full_name']},\n\nA hive inspection has been scheduled for you on {$formattedDate}.\n\nPlease log in to your portal for details.\n\n-- Agrilink Cooperative";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = "Email error: " . $e->getMessage();
        return false;
    }
}

/**
 * Send a welcome email with credentials to a newly created user.
 *
 * @param mysqli  $conn           Active DB connection
 * @param int     $user_id        New member's user_id
 * @param string  $plainPassword  The plaintext password to share
 * @param string  &$errorMsg      Error message if failure
 * @return bool   True on success, false on failure
 */
function sendWelcomeEmail(mysqli $conn, int $user_id, string $plainPassword, string &$errorMsg = ''): bool {
    // Fetch member details
    $stmt = $conn->prepare("SELECT full_name, email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    if (!$member || empty($member['email'])) {
        $errorMsg = "User not found or has no email address.";
        return false;
    }

    $roleName = ucfirst($member['role']);
    $loginUrl = "http://localhost/New/Agrilink/Agrilink/member/login.php"; // Update this to production URL if needed

    $htmlBody = _wrapHtml("
        <h2>👋 Welcome to Agrilink!</h2>
        <p>Dear " . htmlspecialchars($member['full_name']) . ",</p>
        <p>Your account has been successfully created in the <strong>Agrilink Beekeeping Management System</strong> as a <strong>{$roleName}</strong>.</p>
        
        <div class='info-box'>
          <p><strong>Your Login Credentials:</strong></p>
          <p style='margin-top: 10px;'>📧 <strong>Email:</strong> " . htmlspecialchars($member['email']) . "</p>
          <p>🔑 <strong>Password:</strong> <code style='background:#fff; padding:2px 6px; border:1px solid #ddd;'>" . htmlspecialchars($plainPassword) . "</code></p>
        </div>

        <p>You can now log in to the portal using the link below:</p>
        <p style='text-align: center; margin: 30px 0;'>
          <a href='{$loginUrl}' style='background: #2a9d8f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>Log In to Portal</a>
        </p>

        <p><small><em>For security reasons, we recommend changing your password immediately after your first login via the Profile settings.</em></small></p>

        <p>If you have any questions or require assistance, please don't hesitate to reach out to our support team.</p>
        <p>Welcome aboard!<br><strong>Agrilink Cooperative Management</strong></p>
    ");

    try {
        $mail = _createMailer();
        $mail->addAddress($member['email'], $member['full_name']);
        $mail->Subject = "👋 Welcome to Agrilink Cooperative!";
        $mail->Body    = $htmlBody;
        $mail->AltBody = "Welcome to Agrilink, {$member['full_name']}!\n\nYour account has been created. \nEmail: {$member['email']}\nPassword: {$plainPassword}\n\nLog in here: {$loginUrl}\n\n-- Agrilink Cooperative";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = "Email error: " . $member['email'] . " " . $e->getMessage();
        return false;
    }
}

/**
 * Send a cooperative contact message to admin email and admin dashboard.
 *
 * @param mysqli  $conn           Active DB connection
 * @param int     $sender_id      User who sent the message
 * @param string  $subject        Message subject
 * @param string  $message        Message body
 * @param string  &$errorMsg      Error message if failure
 * @return bool   True on success, false on failure
 */
function sendCooperativeContactEmail(mysqli $conn, int $sender_id, string $subject, string $message, string &$errorMsg = ''): bool {
    $stmt = $conn->prepare("SELECT full_name, email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $sender = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sender || empty($sender['email'])) {
        $errorMsg = "Sender not found or has no email address.";
        return false;
    }

    $adminResult = $conn->query("SELECT full_name, email FROM users WHERE role IN ('admin','treasurer','secretary') AND email IS NOT NULL AND email != ''");
    $recipients = [];
    if ($adminResult && $adminResult->num_rows > 0) {
        while ($row = $adminResult->fetch_assoc()) {
            $recipients[] = $row;
        }
    }

    if (empty($recipients)) {
        $recipients[] = ['full_name' => 'Agrilink Support', 'email' => 'livingstoniaagrilink@gmail.com'];
    }

    $senderName = htmlspecialchars($sender['full_name']);
    $senderEmail = $sender['email'];
    $senderRole = ucfirst($sender['role'] ?? 'User');

    $htmlBody = _wrapHtml(
        "<h2>📬 Cooperative Contact Message</h2>" .
        "<p>A user has sent a message to the cooperative administration.</p>" .
        "<div class='info-box'>" .
        "<p><strong>From:</strong> {$senderName}</p>" .
        "<p><strong>Email:</strong> " . htmlspecialchars($senderEmail) . "</p>" .
        "<p><strong>Role:</strong> {$senderRole}</p>" .
        "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>" .
        "</div>" .
        "<div class='info-box'>" .
        "<p>" . nl2br(htmlspecialchars($message)) . "</p>" .
        "</div>" .
        "<p>Please respond through the cooperative email address or directly to the member if appropriate.</p>" .
        "<p>Best regards,<br><strong>Agrilink Cooperative System</strong></p>"
    );

    try {
        $mail = _createMailer();
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient['email'], $recipient['full_name']);
        }
        $mail->addReplyTo($senderEmail, $senderName);
        $mail->Subject = "📬 Cooperative Contact: " . $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = "From: {$senderName} ({$senderEmail})\nRole: {$senderRole}\n\n" . $message . "\n\n-- Agrilink Cooperative";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        return false;
    }
}

/**
 * Send a profit distribution notification to a member.
 *
 * @param mysqli  $conn           Active DB connection
 * @param int     $user_id        Member's user_id
 * @param float   $amount         The profit amount paid
 * @param string  $wallet_info    Information about the payment method (Airtel Money / TNM Mpamba)
 * @param string  &$errorMsg      Error message if failure
 * @return bool   True on success, false on failure
 */
function sendProfitNotification(mysqli $conn, int $user_id, float $amount, string $wallet_info, string &$errorMsg = ''): bool {
    // Fetch member details
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    if (!$member || empty($member['email'])) {
        $errorMsg = "Member not found or has no email address.";
        return false;
    }

    $formattedAmount = number_format($amount, 2);
    $phone = htmlspecialchars($member['phone'] ?? 'N/A');

    $htmlBody = _wrapHtml("
        <h2>💰 Profit Payout Notification</h2>
        <p>Dear " . htmlspecialchars($member['full_name']) . ",</p>
        <p>We are pleased to inform you that your profit distribution for this period has been processed and successfully sent to your mobile wallet.</p>
        
        <div class='info-box'>
          <p><strong>Payout Details:</strong></p>
          <p style='margin-top: 10px;'>💵 <strong>Amount:</strong> MWK {$formattedAmount}</p>
          <p>📱 <strong>Mobile Wallet:</strong> {$wallet_info}</p>
          <p>📞 <strong>Phone Number:</strong> {$phone}</p>
          <p>📅 <strong>Date:</strong> " . date('d F Y') . "</p>
        </div>

        <p>Please check your mobile phone for the confirmation SMS from the respective mobile service provider (TNM Mpamba or Airtel Money).</p>
        
        <p>Thank you for your active participation and contributions to the Agrilink Cooperative. Together we grow!</p>
        
        <p>Best regards,<br><strong>Treasurer, Agrilink Cooperative</strong></p>
    ");

    try {
        $mail = _createMailer();
        $mail->addAddress($member['email'], $member['full_name']);
        $mail->Subject = "💰 Agrilink Profit Payout: MWK {$formattedAmount}";
        $mail->Body    = $htmlBody;
        $mail->AltBody = "Dear {$member['full_name']},\n\nYour profit distribution of MWK {$formattedAmount} has been sent to your mobile wallet ({$wallet_info}).\n\nThank you for your contributions!\n\n-- Agrilink Treasurer";
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = "Email error: " . $e->getMessage();
        return false;
    }
}



