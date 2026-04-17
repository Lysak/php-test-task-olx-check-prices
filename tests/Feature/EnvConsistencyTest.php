<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class EnvConsistencyTest extends TestCase
{
    /** @return list<string> */
    private function parseEnvKeys(string $path): array
    {
        $keys = [];
        $lines = file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        foreach ($lines !== false ? $lines : [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            $keys[] = strtok($line, '=');
        }

        return $keys;
    }

    public function test_env_and_env_example_are_consistent(): void
    {
        $root = \dirname(__DIR__, 2);
        $envPath = $root . '/.env';

        if (! file_exists($envPath)) {
            $this->markTestSkipped('.env file does not exist.');
        }

        $exampleKeys = $this->parseEnvKeys($root . '/.env.example');
        $envKeys = $this->parseEnvKeys($envPath);

        $missingInEnv = array_diff($exampleKeys, $envKeys);
        $extraInEnv = array_diff($envKeys, $exampleKeys);

        $this->assertEmpty(
            $missingInEnv,
            "Keys in .env.example but missing in .env:\n" . implode("\n", $missingInEnv),
        );

        $this->assertEmpty(
            $extraInEnv,
            "Keys in .env but missing in .env.example:\n" . implode("\n", $extraInEnv),
        );
    }
}
