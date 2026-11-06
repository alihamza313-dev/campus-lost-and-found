<?php
declare(strict_types=1);

return [
    ['GET',  '/',                 'home_index'],
    ['GET',  '/health',           'health_check'],

    ['GET',  '/register',         'register_form'],
    ['POST', '/register',         'register_submit'],
    ['GET',  '/login',            'login_form'],
    ['POST', '/login',            'login_submit'],
    ['POST', '/logout',           'logout_submit'],
    ['GET',  '/verify',           'verify_email'],
    ['POST', '/verify/resend',    'verification_resend'],

    ['GET',  '/dashboard',        'dashboard_index'],
    ['GET',  '/profile',          'profile_form'],
    ['POST', '/profile',          'profile_update'],
    ['POST', '/profile/password', 'password_update'],
];