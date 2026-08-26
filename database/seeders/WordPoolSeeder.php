<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class WordPoolSeeder extends Seeder
{
    /**
     * Path to the curated, committed bilingual word pool (source of truth).
     */
    private const DATA_PATH = __DIR__.'/data/word_pool.json';

    /**
     * Seed the words table from the committed JSON word pool.
     *
     * Idempotent: upserts by (word_en, difficulty) so re-running never
     * creates duplicate concepts and picks up hint/category edits.
     */
    public function run(): void
    {
        if (! File::exists(self::DATA_PATH)) {
            $this->command?->warn('Word pool JSON not found at '.self::DATA_PATH);

            return;
        }

        $rows = json_decode(File::get(self::DATA_PATH), true);

        if (! is_array($rows)) {
            $this->command?->error('Word pool JSON is invalid.');

            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $exists = Word::where('word_en', $row['word_en'])
                ->where('difficulty', $row['difficulty'])
                ->exists();

            Word::updateOrCreate(
                [
                    'word_en' => $row['word_en'],
                    'difficulty' => $row['difficulty'],
                ],
                [
                    'hint_en' => $row['hint_en'],
                    'word_ar' => $row['word_ar'],
                    'hint_ar' => $row['hint_ar'],
                    'category' => $row['category'] ?? null,
                    'enabled' => $row['enabled'] ?? true,
                ]
            );

            $exists ? $updated++ : $created++;
        }

        $this->command?->info(sprintf(
            'Word pool seeded: %d created, %d updated (total %d).',
            $created,
            $updated,
            Word::count()
        ));
    }
}
