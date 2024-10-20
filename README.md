# Qyoo Code Generator

This repository contains a PHP script (`src/generate.php`) that generates and caches unique Qyoo code images based on provided numeric or binary IDs. By accessing a specific URL with an ID parameter, the script dynamically creates a visually distinctive image associated with that ID.

## Features

- **Dynamic Image Generation**: Creates unique Qyoo code images based on numeric or binary IDs.
- **Caching Mechanism**: Saves generated images to reduce server load and improve performance.
- **Vibrant Color Palette**: Uses a diverse palette of background colors for visual appeal.
- **Contrast Optimization**: Adjusts foreground and dot colors to maximize contrast with the background.
- **Anti-Aliasing**: Implements image scaling to produce smoother edges and reduce pixelation.

## How It Works

When a user accesses a URL like `http://id.qyoo.com/511305600`, the following process occurs:

1. **Input Processing**:
   - The script retrieves the `code` parameter from the URL.
   - It checks if the `code` is a numeric value or a 36-character binary string.
   - If the `code` is numeric, it converts it to a 36-digit binary string.

2. **Caching Mechanism**:
   - The script checks if an image corresponding to the binary string already exists in the `cache` directory.
   - If it exists, the cached image is served directly to the user.
   - If not, the script proceeds to generate a new image.

3. **Image Generation**:
   - A background color is randomly selected from a predefined color palette.
   - The brightness of the background color is calculated to determine optimal foreground and dot colors for maximum contrast.
   - The script generates the Qyoo code image at a higher resolution to implement anti-aliasing.
   - It draws the background, foreground shapes (circle and square), and a grid of dots based on the binary string.
   - The image is then scaled down to the final size to smooth out edges.

4. **Output**:
   - The generated image is saved in the `cache` directory for future requests.
   - The image is served to the user in PNG format.

## Setup Instructions

Follow these steps to set up the script on your web server:

1. **Upload Files**:
   - Place the `generate.php` script in your web server's document root or the desired subdirectory (e.g., `/var/www/html/` or `/home/username/public_html/`).
   - Ensure a `cache` directory exists in the same location as `generate.php` and that it is writable by the web server.

2. **Configure Web Server for Clean URLs**:
   - To enable clean URLs like `http://id.qyoo.com/511305600`, configure your web server to route requests appropriately.
   - For **Apache** servers, you can use an `.htaccess` file with rewrite rules.

   **Example `.htaccess` Configuration**:

   ```apache
   RewriteEngine On
   RewriteBase /

   # If the request is for a file or directory that exists, don't rewrite
   RewriteCond %{REQUEST_FILENAME} -f [OR]
   RewriteCond %{REQUEST_FILENAME} -d
   RewriteRule ^ - [L]

   # Rewrite numeric codes to generate.php
   RewriteRule ^([0-9]+)$ generate.php?code=$1 [L,QSA]

   # Rewrite binary strings to generate.php (optional)
   RewriteRule ^([01]{36})$ generate.php?code=$1 [L,QSA]
   ```

   - Place the `.htaccess` file in the same directory as `generate.php`.

3. **Set Permissions**:
   - Ensure the `cache` directory is writable by the web server.
   - You can set the directory permissions to `0755` or `0775` as needed.

4. **Verify PHP GD Extension**:
   - Ensure the PHP GD library is installed and enabled on your server, as it is required for image creation and manipulation.

## Usage

To generate and view a Qyoo code image, navigate to your web server's URL followed by the desired ID.

**Examples**:

- **Numeric ID**: `https://id.qyoo.com/511305600`
- **Binary ID**: `https://id.qyoo.com/101010101010101010101010101010101010`

**What Happens**:

- The script processes the provided ID and generates a unique Qyoo code image.
- If the image has been previously generated, the cached version is served.
- The image is displayed directly in the browser.

## Customization

You can customize various aspects of the script to suit your needs.

### Color Palette

Modify the `$color_palette` array in `generate.php` to change the background colors.

```php
// Define a custom color palette
$color_palette = array(
    '#b51dff', // Purple
    '#E91E63', // Pink
    '#9C27B0', // Deep Purple
    // Add or remove colors as desired
);
```

### Image Dimensions

Adjust the `$final_width` and `$final_height` variables to change the output image size.

```php
$final_width = 512;   // Set desired image width
$final_height = 512;  // Set desired image height
```

### Caching Behavior

Implement cache management if necessary:

- **Cache Expiration**: Add logic to expire cached images after a certain period.
- **Cache Cleanup**: Use a script or cron job to remove old cached images.

### Anti-Aliasing Scale

Change the `$scale` variable to adjust the level of anti-aliasing.

```php
$scale = 4; // Increase for smoother images, decrease for performance
```

## Dependencies

- **PHP 5.6 or Higher**: The script is compatible with PHP versions supporting the GD library.
- **PHP GD Library**: Ensure the GD extension is installed and enabled.

## License

This project is licensed under the [BSD-3 Clause License](LICENSE). You are free to use, modify, and distribute this software as per the terms of the license.

## Contributing

Contributions are welcome! Feel free to:

- Submit **issues** for bugs or feature requests.
- Create **pull requests** to contribute code improvements or new features.

Please ensure that your contributions align with the project's coding standards and include appropriate documentation.

## Contact

For questions, support, or suggestions:

- **GitHub Issues**: [Create an issue](https://github.com/qyoocode/QyooGenerate-PHP/issues)
- **Email**: [github@qyoo.com](mailto:github@qyoo.com)