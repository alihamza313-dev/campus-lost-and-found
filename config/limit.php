<?php
declare(strict_types=1);

return [
    'login_fail'         => ['max' => 5,  'window' => 900],    // per email AND per IP, 15 min
    'register_ip'        => ['max' => 5,  'window' => 3600],   // per IP, 1 hour
    'verification_email' => ['max' => 3,  'window' => 3600],   // 1 hour
    'listing'            => ['max' => 5,  'window' => 86400],  // per user, 1 day
    'claim'              => ['max' => 10, 'window' => 86400],  // per user, 1 day
    'message'            => ['max' => 20, 'window' => 3600],   // per user, 1 hour
    'report'             => ['max' => 10, 'window' => 86400],  // per user, 1 day
];