<?php

declare(strict_types=1);

namespace Hyperdrive\Security;

use Hyperdrive\Config\Config;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $expiry;

    public function __construct()
    {
        $this->secret = Config::get('auth.jwt.secret', 'hyperdrive-default-secret-change-in-production');
        $this->algorithm = Config::get('auth.jwt.algorithm', 'HS256');
        $this->expiry = Config::get('auth.jwt.expiry', 3600); // 1 hour
    }

    public function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $payload = array_merge([
            'iat' => time(),
            'exp' => time() + $this->expiry,
            'iss' => Config::get('app.url', 'hyperdrive')
        ], $payload);

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $this->secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWT format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $this->secret, true);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new \RuntimeException('Invalid JWT signature');
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('JWT token expired');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
