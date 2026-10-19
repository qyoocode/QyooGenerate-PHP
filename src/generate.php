<?php
// BSD-3-Clause; see LICENSE. Historical PHP rendering geometry is preserved.

function qyoo_parse_code($code) {
    if (!is_string($code) || strlen($code) > 36) {
        throw new InvalidArgumentException('Expected a decimal ID or exactly 36 binary digits.');
    }
    if (is_binary_string($code)) return $code;
    if (!preg_match('/\A[0-9]+\z/', $code)) {
        throw new InvalidArgumentException('Invalid decimal ID.');
    }
    $decimal = ltrim($code, '0');
    if ($decimal === '') $decimal = '0';
    if (strlen($decimal) > 11 || (strlen($decimal) === 11 && strcmp($decimal, '68719476735') > 0)) {
        throw new InvalidArgumentException('ID exceeds 36 bits.');
    }
    if (PHP_INT_SIZE < 8) throw new RuntimeException('64-bit PHP is required.');
    return str_pad(decbin((int)$decimal), 36, '0', STR_PAD_LEFT);
}

function is_binary_string($input) {
    return is_string($input) && preg_match('/\A[01]{36}\z/', $input) === 1;
}

function number_to_binary_string($number) {
    if (!is_int($number) && !is_string($number)) throw new InvalidArgumentException('Expected a decimal integer.');
    // Decimal conversion must not interpret a decimal string as the binary input form.
    $value = (string)$number;
    if (strlen($value) > 11) throw new InvalidArgumentException('ID exceeds 36 bits.');
    return qyoo_parse_code($value);
}

function qyoo_png($bits) {
    if (!is_binary_string($bits)) throw new InvalidArgumentException('Expected 36 payload bits.');
    if (!extension_loaded('gd')) throw new RuntimeException('GD is required.');
    $palette = ['#b51dff','#E91E63','#9C27B0','#673AB7','#3F51B5','#2196F3',
        '#03A9F4','#00BCD4','#009688','#4CAF50','#8BC34A','#CDDC39',
        '#FFC107','#FF9800','#FF5722','#795548','#9E9E9E','#607D8B'];
    $background = $palette[array_rand($palette)];
    $light = get_brightness($background) >= 128;
    $image = generate_qyoo(2048, 2048, $light ? '#000000' : '#FFFFFF', $light ? '#FFFFFF' : '#000000', $background, $bits);
    $final = null;
    try {
        $final = imagecreatetruecolor(512, 512);
        if ($final === false) throw new RuntimeException('Image allocation failed.');
        imagesavealpha($final, true);
        imagealphablending($final, false);
        if (!imagecopyresampled($final, $image, 0, 0, 0, 0, 512, 512, 2048, 2048)) {
            throw new RuntimeException('Resampling failed.');
        }
        ob_start();
        try {
            if (!imagepng($final)) throw new RuntimeException('PNG encoding failed.');
            $png = ob_get_contents();
        } finally { ob_end_clean(); }
        return $png;
    } finally {
        imagedestroy($image);
        if ($final !== null && $final !== false) imagedestroy($final);
    }
}

function qyoo_cached_png($bits, $directory) {
    if (!is_binary_string($bits)) throw new InvalidArgumentException('Expected 36 payload bits.');
    if ($directory === '') return qyoo_png($bits);
    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Cache directory unavailable.');
    }
    // Serialize opening entries as well as I/O. On Docker Desktop bind mounts,
    // simultaneous creation/opening allowed overlapping exclusive file locks.
    // The directory exists before any worker opens it and provides a stable lock.
    $directoryLock = @fopen($directory, 'r');
    if ($directoryLock === false) throw new RuntimeException('Cache directory unavailable.');
    try {
        if (!flock($directoryLock, LOCK_EX)) throw new RuntimeException('Cache directory lock failed.');
        $file = @fopen($directory . '/' . $bits . '.png', 'c+b');
        if ($file === false) throw new RuntimeException('Cache file unavailable.');
        try {
            if (!flock($file, LOCK_EX)) throw new RuntimeException('Cache lock failed.');
            $stat = fstat($file);
            if ($stat === false || $stat['size'] > 1048576) throw new RuntimeException('Invalid cached image.');
            if ($stat['size'] > 0) {
                $png = stream_get_contents($file);
                if ($png === false || strlen($png) !== $stat['size']) throw new RuntimeException('Cache read failed.');
                // Check dimensions before decoding to bound allocations from corrupt cache data.
                $dimensions = @getimagesizefromstring($png);
                if ($dimensions !== false && $dimensions[0] === 512 && $dimensions[1] === 512
                    && $dimensions[2] === IMAGETYPE_PNG) {
                    $decoded = @imagecreatefromstring($png);
                    if ($decoded !== false) {
                        imagedestroy($decoded);
                        return $png;
                    }
                }
            }
            $png = qyoo_png($bits);
            // Recover interrupted or invalid generated cache content under the same lock.
            if (!rewind($file) || !ftruncate($file, 0)) throw new RuntimeException('Cache repair failed.');
            $offset = 0;
            while ($offset < strlen($png)) {
                $written = @fwrite($file, substr($png, $offset));
                if ($written === false || $written === 0) throw new RuntimeException('Cache write failed.');
                $offset += $written;
            }
            if (!fflush($file)) throw new RuntimeException('Cache flush failed.');
            return $png;
        } finally { flock($file, LOCK_UN); fclose($file); }
    } finally { flock($directoryLock, LOCK_UN); fclose($directoryLock); }
}

