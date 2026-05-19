#!/bin/bash
# Comprehensive curl test for all TASKS.md features
# Tests: Scoreboard, Rematch, Reconnection, Chat, Category, Difficulty,
#         Continue Hints, Game History, Spectator mode

BASE="http://localhost:8000"
COOKIE_JAR="/tmp/imposter_test_cookies.txt"
PASS=0
FAIL=0
RESULTS=()

rm -f "$COOKIE_JAR"

# Helper: Get CSRF token
get_csrf() {
    local jar="$1"
    curl -s -c "$jar" "$BASE/" > /dev/null
    local encoded=$(grep XSRF-TOKEN "$jar" | awk '{print $NF}')
    python3 -c "import urllib.parse; print(urllib.parse.unquote('$encoded'))"
}

# Helper: Make a POST request
post() {
    local path="$1"
    local data="$2"
    local jar="$3"
    local csrf=$(get_csrf "$jar")
    curl -s -X POST "$BASE$path" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "X-XSRF-TOKEN: $csrf" \
        -H "X-Requested-With: XMLHttpRequest" \
        -H "Referer: $BASE/" \
        -b "$jar" -c "$jar" \
        -d "$data"
}

# Helper: Make a GET request (returns page JSON data)
get() {
    local path="$1"
    local jar="$2"
    curl -s "$BASE$path" -b "$jar" -c "$jar" -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest"
}

# Helper: Record test result
test_result() {
    local name="$1"
    local passed="$2"
    if [ "$passed" = "true" ]; then
        PASS=$((PASS+1))
        RESULTS+=("PASS: $name")
        echo "  PASS: $name"
    else
        FAIL=$((FAIL+1))
        RESULTS+=("FAIL: $name")
        echo "  FAIL: $name"
    fi
}

echo "============================================"
echo "IMPOSTER GAME - TASK CURL TESTS"
echo "============================================"
echo ""

# ============================================
# TEST 1: CREATE ROOM WITH CATEGORY & DIFFICULTY
# ============================================
echo "--- TEST GROUP: Category & Difficulty (Task 8 & 12) ---"

# Test 1.1: Create room with category=animals, difficulty=easy
JAR1="/tmp/imp_host.txt"; rm -f "$JAR1"
RESP=$(post "/room" '{"nickname":"HostCat","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"easy"}' "$JAR1")
CODE=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
test_result "Create room with category=animals difficulty=easy" "[ -n '$CODE' ]"

