<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiContractParityTest extends TestCase
{
    private const SPEC = 'docs/openapi/laravel-v1.yaml';

    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * Runtime operations that are intentionally undocumented. Each entry is a
     * legacy compatibility alias kept only until its admin client migrates to
     * the canonical `/api/v1/admin/catalog/*` operation, and must carry the
     * removal note that says which canonical operation replaces it.
     *
     * @var array<string, string>
     */
    private const LEGACY_ALLOWLIST = [
        'GET /api/v1/admin/lessons' => 'Remove with the admin lesson list alias; canonical: GET /api/v1/admin/catalog/lessons.',
        'POST /api/v1/admin/lessons' => 'Remove with the admin lesson create alias; canonical: POST /api/v1/admin/catalog/lessons.',
        'GET /api/v1/admin/lessons/{param}' => 'Remove with the admin lesson show alias; canonical: GET /api/v1/admin/catalog/lessons/{lesson}.',
        'PUT /api/v1/admin/lessons/{param}' => 'Remove with the admin lesson update alias; canonical: PUT /api/v1/admin/catalog/lessons/{lesson}.',
        'DELETE /api/v1/admin/lessons/{param}' => 'Remove with the admin lesson delete alias; canonical: DELETE /api/v1/admin/catalog/lessons/{lesson}.',
    ];

    public function test_documented_and_registered_api_v1_operations_match(): void
    {
        $runtime = $this->runtimeOperations();
        $documented = $this->documentedOperations();

        $undocumented = array_values(array_diff($runtime, $documented, array_keys(self::LEGACY_ALLOWLIST)));
        $phantom = array_values(array_diff($documented, $runtime));

        $this->assertSame([], $undocumented, 'Registered operations missing from '.self::SPEC.":\n".implode("\n", $undocumented));
        $this->assertSame([], $phantom, 'Documented operations with no registered route:\n'.implode("\n", $phantom));
    }

    public function test_every_legacy_allowlist_entry_still_exists_and_carries_a_removal_note(): void
    {
        $runtime = $this->runtimeOperations();

        foreach (self::LEGACY_ALLOWLIST as $operation => $note) {
            $this->assertContains($operation, $runtime, "Allowlisted legacy operation [{$operation}] no longer exists; delete the entry.");
            $this->assertStringContainsString('canonical:', $note, "Allowlist entry [{$operation}] must name its canonical replacement.");
        }
    }

    /** @return list<string> sorted "METHOD /path" operations, parameter names normalized */
    private function runtimeOperations(): array
    {
        $operations = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $operations[] = $method.' '.$this->normalize('/'.$route->uri());
            }
        }

        return $this->sorted($operations);
    }

    /**
     * Reads `paths:` keys and their standard method children without a YAML
     * dependency: path keys sit at two spaces, methods at four.
     *
     * @return list<string>
     */
    private function documentedOperations(): array
    {
        $lines = file(base_path(self::SPEC), FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines, 'Could not read '.self::SPEC);

        $operations = [];
        $inPaths = false;
        $path = null;
        foreach ($lines as $line) {
            if (preg_match('/^\S/', $line)) {
                $inPaths = str_starts_with($line, 'paths:');
                $path = null;

                continue;
            }
            if (! $inPaths) {
                continue;
            }
            if (preg_match('#^ {2}(/\S*):\s*$#', $line, $match)) {
                $path = $this->normalize($match[1]);

                continue;
            }
            if ($path !== null && preg_match('/^ {4}([a-z]+):\s*$/', $line, $match)
                && in_array($match[1], self::METHODS, true)) {
                $operations[] = strtoupper($match[1]).' '.$path;
            }
        }

        return $this->sorted($operations);
    }

    private function normalize(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '{param}', rtrim($path, '/')) ?? $path;
    }

    /** @return list<string> */
    private function sorted(array $operations): array
    {
        $operations = array_values(array_unique($operations));
        sort($operations);

        return $operations;
    }
}
