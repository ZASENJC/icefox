<?php

require_once __DIR__ . '/../core/core.php';

function assertUploadLimit($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . $expected . ', got ' . $actual);
    }
}

assertUploadLimit(2 * 1024 * 1024, icefoxIniSizeToBytes('2M'), 'megabytes must be parsed');
assertUploadLimit(128 * 1024 * 1024, icefoxIniSizeToBytes('128M'), 'post size must be parsed');
assertUploadLimit(1536 * 1024, icefoxIniSizeToBytes('1.5M'), 'fractional limits must be parsed');
assertUploadLimit(0, icefoxIniSizeToBytes('0'), 'zero must represent an unknown or unlimited value');

echo "PHP upload limit parsing verified\n";
