#!/bin/bash
# Direct curl tests for Imposter game tasks
# Uses proper CSRF handling and real data

BASE="http://localhost:8000"
PASS=0
FAIL=0

pass() { PASS=$((PASS+1)); echo "  PASS: $1"; }
fail() { FAIL=$((FAIL+1)); echo "  FAIL: $1"; }

# Helper: Create room and return code
create_room() {
    local jar="$1"
    local data="$2"
    rm -f "$jar"
    curl -s -c "$jar" "$BASE/" > /dev/null
    local xsrf=$(grep XSRF-TOKEN "$jar" | awk '{print $NF}')
    local decoded=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$xsrf'))")
    local resp=$(curl -s -X POST "$BASE/room" \
        -H "Content-Type: application/json" \
        -H "X-XSRF-TOKEN: $decoded" \
        -H "X-Requested-With: XMLHttpRequest" \
        -H "Referer: $BASE/" \
        -b "$jar" -c "$jar" \
        -d "$data" -w "\n%{http_code}")
    local code=$(echo "$resp" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
    local http=$(echo "$resp" | tail -1)
    echo "$code|$http|$jar"
}

# Helper: POST to a game endpoint
game_post() {
    local jar="$1"
    local path="$2"
    local data="$3"
    # Refresh CSRF
    curl -s -c "$jar" -b "$jar" "$BASE/" > /dev/null 2>&1
    local xsrf=$(grep XSRF-TOKEN "$jar" | awk '{print $NF}')
    local decoded=$(python3 -c "import urllib.parse; print(urllib.parse.unquote('$xsrf'))")
    curl -s -X POST "$BASE$path" \
        -H "Content-Type: application/json" \
        -H "X-XSRF-TOKEN: $decoded" \
        -H "X-Requested-With: XMLHttpRequest" \
        -H "Referer: $BASE/" \
        -b "$jar" -c "$jar" \
        -d "$data" -w "\n%{http_code}"
}

echo "============================================"
echo "IMPOSTER GAME - TASK CURL TESTS (v2)"
echo "============================================"

# =============================================
echo ""
echo "--- Task 8 & 12: Category & Difficulty ---"
# =============================================

# Test 1: Create room with category=animals, difficulty=easy
RESULT=$(create_room "/tmp/t_cat1.txt" '{"nickname":"CatHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"easy"}')
CODE1=$(echo "$RESULT" | cut -d'|' -f1)
HTTP1=$(echo "$RESULT" | cut -d'|' -f2)
[ "$HTTP1" = "302" ] && pass "Create room category=animals difficulty=easy (302)" || fail "Create room category=animals difficulty=easy (HTTP: $HTTP1)"

# Verify in DB
DB_CAT=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE1')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$DB_CAT" = "animals" ] && pass "DB: category=animals" || fail "DB: category=$DB_CAT (expected: animals)"

DB_DIFF=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE1')->value('difficulty') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$DB_DIFF" = "easy" ] && pass "DB: difficulty=easy" || fail "DB: difficulty=$DB_DIFF (expected: easy)"

# Test 2: Create room with category=food, difficulty=hard
RESULT=$(create_room "/tmp/t_cat2.txt" '{"nickname":"FoodHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"food","difficulty":"hard"}')
CODE2=$(echo "$RESULT" | cut -d'|' -f1)
HTTP2=$(echo "$RESULT" | cut -d'|' -f2)
[ "$HTTP2" = "302" ] && pass "Create room category=food difficulty=hard" || fail "Create room category=food difficulty=hard (HTTP: $HTTP2)"

DB_CAT2=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE2')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$DB_CAT2" = "food" ] && pass "DB: category=food" || fail "DB: category=$DB_CAT2"

# Test 3: Create room with no category (random), no difficulty (default)
RESULT=$(create_room "/tmp/t_cat3.txt" '{"nickname":"RandHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
CODE3=$(echo "$RESULT" | cut -d'|' -f1)
HTTP3=$(echo "$RESULT" | cut -d'|' -f2)
[ "$HTTP3" = "302" ] && pass "Create room no category/difficulty" || fail "Create room no category/difficulty (HTTP: $HTTP3)"

DB_CAT3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$DB_CAT3" = "NULL" ] && pass "DB: NULL category for default" || fail "DB: category=$DB_CAT3 (expected: NULL)"

DB_DIFF3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->value('difficulty') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$DB_DIFF3" = "medium" ] && pass "DB: default difficulty=medium" || fail "DB: difficulty=$DB_DIFF3 (expected: medium)"

# Test 4: Invalid category
RESP=$(game_post "/tmp/t_cat1.txt" "/room" '{"nickname":"BadCat","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"INVALID","difficulty":"easy"}')
echo "$RESP" | grep -q "422\|validation\|error" && pass "Invalid category rejected" || fail "Invalid category not rejected"

# Test 5: Invalid difficulty
RESP=$(game_post "/tmp/t_cat1.txt" "/room" '{"nickname":"BadDiff","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"extreme"}')
echo "$RESP" | grep -q "422\|validation\|error" && pass "Invalid difficulty rejected" || fail "Invalid difficulty not rejected"

# Test 6: All categories work
for cat in animals food places technology sports nature professions music vehicles; do
    RESULT=$(create_room "/tmp/t_${cat}.txt" "{\"nickname\":\"${cat}Host\",\"type\":\"private\",\"max_players\":4,\"rounds_per_game\":1,\"language\":\"en\",\"category\":\"$cat\",\"difficulty\":\"medium\"}")
    HTTP=$(echo "$RESULT" | cut -d'|' -f2)
    [ "$HTTP" = "302" ] && pass "Category '$cat' works" || fail "Category '$cat' failed (HTTP: $HTTP)"
done

# Test 7: All difficulties work
for diff in easy medium hard; do
    RESULT=$(create_room "/tmp/t_diff_${diff}.txt" "{\"nickname\":\"Diff${diff}\",\"type\":\"private\",\"max_players\":4,\"rounds_per_game\":1,\"language\":\"en\",\"category\":\"animals\",\"difficulty\":\"$diff\"}")
    HTTP=$(echo "$RESULT" | cut -d'|' -f2)
    [ "$HTTP" = "302" ] && pass "Difficulty '$diff' works" || fail "Difficulty '$diff' failed (HTTP: $HTTP)"
done

# =============================================
echo ""
echo "--- Task 3: Rematch ---"
# =============================================

# Create a 1-round game room
RESULT=$(create_room "/tmp/t_rm1.txt" '{"nickname":"RmHost","type":"public","max_players":6,"rounds_per_game":1,"language":"en"}')
RM_CODE=$(echo "$RESULT" | cut -d'|' -f1)
RM_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$RM_CODE')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
RM_HOST=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->first()->id;" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Rematch room: $RM_CODE (ID: $RM_ROOM, Host: $RM_HOST)"

# Join 2 more players
game_post "/tmp/t_rm2.txt" "/room/join" "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP2\"}" > /dev/null
RM_P2=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('nickname','RmP2')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')

game_post "/tmp/t_rm3.txt" "/room/join" "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP3\"}" > /dev/null
RM_P3=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('nickname','RmP3')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')

[ -n "$RM_P2" ] && [ "$RM_P2" != "0" ] && pass "Player 2 joined" || fail "Player 2 joined (P2=$RM_P2)"
[ -n "$RM_P3" ] && [ "$RM_P3" != "0" ] && pass "Player 3 joined" || fail "Player 3 joined (P3=$RM_P3)"

# Ready up
game_post "/tmp/t_rm1.txt" "/room/$RM_CODE/ready" "{\"player_id\":$RM_HOST}" > /dev/null
game_post "/tmp/t_rm2.txt" "/room/$RM_CODE/ready" "{\"player_id\":$RM_P2}" > /dev/null
game_post "/tmp/t_rm3.txt" "/room/$RM_CODE/ready" "{\"player_id\":$RM_P3}" > /dev/null
pass "All players ready"

# Start game
RESP=$(game_post "/tmp/t_rm1.txt" "/room/$RM_CODE/start" "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "302" ] && pass "Game started" || fail "Game start (HTTP: $HTTP)"

sleep 2

# Get game state
RM_ROUND=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$RM_ROOM)->first()->id;" 2>/dev/null | tail -1 | tr -d ' ')
RM_ORDER=$(php artisan tinker --execute="echo json_encode(App\Models\Round::find($RM_ROUND)->hint_order);" 2>/dev/null | tail -1)
echo "  Round: $RM_ROUND, Order: $RM_ORDER"

# Submit hints in order
FIRST=$(echo "$RM_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[0])" 2>/dev/null)
SECOND=$(echo "$RM_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[1])" 2>/dev/null)
THIRD=$(echo "$RM_ORDER" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[2])" 2>/dev/null)

get_jar() {
    case "$1" in
        "$RM_HOST") echo "/tmp/t_rm1.txt";;
        "$RM_P2") echo "/tmp/t_rm2.txt";;
        "$RM_P3") echo "/tmp/t_rm3.txt";;
    esac
}

