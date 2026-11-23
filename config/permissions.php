<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role to permission map
    |--------------------------------------------------------------------------
    |
    | The backend is the only authority on permissions. `GET /api/v1/me` returns
    | this list so the frontend can hide actions the user cannot perform. The
    | frontend check is cosmetic; every endpoint still authorizes server-side.
    |
    */

    'roles' => [

        'patient' => [
            'manage_family_members',
            'book_appointments',
            'cancel_own_appointments',
            'chat_with_centers',
            'buy_membership',
        ],

        'center_staff' => [
            'manage_center',
            'manage_doctors',
            'manage_availability',
            'confirm_appointments',
            'verify_cards',
            'chat_with_patients',
        ],

        'admin' => [
            'manage_users',
            'manage_centers',
            'manage_packages',
            'manage_cms',
        ],

    ],

];