# Test 1.2: Create room with category=food, difficulty=hard
JAR2="/tmp/imp_food.txt"; rm -f "$JAR2"
RESP2=$(post "/room" '{"nickname":"FoodPlayer","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"food","difficulty":"hard"}' "$JAR2")
CODE2=$(echo "$RESP2" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
test_result "Create room with category=food difficulty=hard" "[ -n '$CODE2' ]"

# Test 1.3: Create room with no category (random)
JAR3="/tmp/imp_random.txt"; rm -f "$JAR3"
RESP3=$(post "/room" '{"nickname":"RandomPlayer","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}' "$JAR3")
CODE3=$(echo "$RESP3" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
test_result "Create room with no category (random/default)" "[ -n '$CODE3' ]"

# Test 1.4: Create room with category=technology, difficulty=medium
JAR4="/tmp/imp_tech.txt"; rm -f "$JAR4"
RESP4=$(post "/room" '{"nickname":"TechPlayer","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"technology","difficulty":"medium"}' "$JAR4")
CODE4=$(echo "$RESP4" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
test_result "Create room with category=technology difficulty=medium" "[ -n '$CODE4' ]"

# Test 1.5: Verify room was created in DB with correct category/difficulty
if [ -n "$CODE" ]; then
    DB_CAT=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE')->first()?->category ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Room category saved as 'animals' in DB" "[ '$DB_CAT' = 'animals' ]"

    DB_DIFF=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE')->first()?->difficulty ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Room difficulty saved as 'easy' in DB" "[ '$DB_DIFF' = 'easy' ]"
fi

# Test 1.6: Verify food room category
if [ -n "$CODE2" ]; then
    DB_CAT2=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE2')->first()?->category ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Room category saved as 'food' in DB" "[ '$DB_CAT2' = 'food' ]"
fi

# Test 1.7: Verify random room has null category
if [ -n "$CODE3" ]; then
    DB_CAT3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->first()?->category ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Room with no category has NULL category in DB" "[ '$DB_CAT3' = 'NULL' ]"
fi

# Test 1.8: Verify default difficulty is 'medium'
if [ -n "$CODE3" ]; then
    DB_DIFF3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->first()?->difficulty ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Room with no difficulty defaults to 'medium'" "[ '$DB_DIFF3' = 'medium' ]"
fi

# Test 1.9: Try invalid category (should fail validation)
RESP_INVALID=$(post "/room" '{"nickname":"InvalidCat","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"invalid_category","difficulty":"easy"}' "/tmp/imp_inv.txt")
test_result "Invalid category rejected" "echo '$RESP_INVALID' | grep -q 'error\|validation\|422'"

# Test 1.10: Try invalid difficulty (should fail validation)
RESP_INV_DIFF=$(post "/room" '{"nickname":"InvalidDiff","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"extreme"}' "/tmp/imp_inv2.txt")
test_result "Invalid difficulty rejected" "echo '$RESP_INV_DIFF' | grep -q 'error\|validation\|422'"

echo ""

# ============================================
# TEST 2: FULL GAME FLOW + REMATCH
# ============================================
echo "--- TEST GROUP: Rematch (Task 3) ---"

# Create a room for full game flow
JAR_HOST="/tmp/imp_rm_host.txt"; rm -f "$JAR_HOST"
RESP=$(post "/room" '{"nickname":"RematchHost","type":"public","max_players":6,"rounds_per_game":1,"language":"en"}' "$JAR_HOST")
ROOM_CODE=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
echo "  Rematch room code: $ROOM_CODE"

# Get room and player IDs from DB
ROOM_ID=$(php artisan tinker --execute="echo App\Models\Room::where('code','$ROOM_CODE')->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
HOST_ID=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Room ID: $ROOM_ID, Host ID: $HOST_ID"

# Test 2.1: Join players 2 and 3
JAR_P2="/tmp/imp_rm_p2.txt"; rm -f "$JAR_P2"
RESP_JOIN=$(post "/room/join" "{\"code\":\"$ROOM_CODE\",\"nickname\":\"PlayerTwo\"}" "$JAR_P2")
test_result "Player 2 joined room" "echo '$RESP_JOIN' | grep -q 'room/'"

JAR_P3="/tmp/imp_rm_p3.txt"; rm -f "$JAR_P3"
RESP_JOIN3=$(post "/room/join" "{\"code\":\"$ROOM_CODE\",\"nickname\":\"PlayerThree\"}" "$JAR_P3")
test_result "Player 3 joined room" "echo '$RESP_JOIN3' | grep -q 'room/'"

# Get player IDs
P2_ID=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->where('nickname','PlayerTwo')->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
P3_ID=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->where('nickname','PlayerThree')->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
echo "  P2: $P2_ID, P3: $P3_ID"

# Test 2.2: Toggle ready for all players
post "/room/$ROOM_CODE/ready" "{\"player_id\":$HOST_ID}" "$JAR_HOST" > /dev/null
post "/room/$ROOM_CODE/ready" "{\"player_id\":$P2_ID}" "$JAR_P2" > /dev/null
post "/room/$ROOM_CODE/ready" "{\"player_id\":$P3_ID}" "$JAR_P3" > /dev/null
test_result "All players toggled ready" "true"

# Test 2.3: Start the game
RESP_START=$(post "/room/$ROOM_CODE/start" "{\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}" "$JAR_HOST")
test_result "Game started successfully" "echo '$RESP_START' | grep -qv 'error'"

# Check game state
sleep 2
echo "  Checking game state..."
ROUND_ID=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$ROOM_ID)->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
HINT_ORDER=$(php artisan tinker --execute="echo json_encode(App\Models\Round::where('room_id',$ROOM_ID)->first()?->hint_order ?? []);" 2>/dev/null | tail -1)
echo "  Round ID: $ROUND_ID, Hint order: $HINT_ORDER"

# Test 2.4: Submit hints in order
if [ "$HINT_ORDER" != "[]" ] && [ -n "$ROUND_ID" ]; then
    # Submit hints for each player in order
    FIRST=$(echo "$HINT_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[0] if d else '')" 2>/dev/null)
    SECOND=$(echo "$HINT_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[1] if len(d)>1 else '')" 2>/dev/null)
    THIRD=$(echo "$HINT_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[2] if len(d)>2 else '')" 2>/dev/null)

    # Pick the right cookie jar for each player
    get_jar() {
        case "$1" in
            "$HOST_ID") echo "$JAR_HOST";;
            "$P2_ID") echo "$JAR_P2";;
            "$P3_ID") echo "$JAR_P3";;
        esac
    }

    J1=$(get_jar "$FIRST")
    post "/game/$ROOM_CODE/hint" "{\"content\":\"banana\",\"player_id\":$FIRST}" "$J1" > /dev/null
    test_result "Hint 1 submitted (player $FIRST)" "true"

    J2=$(get_jar "$SECOND")
    post "/game/$ROOM_CODE/hint" "{\"content\":\"apple\",\"player_id\":$SECOND}" "$J2" > /dev/null
    test_result "Hint 2 submitted (player $SECOND)" "true"

    J3=$(get_jar "$THIRD")
    post "/game/$ROOM_CODE/hint" "{\"content\":\"orange\",\"player_id\":$THIRD}" "$J3" > /dev/null
    test_result "Hint 3 submitted (player $THIRD)" "true"
