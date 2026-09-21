<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Security;

use Hyperdrive\Config\Config;
use Hyperdrive\Security\JwtService;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
    }

    protected function tearDown(): void
    {
        Config::clear();
    }

    public function test_it_encodes_a_well_formed_token(): void
    {
        $token = (new JwtService)->encode(['sub' => 'user-123']);

        $parts = explode('.', $token);

        $this->assertCount(3, $parts);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[0]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[1]);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $parts[2]);
    }

    public function test_it_includes_standard_header_fields(): void
    {
        $token = (new JwtService)->encode([]);

        [$headerEncoded] = explode('.', $token);
        $header = json_decode($this->base64UrlDecode($headerEncoded), true);

        $this->assertSame(['typ' => 'JWT', 'alg' => 'HS256'], $header);
    }

    public function test_it_round_trips_a_payload_through_encode_and_verify(): void
    {
        $service = new JwtService;

        $token = $service->encode(['sub' => 'user-123', 'role' => 'admin']);
        $payload = $service->verify($token);

        $this->assertSame('user-123', $payload['sub']);
        $this->assertSame('admin', $payload['role']);
    }

    public function test_it_adds_iat_exp_and_iss_claims_automatically(): void
    {
        Config::set('app.url', 'https://example.test');

        $service = new JwtService;
        $payload = $service->verify($service->encode(['sub' => 'user-123']));

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame('https://example.test', $payload['iss']);
        $this->assertSame($payload['iat'] + 3600, $payload['exp']);
    }

    public function test_a_custom_claim_overrides_the_auto_generated_expiry(): void
    {
        // encode() merges custom claims OVER the generated iat/exp/iss, so a
        // caller-supplied 'exp' should win over the auto-computed one.
        $service = new JwtService;
        $payload = $service->verify($service->encode(['exp' => time() + 999999]));

        $this->assertSame(time() + 999999, $payload['exp']);
    }

    public function test_it_throws_for_a_token_that_is_not_three_dot_separated_parts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT format');

        (new JwtService)->verify('not-a-valid-jwt');
    }

    public function test_it_throws_for_a_tampered_signature(): void
    {
        $token = (new JwtService)->encode(['sub' => 'user-123']);
        [$header, $payload] = explode('.', $token);
        $tampered = "$header.$payload.tampered-signature";

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        (new JwtService)->verify($tampered);
    }

    public function test_it_throws_for_a_tampered_payload(): void
    {
        $token = (new JwtService)->encode(['sub' => 'user-123']);
        [$header, , $signature] = explode('.', $token);
        $forgedPayload = $this->base64UrlEncode(json_encode(['sub' => 'attacker']));
        $tampered = "$header.$forgedPayload.$signature";

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        (new JwtService)->verify($tampered);
    }

    public function test_it_throws_for_an_expired_token(): void
    {
        Config::set('auth.jwt.expiry', -10);
        $service = new JwtService;
        $expired = $service->encode(['sub' => 'user-123']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT token expired');

        $service->verify($expired);
    }

    public function test_a_token_signed_with_a_different_secret_fails_verification(): void
    {
        Config::set('auth.jwt.secret', 'secret-a');
        $signedWithA = (new JwtService)->encode(['sub' => 'user-123']);

        Config::clear();
        Config::set('auth.jwt.secret', 'secret-b');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        (new JwtService)->verify($signedWithA);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
