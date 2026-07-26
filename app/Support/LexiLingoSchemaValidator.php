<?php

namespace App\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

class LexiLingoSchemaValidator
{
    private Validator $validator;

    private string $schemaId;

    public function __construct()
    {
        $path = base_path('docs/openapi/lexilingo-import.schema.json');

        if (! is_file($path)) {
            throw new RuntimeException("LexiLingo import schema not found at {$path}.");
        }

        $schema = json_decode(file_get_contents($path), false);

        if (! is_object($schema) || empty($schema->{'$id'})) {
            throw new RuntimeException("LexiLingo import schema at {$path} is missing a \$id.");
        }

        $this->schemaId = $schema->{'$id'};
        $this->validator = new Validator;
        $this->validator->resolver()->registerFile($this->schemaId, $path);
    }

    /**
     * Validate $data against the named `$defs` entry of the import schema.
     *
     * @return string[] Human-readable error messages; empty when valid.
     */
    public function validate(string $defName, array $data): array
    {
        $decoded = json_decode(json_encode($data));

        $result = $this->validator->validate($decoded, "{$this->schemaId}#/\$defs/{$defName}");

        if ($result->isValid()) {
            return [];
        }

        return array_values((new ErrorFormatter)->formatFlat($result->error()));
    }
}
