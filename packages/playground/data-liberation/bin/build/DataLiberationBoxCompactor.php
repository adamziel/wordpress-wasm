<?php

use KevinGH\Box\Compactor\Compactor;

class DataLiberationBoxCompactor implements Compactor
{
    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        if (!preg_match('/\.(php|json|lock)$/', $file)) {
            return '';
        }

        if (
            str_contains($file, 'platform_check.php') ||
            str_contains($file, '/tests/') ||
            str_contains($file, '/.git/') ||
            str_contains($file, '/.github/') ||
            str_contains($file, '/bin/')
        ) {
            return '';
        }

        if( str_contains($contents, 'Your Composer dependencies require ') ) {
            return '';
        }

        return $contents;
    }
}