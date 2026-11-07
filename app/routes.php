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

    ['GET',  '/items',                'items_search'],
    ['GET',  '/items/new',            'item_new_form'],
    ['POST', '/items/new',            'item_create_submit'],
    ['GET',  '/items/{id}',           'item_show'],
    ['GET',  '/items/{id}/edit',      'item_edit_form'],
    ['POST', '/items/{id}/edit',      'item_edit_submit'],
    ['POST', '/items/{id}/close',     'item_close_submit'],
    ['POST', '/items/{id}/recovered', 'item_recovered_submit'],
    ['GET',  '/my/items',             'my_items_index'],
];