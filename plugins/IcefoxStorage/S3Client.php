<?php

namespace TypechoPlugin\IcefoxStorage;

class S3Client
{
    private $endpoint;
    private $region;
    private $bucket;
    private $accessKey;
    private $secretKey;
    private $pathStyle;
    private $transport;

    public function __construct(array $config, callable $transport = null)
    {
        $this->endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $this->region = trim((string) ($config['region'] ?? 'auto')) ?: 'auto';
        $this->bucket = trim((string) ($config['bucket'] ?? ''));
        $this->accessKey = trim((string) ($config['accessKey'] ?? ''));
        $this->secretKey = (string) ($config['secretKey'] ?? '');
        $this->pathStyle = !empty($config['pathStyle']);
        $this->transport = $transport;

        $parts = parse_url($this->endpoint);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new \InvalidArgumentException('对象存储 Endpoint 格式不正确');
        }
        if ($this->bucket === '' || $this->accessKey === '' || $this->secretKey === '') {
            throw new \InvalidArgumentException('对象存储 Bucket 或访问凭证未配置');
        }
    }

    public function putObject($key, $filePath, array $headers = [])
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException('待上传文件不存在或不可读');
        }

        $size = filesize($filePath);
        $headers['Content-Length'] = (string) $size;
        $request = $this->buildSignedRequest('PUT', $key, $headers, hash_file('sha256', $filePath));

        if ($this->transport) {
            return call_user_func($this->transport, $request, $filePath);
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('无法读取待上传文件');
        }

        try {
            return $this->sendRequest($request, $handle, $size);
        } finally {
            fclose($handle);
        }
    }

    public function putContents($key, $contents, array $headers = [])
    {
        $contents = (string) $contents;
        $headers['Content-Length'] = (string) strlen($contents);
        $request = $this->buildSignedRequest('PUT', $key, $headers, hash('sha256', $contents));

        if ($this->transport) {
            return call_user_func($this->transport, $request, $contents);
        }

        return $this->sendRequest($request, $contents, strlen($contents));
    }

    public function deleteObject($key)
    {
        $request = $this->buildSignedRequest('DELETE', $key, [], hash('sha256', ''));

        if ($this->transport) {
            return call_user_func($this->transport, $request, null);
        }

        return $this->sendRequest($request);
    }

    public function buildSignedRequest($method, $key, array $headers, $payloadHash, $timestamp = null)
    {
        $method = strtoupper((string) $method);
        $key = $this->normalizeObjectKey($key);
        $timestamp = $timestamp === null ? time() : (int) $timestamp;
        $amzDate = gmdate('Ymd\THis\Z', $timestamp);
        $dateStamp = gmdate('Ymd', $timestamp);

        $endpoint = parse_url($this->endpoint);
        $scheme = strtolower($endpoint['scheme']);
        $host = $endpoint['host'];
        $port = isset($endpoint['port']) ? ':' . $endpoint['port'] : '';
        $basePath = isset($endpoint['path']) ? trim($endpoint['path'], '/') : '';

        if ($this->pathStyle) {
            $pathParts = array_filter([$basePath, $this->bucket, $key], 'strlen');
        } else {
            $host = $this->bucket . '.' . $host;
            $pathParts = array_filter([$basePath, $key], 'strlen');
        }

        $canonicalUri = '/' . implode('/', array_map([$this, 'encodePathPart'], $pathParts));
        $requestHost = $host . $port;
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower(trim((string) $name))] = $this->normalizeHeaderValue($value);
        }
        $normalizedHeaders['host'] = $requestHost;
        $normalizedHeaders['x-amz-content-sha256'] = strtolower((string) $payloadHash);
        $normalizedHeaders['x-amz-date'] = $amzDate;
        ksort($normalizedHeaders, SORT_STRING);

        $canonicalHeaders = '';
        foreach ($normalizedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }
        $signedHeaders = implode(';', array_keys($normalizedHeaders));
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            strtolower((string) $payloadHash)
        ]);

        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp));
        $normalizedHeaders['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        return [
            'method' => $method,
            'url' => $scheme . '://' . $requestHost . $canonicalUri,
            'headers' => $normalizedHeaders
        ];
    }

    private function sendRequest(array $request, $body = null, $size = null)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('服务器未安装 PHP cURL 扩展');
        }

        $curl = curl_init($request['url']);
        $headerLines = [];
        foreach ($request['headers'] as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request['method']);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);

        if (is_resource($body)) {
            curl_setopt($curl, CURLOPT_UPLOAD, true);
            curl_setopt($curl, CURLOPT_INFILE, $body);
            curl_setopt($curl, CURLOPT_INFILESIZE, (int) $size);
        } elseif ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false || $curlError !== '') {
            throw new \RuntimeException('对象存储请求失败：' . $curlError);
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('对象存储请求失败，HTTP 状态码：' . $statusCode);
        }

        return $responseBody;
    }

    private function normalizeObjectKey($key)
    {
        $key = trim((string) $key, '/');
        if ($key === '' || strpos($key, "\0") !== false) {
            throw new \InvalidArgumentException('对象 Key 不能为空');
        }

        foreach (explode('/', $key) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new \InvalidArgumentException('对象 Key 包含无效路径片段');
            }
        }

        return $key;
    }

    private function encodePathPart($value)
    {
        return implode('/', array_map('rawurlencode', explode('/', (string) $value)));
    }

    private function normalizeHeaderValue($value)
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function signingKey($dateStamp)
    {
        $dateKey = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }
}
