<?php

return [
    '/' => 'Public@index',
    '/login' => 'Public@index',
    '/register' => 'Public@register',
    '/logout' => 'Public@logout',

    '/auth/authenticate' => 'Auth@authenticate',
    '/auth/register' => 'Auth@register',
];