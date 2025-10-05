<?php
// Email service using Gmail SMTP
require_once 'includes/email_config.php';

class EmailService {
    private $config;
    
    public function __construct() {
        $this->config = require 'includes/email_config.php';
    }
    
    public function sendVerificationEmail($userEmail, $userName, $verificationCode) {
        try {
            $verificationUrl = $this->config['verification']['base_url'] . "/verify-email.php";
            
            $subject = 'Verify Your Email - EyeLux Store';
            $message = $this->getVerificationEmailTemplate($userName, $verificationCode, $verificationUrl);
            
            return $this->sendSMTPEmail($userEmail, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Verification email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
        try {
            $resetUrl = $this->config['password_reset']['base_url'] . "/reset-password.php?token=" . $resetToken;
            
            $subject = 'Reset Your Password - EyeLux Store';
            $message = $this->getPasswordResetEmailTemplate($userName, $resetUrl);
            
            return $this->sendSMTPEmail($userEmail, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Password reset email error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendSMTPEmail($to, $subject, $message) {
        $config = $this->config['smtp'];
        
        // Create SMTP connection
        $smtp = fsockopen($config['host'], $config['port'], $errno, $errstr, 30);
        if (!$smtp) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read initial response
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP server error: $response");
            fclose($smtp);
            return false;
        }
        
        // Send EHLO command
        fputs($smtp, "EHLO localhost\r\n");
        $response = fgets($smtp, 512);
        
        // Read all EHLO responses
        while (substr($response, 3, 1) == '-') {
            $response = fgets($smtp, 512);
        }
        
        // Start TLS
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '220') {
            error_log("STARTTLS failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Enable crypto
        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("TLS encryption failed");
            fclose($smtp);
            return false;
        }
        
        // Send EHLO again after TLS
        fputs($smtp, "EHLO localhost\r\n");
        $response = fgets($smtp, 512);
        
        // Read all EHLO responses after TLS
        while (substr($response, 3, 1) == '-') {
            $response = fgets($smtp, 512);
        }
        
        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '334') {
            error_log("AUTH LOGIN failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send username
        fputs($smtp, base64_encode($config['username']) . "\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '334') {
            error_log("Username authentication failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send password
        fputs($smtp, base64_encode($config['password']) . "\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '235') {
            error_log("Password authentication failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send MAIL FROM
        fputs($smtp, "MAIL FROM: <" . $config['from_email'] . ">\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '250') {
            error_log("MAIL FROM failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '250') {
            error_log("RCPT TO failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '354') {
            error_log("DATA command failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send email headers and body
        $headers = "From: " . $config['from_name'] . " <" . $config['from_email'] . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        fputs($smtp, $headers . $message . "\r\n.\r\n");
        $response = fgets($smtp, 512);
        if (substr($response, 0, 3) != '250') {
            error_log("Email sending failed: $response");
            fclose($smtp);
            return false;
        }
        
        // Send QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
    }
    
    private function getVerificationEmailTemplate($userName, $verificationCode, $verificationUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .verification-code { 
                    background: #007bff; 
                    color: white; 
                    font-size: 32px; 
                    font-weight: bold; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 10px; 
                    margin: 20px 0; 
                    letter-spacing: 5px;
                }
                .button { display: inline-block; background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
                .instructions { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to EyeLux Store!</h1>
                </div>
                <div class='content'>
                    <h2>Hello $userName,</h2>
                    <p>Thank you for registering with EyeLux Store. To complete your registration and start shopping, please verify your email address using the PIN code below:</p>
                    
                    <div class='verification-code'>$verificationCode</div>
                    
                    <div class='instructions'>
                        <h3>How to verify:</h3>
                        <ol>
                            <li>Enter the 6-digit PIN code above in the verification modal</li>
                            <li>Click 'Verify Email'</li>
                            <li>You'll be automatically logged in after verification</li>
                        </ol>
                    </div>
                    
                    <p><strong>Important:</strong> This verification PIN will expire in 10 minutes for security reasons.</p>
                    
                    <p>If you didn't create an account with us, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 EyeLux Store. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPasswordResetEmailTemplate($userName, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .button { display: inline-block; background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello $userName,</h2>
                    <p>We received a request to reset your password for your EyeLux Store account. Click the button below to reset your password:</p>
                    
                    <a href='$resetUrl' class='button'>Reset Password</a>
                    
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 3px;'>$resetUrl</p>
                    
                    <p>This reset link will expire in 1 hour for security reasons.</p>
                    
                    <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                </div>
                <div class='footer'>
                    <p>© 2024 EyeLux Store. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>
