<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Legacy UVTT square coordinates -> existing pixel-based map world (y down). */
class Dd2vttParser
{
    public const MAX_FILE = 29 * 1024 * 1024;

    public const MAX_IMAGE = 20 * 1024 * 1024;

    public function parse(string $json): array
    {
        $this->check(strlen($json) <= self::MAX_FILE, 'Arquivo excede 29 MiB.');
        try {
            $d = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->fail('JSON inválido ou profundidade superior a 32.');
        }
        $this->check(is_array($d) && ! array_is_list($d), 'Raiz deve ser objeto JSON.');
        $this->check(isset($d['format']) && (is_int($d['format']) || is_float($d['format'])) && in_array((float) $d['format'], [0.2, 0.3], true), 'Somente format 0.2 e 0.3 são aceitos.');
        $warnings = ['Paredes, portais e luzes serão guardados somente para o mestre; não têm renderização funcional, iluminação, colisão ou portas interativas.'];
        $r = $d['resolution'] ?? null;
        $this->check(is_array($r), 'resolution obrigatório.');
        $origin = $r['map_origin'] ?? ['x' => 0, 'y' => 0];
        if (! isset($r['map_origin'])) {
            $warnings[] = 'map_origin ausente: adotado (0,0).';
        }
        $origin = $this->xy($origin, -1000000, 1000000);
        $size = $this->xy($r['map_size'] ?? null, 0.000001, 1000);
        $ppg = $r['pixels_per_grid'] ?? null;
        $this->check(is_int($ppg) && $ppg >= 8 && $ppg <= 256, 'pixels_per_grid deve ser inteiro entre 8 e 256.');
        $width = $size['x'] * $ppg;
        $height = $size['y'] * $ppg;
        $this->check($width >= 200 && $height >= 200 && $width <= 8000 && $height <= 8000 && abs(round($width) - $width) < 0.00001 && abs(round($height) - $height) < 0.00001, 'Dimensões devem gerar pixels inteiros entre 200 e 8000.');
        $width = (int) round($width);
        $height = (int) round($height);
        $this->check($width * $height <= 16000000, 'Imagem excede 16 milhões de pixels.');
        $encoded = $d['image'] ?? null;
        $this->check(is_string($encoded) && strlen($encoded) <= 4 * (int) ceil(self::MAX_IMAGE / 3) && strlen($encoded) % 4 === 0 && preg_match('/[^A-Za-z0-9+\/=]/', $encoded) === 0, 'image deve ser Base64 canônica, sem URL, prefixo ou espaços.');
        $image = base64_decode($encoded, true);
        $this->check(is_string($image) && strlen($image) > 0 && strlen($image) <= self::MAX_IMAGE && base64_encode($image) === $encoded, 'Imagem Base64 inválida ou maior que 20 MiB.');
        $info = @getimagesizefromstring($image);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($image);
        $this->check($info && in_array($mime, ['image/png', 'image/webp'], true) && $info['mime'] === $mime && $info[0] === $width && $info[1] === $height, 'Imagem deve ser PNG/WebP real com dimensões iguais a map_size × pixels_per_grid.');
        $this->validateContainer($image, $mime);
        $data = ['contract' => 1, 'format' => $d['format'], 'origin' => $origin, 'map_size' => $size, 'pixels_per_grid' => $ppg, 'walls' => [], 'portals' => [], 'lights' => []];
        $pointCount = 0;
        foreach (['line_of_sight', 'objects_line_of_sight'] as $field) {
            foreach ($this->items($d, $field, $warnings) as $line) {
                $this->check(count($data['walls']) < 1000, 'Limite total de 1.000 paredes excedido.');
                $this->check(is_array($line) && array_is_list($line) && count($line) >= 2 && count($line) <= 256, 'Polilinha deve ter de 2 a 256 pontos.');
                $this->check($pointCount + count($line) <= 20000, 'Limite total de pontos excedido.');
                $points = [];
                foreach ($line as $p) {
                    $points[] = $this->point($p, $origin, $ppg, $width, $height);
                    $pointCount++;
                    $this->check($pointCount <= 20000, 'Limite total de pontos excedido.');
                }
                $data['walls'][] = ['source' => $field, 'points' => $points];
            }
        }
        foreach ($this->items($d, 'portals', $warnings) as $p) {
            $this->check($pointCount + 2 <= 20000, 'Limite total de pontos excedido.');
            $this->check(is_array($p) && isset($p['bounds']) && is_array($p['bounds']) && array_is_list($p['bounds']) && count($p['bounds']) === 2, 'Portal exige bounds com exatamente dois pontos.');
            $bounds = array_map(fn ($v) => $this->point($v, $origin, $ppg, $width, $height), $p['bounds']);
            $pointCount += 2;
            $data['portals'][] = ['bounds' => $bounds, 'position' => isset($p['position']) ? $this->point($p['position'], $origin, $ppg, $width, $height) : ['x' => ($bounds[0]['x'] + $bounds[1]['x']) / 2, 'y' => ($bounds[0]['y'] + $bounds[1]['y']) / 2], 'rotation' => $this->number($p['rotation'] ?? 0, -100000, 100000), 'closed' => $this->boolean($p['closed'] ?? true), 'freestanding' => $this->boolean($p['freestanding'] ?? false)];
            if (array_diff(['position', 'rotation', 'closed', 'freestanding'], array_keys($p))) {
                $warnings[] = 'Portal incompleto: posição média, rotação 0, closed=true e freestanding=false usados quando ausentes.';
            }
            if (array_diff(array_keys($p), ['bounds', 'position', 'rotation', 'closed', 'freestanding'])) {
                $warnings[] = 'Propriedades adicionais de portal foram ignoradas.';
            }
        }
        foreach ($this->items($d, 'lights', $warnings) as $l) {
            $this->check($pointCount < 20000, 'Limite total de pontos excedido.');
            $this->check(is_array($l), 'Luz inválida.');
            $color = $l['color'] ?? 'ffffffff';
            $this->check(is_string($color) && preg_match('/\A#?(?:[a-fA-F0-9]{6}|[a-fA-F0-9]{8})\z/', $color) === 1, 'Cor deve ter 6 ou 8 dígitos hexadecimais.');
            $data['lights'][] = ['position' => $this->point($l['position'] ?? null, $origin, $ppg, $width, $height), 'range' => $this->number($l['range'] ?? null, 0, 1000) * $ppg, 'intensity' => $this->number($l['intensity'] ?? 1, 0, 100), 'color' => $color, 'shadows' => $this->boolean($l['shadows'] ?? true)];
            if (array_diff(['intensity', 'color', 'shadows'], array_keys($l))) {
                $warnings[] = 'Luz incompleta: intensity=1, color=ffffffff e shadows=true usados quando ausentes.';
            }
            if (array_diff(array_keys($l), ['position', 'range', 'intensity', 'color', 'shadows'])) {
                $warnings[] = 'Propriedades adicionais de luz foram ignoradas.';
            }
            $pointCount++;
        }
        $this->check($pointCount <= 20000 && count($data['walls']) <= 1000, 'Limite total de 20.000 pontos ou 1.000 paredes excedido.');
        if (array_diff(array_keys($d), ['format', 'resolution', 'image', 'line_of_sight', 'objects_line_of_sight', 'portals', 'lights'])) {
            $warnings[] = 'Campos adicionais, inclusive environment, software e creator, foram ignorados; não se aplica iluminação ambiente.';
        }
        if (array_diff(array_keys($r), ['map_origin', 'map_size', 'pixels_per_grid'])) {
            $warnings[] = 'Campos adicionais de resolution ignorados.';
        }
        if ($origin['x'] !== 0.0 || $origin['y'] !== 0.0) {
            $warnings[] = 'Origem normalizada: (ponto − map_origin) × pixels_per_grid. Esta é a política explícita do Freecast.';
        }

        return ['width' => $width, 'height' => $height, 'cell_size' => $ppg, 'mime' => $mime, 'image' => $image, 'data' => $data, 'warnings' => array_values(array_unique($warnings)), 'counts' => ['walls' => count($data['walls']), 'portals' => count($data['portals']), 'lights' => count($data['lights']), 'points' => $pointCount]];
    }

