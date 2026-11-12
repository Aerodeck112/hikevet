<?php

return [
    // Versiunea platformei, afisata in admin si verificata de sistemul de actualizari.
    'version' => '0.1.0',

    // Copia directorului wp-content/uploads folosita la importul documentelor (optional).
    'wp_uploads_path' => env('WP_UPLOADS_PATH'),

    // Cheia publica Ed25519 (base64) cu care sunt semnate pachetele de actualizare.
    'update_public_key' => env('HIKEVET_UPDATE_PUBLIC_KEY', ''),

    // In afara productiei aplicatia se considera instalata (dezvoltare, teste automate).
    'assume_installed' => env('APP_ENV', 'production') !== 'production',
    'install_lock' => null,
    'install_env_path' => null,

    // Folosit doar de testele automate, pentru a aplica o actualizare intr-un director de proba.
    'update_base_path' => null,
];
