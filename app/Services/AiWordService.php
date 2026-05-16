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
                "- جيد: word=\"مظلة\"، hint=\"مطر\" — مطر ممكن يكون مع: معطف، سحاب، نافذة، حذاء\n- جيد: word=\"سكين\"، hint=\"مطبخ\" — مطبخ ممكن يكون مع: فرن، ثلاجة، طباخ\n- جيد: word=\"حصان\"، hint=\"مزرعة\" — مزرعة ممكن تكون مع: بقرة، جرار، حظيرة\n- جيد: word=\"ساعة\"، hint=\"وقت\" — وقت ممكن يكون مع: تقويم، ساعة يد\n- سيء: word=\"بيتزا\"، hint=\"جبنة\" — جزء من البيتزا، مباشر جداً\n- سيء: word=\"مظلة\"، hint=\"خفة\" — كلمة خفة ما تخلي أحد يفكر بالمظلة\n- سيء: word=\"تلسكوب\"، hint=\"نجوم\" — جزء من التلسكوب، مباشر",
                '- اكتب الكلمة والتلميح باللغة العربية فقط. لا تستخدم أي حروف لاتينية إطلاقاً.',
                "- يجب أن تكون \"الكلمة\" من هذه الفئة: {$category}",
            ],
            default => [
                'English',
                "- GOOD: word=\"Umbrella\", hint=\"Rain\" — rain also fits: raincoat, clouds, window, boots\n- GOOD: word=\"Knife\", hint=\"Kitchen\" — kitchen also fits: stove, fridge, chef, recipe\n- GOOD: word=\"Horse\", hint=\"Farm\" — farm also fits: cow, tractor, barn, chicken\n- GOOD: word=\"Clock\", hint=\"Time\" — time also fits: calendar, watch, hourglass\n- BAD: word=\"Pizza\", hint=\"Cheese\" — ingredient, too obvious\n- BAD: word=\"Umbrella\", hint=\"Lightness\" — useless abstract hint\n- BAD: word=\"Telescope\", hint=\"Stars\" — too direct",
                '',
                "- The \"word\" must be from this category: {$category}",
            ],
        };

        $prompt = <<<PROMPT
You are generating a word and hint for the social deduction game "Imposter".

HOW THE GAME WORKS:
- All players get the SAME word EXCEPT one player (the imposter) who gets only the hint.
- Players take turns saying ONE word related to their word. The imposter must blend in without knowing the real word.
- The hint is given to the imposter as their ONLY clue. It must be useful enough that the imposter can participate, but not so specific that they can guess the word.

RULES FOR THE HINT:
- The hint must be a STRONG association — something most people would think of when they hear the word
- BUT it must also be AMBIGUOUS enough that 2-3 other common words could match it
- The hint must NOT be a physical part/ingredient (e.g. NOT "wax" for candle, NOT "cheese" for pizza)
- The hint must NOT be an abstract quality (e.g. NOT "lightness" for umbrella, NOT "pressure" for volcano)
- The hint SHOULD be: a related place, activity, context, or strong association
- Sweet spot examples:
  - umbrella → rain (but rain also fits raincoat, clouds, window, boots)
  - knife → kitchen (but kitchen also fits stove, fridge, chef, recipe)
  - guitar → concert (but concert also fits singer, drums, stage, ticket)
  - clock → time (but time also fits calendar, watch, hourglass)

WORD requirements:
- A single, recognizable noun everyone knows
- Avoid the most overused words (pizza, car, house, dog, ocean, tree)
- Prefer specific, evocative nouns (lighthouse, astronaut, telescope, compass, parachute, archaeologist)
{$categoryLine}

Examples:
{$examples}

