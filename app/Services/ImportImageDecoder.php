<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/** Decode imported pixels on the server; headers and browser load are insufficient. */
class ImportImageDecoder
{
    public function validate(string $bytes, int $width, int $height): void
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['file' => 'Importação exige PHP GD com suporte PNG e WebP no servidor.']);
        }

        // Some libgd/WebP failures are fatal, not catchable PHP exceptions. Decode
        // untrusted compressed pixels in a bounded child, before durable writes.
        $php = (new PhpExecutableFinder)->find(false);
        $valid = false;
        try {
            if ($php !== false) {
                // Apache's $_SERVER may omit process environment entries. Symfony
                // filters inherited variables against it; preserve the project's
                // GD scan directory explicitly for the isolated CLI decoder.
                $scan = getenv('PHP_INI_SCAN_DIR');
                $environment = $scan === false ? null : ['PHP_INI_SCAN_DIR' => $scan];
                $process = new Process([$php, '-d', 'memory_limit=256M', base_path('scripts/decode-import-image.php'), (string) $width, (string) $height], base_path(), $environment, $bytes, 15);
                $process->disableOutput();
                $valid = $process->run() === 0;
            }
        } catch (\Symfony\Component\Process\Exception\ExceptionInterface) {
            // Timeout/process failure also rejects the import, never a header-only fallback.
        }
        if (! $valid) {
            throw ValidationException::withMessages(['file' => 'Não foi possível decodificar integralmente a imagem PNG/WebP.']);
        }
    }

    protected function available(): bool
    {
        return function_exists('imagecreatefromstring') && (imagetypes() & IMG_PNG) !== 0 && (imagetypes() & IMG_WEBP) !== 0;
    }
}
