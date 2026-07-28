<?php

namespace Typecho {
    class Widget
    {
    }

    class Db
    {
    }

    class Request
    {
    }
}

namespace Widget {
    interface ActionInterface
    {
    }
}

namespace {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
    require_once __DIR__ . '/../plugins/Icefox/Action.php';

    $reflection = new ReflectionClass('TypechoPlugin\\Icefox\\Action');
    if (!$reflection->hasMethod('buildAttachmentMetadata')) {
        fwrite(STDERR, "Action::buildAttachmentMetadata is missing\n");
        exit(1);
    }

    $action = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('buildAttachmentMetadata');
    $method->setAccessible(true);
    $metadata = $method->invoke($action, [
        'name' => 'photo.JPG',
        'path' => 'https://img.example.com/icefox/2026/07/photo.jpg',
        'url' => 'https://img.example.com/icefox/2026/07/photo.jpg',
        'size' => 1234,
        'type' => 'image',
        'mime' => 'image/jpeg',
        'storage' => 'object',
        'objectKey' => 'icefox/2026/07/photo.jpg'
    ]);

    $expected = [
        'name' => 'photo.JPG',
        'path' => 'https://img.example.com/icefox/2026/07/photo.jpg',
        'size' => 1234,
        'type' => 'jpg',
        'mime' => 'image/jpeg',
        'storage' => 'object',
        'objectKey' => 'icefox/2026/07/photo.jpg',
        'url' => 'https://img.example.com/icefox/2026/07/photo.jpg'
    ];

    if ($metadata !== $expected) {
        fwrite(STDERR, "Companion attachment metadata mismatch\n");
        exit(1);
    }

    echo "Companion attachment metadata verified\n";
}
