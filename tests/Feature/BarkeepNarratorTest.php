<?php

namespace Tests\Feature;

use App\Services\AiWordService;
use Tests\TestCase;

class BarkeepNarratorTest extends TestCase
{
    /**
     * Test the Barkeep Recap fallback/AI generation in Arabic.
     */
    public function test_barkeep_recap_fallback_arabic_caught(): void
    {
        $service = new AiWordService;
        $recap = $service->generateBarkeepRecap(
            hints: [],
            chatMessages: [],
            votes: [],
            realWord: 'حصان',
            imposterHint: 'حيوان',
            imposterName: 'أبو صهيب',
            imposterCaught: true,
            language: 'ar'
        );

        $this->assertNotEmpty($recap);
        $this->assertIsString($recap);
    }

    /**
     * Test the Barkeep Recap fallback/AI generation in English.
     */
    public function test_barkeep_recap_fallback_english_escaped(): void
    {
        $service = new AiWordService;
        $recap = $service->generateBarkeepRecap(
            hints: [],
            chatMessages: [],
            votes: [],
            realWord: 'Horse',
            imposterHint: 'Animal',
            imposterName: 'Slim Jim',
            imposterCaught: false,
            language: 'en'
        );

        $this->assertNotEmpty($recap);
        $this->assertIsString($recap);
    }
}