Respond with a JSON object: { "word": "...", "hint": "..." }
Both must be a single word in {$languageName}.
{$extraRule}
PROMPT;

        if (! empty($avoidList)) {
            $prompt .= "\n\nDo NOT use any of these words (already used): {$avoidList}";
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
                "- جيد: word=\"مظلة\"، hint=\"مطر\" — قوي ومفيد، لكن مطر ممكن يكون مع: معطف، سحاب، نافذة، حذاء\n- جيد: word=\"سكين\"، hint=\"مطبخ\" — قوي ومفيد، لكن مطبخ ممكن يكون مع: فرن، ثلاجة، طباخ\n- جيد: word=\"حصان\"، hint=\"مزرعة\" — قوي ومفيد، لكن مزرعة ممكن تكون مع: بقرة، جرار، حظيرة\n- جيد: word=\"ساعة\"، hint=\"وقت\" — قوي ومفيد، لكن وقت ممكن يكون مع: تقويم، ساعة يد، رمل\n- سيء: word=\"بيتزا\"، hint=\"جبنة\" — جزء من البيتزا، مباشر جداً\n- سيء: word=\"مظلة\"، hint=\"خفة\" — كلمة خفة ما تخلي أحد يفكر بالمظلة\n- سيء: word=\"تلسكوب\"، hint=\"نجوم\" — جزء من التلسكوب، مباشر",
                '- اكتب جميع الكلمات والتلميحات باللغة العربية فقط. لا تستخدم أي حروف لاتينية إطلاقاً.',
                'مظلة، سكين، حصان، ساعة، بيتزا، تلسكوب، منارة، بوصلة، مفتاح، شمعة، جيتار، قارب، فأس، طبال',
            ],
            default => [
                'English',
                "- GOOD: word=\"Umbrella\", hint=\"Rain\" — strong, but rain also fits: raincoat, clouds, window, boots\n- GOOD: word=\"Knife\", hint=\"Kitchen\" — strong, but kitchen also fits: stove, fridge, chef, recipe\n- GOOD: word=\"Horse\", hint=\"Farm\" — strong, but farm also fits: cow, tractor, barn, chicken\n- GOOD: word=\"Clock\", hint=\"Time\" — strong, but time also fits: calendar, watch, hourglass\n- GOOD: word=\"Crown\", hint=\"King\" — strong, but king also fits: castle, throne, kingdom\n- BAD: word=\"Pizza\", hint=\"Cheese\" — ingredient, too obvious\n- BAD: word=\"Umbrella\", hint=\"Lightness\" — useless abstract hint\n- BAD: word=\"Telescope\", hint=\"Stars\" — too direct, stars is what you look at",
                '',
                'Umbrella, Knife, Horse, Clock, Crown, Pizza, Telescope, Lighthouse, Compass, Guitar, Astronaut, Parachute, Violin, Candle, Drum, Lantern, Mirror, Bridge, Key',
            ],
        };

        $prompt = <<<PROMPT
Generate EXACTLY {$count} distinct word/hint pairs for the social deduction game "Imposter".

HOW THE GAME WORKS:
- All players get the SAME word EXCEPT the imposter, who gets only the hint.
- Players take turns saying ONE word related to their word. The imposter must blend in.
- The hint is the imposter's ONLY clue about the real word.

Respond with JSON: { "rounds": [ { "word": "...", "hint": "..." }, ... ] }
Exactly {$count} entries. Keys must be "word" and "hint".

WORD requirements:
- Single, recognizable noun everyone knows
- DO NOT use any of these overused words: pizza, car, house, dog, ocean, tree, sun, moon, cat, fish, book, phone, chair, table, door, water, fire, bed, shoe, hat, ball, ring, key, knife, candle, clock, mirror, bridge, crown, horse, umbrella, guitar, drum, violin, compass, telescope, lighthouse, parachute, astronaut, volcano, submarine, penguin, butterfly, rainbow, snowman
- Prefer specific, evocative nouns (lighthouse, astronaut, telescope, compass, parachute)
- Spread across different categories: profession, tool, place, vehicle, hobby, instrument, landmark, animal, technology, food, natural phenomenon
- Every word must be DISTINCT

HINT requirements (THIS IS CRITICAL — READ CAREFULLY):
- The hint must be a STRONG association with the word — something most people would think of
- BUT it must also be ambiguous enough that 2-3 other common words could match it
- The hint must NOT be a physical part/ingredient (e.g. NOT "wax" for candle, NOT "cheese" for pizza, NOT "mane" for horse)
- The hint must NOT be an abstract quality (e.g. NOT "lightness" for umbrella, NOT "pressure" for volcano, NOT "solitude" for lighthouse)
- The hint SHOULD be: a related place, activity, context, or strong association
- PERFECT examples of the sweet spot:
  - umbrella → rain (strong association, but "rain" also fits: raincoat, window, clouds, boots)
  - knife → kitchen (strong association, but "kitchen" also fits: stove, recipe, chef, fridge)
  - guitar → concert (strong association, but "concert" also fits: singer, drums, stage, ticket)
  - clock → time (strong association, but "time" also fits: calendar, hourglass, watch, stopwatch)
  - horse → farm (strong association, but "farm" also fits: cow, tractor, barn, chicken)

Examples:
{$examples}

Final rules:
- Each word and hint must be a SINGLE word
- All values in {$languageName}.
{$extraRule}
- Return EXACTLY {$count} entries
- Do NOT reuse any example words. Forbidden: {$bannedExamples}
PROMPT;

        if (! empty($avoidList)) {
            $prompt .= "\n\nAlso avoid these words (used in previous games): {$avoidList}";
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
