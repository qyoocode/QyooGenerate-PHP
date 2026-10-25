<?php
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8099', '-t', __DIR__.'/../src'], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Server launch failed');
try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1',8099);
        if ($socket) { fclose($socket); break; }
        usleep(20000);
    }
    foreach (['' => 400, '?code[]=1' => 400, '?code=68719476736' => 400, '?code=1%0A' => 400, '?code=68719476735' => 200, '?code=0' => 200] as $query => $status) {
        $body = file_get_contents('http://127.0.0.1:8099/generate.php'.$query, false, stream_context_create(['http'=>['ignore_errors'=>true]]));
        if (!str_contains($http_response_header[0], (string)$status)) throw new RuntimeException('HTTP status '.$query);
        if (!in_array('X-Content-Type-Options: nosniff', $http_response_header)) throw new RuntimeException('nosniff missing');
        if (!in_array('Cache-Control: no-store', $http_response_header)) throw new RuntimeException('cache header missing');
        if ($status === 200 && substr($body,0,8) !== "\x89PNG\r\n\x1a\n") throw new RuntimeException('PNG response');
    }
    file_get_contents('http://127.0.0.1:8099/generate.php?code=1', false, stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true]]));
    if (!str_contains($http_response_header[0], '405')) throw new RuntimeException('POST accepted');
    echo "PASS: HTTP input, status, headers, PNG and method handling\n";
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) fclose($pipe);
    proc_close($process);
}