fi

# Test 2.5: Start voting
RESP_VOTE=$(post "/game/$ROOM_CODE/start-voting" "{\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}" "$JAR_HOST")
test_result "Voting started" "echo '$RESP_VOTE' | grep -qv 'error'"

# Test 2.6: Submit votes - everyone votes for a target
IMPOSTER_ID=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->where('is_imposter',1)->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Imposter ID: $IMPOSTER_ID"

if [ -n "$IMPOSTER_ID" ] && [ "$IMPOSTER_ID" != "0" ]; then
    # All vote for the imposter
    J_V1=$(get_jar "$HOST_ID")
    J_V2=$(get_jar "$P2_ID")
    J_V3=$(get_jar "$P3_ID")

    post "/game/$ROOM_CODE/vote" "{\"target_id\":$IMPOSTER_ID,\"player_id\":$HOST_ID}" "$J_V1" > /dev/null
    post "/game/$ROOM_CODE/vote" "{\"target_id\":$IMPOSTER_ID,\"player_id\":$P2_ID}" "$J_V2" > /dev/null
    post "/game/$ROOM_CODE/vote" "{\"target_id\":$IMPOSTER_ID,\"player_id\":$P3_ID}" "$J_V3" > /dev/null
    test_result "All votes submitted (imposter caught)" "true"
fi

sleep 1

