# Imposter

A real-time multiplayer social deduction word game built with Laravel 13, Vue 3, and WebSocket broadcasting.

One player is secretly the **Imposter**. The rest of the crew sees a secret word — the imposter only sees a vague, misleading hint. Everyone submits a one-word clue, then the crew votes to catch the imposter. Can you blend in, or will you be exposed?

## How It Works

1. **Create or join a room** — share the 6-character code with friends
2. **Crew sees the word** (e.g. "Lighthouse") — the imposter sees a vague hint (e.g. "Solitude")
3. **Submit one-word hints** — crew describes the word subtly, the imposter tries to fake it
4. **Vote to catch the imposter** — majority rules, but ties let the imposter slip away
5. **Score points** across multiple rounds — crew gets points for catching the imposter, the imposter scores for surviving

### Scoring

| Outcome | Crew Points | Imposter Points |
|---------|-------------|-----------------|
| Imposter caught (majority vote) | 2 pts (voters), 1 pt (others) | 0 |
| Wrong player accused | 0 | 3 |
| Tie vote | 0 | 1 |

## Features

- **Real-time multiplayer** via Laravel Reverb (WebSocket)
- **AI-powered word generation** — creative word/hint pairs generated on the fly (supports OpenAI, Anthropic, Gemini, Groq, Ollama, and more)
- **Hardcoded fallback** — 50+ word/hint pairs when AI is unavailable
- **Multi-round games** — configurable 1–5 rounds per game
- **Room management** — public/private rooms, 3–8 players, unique room codes
- **Player heartbeat system** — auto-removes disconnected players after 60 seconds
- **Bilingual** — English and Arabic (RTL) with AI-aware language prompts
- **No account required** — jump in with a nickname, no registration needed

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | Vue 3, Inertia.js v3, Tailwind CSS v4 |
| Real-time | Laravel Reverb, Laravel Echo, Pusher.js |
| AI | `laravel/ai` (multi-provider) |
| Database | SQLite (default) — also supports MySQL, PostgreSQL, SQL Server |
| Queue/Cache/Session | Database driver |

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js & npm
- An AI provider (optional but recommended — Ollama works locally)

### Installation

```bash
git clone https://github.com/MamounHisham1/imposter-server.git
cd imposter-server

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate
php artisan migrate

# Build frontend
npm run build
```

### AI Configuration (Optional)

The game works without AI using hardcoded word pairs. To enable AI word generation, configure a provider in `.env`:

```env
# Example: Ollama (local, free)
AI_DEFAULT=ollama
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=minimax-m2.5:cloud

# Example: OpenAI
AI_DEFAULT=openai
OPENAI_API_KEY=your-key-here
OPENAI_MODEL=gpt-4o

# Example: Anthropic
AI_DEFAULT=anthropic
ANTHROPIC_API_KEY=your-key-here
ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

Supported providers: OpenAI, Anthropic, Gemini, Groq, DeepSeek, Mistral, Ollama, OpenRouter, xAI, Cohere, Azure, Bedrock, and more.

### Running

```bash
# Run everything (PHP server, queue worker, scheduler, Reverb, Vite)
composer dev
```

Or run services individually:

```bash
php artisan serve                    # Laravel dev server
php artisan reverb:start             # WebSocket server
php artisan queue:table && php artisan migrate  # (first time only)
php artisan queue:work               # Queue worker
php artisan schedule:work            # Scheduled tasks (cleanup)
npm run dev                          # Vite dev server
```

Open `http://localhost:8000` in your browser.

## Project Structure

```
├── app/
│   ├── Console/Commands/
│   │   └── CleanInactiveRooms.php    # Scheduled cleanup (runs every minute)
│   ├── Events/
│   │   ├── GameEvent.php             # Per-room real-time events
│   │   └── RoomListEvent.php         # Public room list updates
│   ├── Http/Controllers/
│   │   ├── GameController.php        # Game flow: hints, voting, results
│   │   └── RoomController.php        # Room CRUD, join, ready, leave
│   ├── Models/
│   │   ├── GameStat.php              # Player statistics (planned)
│   │   ├── Hint.php                  # Player hints per round
│   │   ├── Player.php                # Room players
│   │   ├── Room.php                  # Game rooms
│   │   ├── Round.php                 # Game rounds
│   │   └── Vote.php                  # Player votes per round
│   └── Services/
│       ├── AiWordService.php         # AI word/hint generation with fallback
│       └── GameService.php           # Core game logic (state, scoring, broadcasting)
├── database/migrations/              # 17 migrations (rooms, players, rounds, hints, votes, etc.)
├── resources/
│   └── js/
│       ├── Pages/                    # Vue pages (Home, Room, Game, Vote, Result)
│       ├── Components/               # Shared components (Toast)
│       ├── Composables/              # Vue composables (useToast)
│       └── i18n/                     # Translation files (en.json, ar.json)
└── routes/
    ├── web.php                       # All web routes
    └── console.php                   # Scheduled commands
```

## Game Flow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  Lobby   │───>│  Hints   │───>│  Voting  │───>│  Result  │───>│  Hints   │
│(waiting) │    │(playing) │    │(voting)  │    │(result)  │    │(round 2) │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
     │               │               │               │               │
  Create room    AI picks word     Each player    Tally votes     New word,
  Join by code   Assign imposter   votes once      Award points    new imposter
  Ready up       Submit hints                      Show answer     (repeat)
```

## WebSocket Events

| Event | Channel | Trigger |
|-------|---------|---------|
| `room_created` | `public-rooms` | New public room created |
| `player_joined` | `room.{id}` | Player joins a room |
| `player_ready` | `room.{id}` | Player toggles ready |
| `game_started` | `room.{id}` | Game begins |
| `hint_submitted` | `room.{id}` | Hint submitted (count update) |
| `hints_complete` | `room.{id}` | All hints in — reveal to all |
| `voting_started` | `room.{id}` | Voting phase begins |
| `vote_submitted` | `room.{id}` | Vote cast (count update) |
| `round_result` | `room.{id}` | Round resolved — winner announced |
| `next_round` | `room.{id}` | Next round begins |
| `game_over` | `room.{id}` | All rounds complete |
| `player_left` | `room.{id}` | Player disconnected/removed |
| `room_deleted` | `room.{id}` | Room cleaned up |

## Cleanup & Maintenance

A scheduled command runs every minute to keep things tidy:

- **Stale players** removed after 60 seconds of no heartbeat
- **Empty rooms** deleted immediately
- **Inactive rooms** cleaned up based on status:
  - `waiting` rooms: after 30 minutes
  - `finished` games: after 10 minutes
  - `playing`/`voting` games: after 60 minutes

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
