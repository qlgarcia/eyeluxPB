<?php
// Email configuration for PHPMailer
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',  // Change to your SMTP server
        'port' => 587,
        'username' => 'qpboolaivar@tip.edu.ph',  // Your email
        'password' => 'uzit jovq gtqq bcrl',     // Your app password
        'encryption' => 'tls',
        'from_email' => 'qpboolaivar@tip.edu.ph',
        'from_name' => 'EyeLux Store'
    ],
    'verification' => [
        'expiry_hours' => 24,
        'base_url' => 'http://localhost'  // Change to your domain
    ],
    'password_reset' => [
        'expiry_hours' => 1,
        'base_url' => 'http://localhost'  // Change to your domain
    ]
];
?>





