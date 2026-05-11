<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

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
     * @param  string  $language  Language code (e.g. 'en', 'ar').
     * @return array{word: string, hint: string}
     */
    public function generateWord(array $usedWords, string $language = 'en'): array
    {
        try {
            $avoidList = implode(', ', array_slice($usedWords, -20));

            $prompt = $this->buildPrompt($avoidList, $language);

            $wordAgent = agent(
                instructions: 'You are a creative word game assistant. You always respond with valid JSON matching the exact schema requested. Be creative and varied in your word choices.',
                schema: fn ($schema) => [
                    'word' => $schema->string()->description('A random everyday noun')->required(),
                    'hint' => $schema->string()->description('A related but vague hint word')->required(),
                ],
            );

            $response = $wordAgent->prompt($prompt, timeout: 120);

            $pair = $this->extractWordPair($response->structured ?? [])
                ?? $this->extractWordPair($this->parseJsonLoose($response->text ?? ''));

            if ($pair !== null) {
                return $pair;
            }

            Log::warning('AI word generation returned unusable payload, using fallback', [
                'text' => $response->text ?? null,
                'structured' => $response->structured ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI word generation failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->getFallbackWord($usedWords);
    }

    /**
     * Generate multiple word/hint pairs in a single AI call.
     *
     * @param  int  $count  Number of pairs to generate.
     * @param  array  $usedWords  Words to avoid repeating.
     * @param  string  $language  Language code (e.g. 'en', 'ar').
     * @return array<int, array{word: string, hint: string}>
     */
    public function generateWords(int $count, array $usedWords = [], string $language = 'en'): array
    {
        if ($count <= 0) {
            return [];
        }

        try {
            $avoidList = implode(', ', array_slice($usedWords, -20));
            $prompt = $this->buildBatchPrompt($count, $avoidList, $language);

            $batchAgent = agent(
                instructions: 'You are a creative word game assistant. You always respond with valid JSON matching the exact schema requested. Be creative and varied in your word choices, ensuring every entry is distinct.',
                schema: fn ($schema) => [
                    'rounds' => $schema->array()
                        ->items($schema->object([
                            'word' => $schema->string()->required(),
                            'hint' => $schema->string()->required(),
                        ]))
                        ->required(),
                ],
            );

            $response = $batchAgent->prompt($prompt, timeout: 180);

            $pairs = $this->extractWordPairs($response->structured ?? [])
                ?? $this->extractWordPairs($this->parseJsonLoose($response->text ?? ''));

            if (! empty($pairs)) {
                $pairs = $this->dedupePairs($pairs);

                if (count($pairs) >= $count) {
                    return array_slice($pairs, 0, $count);
                }

                $existing = array_merge($usedWords, array_column($pairs, 'word'));
                while (count($pairs) < $count) {
                    $pairs[] = $this->generateWord($existing, $language);
                    $existing[] = end($pairs)['word'];
                }

                return $pairs;
            }

            Log::warning('AI batch word generation returned unusable payload, using fallback', [
                'text' => $response->text ?? null,
                'structured' => $response->structured ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI batch word generation failed, falling back to per-round generation', [
                'error' => $e->getMessage(),
            ]);
        }

        $pairs = [];
        $existing = $usedWords;
        for ($i = 0; $i < $count; $i++) {
            $pair = $this->generateWord($existing, $language);
            $pairs[] = $pair;
            $existing[] = $pair['word'];
        }

        return $pairs;
    }

    /**
     * Extract a list of word/hint pairs from an arbitrary array shape.
     *
     * @param  array<string, mixed>|array<int, mixed>|null  $data
     * @return array<int, array{word: string, hint: string}>|null
     */
    private function extractWordPairs(?array $data): ?array
    {
        if (! is_array($data) || empty($data)) {
            return null;
        }

        $list = null;
        foreach (['rounds', 'words', 'items', 'data', 'pairs'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $list = $data[$key];
                break;
            }
        }

        if ($list === null && array_is_list($data)) {
            $list = $data;
        }

        if (! is_array($list)) {
            return null;
        }

        $pairs = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pair = $this->extractWordPair($item);
            if ($pair !== null) {
                $pairs[] = $pair;
            }
        }

        return $pairs ?: null;
    }

    /**
     * Remove duplicate word entries (case-insensitive on word value).
     *
     * @param  array<int, array{word: string, hint: string}>  $pairs
     * @return array<int, array{word: string, hint: string}>
     */
    private function dedupePairs(array $pairs): array
    {
        $seen = [];
        $out = [];
        foreach ($pairs as $pair) {
            $key = mb_strtolower($pair['word']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $pair;
        }
        return $out;
    }

    /**
     * Pull a word/hint pair out of an arbitrary array, tolerating common
     * alternate key names that the LLM sometimes produces.
     *
     * @param  array<string, mixed>|null  $data
     * @return array{word: string, hint: string}|null
     */
    private function extractWordPair(?array $data): ?array
    {
        if (! is_array($data) || empty($data)) {
            return null;
        }

        $wordKeys = ['word', 'noun', 'secret', 'answer', 'الكلمة', 'كلمة'];
        $hintKeys = ['hint', 'clue', 'related', 'تلميح', 'الدليل'];

        $word = null;
        $hint = null;

        foreach ($wordKeys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $word = trim($data[$key]);
                break;
            }
        }

        foreach ($hintKeys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $hint = trim($data[$key]);
                break;
            }
        }

        if ($word === null || $hint === null) {
            return null;
        }

        return ['word' => $word, 'hint' => $hint];
    }

    /**
     * Parse JSON from a string, tolerating markdown code fences and
     * surrounding text the LLM may have added.
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonLoose(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Build the prompt for the AI agent.
     */
    private function buildPrompt(string $avoidList, string $language = 'en'): string
    {
        $categories = [
            'a profession or occupation',
            'a tool or instrument',
            'a place or location',
            'a vehicle or mode of transport',
            'a sport or hobby',
            'a piece of clothing or accessory',
            'a musical instrument',
            'a famous landmark or building type',
            'an animal',
            'a piece of technology or appliance',
            'a food or drink',
            'a natural phenomenon',
        ];
        $category = $categories[array_rand($categories)];

        [$languageName, $examples, $extraRule, $categoryLine] = match ($language) {
            'ar' => [
                'Arabic (Modern Standard Arabic)',
                "- مثال جيد: word=\"منارة\"، hint=\"عزلة\" — العزلة شعور مرتبط بالمنارة لكنه غامض يصلح لأشياء كثيرة\n- مثال جيد: word=\"طبيب\"، hint=\"ثقة\" — الثقة مرتبطة بالطبيب لكنها مفهوم عام\n- مثال سيء: word=\"بيتزا\"، hint=\"جبنة\" — مباشر جداً\n- مثال سيء: word=\"محيط\"، hint=\"أمواج\" — مباشر جداً",
                "- اكتب الكلمة والتلميح باللغة العربية فقط. لا تستخدم أي حروف لاتينية إطلاقاً.",
                "- يجب أن تكون \"الكلمة\" من هذه الفئة: {$category}",
            ],
            default => [
                'English',
                "- GOOD example: word=\"Lighthouse\", hint=\"Solitude\" — solitude evokes a lighthouse but is abstract enough to fit many things\n- GOOD example: word=\"Dentist\", hint=\"Routine\" — routine fits a dentist visit but is broad\n- GOOD example: word=\"Volcano\", hint=\"Pressure\" — abstract concept, not a direct physical clue\n- BAD example: word=\"Pizza\", hint=\"Cheese\" — way too obvious, gives the answer away\n- BAD example: word=\"Ocean\", hint=\"Waves\" — way too direct",
                '',
                "- The \"word\" must be from this category: {$category}",
            ],
        };

        $prompt = <<<PROMPT
Generate a creative, interesting word and an oblique "hint" for a social deduction game called Imposter.

Respond with a JSON object containing EXACTLY two keys: "word" and "hint". Use those exact key names.

WORD requirements:
- Must be a single, recognizable noun that everyone knows (no obscure jargon)
- Be CREATIVE and SPECIFIC — avoid the most generic nouns like "pizza", "car", "house", "dog", "ocean", "tree"
- Prefer concrete, evocative things (e.g. lighthouse, astronaut, parachute, dentist, vinyl, telescope, glacier)
{$categoryLine}

HINT requirements:
- A single word that is RELATED to the real word but NOT a direct physical part, ingredient, or synonym
- Prefer ABSTRACT concepts: a feeling, a quality, an action, a setting, a purpose, an atmosphere
- The hint should be vague enough that an imposter who doesn't know the real word could plausibly say a similar word and blend in
- The hint must NOT be a defining feature, ingredient, or location of the word
- NEVER use words like "wheels" for car, "cheese" for pizza, "pages" for book — those give it away

Examples:
{$examples}

Final rules:
- Both values must be a single word
- Both the "word" and the "hint" MUST be written in {$languageName}.
{$extraRule}
PROMPT;

        if (! empty($avoidList)) {
            $prompt .= "\n\nDo NOT use any of these words (already used in previous rounds): {$avoidList}";
        }

        return $prompt;
    }

    /**
     * Build the prompt for batch generation of N word/hint pairs.
     */
    private function buildBatchPrompt(int $count, string $avoidList, string $language = 'en'): string
    {
        [$languageName, $examples, $extraRule, $bannedExamples] = match ($language) {
            'ar' => [
                'Arabic (Modern Standard Arabic)',
                "- مثال جيد: word=\"منارة\"، hint=\"عزلة\"\n- مثال جيد: word=\"طبيب\"، hint=\"ثقة\"\n- مثال جيد: word=\"بركان\"، hint=\"ضغط\"\n- مثال سيء: word=\"بيتزا\"، hint=\"جبنة\" (مباشر جداً)\n- مثال سيء: word=\"محيط\"، hint=\"أمواج\" (مباشر جداً)",
                "- اكتب جميع الكلمات والتلميحات باللغة العربية فقط. لا تستخدم أي حروف لاتينية إطلاقاً.",
                'منارة، طبيب، بركان، بيتزا، محيط',
            ],
            default => [
                'English',
                "- GOOD: word=\"Lighthouse\", hint=\"Solitude\"\n- GOOD: word=\"Dentist\", hint=\"Routine\"\n- GOOD: word=\"Volcano\", hint=\"Pressure\"\n- GOOD: word=\"Astronaut\", hint=\"Distance\"\n- BAD: word=\"Pizza\", hint=\"Cheese\" (way too obvious)\n- BAD: word=\"Ocean\", hint=\"Waves\" (way too direct)",
                '',
                'Lighthouse, Dentist, Volcano, Astronaut, Pizza, Ocean',
            ],
        };

        $prompt = <<<PROMPT
Generate EXACTLY {$count} distinct word/hint pairs for a social deduction game called Imposter.

Respond with a JSON object containing a single key "rounds" whose value is an array of EXACTLY {$count} objects. Each object MUST have exactly two keys: "word" and "hint". Use those exact key names.

Schema:
{
  "rounds": [
    { "word": "...", "hint": "..." },
    ... ({$count} entries total)
  ]
}

WORD requirements (apply to every entry):
- Must be a single, recognizable noun that everyone knows (no obscure jargon)
- Be CREATIVE and SPECIFIC — avoid the most generic nouns like "pizza", "car", "house", "dog", "ocean", "tree"
- Prefer concrete, evocative things (e.g. lighthouse, astronaut, parachute, dentist, vinyl, telescope, glacier, submarine, compass)
- Spread across DIFFERENT categories (profession, tool, place, vehicle, hobby, instrument, landmark, animal, technology, food, natural phenomenon)
- Every word must be DISTINCT from every other word in the list

HINT requirements (apply to every entry):
- A single word RELATED to the real word but NOT a direct physical part, ingredient, or synonym
- Prefer ABSTRACT concepts: a feeling, a quality, an action, a setting, a purpose, an atmosphere
- Vague enough that an imposter who doesn't know the real word could plausibly bluff a similar word
- NEVER use defining features (e.g. "wheels" for car, "cheese" for pizza, "pages" for book)

Examples (style guide):
{$examples}

Final rules:
- Each "word" and each "hint" must be a single word
- Every value MUST be written in {$languageName}.
{$extraRule}
- Return EXACTLY {$count} entries — not more, not fewer
- The example words above are illustrations ONLY. Do NOT include any of them in your output. Forbidden: {$bannedExamples}
PROMPT;

        if (! empty($avoidList)) {
            $prompt .= "\n\nAlso do NOT use any of these words (already used in previous games): {$avoidList}";
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
