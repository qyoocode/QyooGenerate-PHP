<?php
// generate.php

// Ensure code parameter is present
if (!isset($_GET['code'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Code parameter is missing.";
    exit;
}

$code = $_GET['code'];

// Sanitize input and convert to binary string
if (ctype_digit($code)) {
    // It's a number, convert to binary string
    $binary_string = number_to_binary_string($code);
} elseif (is_binary_string($code)) {
    // It's a binary string
    $binary_string = $code;
} else {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid code.";
    exit;
}

// Caching
$cacheDir = __DIR__ . '/cache/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$fileName = $cacheDir . $binary_string . '.png';

if (file_exists($fileName)) {
    header('Content-Type: image/png');
    readfile($fileName);
    exit;
}

// Define a better color palette
$color_palette = array(
    '#b51dff', // Purple
    '#E91E63', // Pink
    '#9C27B0', // Deep Purple
    '#673AB7', // Indigo
    '#3F51B5', // Blue
    '#2196F3', // Light Blue
    '#03A9F4', // Cyan
    '#00BCD4', // Teal
    '#009688', // Green
    '#4CAF50', // Light Green
    '#8BC34A', // Lime
    '#CDDC39', // Yellow
    '#FFC107', // Amber
    '#FF9800', // Orange
    '#FF5722', // Deep Orange
    '#795548', // Brown
    '#9E9E9E', // Grey
    '#607D8B'  // Blue Grey
);

// Randomize the background color
$bg_color = $color_palette[array_rand($color_palette)];

// Adjust foreground and dot colors based on background brightness
$brightness = get_brightness($bg_color);

if ($brightness >= 128) {
    // Light background
    $fg_color = '#000000'; // Black foreground
    $dot_color = '#FFFFFF'; // White dots
} else {
    // Dark background
    $fg_color = '#FFFFFF'; // White foreground
    $dot_color = '#000000'; // Black dots
}

// Generate the Qyoo code image with anti-aliasing
$scale = 4; // Scaling factor for anti-aliasing
$final_width = 512;
$final_height = 512;
$width = $final_width * $scale;
$height = $final_height * $scale;

$image = generate_qyoo($width, $height, $fg_color, $dot_color, $bg_color, $binary_string);

// Resize the image down to the desired size
$final_image = imagecreatetruecolor($final_width, $final_height);

// Enable alpha blending and save full alpha channel
imagesavealpha($final_image, true);
imagealphablending($final_image, false);

// Resample the image
imagecopyresampled($final_image, $image, 0, 0, 0, 0, $final_width, $final_height, $width, $height);

// Save the image to cache
imagepng($final_image, $fileName);
imagedestroy($image);
imagedestroy($final_image);

// Serve the image
header('Content-Type: image/png');
readfile($fileName);

// Function definitions

function generate_qyoo($width, $height, $fg_color, $dot_color, $bg_color, $binary_string) {
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
    imagefilledellipse($image, $center_x, $center_y, $radius * 2, $radius * 2, $fg_col);

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
                imagefilledellipse($image, $x, $y, $dot_radius * 2, $dot_radius * 2, $dot_col);
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

function is_binary_string($input) {
    return strlen($input) == 36 && preg_match('/^[01]{36}$/', $input);
}

function number_to_binary_string($number) {
    $binary_string = str_pad(decbin($number), 36, '0', STR_PAD_LEFT);
    return $binary_string;
}

function get_brightness($hex_color) {
    $hex_color = ltrim($hex_color, '#');
    list($r, $g, $b) = sscanf($hex_color, "%02x%02x%02x");
    // Calculate brightness using the luminosity method
    return (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
}
?>