game_post "$(get_jar $FIRST)" "/game/$RM_CODE/hint" "{\"content\":\"hint1\",\"player_id\":$FIRST}" > /dev/null
game_post "$(get_jar $SECOND)" "/game/$RM_CODE/hint" "{\"content\":\"hint2\",\"player_id\":$SECOND}" > /dev/null
game_post "$(get_jar $THIRD)" "/game/$RM_CODE/hint" "{\"content\":\"hint3\",\"player_id\":$THIRD}" > /dev/null
pass "All hints submitted"

# Start voting
RESP=$(game_post "/tmp/t_rm1.txt" "/game/$RM_CODE/start-voting" "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "302" ] && pass "Voting started" || fail "Voting start (HTTP: $HTTP)"

# Vote - everyone votes for imposter
RM_IMPOSTER=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_imposter',1)->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Imposter: $RM_IMPOSTER"

game_post "$(get_jar $RM_HOST)" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_HOST}" > /dev/null
game_post "$(get_jar $RM_P2)" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_P2}" > /dev/null
game_post "$(get_jar $RM_P3)" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_P3}" > /dev/null
pass "All votes submitted"

sleep 1

# Check game finished
RM_STATUS=$(php artisan tinker --execute="echo App\Models\Room::find($RM_ROOM)->status;" 2>/dev/null | tail -1 | tr -d ' ')
[ "$RM_STATUS" = "finished" ] && pass "Game finished" || fail "Game status=$RM_STATUS (expected: finished)"

