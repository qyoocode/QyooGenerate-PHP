<?php
require __DIR__ . '/../src/generate.php';
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
foreach (['0' => str_repeat('0', 36), '1' => str_repeat('0', 35).'1', '68719476735' => str_repeat('1', 36), '34359738368' => '1'.str_repeat('0', 35)] as $id => $bits) {
    check(qyoo_parse_code((string)$id) === $bits, 'boundary serialization '.$id);
}
foreach ([null, [], 1, true, '', '-1', '+1', '1.0', '1e3', ' 1', "1\n", '68719476736', str_repeat('1', 37), str_repeat('9', 10000)] as $bad) {
    try { qyoo_parse_code($bad); throw new RuntimeException('accepted malformed input'); }
    catch (InvalidArgumentException $expected) {}
}
$bits = '101010101010101010101010101010101010';
check(qyoo_parse_code($bits) === $bits, 'binary identity');
check(number_to_binary_string(68719476735) === str_repeat('1',36), 'public conversion boundary');
try { number_to_binary_string(68719476736); throw new RuntimeException('accepted oversized number'); }
catch (InvalidArgumentException $expected) {}
check(is_binary_string([]) === false, 'array is not binary');
$png = qyoo_png($bits);
check(substr($png, 0, 8) === "\x89PNG\r\n\x1a\n", 'PNG signature');
$image = imagecreatefromstring($png);
check(imagesx($image) === 512 && imagesy($image) === 512, 'fixed image dimensions');
imagedestroy($image);
// Pin the historical geometry and MSB-first row-major cell locations.
$image = generate_qyoo(2048,2048,'#000000','#FFFFFF','#FFFFFF','1'.str_repeat('0',35));
check((imagecolorat($image, 711, 711) & 0xffffff) === 0xffffff, 'first bit at upper-left');
check((imagecolorat($image, 1314, 1314) & 0xffffff) === 0, 'last bit remains unset');
check((imagecolorat($image, 400, 1024) & 0xffffff) === 0xffffff, 'historical carrier margin');
imagedestroy($image);
check(qyoo_cached_png($bits, '') !== '', 'uncached rendering');
try { qyoo_cached_png($bits, __FILE__.'/blocked'); throw new RuntimeException('accepted blocked cache'); }
catch (RuntimeException $expected) { check($expected->getMessage() !== 'accepted blocked cache', 'cache failure propagated'); }
echo "PASS: boundaries, malformed requests, PNG, historical geometry, cache failure\n";
