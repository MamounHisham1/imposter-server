<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\agent;

class AiWordService
{
    /**
     * Hardcoded fallback word/hint pairs used when AI is unavailable.
     */
    private const FALLBACK_WORDS = [
        ['word' => 'Pizza', 'hint' => 'Cheese'],
        ['word' => 'Ocean', 'hint' => 'Waves'],
        ['word' => 'Guitar', 'hint' => 'Strings'],
        ['word' => 'Mountain', 'hint' => 'Summit'],
        ['word' => 'Coffee', 'hint' => 'Morning'],
        ['word' => 'Library', 'hint' => 'Books'],
        ['word' => 'Hospital', 'hint' => 'Healing'],
        ['word' => 'Airplane', 'hint' => 'Wings'],
        ['word' => 'Garden', 'hint' => 'Seeds'],
        ['word' => 'Piano', 'hint' => 'Keys'],
        ['word' => 'Desert', 'hint' => 'Sand'],
        ['word' => 'Telescope', 'hint' => 'Stars'],
        ['word' => 'Umbrella', 'hint' => 'Rain'],
        ['word' => 'Candle', 'hint' => 'Flame'],
        ['word' => 'Bicycle', 'hint' => 'Pedals'],
        ['word' => 'Refrigerator', 'hint' => 'Cold'],
        ['word' => 'Camera', 'hint' => 'Lens'],
        ['word' => 'Theater', 'hint' => 'Stage'],
        ['word' => 'Balloon', 'hint' => 'Float'],
        ['word' => 'Anchor', 'hint' => 'Ship'],
        ['word' => 'Forest', 'hint' => 'Trees'],
        ['word' => 'Clock', 'hint' => 'Hands'],
        ['word' => 'Volcano', 'hint' => 'Lava'],
        ['word' => 'Bridge', 'hint' => 'River'],
        ['word' => 'Lighthouse', 'hint' => 'Beam'],
        ['word' => 'Compass', 'hint' => 'North'],
        ['word' => 'Pillow', 'hint' => 'Sleep'],
        ['word' => 'Treasure', 'hint' => 'Map'],
        ['word' => 'Rocket', 'hint' => 'Launch'],
        ['word' => 'Penguin', 'hint' => 'Ice'],
        ['word' => 'Drum', 'hint' => 'Beat'],
        ['word' => 'Rainbow', 'hint' => 'Colors'],
        ['word' => 'Snowman', 'hint' => 'Winter'],
        ['word' => 'Treasure', 'hint' => 'Gold'],
        ['word' => 'Butterfly', 'hint' => 'Wings'],
        ['word' => 'Submarine', 'hint' => 'Deep'],
        ['word' => 'Flashlight', 'hint' => 'Dark'],
        ['word' => 'Kite', 'hint' => 'Wind'],
        ['word' => 'Ladder', 'hint' => 'Climb'],
        ['word' => 'Whistle', 'hint' => 'Sound'],
        ['word' => 'Helmet', 'hint' => 'Protection'],
        ['word' => 'Parachute', 'hint' => 'Jump'],
        ['word' => 'Microscope', 'hint' => 'Small'],
        ['word' => 'Suitcase', 'hint' => 'Travel'],
        ['word' => 'Crown', 'hint' => 'King'],
        ['word' => 'Fence', 'hint' => 'Boundary'],
        ['word' => 'Toothbrush', 'hint' => 'Clean'],
        ['word' => 'Strawberry', 'hint' => 'Sweet'],
        ['word' => 'Jungle', 'hint' => 'Vines'],
    ];

    /**
     * Generate a random word and imposter hint using AI.
     *
     * @param  array  $usedWords  Words to avoid repeating.
     * @return array{word: string, hint: string}
     */
    public function generateWord(array $usedWords): array
    {
        try {
            $avoidList = implode(', ', array_slice($usedWords, -20));

            $prompt = $this->buildPrompt($avoidList);

            $wordAgent = agent(
                instructions: 'You are a creative word game assistant. You always respond with valid JSON matching the exact schema requested. Be creative and varied in your word choices.',
                model: config('ai.providers.' . config('ai.default') . '.model', 'gpt-4o-mini'),
                schema: fn ($schema) => $schema
                    ->type('object')
                    ->properties([
                        'word' => $schema->type('string')->description('A random everyday noun'),
                        'hint' => $schema->type('string')->description('A related but vague hint word'),
                    ])
                    ->required(['word', 'hint']),
            );

            $response = $wordAgent->prompt($prompt);

            $data = $response->structured ?? null;

            if ($data && isset($data['word'], $data['hint'])) {
                return [
                    'word' => trim($data['word']),
                    'hint' => trim($data['hint']),
                ];
            }

            // Try parsing text as JSON fallback
            $decoded = json_decode($response->text, true);
            if ($decoded && isset($decoded['word'], $decoded['hint'])) {
                return [
                    'word' => trim($decoded['word']),
                    'hint' => trim($decoded['hint']),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('AI word generation failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->getFallbackWord($usedWords);
    }

    /**
     * Build the prompt for the AI agent.
     */
    private function buildPrompt(string $avoidList): string
    {
        $prompt = <<<'PROMPT'
Generate a single random everyday word (must be a noun) and a related "hint" word for a social deduction game.

Rules:
- The "word" should be a common, everyday noun (e.g., Pizza, Guitar, Ocean)
- The "hint" should be a single word that is associated with the real word, but vague enough that an imposter (who doesn't know the real word) could still try to blend in when giving their own hint
- Example: word="Pizza", hint="Cheese" — cheese is related to pizza, but someone could plausibly give "cheese" as a hint for many things
- Example: word="Ocean", hint="Waves" — waves are associated with the ocean, but vague enough to fool people
- The hint must NOT be too obvious or specific (don't use "pepperoni" for Pizza)
- Both words should be single words, capitalized
PROMPT;

        if (! empty($avoidList)) {
            $prompt .= "\n\nDo NOT use any of these words (already used in previous rounds): {$avoidList}";
        }

        return $prompt;
    }

    /**
     * Get a fallback word from the hardcoded list, avoiding used words.
     *
     * @return array{word: string, hint: string}
     */
    private function getFallbackWord(array $usedWords): array
    {
        $available = array_filter(self::FALLBACK_WORDS, function (array $pair) use ($usedWords) {
            return ! in_array(strtolower($pair['word']), array_map('strtolower', $usedWords));
        });

        if (empty($available)) {
            $available = self::FALLBACK_WORDS;
        }

        return Arr::random($available);
    }
}