function qyoo_serve() {
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            http_response_code(405);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Use GET.\n";
            return;
        }
        $bits = qyoo_parse_code($_GET['code'] ?? null);
        $png = qyoo_cached_png($bits, getenv('QYOO_CACHE_DIR') ?: '');
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        echo $png;
    } catch (InvalidArgumentException $error) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Expected an ID from 0 through 68719476735 or exactly 36 binary digits.\n";
    } catch (Throwable $error) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Image generation unavailable.\n";
    }
}

function generate_qyoo($width, $height, $fg_color, $dot_color, $bg_color, $binary_string) {
    if ($width !== 2048 || $height !== 2048 || !is_binary_string($binary_string)) {
        throw new InvalidArgumentException("Expected a 2048px square and 36 payload bits.");
    }
    // Create an image resource
    $image = imagecreatetruecolor($width, $height);

    // Allocate colors
    $bg_col = allocate_color($image, $bg_color);
    $fg_col = allocate_color($image, $fg_color);
    $dot_col = allocate_color($image, $dot_color);

    // Fill the background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_col);

    // Calculate dimensions
    $radius = $width / 4;
    $square_size = $radius * 2 * 0.707; // Approximately radius * sqrt(2)
    $center_x = $width / 2;
    $center_y = $height / 2;

    // Draw the foreground circle
    imagefilledellipse($image, (int)$center_x, (int)$center_y, (int)($radius * 2), (int)($radius * 2), $fg_col);

    // Draw the foreground square
    $square_half = $square_size / 2;
    imagefilledrectangle(
        $image,
        $center_x,
        $center_y,
        $center_x + $radius,
        $center_y + $radius,
        $fg_col
    );

    // Draw the 6x6 grid of dots based on the binary string
    $grid_size = 6;
    $dot_radius = $square_size / (2 * $grid_size);

    // Add a small bit of padding
    $dot_radius -= (($dot_radius + 9) / 10);

    $binary_index = 0;
    for ($row = 0; $row < $grid_size; $row++) {
        for ($col = 0; $col < $grid_size; $col++) {
            $x = $center_x - $square_size / 2 + $col * $square_size / $grid_size + $dot_radius;
            $y = $center_y - $square_size / 2 + $row * $square_size / $grid_size + $dot_radius;

            if ($binary_string[$binary_index] == '1') {
                imagefilledellipse($image, (int)$x, (int)$y, (int)($dot_radius * 2), (int)($dot_radius * 2), $dot_col);
            }
            $binary_index++;
        }
    }

    return $image;
}

function allocate_color($image, $hex_color) {
    $hex_color = ltrim($hex_color, '#');
    if (strlen($hex_color) == 6) {
        list($r, $g, $b) = sscanf($hex_color, "%02x%02x%02x");
    } elseif (strlen($hex_color) == 3) {
        list($r, $g, $b) = sscanf($hex_color, "%1x%1x%1x");
        $r = $r * 17;
        $g = $g * 17;
        $b = $b * 17;
    } else {
        // Default to black if invalid color
        $r = $g = $b = 0;
    }
    return imagecolorallocate($image, $r, $g, $b);
}

function get_brightness($hex_color) {
    list($r, $g, $b) = sscanf(ltrim($hex_color, '#'), "%02x%02x%02x");
    return (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) qyoo_serve();
