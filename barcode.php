<?php
require __DIR__ . '/includes/code39.php';

$code = strtoupper(trim($_GET['code'] ?? ''));

header('Content-Type: image/svg+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');

$opts = [
    'module_width' => isset($_GET['w']) ? max(1, min(4, (float)$_GET['w'])) : 2.0,
    'height'       => isset($_GET['h']) ? max(30, min(200, (int)$_GET['h'])) : 60,
    'show_text'    => !empty($_GET['text']),
    'text'         => $code,
    'text_size'    => 11,
];

echo code39_svg($code, $opts);