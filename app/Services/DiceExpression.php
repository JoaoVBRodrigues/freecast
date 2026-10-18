<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DiceExpression
{
    private const SIDES = [4, 6, 8, 10, 12, 20, 100];

    /**
     * @return array{expression: string, components: array<int, array<string, mixed>>, total: int}
     */
    public function roll(string $expression): array
    {
        $expression = strtolower(preg_replace('/\s+/', '', $expression) ?? '');
        if ($expression === '' || strlen($expression) > 120) {
            $this->invalid();
        }

        preg_match_all('/\d{1,3}d(?:100|20|12|10|8|6|4)|\d{1,6}|[+-]/', $expression, $matches);
        $tokens = $matches[0];
        if (implode('', $tokens) !== $expression || count($tokens) > 20) {
            $this->invalid();
        }

        $components = [];
        $total = 0;
        $diceCount = 0;
        $index = 0;
        $sign = 1;
        $expectTerm = true;

        while ($index < count($tokens)) {
            if ($tokens[$index] === '+' || $tokens[$index] === '-') {
                if ($expectTerm && $index !== 0) {
                    $this->invalid();
                }
                $sign = $tokens[$index] === '-' ? -1 : 1;
                $expectTerm = true;
                $index++;
                if ($index >= count($tokens)) {
                    $this->invalid();
                }
            } elseif (! $expectTerm) {
                $this->invalid();
            }

            $term = $tokens[$index++];
            if (preg_match('/^(\d{1,3})d(\d+)$/', $term, $dice)) {
                $count = (int) $dice[1];
                $sides = (int) $dice[2];
                if ($count < 1 || ! in_array($sides, self::SIDES, true) || $diceCount + $count > 100) {
                    $this->invalid();
                }

                $results = [];
                for ($die = 0; $die < $count; $die++) {
                    $results[] = random_int(1, $sides);
                }

                $subtotal = array_sum($results) * $sign;
                $components[] = [
                    'kind' => 'dice',
                    'sign' => $sign,
                    'count' => $count,
                    'sides' => $sides,
                    'results' => $results,
                    'subtotal' => $subtotal,
                ];
                $diceCount += $count;
                $total += $subtotal;
            } elseif (ctype_digit($term)) {
                $value = (int) $term;
                $subtotal = $value * $sign;
                $components[] = ['kind' => 'constant', 'sign' => $sign, 'value' => $value, 'subtotal' => $subtotal];
                $total += $subtotal;
            } else {
                $this->invalid();
            }

            $expectTerm = false;
            $sign = 1;
        }

        if ($expectTerm || $components === [] || abs($total) > 10000000) {
            $this->invalid();
        }

        return ['expression' => $expression, 'components' => $components, 'total' => $total];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'expression' => 'Use dados d4, d6, d8, d10, d12, d20 ou d100, com até 100 dados e somas/subtrações.',
        ]);
    }
}