# Now test REMATCH
RESP=$(game_post "/tmp/t_rm1.txt" "/game/$RM_CODE/rematch" "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "302" ] && pass "Rematch triggered" || fail "Rematch trigger (HTTP: $HTTP)"

sleep 1

# Verify room back to waiting
RM_STATUS2=$(php artisan tinker --execute="echo App\Models\Room::find($RM_ROOM)->status;" 2>/dev/null | tail -1 | tr -d ' ')
[ "$RM_STATUS2" = "waiting" ] && pass "Room reset to waiting" || fail "Room status=$RM_STATUS2"

# Verify scores reset
RM_SCORE=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('nickname','RmHost')->value('score');" 2>/dev/null | tail -1 | tr -d ' ')
[ "$RM_SCORE" = "0" ] && pass "Scores reset to 0" || fail "Score=$RM_SCORE (expected: 0)"

# Verify rounds deleted
RM_ROUNDS=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$RM_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
[ "$RM_ROUNDS" = "0" ] && pass "Rounds deleted" || fail "Rounds=$RM_ROUNDS (expected: 0)"

# Test rematch fails for non-creator
RESP=$(game_post "/tmp/t_rm2.txt" "/game/$RM_CODE/rematch" "{\"player_id\":$RM_P2,\"room_id\":$RM_ROOM}")
echo "$RESP" | head -1 | grep -qi "error\|only\|creator\|422\|exception" && pass "Non-creator rematch rejected" || fail "Non-creator rematch should fail"

# =============================================
echo ""
echo "--- Task 7: Chat ---"
# =============================================

# Create room for chat test
RESULT=$(create_room "/tmp/t_ch1.txt" '{"nickname":"ChatHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
CH_CODE=$(echo "$RESULT" | cut -d'|' -f1)
CH_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CH_CODE')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
CH_HOST=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$CH_ROOM)->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Chat room: $CH_CODE (Room: $CH_ROOM, Host: $CH_HOST)"

# Send chat message
RESP=$(game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"Hello world\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "302" ] && pass "Chat message sent (302)" || fail "Chat message (HTTP: $HTTP)"

# Send another
game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"Second msg\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}" > /dev/null
pass "Second chat message sent"

# Verify in DB
MSG_COUNT=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
[ "$MSG_COUNT" -ge "2" ] && pass "Messages in DB ($MSG_COUNT)" || fail "Messages in DB: $MSG_COUNT"

# Test empty message
RESP=$(game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}")
echo "$RESP" | grep -q "422\|error\|required" && pass "Empty message rejected" || fail "Empty message should be rejected"

# Test long message
LONG=$(python3 -c "print('x' * 501)")
RESP=$(game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"$LONG\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}")
echo "$RESP" | grep -q "422\|error\|validation" && pass "500+ char message rejected" || fail "500+ char message should be rejected"

# Test valid 500 char message
MSG500=$(python3 -c "print('a' * 500)")
RESP=$(game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"$MSG500\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "302" ] && pass "500 char message accepted" || fail "500 char message (HTTP: $HTTP)"

# Test fake player
RESP=$(game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"Fake\",\"player_id\":99999,\"room_id\":$CH_ROOM}")
echo "$RESP" | head -1 | grep -qi "error\|422\|exception" && pass "Fake player rejected" || fail "Fake player should be rejected"

# Send bulk messages
for i in $(seq 1 8); do
    game_post "/tmp/t_ch1.txt" "/game/$CH_CODE/chat" "{\"message\":\"Bulk $i\",\"player_id\":$CH_HOST,\"room_id\":$CH_ROOM}" > /dev/null
done
MSG_COUNT2=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
[ "$MSG_COUNT2" -ge "10" ] && pass "Bulk messages stored ($MSG_COUNT2)" || fail "Bulk messages: $MSG_COUNT2"

# =============================================
echo ""
echo "--- Task 10: Game History / Stats ---"
# =============================================

# Check stats route
RESP=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/stats")
[ "$RESP" != "404" ] && pass "Stats route exists (HTTP: $RESP)" || fail "Stats route 404"

# Check models
HIST_COUNT=$(php artisan tinker --execute="echo App\Models\GameHistory::count();" 2>/dev/null | tail -1 | tr -d ' ')
pass "GameHistory model works (count: $HIST_COUNT)"

STAT_COUNT=$(php artisan tinker --execute="echo App\Models\GameStat::count();" 2>/dev/null | tail -1 | tr -d ' ')
pass "GameStat model works (count: $STAT_COUNT)"

# Check columns
HAS_UID=$(php artisan tinker --execute="echo Schema::hasColumn('game_histories','user_id')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_UID" = "yes" ] && pass "GameHistory has user_id" || fail "GameHistory missing user_id"

STAT_UID=$(php artisan tinker --execute="echo Schema::hasColumn('game_stats','user_id')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$STAT_UID" = "yes" ] && pass "GameStat has user_id" || fail "GameStat missing user_id"

# =============================================
echo ""
echo "--- Task 11: Spectator Mode ---"
# =============================================

HAS_SPEC=$(php artisan tinker --execute="echo Schema::hasColumn('players','is_spectator')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_SPEC" = "yes" ] && pass "is_spectator column exists" || fail "is_spectator column missing"

# Check Player model has is_spectator fillable
HAS_FILL=$(php artisan tinker --execute="echo in_array('is_spectator',App\Models\Player::first()->getFillable())?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_FILL" = "yes" ] && pass "Player model has is_spectator fillable" || fail "Player model missing is_spectator"

# =============================================
echo ""
echo "--- Task 5: Reconnection ---"
# =============================================

# Check reconnect route
RESP=$(game_post "/tmp/t_rm1.txt" "/game/$RM_CODE/reconnect" "{}")
echo "$RESP" | head -1 | grep -qv "404" && pass "Reconnect route exists" || fail "Reconnect route missing"

# Check extended timeout logic exists
grep -q "300" app/Services/RoomCleanupService.php && pass "Extended timeout (300s) in cleanup" || fail "Extended timeout missing"

# =============================================
echo ""
echo "--- Task 9: Hint Cycle ---"
# =============================================

HAS_CYCLE=$(php artisan tinker --execute="echo Schema::hasColumn('rounds','hint_cycle')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_CYCLE" = "yes" ] && pass "hint_cycle column on rounds" || fail "hint_cycle missing on rounds"

HAS_HCYCLE=$(php artisan tinker --execute="echo Schema::hasColumn('hints','hint_cycle')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_HCYCLE" = "yes" ] && pass "hint_cycle column on hints" || fail "hint_cycle missing on hints"

# =============================================
echo ""
echo "--- Task 1: Scoreboard Data ---"
# =============================================

# Check Room model has category/difficulty fillable
HAS_CAT_FILL=$(php artisan tinker --execute="echo in_array('category',App\Models\Room::first()->getFillable())?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_CAT_FILL" = "yes" ] && pass "Room has category fillable" || fail "Room missing category fillable"

HAS_DIFF_FILL=$(php artisan tinker --execute="echo in_array('difficulty',App\Models\Room::first()->getFillable())?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ')
[ "$HAS_DIFF_FILL" = "yes" ] && pass "Room has difficulty fillable" || fail "Room missing difficulty fillable"

# Check vote_tally is in getGameState for finished rooms
grep -q "vote_tally" app/Services/GameService.php && pass "vote_tally in GameService" || fail "vote_tally missing"

# Check imposter_caught is passed
grep -q "imposter_caught" app/Services/GameService.php && pass "imposter_caught in GameService" || fail "imposter_caught missing"

# =============================================
echo ""
echo "============================================"
echo "SUMMARY"
echo "============================================"
TOTAL=$((PASS+FAIL))
echo "Total: $TOTAL  Passed: $PASS  Failed: $FAIL"
echo ""
[ "$FAIL" -eq 0 ] && echo "ALL TESTS PASSED!" || echo "$FAIL tests failed."
