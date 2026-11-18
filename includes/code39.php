<?php

/**
 * Code 39 barcode → SVG generator.
 * Standard 9-element (5 bars + 4 spaces, 3 wide) encoding, 12-module binary patterns.
 */

function code39_patterns(): array
{
    static $p = null;
    if ($p !== null) return $p;
    return $p = [
        '0' => '101001101101',
        '1' => '110100101011',
        '2' => '101100101011',
        '3' => '110110010101',
        '4' => '101001101011',
        '5' => '110100110101',
        '6' => '101100110101',
        '7' => '101001011011',
        '8' => '110100101101',
        '9' => '101100101101',
        'A' => '110101001011',
        'B' => '101101001011',
        'C' => '110110100101',
        'D' => '101011001011',
        'E' => '110101100101',
        'F' => '101101100101',
        'G' => '101010011011',
        'H' => '110101001101',
        'I' => '101101001101',
        'J' => '101011001101',
        'K' => '110101010011',
        'L' => '101101010011',
        'M' => '110110101001',
        'N' => '101011010011',
        'O' => '110101101001',
        'P' => '101101101001',
        'Q' => '101010110011',
        'R' => '110101011001',
        'S' => '101101011001',
        'T' => '101011011001',
        'U' => '110010101011',
        'V' => '100110101011',
        'W' => '110011010101',
        'X' => '100101101011',
        'Y' => '110010110101',
        'Z' => '100110110101',
        '-' => '100101011011',
        '.' => '110010101101',
        ' ' => '100110101101',
        '$' => '100100100101',
        '/' => '100100101001',
        '+' => '100101001001',
        '%' => '101001001001',
        '*' => '100101101101',
    ];
}

function code39_allowed(): string
{
    return '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
}

/** Returns null if valid, or an error string. */
function code39_validate(string $code): ?string
{
    if ($code === '') return 'Barcode value is empty.';
    if (strlen($code) > 64) return 'Barcode value is too long (max 64).';
    $allowed = code39_allowed();
    for ($i = 0, $n = strlen($code); $i < $n; $i++) {
        if (strpos($allowed, $code[$i]) === false) {
            return 'Unsupported character "' . $code[$i] . '".';
        }
    }
    return null;
}

/**
 * Build a clean Code 39 SVG.
 * @param string $code raw barcode value (start/stop * added internally)
 * @param array  $opts module_width, height, quiet, show_text, text, text_size
 */
function code39_svg(string $code, array $opts = []): string
{
    $code = strtoupper(trim($code));
    $err  = code39_validate($code);
    if ($err !== null) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="56" viewBox="0 0 220 56">'
            . '<rect width="220" height="56" fill="#FFFDFB"/>'
            . '<text x="110" y="30" text-anchor="middle" font-family="Inter,sans-serif" font-size="11" fill="#B3261E">'
            . htmlspecialchars($err) . '</text></svg>';
    }

    $patterns = code39_patterns();
    $full     = '*' . $code . '*';

    $mw       = (float)($opts['module_width'] ?? 2.0);
    $h        = (int)($opts['height'] ?? 56);
    $q        = (int)($opts['quiet'] ?? 12);
    $showText = !empty($opts['show_text']);
    $text     = (string)($opts['text'] ?? $code);
    $textSize = (int)($opts['text_size'] ?? 11);

    // Width calc
    $modules = 0;
    $n = strlen($full);
    for ($i = 0; $i < $n; $i++) {
        $modules += strlen($patterns[$full[$i]]);
        if ($i < $n - 1) $modules += 1;
    }
    $svgW = $modules * $mw + $q * 2;
    $svgH = $h + ($showText ? $textSize + 8 : 0);

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . round($svgW, 2) . '" height="' . $svgH . '"'
        . ' viewBox="0 0 ' . round($svgW, 2) . ' ' . $svgH . '" preserveAspectRatio="xMidYMid meet"'
        . ' role="img" aria-label="Barcode ' . htmlspecialchars($code) . '">';
    $svg .= '<rect width="100%" height="100%" fill="#FFFDFB"/>';

    $x = $q;
    for ($i = 0; $i < $n; $i++) {
        $pattern = $patterns[$full[$i]];
        $len = strlen($pattern);
        $j = 0;
        while ($j < $len) {
            if ($pattern[$j] === '1') {
                $start = $j;
                while ($j < $len && $pattern[$j] === '1') $j++;
                $w = ($j - $start) * $mw;
                $svg .= '<rect x="' . round($x + $start * $mw, 3) . '" y="0" width="' . round($w, 3)
                    . '" height="' . $h . '" fill="#292525"/>';
            } else {
                $j++;
            }
        }
        $x += $len * $mw;
        if ($i < $n - 1) $x += $mw; // inter-character gap
    }

    if ($showText) {
        $svg .= '<text x="' . round($svgW / 2, 2) . '" y="' . ($h + $textSize + 1) . '"'
            . ' text-anchor="middle" font-family="Inter,sans-serif" font-size="' . $textSize . '"'
            . ' font-weight="600" fill="#292525" letter-spacing="1">'
            . htmlspecialchars($text) . '</text>';
    }

    return $svg . '</svg>';
}
