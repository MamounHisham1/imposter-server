<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class AiWordService
{
    /**
     * Emergency static fallback (English) used only when the `words` table
     * is empty or yields no candidates. The curated word pool in the DB is
     * the primary source of truth.
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

    private const FALLBACK_WORDS_EASY = [
        ['word' => 'Apple', 'hint' => 'Tree'],
        ['word' => 'Car', 'hint' => 'Road'],
        ['word' => 'House', 'hint' => 'Family'],
        ['word' => 'Dog', 'hint' => 'Park'],
        ['word' => 'Sun', 'hint' => 'Sky'],
        ['word' => 'Book', 'hint' => 'Story'],
        ['word' => 'Phone', 'hint' => 'Call'],
        ['word' => 'Chair', 'hint' => 'Sit'],
        ['word' => 'Cat', 'hint' => 'Purr'],
        ['word' => 'Ball', 'hint' => 'Throw'],
        ['word' => 'Milk', 'hint' => 'Cow'],
        ['word' => 'Shoe', 'hint' => 'Walk'],
        ['word' => 'Hat', 'hint' => 'Head'],
        ['word' => 'Fish', 'hint' => 'Water'],
        ['word' => 'Tree', 'hint' => 'Leaf'],
        ['word' => 'Moon', 'hint' => 'Night'],
        ['word' => 'Egg', 'hint' => 'Breakfast'],
        ['word' => 'Door', 'hint' => 'Open'],
        ['word' => 'Bed', 'hint' => 'Sleep'],
        ['word' => 'Cup', 'hint' => 'Drink'],
    ];

    private const FALLBACK_WORDS_HARD = [
        ['word' => 'Astrolabe', 'hint' => 'Navigation'],
        ['word' => 'Trebuchet', 'hint' => 'Siege'],
        ['word' => 'Bioluminescence', 'hint' => 'Depth'],
        ['word' => 'Symbiosis', 'hint' => 'Partnership'],
        ['word' => 'Cathode', 'hint' => 'Current'],
        ['word' => 'Gargoyle', 'hint' => 'Cathedral'],
        ['word' => 'Caldera', 'hint' => 'Collapse'],
        ['word' => 'Corona', 'hint' => 'Eclipse'],
        ['word' => 'Quartz', 'hint' => 'Resonance'],
        ['word' => 'Obsidian', 'hint' => 'Volcanic'],
        ['word' => 'Zephyr', 'hint' => 'Breeze'],
        ['word' => 'Albedo', 'hint' => 'Reflection'],
        ['word' => 'Stalactite', 'hint' => 'Mineral'],
        ['word' => 'Tundra', 'hint' => 'Permafrost'],
        ['word' => 'Abyss', 'hint' => 'Pressure'],
        ['word' => 'Mirage', 'hint' => 'Refraction'],
        ['word' => 'Aurora', 'hint' => 'Magnetic'],
        ['word' => 'Equinox', 'hint' => 'Balance'],
        ['word' => 'Prism', 'hint' => 'Spectrum'],
        ['word' => 'Cipher', 'hint' => 'Algorithm'],
    ];

    /**
     * Words excluded defensively at runtime even if they slip into the pool.
     * The generator also avoids these when building the pool.
     */
    private const BANNED_WORDS = [
        'pizza', 'car', 'house', 'dog', 'ocean', 'tree', 'sun', 'moon', 'cat', 'fish',
        'book', 'phone', 'chair', 'table', 'door', 'water', 'fire', 'bed', 'shoe', 'hat',
        'ball', 'ring', 'key', 'knife', 'candle', 'clock', 'mirror', 'bridge', 'crown', 'horse',
        'umbrella', 'guitar', 'drum', 'violin', 'compass', 'telescope', 'lighthouse', 'parachute',
        'astronaut', 'volcano', 'submarine', 'penguin', 'butterfly', 'rainbow', 'snowman',
        'apple', 'flower', 'mountain', 'river', 'forest', 'desert', 'cloud', 'star', 'wind',
        'sword', 'shield', 'castle', 'tower', 'dragon', 'knight', 'king', 'queen', 'princess',
    ];

    /**
     * Resolve a single word/hint pair from the curated DB pool.
     *
     * @param  array  $usedWords  Words to avoid repeating (in the room's language).
     * @param  string  $language  Language code ('en' or 'ar').
     * @param  string|null  $category  Optional category to constrain to.
     * @param  string  $difficulty  'easy', 'medium', or 'hard'.
     * @return array{word: string, hint: string}
     */
    public function generateWord(array $usedWords, string $language = 'en', ?string $category = null, string $difficulty = 'medium'): array
    {
        $pairs = $this->pickFromPool(1, $usedWords, $language, $category, $difficulty);

        if (! empty($pairs)) {
            return $pairs[0];
        }

        return $this->fallbackWord($difficulty);
    }

    /**
     * Resolve multiple word/hint pairs from the curated DB pool.
     *
     * @param  int  $count  Number of distinct pairs to return.
     * @param  array  $usedWords  Words to avoid repeating.
     * @param  string  $language  Language code ('en' or 'ar').
     * @param  string|null  $category  Optional category to constrain to.
     * @param  string  $difficulty  'easy', 'medium', or 'hard'.
     * @return array<int, array{word: string, hint: string}>
     */
    public function generateWords(int $count, array $usedWords = [], string $language = 'en', ?string $category = null, string $difficulty = 'medium'): array
    {
        if ($count <= 0) {
            return [];
        }

        $pairs = $this->pickFromPool($count, $usedWords, $language, $category, $difficulty);

        while (count($pairs) < $count) {
            $pairs[] = $this->fallbackWord($difficulty);
        }

        return array_slice($pairs, 0, $count);
    }

    /**
     * Query the curated pool for distinct, unused word/hint pairs.
     *
     * @return array<int, array{word: string, hint: string}>
     */
    private function pickFromPool(int $count, array $usedWords, string $language, ?string $category, string $difficulty): array
    {
        if (! Word::exists()) {
            return [];
        }

        $exclude = $this->buildExcludeList($usedWords);

        $baseQuery = Word::where('enabled', true)
            ->whereIn('difficulty', $this->difficultiesFor($difficulty));

        // Only constrain by category when it has a healthy supply of rows;
        // otherwise fall back to "any category" so the round can still start.
        if ($category !== null) {
            $categoryQuery = (clone $baseQuery)->where('category', $category);
            if ($categoryQuery->count() >= 12) {
                $baseQuery = $categoryQuery;
            }
        }

        // Over-fetch (in random order) so PHP-side dedupe/exclusion can still
        // yield the requested count even after dropping used/banned words.
        $rows = (clone $baseQuery)
            ->inRandomOrder()
            ->limit(max($count * 6, 30))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if (in_array(mb_strtolower($row->word_en), $exclude, true)) {
                continue;
            }
            if (in_array(mb_strtolower($row->word_ar), $exclude, true)) {
                continue;
            }

            $out[] = $language === 'ar'
                ? ['word' => $row->word_ar, 'hint' => $row->hint_ar]
                : ['word' => $row->word_en, 'hint' => $row->hint_en];

            if (count($out) >= $count) {
                break;
            }
        }

        if (empty($out)) {
            Log::warning('Word pool returned no candidates for the requested filters', [
                'language' => $language,
                'category' => $category,
                'difficulty' => $difficulty,
                'pool_size' => Word::count(),
            ]);
        }

        return $out;
    }

    /**
     * Map a requested difficulty to the pool difficulties we should draw from.
     * 'hard' falls back to 'medium' because the pool only ships easy + medium.
     *
     * @return string[]
     */
    private function difficultiesFor(string $difficulty): array
    {
        return match ($difficulty) {
            'easy' => ['easy'],
            'hard' => ['hard', 'medium'],
            default => ['medium'],
        };
    }

    /**
     * Build a lowercased exclusion list from used words + the defensive banned list.
     *
     * @return string[]
     */
    private function buildExcludeList(array $usedWords): array
    {
        $exclude = array_map('mb_strtolower', $usedWords);

        foreach (self::BANNED_WORDS as $banned) {
            $exclude[] = $banned;
        }

        return array_values(array_unique($exclude));
    }

    /**
     * Pick a static fallback pair when the DB pool is unavailable.
     *
     * @return array{word: string, hint: string}
     */
    private function fallbackWord(string $difficulty = 'medium'): array
    {
        $fallbackList = match ($difficulty) {
            'easy' => self::FALLBACK_WORDS_EASY,
            'hard' => self::FALLBACK_WORDS_HARD,
            default => self::FALLBACK_WORDS,
        };

        return Arr::random($fallbackList);
    }

    /**
     * Generate a humorous, atmospheric Saloon Journalist narration of the round events.
     *
     * @param  array  $hints  List of hints submitted during the round.
     * @param  array  $chatMessages  Recent in-game chat messages.
     * @param  array  $votes  Votes details (who voted for whom).
     * @param  string  $realWord  The secret word of the crew.
     * @param  string  $imposterHint  The hint the imposter had.
     * @param  string  $imposterName  The nickname of the imposter.
     * @param  bool  $imposterCaught  Whether the imposter was caught.
     * @param  string  $language  Language code ('en', 'ar').
     * @return string Humorous 2-sentence saloon journalist scoop.
     */
    public function generateBarkeepRecap(array $hints, array $chatMessages, array $votes, string $realWord, string $imposterHint, string $imposterName, bool $imposterCaught, string $language = 'en'): string
    {
        try {
            $hintsList = '';
            foreach ($hints as $h) {
                $pName = $h['player_nickname'] ?? ($h['player']['nickname'] ?? 'Someone');
                $hText = $h['hint'] ?? '';
                $hintsList .= "- {$pName} said '{$hText}'\n";
            }

            $chatList = '';
            foreach (array_slice($chatMessages, -8) as $c) {
                $pName = $c['player_nickname'] ?? ($c['player']['nickname'] ?? 'Someone');
                $msg = $c['message'] ?? '';
                $chatList .= "[{$pName}]: {$msg}\n";
            }

            $votesList = '';
            foreach ($votes as $v) {
                $voterName = $v['voter_nickname'] ?? ($v['voter']['nickname'] ?? 'Someone');
                $targetName = $v['target_nickname'] ?? ($v['target']['nickname'] ?? 'Someone');
                $votesList .= "- {$voterName} voted for {$targetName}\n";
            }
            $votesList .= $imposterCaught ? 'Result: Imposter caught!' : 'Result: Imposter survived!';

            $languageRules = match ($language) {
                'ar' => [
                    'language' => 'Arabic (Modern Standard Arabic)',
                    'style' => 'sensational and cynical Wild West saloon journalist persona, speaking in a dramatic newspaper headline style (like EXTRA! EXTRA! or خبر عاجل!). Keep it strictly to EXACTLY 2 sentences. Use local Newspaper/Wild West terms (like الصحيفة، المطبعة، الخبر، فضيحة، رصاص، شريف، المأمور، الذهب، الصحراء). Do not use any latin/English words.',
                ],
                default => [
                    'language' => 'English',
                    'style' => 'sensational and cynical Wild West saloon journalist persona, speaking in a dramatic newspaper headline style (like EXTRA! EXTRA! or BREAKING NEWS!). Keep it strictly to EXACTLY 2 sentences. Use local Newspaper/Wild West terms (like gazette, press, headline, scoop, scandal, sheriff, marshal, gold, desert, bullets).',
                ],
            };

            $prompt = <<<PROMPT
You are "The Saloon Journalist" (الصحفي بارنابي), a fast-talking, cynical journalist running the "Saloon Gazette" newspaper in a dusty Wild West town. You are observing a game of "Imposter" (الخائن) happening at one of the card tables.

Your task is to write a sensational front-page newspaper scoop summarizing the high-stakes round that just ended in your distinct, headline-grabbing voice.

ROUND DETAILS:
- Secret Word: {$realWord}
- Imposter: {$imposterName}
- Imposter Hint: {$imposterHint}

PLAYER HINTS SUBMITTED:
{$hintsList}

RECENT CHAT CONVERSATION:
{$chatList}

VOTING OUTCOME:
{$votesList}

Write a sensational, dramatic, and humorous front-page scoop about this round.
RULES:
1. Respond in {$languageRules['language']}.
2. Use the tone: {$languageRules['style']}.
3. Keep it strictly to EXACTLY 2 sentences.
4. Do NOT output any markdown blocks, JSON, or code. Return ONLY the raw scoop text.
PROMPT;

            $barkeepAgent = agent(
                instructions: 'You are a Wild West newspaper journalist roleplayer. You respond with exactly 2 sentences in the requested language, starting with a sensational headline hook, with no wrapper, markdown, or extra explanations.',
            );

            $response = $barkeepAgent->prompt($prompt, timeout: 60);
            $recap = trim($response->text ?? '');

            if (! empty($recap)) {
                return $recap;
            }
        } catch (\Throwable $e) {
            Log::warning('AI Saloon Journalist recap generation failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Return a themed fallback recap if the AI call fails
        if ($language === 'ar') {
            $fallbacksCaught = [
                "طبعة عاجلة! صحيفة الحانة تعلن سقوط {$imposterName} متلبساً بالجرم المشهود! اتضح أن دليل '{$imposterHint}' كان كذبة مكشوفة تصدرت الصفحة الأولى!",
                "خبر عاجل: المأمور يزج بالخائن {$imposterName} في غياهب السجن! كشف رواد الحانة كذبته الفاضحة '{$imposterHint}' وصاغوا له نهاية تليق بأفلام الغرب!",
            ];
            $fallbacksEscaped = [
                "سبق صحفي: الخائن المراوغ {$imposterName} يختفي في ظلام الليل حاملاً ذهب الحانة! تفيد مصادرنا أن الجميع انشغلوا بالجدال حول '{$realWord}' حتى غاب عنهم السارق!",
                "آخر الأنباء: فرار الخائن {$imposterName} بالغنيمة وترك القوم في فوضى عارمة! تساءلت صحيفتنا اليوم: كيف لعصابة كاملة أن تفوت مثل هذه السرقة الذكية؟",
            ];

            return $imposterCaught ? Arr::random($fallbacksCaught) : Arr::random($fallbacksEscaped);
        } else {
            $fallbacksCaught = [
                "EXTRA! EXTRA! The Saloon Gazette reports that {$imposterName} has been caught red-handed! Turns out their slick '{$imposterHint}' clue was a front-page fake, and the crew locked them up tight!",
                "BREAKING NEWS: The Sheriff hangs the traitor {$imposterName} high before noon! The town posse spotted their sweaty '{$imposterHint}' lie and delivered immediate street justice!",
            ];
            $fallbacksEscaped = [
                "FRONT PAGE SCOOP: The slippery {$imposterName} vanishes into the night with the saloon gold! Our sources report the posse was too busy arguing over '{$realWord}' to notice the heist!",
                "LATEST BULLETIN: Traitor {$imposterName} makes a clean getaway, leaving the local crew in absolute shambles! The Gazette asks: how could a group of cowboys miss such a high-stakes robbery?",
            ];

            return $imposterCaught ? Arr::random($fallbacksCaught) : Arr::random($fallbacksEscaped);
        }
    }
}
