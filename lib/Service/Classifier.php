<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

/**
 * Which kind of document a text is. Plain keyword scoring: every keyword of a rule that occurs in
 * the text (whole words, no diacritics, no case) adds its weight; the rule with the highest score
 * wins if it reaches its minimum. Simple on purpose: the rules are readable and the administrator
 * can fix a wrong guess by adding a word.
 */
final class Classifier
{
    /**
     * @param list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}> $rules
     *
     * @return array{kind:?string, category:?string, score:int, min:int, hits:list<string>, runnerUp:?string, runnerUpScore:int}
     */
    public static function classify(string $text, array $rules): array
    {
        $haystack = ' '.self::normalise($text).' ';
        $best = null;
        $second = null;
        foreach ($rules as $rule) {
            $score = 0;
            $hits = [];
            foreach ($rule['keywords'] as $keyword => $weight) {
                if (str_contains($haystack, ' '.$keyword.' ')) {
                    $score += $weight;
                    $hits[] = $keyword;
                }
            }
            if (0 === $score) {
                continue;
            }
            $entry = ['rule' => $rule, 'score' => $score, 'hits' => $hits];
            if (null === $best || $score > $best['score']) {
                $second = $best;
                $best = $entry;
            } elseif (null === $second || $score > $second['score']) {
                $second = $entry;
            }
        }
        if (null === $best || $best['score'] < $best['rule']['min']) {
            return ['kind' => null, 'category' => null, 'score' => $best['score'] ?? 0, 'min' => $best['rule']['min'] ?? 0, 'hits' => $best['hits'] ?? [], 'runnerUp' => null, 'runnerUpScore' => 0];
        }

        return [
            'kind' => $best['rule']['id'],
            'category' => $best['rule']['category'],
            'score' => $best['score'],
            'min' => $best['rule']['min'],
            'hits' => $best['hits'],
            'runnerUp' => $second['rule']['id'] ?? null,
            'runnerUpScore' => $second['score'] ?? 0,
        ];
    }

    /** Lower case, no diacritics, one space between words, punctuation gone. */
    public static function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'é' => 'e', 'è' => 'e', 'ö' => 'o', 'ü' => 'u', 'ä' => 'a']);
        $text = preg_replace('/[^\p{L}\p{N}<\-]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
