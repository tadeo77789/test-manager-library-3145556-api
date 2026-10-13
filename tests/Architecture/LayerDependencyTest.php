<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * T-19: Constitution Articles I, II and III checked over the `use` statements (and fully qualified
 * names) of the source files. It fails if a forbidden import is introduced.
 */
final class LayerDependencyTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../app';

    /** @return list<string> */
    private static function files(string $dir): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/' . $dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Every namespaced reference found in "use" statements or written fully qualified.
     *
     * @return list<string>
     */
    public static function references(string $code): array
    {
        $refs = [];
        if (preg_match_all('/^use\s+(?:function\s+|const\s+)?([A-Za-z0-9_\\\\]+)/m', $code, $m)) {
            $refs = array_merge($refs, $m[1]);
        }
        if (preg_match_all('/(?<![A-Za-z0-9_])\\\\([A-Z][A-Za-z0-9_]*(?:\\\\[A-Za-z0-9_]+)+)/', $code, $m)) {
            $refs = array_merge($refs, $m[1]);
        }

        return $refs;
    }

    /**
     * @param list<string> $allowedPrefixes namespaces a file of the layer may import
     * @return list<string> offending references
     */
    public static function violations(string $code, array $allowedPrefixes): array
    {
        $bad = [];
        foreach (self::references($code) as $ref) {
            $ref = ltrim($ref, '\\');
            if (! str_contains($ref, '\\')) {
                continue; // global PHP class (DateTimeImmutable, RuntimeException...)
            }
            $ok = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($ref, $prefix)) {
                    $ok = true;
                }
            }
            if (! $ok) {
                $bad[] = $ref;
            }
        }

        return $bad;
    }

    public function test_domain_depends_on_nothing_but_itself_article_i(): void
    {
        $files = self::files('Domain');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], self::violations(file_get_contents($file), ['App\\Domain\\']), $file);
        }
    }

    public function test_application_only_knows_the_domain_article_ii(): void
    {
        $files = self::files('Application');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], self::violations(file_get_contents($file), ['App\\Domain\\', 'App\\Application\\']), $file);
        }
    }

    public function test_no_adapter_is_instantiated_outside_the_composition_root_article_iii(): void
    {
        $adapterClasses = [];
        foreach (['Persistence', 'Security', 'Storage', 'System'] as $dir) {
            foreach (self::files('Adapters/' . $dir) as $file) {
                $adapterClasses[] = basename($file, '.php');
            }
        }
        $this->assertNotEmpty($adapterClasses);
        foreach (self::files('') as $file) {
            if (str_contains(str_replace('\\', '/', $file), '/Bootstrap/')) {
                continue;
            }
            $code = file_get_contents($file);
            foreach ($adapterClasses as $class) {
                if ($class === 'Rows') {
                    continue; // static mapping helper, not a port implementation
                }
                $this->assertDoesNotMatchRegularExpression('/new\s+\\\\?(?:[A-Za-z0-9_\\\\]*\\\\)?' . $class . '\s*\(/', $code, "$file instantiates $class");
            }
        }
    }

    public function test_the_checker_really_fails_on_forbidden_imports(): void
    {
        $this->assertSame(['Illuminate\\Support\\Facades\\DB'], self::violations("use Illuminate\\Support\\Facades\\DB;\nuse DateTimeImmutable;", ['App\\Domain\\']));
        $this->assertSame(['App\\Adapters\\Persistence\\PgBookRepository'], self::violations("use App\\Adapters\\Persistence\\PgBookRepository;", ['App\\Domain\\', 'App\\Application\\']));
        $this->assertSame(['Illuminate\\Http\\Request'], self::violations('$x = new \\Illuminate\\Http\\Request();', ['App\\Domain\\']));
        $this->assertSame([], self::violations("use App\\Domain\\Book;\nuse DateTimeImmutable;", ['App\\Domain\\']));
    }
}
