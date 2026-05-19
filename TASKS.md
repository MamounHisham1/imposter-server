# Tasks

## 🔴 High Priority

### 1. Scoreboard on Result screen
The Result page reveals the imposter and shows a winner banner, but never renders the actual player scores and the actual word. The `showScores` transition exists and wraps the action buttons, but there's no scoreboard/leaderboard UI inside it. Players earn points throughout the game but never see them.

### 2. Imposter's last guess (Deprecated)
When the imposter is caught, they should get one final chance to guess the real word to steal points. This adds a dramatic final moment — a "last stand" mechanic that's standard in social deduction word games.

### 3. Rematch / play again with the same group
The `playAgain()` on the Result page just POSTs to create a new room. There's no way for the whole group to rematch together. After a game ends, everyone is scattered. Need a "return all players to the same lobby" mechanic for seamless rematching.

### 4. Discussion phase (Deprecated)
Right now the flow is: hints → instant vote. There's no time or space for players to discuss what they saw. Even a simple text chat or a timed "discussion" countdown before voting would massively improve the social deduction experience — it's the soul of the genre.

### 5. Reconnection handling
If a player refreshes or loses connection briefly, the 60s heartbeat timeout could get them purged mid-game. There's no explicit reconnection logic or "rejoin" flow for a disconnected player to come back to an active game.

## 🟡 Medium Priority

### 6. Sound effects / audio cues
Zero audio cues currently — no sound when it's your turn, when the timer is running low, when the imposter is revealed, or when someone votes. For a game that wants "dramatic tension" (per PRODUCT.md), this is a big gap.

### 7. In-game chat
Players have no way to communicate within the app. The discussion that drives social deduction has to happen outside the game (Discord, in-person). Even a basic text chat during the hint-review/discussion phase would help remote players.

### 8. Category / topic selection for words
Currently AiWordService generates words with no topic filter. Players can't choose categories (animals, food, places, etc.). Adding categories would make the game more replayable and let hosts tailor difficulty.

### 9. "Continue hints" flow is confusing
When players vote to "continue hints," it clears all previous hints and restarts the same round with the same word. Players might expect a second round of hints to add to the first, not replace them. The UX doesn't explain this — either change the behavior to accumulate hints, or clearly communicate what "continue" means.

## 🟢 Lower Priority

### 10. Game history / stats page
There's a GameStat model but no player-facing screen to see past game stats, win rates, or history.

### 11. Spectator mode and retuner mode
If a room is mid-game, there's no way to watch. Late arrivals are just stuck waiting. The returner mood is when a player leave the game and come back to it. they should be able to continue in the next round (if they come in running round) and they can spectate the current round and start playing from the next, but if they come in an ended round they should be able to play normally

### 12. Word difficulty control & AI uniuqe words
All words come from the same AI prompt without difficulty scaling. Having easy/medium/hard word pools would help casual vs. competitive groups. Also sometimes AI generate repetitive words.

---

## ✅ Already Done (clean up from old list)
- ~~adding timer in each phase (hints 20s, votes 30s)~~ — implemented
- ~~leader should be able to kick players~~ — implemented
- ~~voting/keep-playing phase should be by votes~~ — implemented (phase voting system)
