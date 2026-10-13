<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Adapters\Security\Argon2idPasswordHasher;
use App\Adapters\Security\JwtTokenIssuer;
use App\Adapters\Storage\DiskCoverStorage;
use App\Adapters\System\SystemClock;
use App\Adapters\System\UuidGenerator;
use App\Domain\Errors\ValidationFailed;
use App\Domain\User;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeClock;

/** T-14 / T-15 (no database needed). */
final class SecurityAndStorageTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ml-covers-' . bin2hex(random_bytes(4));
    }

    private function storage(): DiskCoverStorage
    {
        return new DiskCoverStorage($this->dir, $this->dir . '-fallback', new UuidGenerator());
    }

    public function test_argon2id_hash_verifies_and_is_not_plain(): void
    {
        $h = new Argon2idPasswordHasher();
        $hash = $h->hash('secret-pass');
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString('secret-pass', $hash);
        $this->assertTrue($h->verify('secret-pass', $hash));
        $this->assertFalse($h->verify('other', $hash));
    }

    public function test_jwt_has_8h_expiry_only_sub_and_role_and_expires(): void
    {
        $clock = FakeClock::at('2026-10-14T00:00:00Z');
        $issuer = new JwtTokenIssuer(str_repeat('s', 40), $clock);
        $token = $issuer->issue(new User('u1', 'ana', 'hash', 'librarian'));
        $this->assertSame(28800, $token->expiresIn);
        $payload = json_decode(base64_decode(strtr(explode('.', $token->accessToken)[1], '-_', '+/')), true);
        $this->assertSame(['sub', 'role', 'iat', 'exp'], array_keys($payload));
        $this->assertSame($payload['iat'] + 28800, $payload['exp']);

        $claims = $issuer->verify($token->accessToken);
        $this->assertSame('u1', $claims->userId);
        $this->assertSame('librarian', $claims->role);

        $clock->advanceDays(1);
        $this->assertNull($issuer->verify($token->accessToken));
        $this->assertNull((new JwtTokenIssuer(str_repeat('x', 40), $clock))->verify($token->accessToken));
        $this->assertNull($issuer->verify('garbage'));
    }

    public function test_clock_offset_shifts_now(): void
    {
        $real = (new SystemClock(0))->now()->getTimestamp();
        $shifted = (new SystemClock(-20))->now()->getTimestamp();
        $this->assertEqualsWithDelta(20 * 86400, $real - $shifted, 5);
    }

    public function test_png_cover_roundtrip_and_delete(): void
    {
        $s = $this->storage();
        $key = $s->store(base64_decode(self::PNG));
        $this->assertMatchesRegularExpression('/\.png$/', $key);
        $this->assertSame('image/png', $s->read($key)->mime);
        $s->delete($key);
        $this->assertNull($s->read($key));
    }

    public function test_two_megabyte_png_ok_three_megabytes_rejected(): void
    {
        $s = $this->storage();
        $png = base64_decode(self::PNG);
        $ok = $png . str_repeat("\0", 2 * 1024 * 1024 - strlen($png));
        $this->assertNotSame('', $s->store($ok));
        $this->expectException(ValidationFailed::class);
        $s->store($png . str_repeat("\0", 3 * 1024 * 1024));
    }

    public function test_type_is_validated_by_content_not_name(): void
    {
        $this->expectException(ValidationFailed::class);
        $this->storage()->store('<?php echo "not an image"; ?>');
    }

    public function test_path_traversal_keys_are_ignored(): void
    {
        $this->assertNull($this->storage()->read('../../etc/passwd'));
        $this->storage()->delete('../../x.png');
        $this->addToAssertionCount(1);
    }

    public function test_unwritable_directory_falls_back(): void
    {
        $s = new DiskCoverStorage('/proc/nope/covers', $this->dir, new UuidGenerator());
        $key = $s->store(base64_decode(self::PNG));
        $this->assertFileExists($this->dir . '/' . $key);
    }
}