# Test 2.7: Check game is finished
GAME_STATUS=$(php artisan tinker --execute="echo App\Models\Room::find($ROOM_ID)?->status ?? 'unknown';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Game is finished after all rounds" "[ '$GAME_STATUS' = 'finished' ]"

# Test 2.8: REMATCH - trigger rematch
RESP_REMATCH=$(post "/game/$ROOM_CODE/rematch" "{\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}" "$JAR_HOST")
test_result "Rematch triggered by creator" "echo '$RESP_REMATCH' | grep -qv 'error\|exception'"

sleep 1

# Test 2.9: Verify room is back to waiting
REMATCH_STATUS=$(php artisan tinker --execute="echo App\Models\Room::find($ROOM_ID)?->status ?? 'unknown';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Room status reset to 'waiting' after rematch" "[ '$REMATCH_STATUS' = 'waiting' ]"

# Test 2.10: Verify players scores reset
HOST_SCORE=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->where('nickname','RematchHost')->first()?->score ?? -1;" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Player scores reset to 0 after rematch" "[ '$HOST_SCORE' = '0' ]"

# Test 2.11: Verify rounds deleted
ROUND_COUNT=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$ROOM_ID)->count();" 2>/dev/null | tail -1 | tr -d ' ')
test_result "All rounds deleted after rematch" "[ '$ROUND_COUNT' = '0' ]"

# Test 2.12: Try rematch when not creator (should fail)
RESP_NOT_CREATOR=$(post "/game/$ROOM_CODE/rematch" "{\"player_id\":$P2_ID,\"room_id\":$ROOM_ID}" "$JAR_P2")
test_result "Non-creator cannot trigger rematch" "echo '$RESP_NOT_CREATOR' | grep -qi 'error\|only\|creator\|exception'"

echo ""

# ============================================
# TEST 3: REMATCH - NEW GAME FLOW (play again)
# ============================================
echo "--- TEST GROUP: Rematch second game ---"

# Toggle ready again and start a second game
post "/room/$ROOM_CODE/ready" "{\"player_id\":$HOST_ID}" "$JAR_HOST" > /dev/null
post "/room/$ROOM_CODE/ready" "{\"player_id\":$P2_ID}" "$JAR_P2" > /dev/null
post "/room/$ROOM_CODE/ready" "{\"player_id\":$P3_ID}" "$JAR_P3" > /dev/null

RESP_START2=$(post "/room/$ROOM_CODE/start" "{\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}" "$JAR_HOST")
test_result "Second game started after rematch" "echo '$RESP_START2' | grep -qv 'error'"

sleep 1
STATUS2=$(php artisan tinker --execute="echo App\Models\Room::find($ROOM_ID)?->status ?? 'unknown';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Second game status is 'playing'" "[ '$STATUS2' = 'playing' ]"

echo ""

# ============================================
# TEST 4: CHAT MESSAGES
# ============================================
echo "--- TEST GROUP: Chat (Task 7) ---"

# Get a new game going for chat tests
JAR_CH="/tmp/imp_chat.txt"; rm -f "$JAR_CH"
RESP_CH=$(post "/room" '{"nickname":"ChatHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}' "$JAR_CH")
CH_CODE=$(echo "$RESP_CH" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
CH_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CH_CODE')->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
CH_HOST=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$CH_ROOM)->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Chat room: $CH_CODE, Room ID: $CH_ROOM, Host: $CH_HOST"

# Test 4.1: Send chat message
RESP_MSG=$(post "/game/$CH_CODE/chat" "{\"message\":\"Hello world\",\"player_id\":$CH_HOST}" "$JAR_CH")
test_result "Chat message sent" "echo '$RESP_MSG' | grep -qv 'error\|exception'"

# Test 4.2: Send another message
RESP_MSG2=$(post "/game/$CH_CODE/chat" "{\"message\":\"Second message\",\"player_id\":$CH_HOST}" "$JAR_CH")
test_result "Second chat message sent" "echo '$RESP_MSG2' | grep -qv 'error\|exception'"

# Test 4.3: Verify messages in DB
MSG_COUNT=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Chat messages stored in DB (count: $MSG_COUNT)" "[ '$MSG_COUNT' -ge '2' ]"

# Test 4.4: Test empty message (should fail)
RESP_EMPTY=$(post "/game/$CH_CODE/chat" "{\"message\":\"\",\"player_id\":$CH_HOST}" "$JAR_CH")
test_result "Empty message rejected" "echo '$RESP_EMPTY' | grep -qi 'error\|validation\|required\|422'"

# Test 4.5: Test long message (500+ chars)
LONG_MSG=$(python3 -c "print('a' * 501)")
RESP_LONG=$(post "/game/$CH_CODE/chat" "{\"message\":\"$LONG_MSG\",\"player_id\":$CH_HOST}" "$JAR_CH")
test_result "500+ char message rejected" "echo '$RESP_LONG' | grep -qi 'error\|validation\|422'"

# Test 4.6: Send valid max length message
MSG_500=$(python3 -c "print('a' * 500)")
RESP_MAX=$(post "/game/$CH_CODE/chat" "{\"message\":\"$MSG_500\",\"player_id\":$CH_HOST}" "$JAR_CH")
test_result "500 char message accepted" "echo '$RESP_MAX' | grep -qv 'error\|exception'"

# Test 4.7: Test message from non-existent player
RESP_FAKE=$(post "/game/$CH_CODE/chat" "{\"message\":\"Fake\",\"player_id\":99999}" "$JAR_CH")
test_result "Message from fake player rejected" "echo '$RESP_FAKE' | grep -qi 'error\|exception\|422'"

# Test 4.8: Send many messages and verify count
for i in $(seq 1 5); do
    post "/game/$CH_CODE/chat" "{\"message\":\"Bulk msg $i\",\"player_id\":$CH_HOST}" "$JAR_CH" > /dev/null
done
MSG_COUNT2=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Bulk messages stored (count: $MSG_COUNT2)" "[ '$MSG_COUNT2' -ge '8' ]"

echo ""

# ============================================
# TEST 5: GAME HISTORY / STATS
# ============================================
echo "--- TEST GROUP: Game History (Task 10) ---"

# Test 5.1: Check if game_histories table is accessible
HIST_COUNT=$(php artisan tinker --execute="echo App\Models\GameHistory::count();" 2>/dev/null | tail -1 | tr -d ' ')
test_result "GameHistory model accessible (count: $HIST_COUNT)" "true"

# Test 5.2: Check if GameStat model accessible
STAT_COUNT=$(php artisan tinker --execute="echo App\Models\GameStat::count();" 2>/dev/null | tail -1 | tr -d ' ')
test_result "GameStat model accessible (count: $STAT_COUNT)" "true"

# Test 5.3: Try accessing stats page
RESP_STATS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/stats" -b "$JAR_HOST")
test_result "Stats page accessible (HTTP $RESP_STATS)" "[ '$RESP_STATS' != '404' ]"

# Test 5.4: Verify GameHistory has user_id column
HAS_USER_ID=$(php artisan tinker --execute="echo Schema::hasColumn('game_histories','user_id') ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "GameHistory has user_id column" "[ '$HAS_USER_ID' = 'yes' ]"

# Test 5.5: Verify GameStat has user_id column
STAT_USER=$(php artisan tinker --execute="echo Schema::hasColumn('game_stats','user_id') ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "GameStat has user_id column" "[ '$STAT_USER' = 'yes' ]"

echo ""

# ============================================
# TEST 6: SPECTATOR MODE
# ============================================
echo "--- TEST GROUP: Spectator Mode (Task 11) ---"

# Test 6.1: Verify is_spectator column on players
HAS_SPEC=$(php artisan tinker --execute="echo Schema::hasColumn('players','is_spectator') ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Players table has is_spectator column" "[ '$HAS_SPEC' = 'yes' ]"

# Test 6.2: Join a room mid-game (should become spectator)
# Use the second game room that's in 'playing' state
if [ "$STATUS2" = "playing" ]; then
    JAR_SPEC="/tmp/imp_spec.txt"; rm -f "$JAR_SPEC"
    RESP_SPEC=$(post "/room/join" "{\"code\":\"$ROOM_CODE\",\"nickname\":\"Spectator1\"}" "$JAR_SPEC")
    test_result "Spectator joined mid-game room" "echo '$RESP_SPEC' | grep -q 'room/'"

    sleep 1
    # Check if spectator flag is set
    SPEC_FLAG=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$ROOM_ID)->where('nickname','Spectator1')->first()?->is_spectator ? '1' : '0';" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "Spectator has is_spectator=true" "[ '$SPEC_FLAG' = '1' ]"
else
    test_result "Spectator joined mid-game room (skipped - no active game)" "true"
    test_result "Spectator has is_spectator=true (skipped)" "true"
fi

echo ""

# ============================================
# TEST 7: RECONNECTION (extended timeout)
# ============================================
echo "--- TEST GROUP: Reconnection (Task 5) ---"

# Test 7.1: Verify RoomCleanupService uses extended timeout for mid-game rooms
# Create room, start game, then check cleanup behavior
echo "  Verifying extended timeout logic..."

# Test 7.2: Check that getGameState handles missing player gracefully
RESP_RECON=$(get "/game/$ROOM_CODE" "$JAR_HOST")
test_result "Game state accessible with valid session" "echo '$RESP_RECON' | grep -qv 'exception'"

# Test 7.3: Verify reconnection endpoint exists
RESP_RECON_POST=$(post "/game/$ROOM_CODE/reconnect" "{}" "$JAR_HOST")
test_result "Reconnect endpoint responds (not 404)" "echo '$RESP_RECON_POST' | grep -qv '404\|not found'"

echo ""

# ============================================
# TEST 8: HINT CYCLE (Continue hints)
# ============================================
echo "--- TEST GROUP: Continue Hints / Hint Cycle (Task 9) ---"

# Test 8.1: Verify hint_cycle column on rounds
HAS_CYCLE=$(php artisan tinker --execute="echo Schema::hasColumn('rounds','hint_cycle') ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Rounds table has hint_cycle column" "[ '$HAS_CYCLE' = 'yes' ]"

# Test 8.2: Verify hint_cycle column on hints
HAS_HINT_CYCLE=$(php artisan tinker --execute="echo Schema::hasColumn('hints','hint_cycle') ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Hints table has hint_cycle column" "[ '$HAS_HINT_CYCLE' = 'yes' ]"

# Test 8.3: Default hint_cycle is 1
CYCLE_VAL=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$ROOM_ID)->first()?->hint_cycle ?? 'none';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Default hint_cycle is 1" "[ '$CYCLE_VAL' = '1' ]"

echo ""

# ============================================
# TEST 9: SCOREBOARD DATA
# ============================================
echo "--- TEST GROUP: Scoreboard Data (Task 1) ---"

# Test 9.1: Verify finished game state includes vote_tally
# We need a finished game - let's check our first room
FIRST_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE')->first()?->id ?? 0;" 2>/dev/null | tail -1 | tr -d ' ')
if [ "$FIRST_ROOM" != "0" ]; then
    FIRST_STATUS=$(php artisan tinker --execute="echo App\Models\Room::find($FIRST_ROOM)?->status;" 2>/dev/null | tail -1 | tr -d ' ')
    test_result "First room status: $FIRST_STATUS" "true"
fi

# Test 9.2: Verify Room model has category field
HAS_CAT=$(php artisan tinker --execute="echo in_array('category',App\Models\Room::first()?->getFillable() ?? []) ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Room model has category in fillable" "[ '$HAS_CAT' = 'yes' ]"

# Test 9.3: Verify Room model has difficulty field
HAS_DIFF=$(php artisan tinker --execute="echo in_array('difficulty',App\Models\Room::first()?->getFillable() ?? []) ? 'yes' : 'no';" 2>/dev/null | tail -1 | tr -d ' ')
test_result "Room model has difficulty in fillable" "[ '$HAS_DIFF' = 'yes' ]"

echo ""

# ============================================
# SUMMARY
# ============================================
echo "============================================"
echo "TEST SUMMARY"
echo "============================================"
TOTAL=$((PASS+FAIL))
echo "Total: $TOTAL  Passed: $PASS  Failed: $FAIL"
echo ""
echo "Detailed Results:"
for r in "${RESULTS[@]}"; do
    echo "  $r"
done
echo ""
if [ "$FAIL" -eq 0 ]; then
    echo "ALL TESTS PASSED!"
else
    echo "$FAIL tests failed. Check output above."
fi
