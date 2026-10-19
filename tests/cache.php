<?php
require __DIR__.'/../src/generate.php';
// Supply a fresh, disposable directory; evidence is retained for inspection.
$directory = $argv[1] ?? '';
if ($directory === '' || file_exists($directory)) throw new RuntimeException('Supply a new cache directory');
$children = [];
for ($i=0; $i<4; $i++) {
    $code = 'require $argv[1]; echo hash("sha256", qyoo_cached_png(str_repeat("1",36),$argv[2]));';
    $process = proc_open([PHP_BINARY,'-r',$code,__DIR__.'/../src/generate.php',$directory], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    $children[] = [$process,$pipes];
}
$hashes = [];
foreach ($children as [$process,$pipes]) {
    fclose($pipes[0]);
    $hashes[] = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $errors !== '') throw new RuntimeException('Cache worker failed: '.$errors.' stdout: '.end($hashes));
}
if (count(array_unique($hashes)) !== 1 || strlen($hashes[0]) !== 64) throw new RuntimeException('Concurrent cache mismatch: '.json_encode($hashes));
$path = $directory.'/'.str_repeat('1',36).'.png';
if (!is_file($path)) throw new RuntimeException('Cache not persisted');
file_put_contents($path, 'corrupt');
try { qyoo_cached_png(str_repeat('1',36),$directory); throw new LogicException('Corrupt PNG served'); }
catch (RuntimeException $expected) {}
echo "PASS: concurrent cold cache, warm reads and corrupt-cache rejection\n";
