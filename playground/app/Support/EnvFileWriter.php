<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Updates specific KEY=VALUE lines in the app's .env file, leaving
 * everything else untouched — no framework helper does this (env() only
 * reads), and hand-editing .env is the whole point of the Settings page
 * this exists for.
 *
 * Deliberately simple and playground-scoped: single-user local dev tool,
 * one process at a time. Don't lift this into a real app without adding
 * a file lock and thinking about concurrent writers.
 */
class EnvFileWriter
{
    public function __construct(protected string $path)
    {
    }

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $lines = file_exists($this->path)
            ? explode("\n", file_get_contents($this->path))
            : [];

        $remaining = $values;

        foreach ($lines as $i => $line) {
            foreach ($remaining as $key => $value) {
                if (preg_match('/^'.preg_quote($key, '/').'=/', $line)) {
                    $lines[$i] = $this->formatLine($key, $value);
                    unset($remaining[$key]);
                    break;
                }
            }
        }

        foreach ($remaining as $key => $value) {
            $lines[] = $this->formatLine($key, $value);
        }

        file_put_contents($this->path, implode("\n", $lines));
    }

    protected function formatLine(string $key, string $value): string
    {
        // Quote only when needed, matching how .env files are normally
        // hand-written — an unquoted value with a space would otherwise
        // be silently truncated at the first space when read back.
        $needsQuotes = $value === '' || preg_match('/[\s#"\'\\\\]/', $value);

        if ($needsQuotes) {
            $value = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return "{$key}={$value}";
    }
}
