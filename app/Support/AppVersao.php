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
     * Rotulo de versao para a tela inicial.
     *
     * config('app.version') e um numero fixo que ninguem atualiza — nao diz o que
     * esta rodando. O que identifica de fato a build publicada e o commit, que muda
     * a cada deploy; a data ajuda quem esta em campo a saber se pegou a ultima.
     * Dentro do app de campo o rotulo e completado com a versao do APK, injetada
     * pela MainActivity (window.__sizemAppVersao) — ver resources/views/home/index.
     */
    public static function telaInicial(): string
    {
        $git = self::git();

        if ($git['commit'] === null) {
            return self::label();
        }

        $data = self::dataCurta($git['date']);

        return $data !== null ? $data.' · '.$git['commit'] : $git['commit'];
    }

    /**
     * Detalhe da build, para o title do rotulo — quem precisa reportar um problema
     * consegue ler branch, commit e data sem abrir a area administrativa.
     */
    public static function detalhe(): string
    {
        $git = self::git();
        $partes = array_filter([
            self::label(),
            $git['branch'] !== null ? 'branch '.$git['branch'] : null,
            $git['commit'] !== null ? 'commit '.$git['commit'] : null,
            $git['date'] !== null ? 'publicado em '.$git['date'] : null,
            self::pwaCacheVersion() !== null ? 'cache PWA v'.self::pwaCacheVersion() : null,
        ]);

        return implode(' · ', $partes);
    }

    /** dd/mm/aaaa a partir do formato dd/mm/aaaa HH:MM devolvido por git(). */
    private static function dataCurta(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        return preg_match('#^(\d{2}/\d{2}/\d{4})#', $data, $m) ? $m[1] : null;
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
