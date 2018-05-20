<?php
    global $config;

    $config = array(
        'pusher_app_id' => "12345",
        'pusher_key'    => "abcde",
        'pusher_secret' => "fghij"
    );

    $asana_app = array(
        'localhost:8080' => array(
            'key' => 'abc123',
            'secret' => 'def456',
            'redirect' => 'http://localhost:8080/auth',
            'secure' => false
        ),
        'asana.kothar.net' => array(
            'key' => 'abc123',
            'secret' => 'def456',
            'redirect' => 'https://asana.kothar.net/auth',
            'secure' => true
        )
    );
