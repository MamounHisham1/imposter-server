<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WellKnownController extends Controller
{
    public function sitemap(): Response
    {
        $baseUrl = config('app.url');
        $now = now()->toAtomString();

        $pages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/install', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['url' => '/how-to-play', 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['url' => '/faq', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/about', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => '/stats', 'priority' => '0.6', 'changefreq' => 'daily'],
            ['url' => '/shop', 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['url' => '/credits', 'priority' => '0.5', 'changefreq' => 'weekly'],
            ['url' => '/login', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['url' => '/register', 'priority' => '0.4', 'changefreq' => 'monthly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'."\n";
        $xml .= '  xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($pages as $page) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$baseUrl}{$page['url']}</loc>\n";
            $xml .= "    <lastmod>{$now}</lastmod>\n";
            $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$page['priority']}</priority>\n";
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"ar\" href=\"{$baseUrl}{$page['url']}?lang=ar\" />\n";
            $xml .= "    <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"{$baseUrl}{$page['url']}?lang=en\" />\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function apiCatalog(): JsonResponse
    {
        $baseUrl = config('app.url');

        return response()->json([
            'linkset' => [
                [
                    'anchor' => $baseUrl.'/api/inventory',
                    'service-doc' => [[
                        'href' => $baseUrl.'/.well-known/api-catalog',
                        'type' => 'application/linkset+json',
                    ]],
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/linkset+json',
        ]);
    }

    public function agentSkillsIndex(): JsonResponse
    {
        $baseUrl = config('app.url');

        return response()->json([
            '$schema' => 'https://agentskills.io/schemas/agent-skills-index/v0.2.0.json',
            'skills' => [
                [
                    'name' => 'join-game',
                    'type' => 'action',
                    'description' => 'Join a game room by code or create a new room',
                    'url' => $baseUrl.'/.well-known/agent-skills/join-game.json',
                ],
                [
                    'name' => 'view-stats',
                    'type' => 'query',
                    'description' => 'View game statistics including total games, players, and rounds played',
                    'url' => $baseUrl.'/.well-known/agent-skills/view-stats.json',
                ],
            ],
        ]);
    }

    public function markdownPage(Request $request, string $path = '/'): Response
    {
        $markdown = $this->generateMarkdown($path);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    private function generateMarkdown(string $path): string
    {
        $name = 'الخائن - Traitor';
        $tagline = 'من هو الخائن في البلدة؟';
        $baseUrl = config('app.url');

        $lines = [
            "# {$name}",
            "> {$tagline}",
            '',
            'A real-time multiplayer social deduction party game. One player is secretly the **imposter** and receives a vague hint instead of the real word. Players give one-word clues, then vote to identify the imposter.',
            '',
            '## Play Now',
            '',
            "- [Home / Create Room]({$baseUrl}/)",
            "- [Login]({$baseUrl}/login)",
            "- [Register]({$baseUrl}/register)",
            "- [Install as App]({$baseUrl}/install)",
            '',
            '## Pages',
            '',
            '| Page | URL | Description |',
            '|------|-----|-------------|',
            '| Home | `/` | Create or join a game room |',
            '| Login | `/login` | Sign in with nickname or Google |',
            '| Register | `/register` | Create an account |',
            '| Stats | `/stats` | Game statistics |',
            '| Shop | `/shop` | Buy avatar items (auth required) |',
            '| Credits | `/credits` | View balance (auth required) |',
            '| Install | `/install` | PWA install instructions |',
            '',
            '## Game Flow',
            '',
            '1. Create or join a room from the home page',
            '2. Players toggle ready state in the lobby',
            '3. Creator starts the game',
            '4. Each round: players submit one-word hints in turn order',
            '5. Voting phase: all players vote to identify the imposter',
            '6. Results: scores tallied, next round or game over',
            '',
            '## API Endpoints',
            '',
            '| Method | URL | Description |',
            '|--------|-----|-------------|',
            '| POST | `/room` | Create a room |',
            '| POST | `/room/join` | Join a room |',
            '| GET | `/room/{code}` | Room lobby |',
            '| POST | `/room/{code}/ready` | Toggle ready |',
            '| POST | `/room/{code}/start` | Start game |',
            '| POST | `/heartbeat` | Player heartbeat |',
            '| GET | `/game/{code}` | Game page |',
            '| POST | `/game/{code}/hint` | Submit hint |',
            '| GET | `/game/{code}/vote` | Vote page |',
            '| POST | `/game/{code}/vote` | Submit vote |',
            '| GET | `/game/{code}/result` | Round results |',
            '| POST | `/locale` | Switch language (en/ar) |',
            '| GET | `/api/inventory` | Player inventory (auth) |',
        ];

        return implode("\n", $lines);
    }
}
