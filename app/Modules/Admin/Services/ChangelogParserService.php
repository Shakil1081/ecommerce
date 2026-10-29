<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

class ChangelogParserService
{
    /**
     * Parse the CHANGELOG.md file into structured release entries.
     *
     * @return array<int, array{
     *     version: string,
     *     date: ?string,
     *     is_unreleased: bool,
     *     sections: array<string, array<int, string>>
     * }>
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $releases = [];
        $currentRelease = null;
        $currentSection = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Match release headers: ## [0.1.0] - 2026-10-29 or ## [Unreleased]
            if (preg_match('/^##\s+\[(.*?)\](?:\s+-\s+(\d{4}-\d{2}-\d{2}))?/', $trimmed, $matches)) {
                if ($currentRelease !== null) {
                    $releases[] = $currentRelease;
                }

                $version = $matches[1];
                $date = $matches[2] ?? null;

                $currentRelease = [
                    'version' => $version,
                    'date' => $date,
                    'is_unreleased' => strtolower($version) === 'unreleased',
                    'sections' => [],
                ];
                $currentSection = null;

                continue;
            }

            if ($currentRelease === null) {
                continue;
            }

            // Match section headers: ### Added, ### Changed, ### Fixed, etc.
            if (preg_match('/^###\s+(.*)/', $trimmed, $sectionMatches)) {
                $currentSection = trim($sectionMatches[1]);
                if (! isset($currentRelease['sections'][$currentSection])) {
                    $currentRelease['sections'][$currentSection] = [];
                }

                continue;
            }

            // Match bullet points: - Some feature or * Some feature
            if ($currentSection !== null && preg_match('/^[-*]\s+(.*)/', $trimmed, $itemMatches)) {
                $currentRelease['sections'][$currentSection][] = trim($itemMatches[1]);
            }
        }

        if ($currentRelease !== null) {
            $releases[] = $currentRelease;
        }

        return $releases;
    }
}
