<?php

require_once __DIR__ . '/../plugins/IcefoxStorage/StorageService.php';

use TypechoPlugin\IcefoxStorage\StorageService;

function storageAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

class FakeObjectClient
{
    public $uploads = [];
    public $deletes = [];

    public function putObject($key, $filePath, array $headers)
    {
        $this->uploads[] = compact('key', 'filePath', 'headers');
    }

    public function deleteObject($key)
    {
        $this->deletes[] = $key;
    }
}

$client = new FakeObjectClient();
$service = new StorageService([
    'publicUrl' => 'https://img.example.com/',
    'pathPrefix' => 'icefox',
    'maxFileSizeMb' => 5,
    'cacheControl' => 'public, max-age=31536000, immutable'
], $client, function () {
    return 1780283045;
}, function ($length) {
    return str_repeat("\x01", $length);
});

$pngPath = tempnam(sys_get_temp_dir(), 'icefox-png-');
file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$uploaded = $service->uploadFile($pngPath, 'avatar.jpg');

storageAssert($uploaded['mime'] === 'image/png', 'MIME must come from file contents, not the original extension');
storageAssert($uploaded['type'] === 'image', 'uploaded object must retain the image attachment type');
storageAssert($uploaded['objectKey'] === 'icefox/2026/06/01010101010101010101010101010101.png', 'object key is not deterministic under the test clock/random source');
storageAssert($uploaded['path'] === 'https://img.example.com/icefox/2026/06/01010101010101010101010101010101.png', 'public URL is incorrect');
storageAssert(count($client->uploads) === 1, 'validated image must be uploaded once');

$service->cleanupUploadedObjects([$uploaded]);
storageAssert($client->deletes === [$uploaded['objectKey']], 'rollback must delete the uploaded object key');

$svgPath = tempnam(sys_get_temp_dir(), 'icefox-svg-');
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$svgRejected = false;
try {
    $service->uploadFile($svgPath, 'photo.png');
} catch (InvalidArgumentException $error) {
    $svgRejected = true;
}
storageAssert($svgRejected, 'active SVG content must be rejected even when its filename uses an allowed extension');

@unlink($pngPath);
@unlink($svgPath);

echo "Object storage validation and rollback verified\n";
