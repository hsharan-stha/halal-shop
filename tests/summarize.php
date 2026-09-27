<?php

/*
 * Summarises the agent-formatted PHPUnit JSON output: `php artisan test --compact | php tests/summarize.php`
 */
$raw = stream_get_contents(STDIN);
$start = strpos($raw, '{"tool"');
$json = $start === false ? null : json_decode(substr($raw, $start), true);

if (! $json) {
    echo $raw;
    exit(1);
}

printf("%s: %d tests, %d passed, %d failed\n", $json['result'], $json['tests'], $json['passed'], $json['failed'] ?? 0);

foreach ($json['failures'] ?? [] as $failure) {
    $message = preg_split('/\R/', $failure['message']);
    $exception = '';

    foreach ($message as $line) {
        if (preg_match('/^[A-Za-z\\\\]+(Exception|Error)[^#]*/', $line, $m)) {
            $exception = substr($m[0], 0, 400);
            break;
        }
    }

    echo "\n- ".$failure['test']."\n  ".substr($message[0], 0, 300)."\n";

    if ($exception) {
        echo '  '.$exception."\n";
    }
}
