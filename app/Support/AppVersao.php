<?php

namespace App\Support;

use Carbon\Carbon;

class AppVersao
{
    public const REPO_URL = 'https://github.com/murtafilho/poprua-cras';

    public static function release(): string
    {
        return (string) config('app.version', '2.0');
    }

    public static function label(): string
    {
        return 'v'.self::release();
    }

    /**
     * @return array{branch: ?string, commit: ?string, commit_full: ?string, message: ?string, date: ?string}
     */
    public static function git(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $empty = [
            'branch' => null,
            'commit' => null,
            'commit_full' => null,
            'message' => null,
            'date' => null,
        ];

        if (! is_dir(base_path('.git'))) {
            return $cached = $empty;
        }

        $run = static function (string $args): ?string {
            $cmd = 'git -C '.escapeshellarg(base_path()).' '.$args.' 2>/dev/null';
            $out = @shell_exec($cmd);
            if (! is_string($out)) {
                return null;
            }
            $out = trim($out);

            return $out !== '' ? $out : null;
        };

        $commitFull = $run('rev-parse HEAD');
        if ($commitFull === null) {
            return $cached = $empty;
        }

        $dateRaw = $run('log -1 --format=%ci HEAD');
        $date = null;
        if ($dateRaw !== null) {
            try {
                $date = Carbon::parse($dateRaw)->timezone('America/Sao_Paulo')->format('d/m/Y H:i');
            } catch (\Throwable) {
                $date = $dateRaw;
            }
        }

        return $cached = [
            'branch' => $run('rev-parse --abbrev-ref HEAD'),
            'commit' => $run('rev-parse --short HEAD'),
            'commit_full' => $commitFull,
            'message' => $run('log -1 --format=%s HEAD'),
            'date' => $date,
        ];
    }

    public static function commitUrl(?string $commitFull): ?string
    {
        if ($commitFull === null || $commitFull === '') {
            return null;
        }

        return self::REPO_URL.'/commit/'.$commitFull;
    }

    public static function pwaCacheVersion(): ?string
    {
        $sw = @file_get_contents(public_path('sw.js'));
        if ($sw === false) {
            return null;
        }

        if (preg_match('/const\s+CACHE_VERSION\s*=\s*(\d+)/', $sw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
