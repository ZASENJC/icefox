<?php

require_once __DIR__ . '/../plugins/IcefoxStorage/S3Client.php';

use TypechoPlugin\IcefoxStorage\S3Client;

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$client = new S3Client([
    'endpoint' => 'https://s3.us-east-1.amazonaws.com',
    'region' => 'us-east-1',
    'bucket' => 'example-bucket',
    'accessKey' => 'AKIDEXAMPLE',
    'secretKey' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY',
    'pathStyle' => true
]);

$request = $client->buildSignedRequest(
    'PUT',
    'icefox/2026/07/photo.jpg',
    [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Length' => '5',
        'Content-Type' => 'image/jpeg'
    ],
    hash('sha256', 'hello'),
    1767323045
);

assertSameValue(
    'https://s3.us-east-1.amazonaws.com/example-bucket/icefox/2026/07/photo.jpg',
    $request['url'],
    'path-style S3 URL is incorrect'
);
assertSameValue(
    '20260102T030405Z',
    $request['headers']['x-amz-date'],
    'x-amz-date is incorrect'
);
assertSameValue(
    'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20260102/us-east-1/s3/aws4_request, SignedHeaders=cache-control;content-length;content-type;host;x-amz-content-sha256;x-amz-date, Signature=846b72836ac67aaae6117ee81fa2c83fd19c3f8ec5fd4d43ce46847f028366a2',
    $request['headers']['authorization'],
    'AWS Signature V4 does not match the independent aws4 reference'
);

echo "S3 signing vector verified\n";