    /** Shared transform for every imported point. No token-anchor offset is applied. */
    public function point(mixed $p, array $origin, int $ppg, int $width, int $height): array
    {
        $p = $this->xy($p, -1000000, 1000000);
        $result = ['x' => ($p['x'] - $origin['x']) * $ppg, 'y' => ($p['y'] - $origin['y']) * $ppg];
        $this->check($result['x'] >= 0 && $result['y'] >= 0 && $result['x'] <= $width && $result['y'] <= $height, 'Coordenada fora do mapa normalizado.');

        return $result;
    }

    private function validateContainer(string $bytes, string $mime): void
    {
        if ($mime === 'image/png') {
            $offset = 8;
            $hasData = false;
            $ended = false;
            while ($offset < strlen($bytes)) {
                $this->check(strlen($bytes) - $offset >= 12, 'PNG truncado.');
                $length = unpack('N', substr($bytes, $offset, 4))[1];
                $this->check($length <= strlen($bytes) - $offset - 12, 'Chunk PNG truncado.');
                $type = substr($bytes, $offset + 4, 4);
                $chunk = substr($bytes, $offset + 4, $length + 4);
                $this->check(hash_equals(hash('crc32b', $chunk), bin2hex(substr($bytes, $offset + 8 + $length, 4))), 'CRC PNG inválido.');
                if ($type === 'IDAT') {
                    $hasData = true;
                }
                $offset += $length + 12;
                if ($type === 'IEND') {
                    $ended = true;
                    break;
                }
            }
            $this->check($hasData && $ended && $offset === strlen($bytes), 'PNG incompleto ou com conteúdo adicional.');
        } else {
            $this->check(substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' && unpack('V', substr($bytes, 4, 4))[1] === strlen($bytes) - 8, 'Container WebP inválido.');
            $offset = 12;
            $hasData = false;
            while ($offset < strlen($bytes)) {
                $this->check(strlen($bytes) - $offset >= 8, 'WebP truncado.');
                $type = substr($bytes, $offset, 4);
                $length = unpack('V', substr($bytes, $offset + 4, 4))[1];
                $this->check($length <= strlen($bytes) - $offset - 8, 'Chunk WebP truncado.');
                if (in_array($type, ['VP8 ', 'VP8L', 'ANMF'], true)) {
                    $hasData = true;
                }
                $offset += 8 + $length + ($length % 2);
            }
            $this->check($hasData && $offset === strlen($bytes), 'WebP incompleto.');
        }
    }

    private function items(array $d, string $field, array &$warnings): array
    {
        if (! array_key_exists($field, $d)) {
            $warnings[] = "$field ausente: lista vazia adotada.";

            return [];
        }
        $v = $d[$field];
        $this->check(is_array($v) && array_is_list($v) && count($v) <= 1000, "$field deve ser lista de até 1.000 elementos.");

        return $v;
    }

    private function xy(mixed $p, float $min, float $max): array
    {
        $this->check(is_array($p) && isset($p['x'],$p['y']), 'Ponto/dimensão exige x e y.');

        return ['x' => $this->number($p['x'], $min, $max), 'y' => $this->number($p['y'], $min, $max)];
    }

    private function number(mixed $n, float $min, float $max): float
    {
        $this->check((is_int($n) || is_float($n)) && is_finite((float) $n) && $n >= $min && $n <= $max, 'Número inválido ou fora dos limites.');

        return (float) $n;
    }

    private function boolean(mixed $v): bool
    {
        $this->check(is_bool($v),'Valor booleano inválido.');

        return $v;
    }

    private function check(bool $ok,string $message): void
    {
        if (! $ok) {
            $this->fail($message);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
