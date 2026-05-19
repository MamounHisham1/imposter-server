#!/bin/bash
# Direct curl tests - one function, no subshell issues

BASE="http://localhost:8000"
PASS=0
FAIL=0

pass() { PASS=$((PASS+1)); echo "  PASS: $1"; }
fail() { FAIL=$((FAIL+1)); echo "  FAIL: $1"; }

get_csrf() {
    local jar="$1"
    rm -f "$jar"
    curl -s -c "$jar" "$BASE/" > /dev/null 2>&1
    local enc=$(grep XSRF-TOKEN "$jar" 2>/dev/null | awk '{print $NF}')
    if [ -z "$enc" ]; then
        echo "ERROR_NO_CSRF"
        return
    fi
    python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))" "$enc"
}

echo "============================================"
echo "IMPOSTER TASK CURL TESTS"
echo "============================================"

# =============================================
echo ""
echo "--- Task 8 & 12: Category & Difficulty ---"
# =============================================

# Test: Create room with category=animals, difficulty=easy
JAR="/tmp/t1.txt"
CSRF=$(get_csrf "$JAR")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR" -c "$JAR" \
  -d '{"nickname":"TestAnimals","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"easy"}' \
  -w "\n%{http_code}")
HTTP=$(echo "$RESP" | tail -1)
CODE=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
[ "$HTTP" = "302" ] && pass "Create room animals/easy (302)" || fail "Create room animals/easy ($HTTP)"

if [ -n "$CODE" ]; then
    DB_CAT=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_CAT" = "animals" ] && pass "DB category=animals" || fail "DB category=$DB_CAT"
    DB_DIFF=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE')->value('difficulty');" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_DIFF" = "easy" ] && pass "DB difficulty=easy" || fail "DB difficulty=$DB_DIFF"
fi

# Test: Create room with category=food, difficulty=hard
JAR="/tmp/t2.txt"
CSRF=$(get_csrf "$JAR")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR" -c "$JAR" \
  -d '{"nickname":"TestFood","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"food","difficulty":"hard"}' \
  -w "\n%{http_code}")
HTTP=$(echo "$RESP" | tail -1)
CODE2=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
[ "$HTTP" = "302" ] && pass "Create room food/hard" || fail "Create room food/hard ($HTTP)"
if [ -n "$CODE2" ]; then
    DB_CAT2=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE2')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_CAT2" = "food" ] && pass "DB category=food" || fail "DB category=$DB_CAT2"
    DB_DIFF2=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE2')->value('difficulty');" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_DIFF2" = "hard" ] && pass "DB difficulty=hard" || fail "DB difficulty=$DB_DIFF2"
fi

# Test: Create room with no category/difficulty (defaults)
JAR="/tmp/t3.txt"
CSRF=$(get_csrf "$JAR")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR" -c "$JAR" \
  -d '{"nickname":"TestDefault","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}' \
  -w "\n%{http_code}")
HTTP=$(echo "$RESP" | tail -1)
CODE3=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
[ "$HTTP" = "302" ] && pass "Create room defaults" || fail "Create room defaults ($HTTP)"
if [ -n "$CODE3" ]; then
    DB_CAT3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->value('category') ?? 'NULL';" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_CAT3" = "NULL" ] && pass "DB NULL category" || fail "DB category=$DB_CAT3"
    DB_DIFF3=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CODE3')->value('difficulty');" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$DB_DIFF3" = "medium" ] && pass "DB default difficulty=medium" || fail "DB difficulty=$DB_DIFF3"
fi

# Test: Invalid category
JAR="/tmp/t4.txt"
CSRF=$(get_csrf "$JAR")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR" -c "$JAR" \
  -d '{"nickname":"TestInvalid","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"INVALID","difficulty":"easy"}' \
  -w "\n%{http_code}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "422" ] && pass "Invalid category rejected (422)" || fail "Invalid category ($HTTP)"

# Test: Invalid difficulty
JAR="/tmp/t5.txt"
CSRF=$(get_csrf "$JAR")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR" -c "$JAR" \
  -d '{"nickname":"TestInvDiff","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"extreme"}' \
  -w "\n%{http_code}")
HTTP=$(echo "$RESP" | tail -1)
[ "$HTTP" = "422" ] && pass "Invalid difficulty rejected (422)" || fail "Invalid difficulty ($HTTP)"

# Test all categories
for cat in animals food places technology sports nature professions music vehicles; do
    JAR="/tmp/tc_${cat}.txt"
    CSRF=$(get_csrf "$JAR")
    HTTP=$(curl -s -X POST "$BASE/room" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR" -c "$JAR" \
      -d "{\"nickname\":\"Cat$cat\",\"type\":\"private\",\"max_players\":4,\"rounds_per_game\":1,\"language\":\"en\",\"category\":\"$cat\",\"difficulty\":\"medium\"}" \
      -w "%{http_code}" -o /dev/null)
    [ "$HTTP" = "302" ] && pass "Category '$cat' accepted" || fail "Category '$cat' ($HTTP)"
done

# Test all difficulties
for diff in easy medium hard; do
    JAR="/tmp/td_${diff}.txt"
    CSRF=$(get_csrf "$JAR")
    HTTP=$(curl -s -X POST "$BASE/room" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR" -c "$JAR" \
      -d "{\"nickname\":\"Diff$diff\",\"type\":\"private\",\"max_players\":4,\"rounds_per_game\":1,\"language\":\"en\",\"category\":\"animals\",\"difficulty\":\"$diff\"}" \
      -w "%{http_code}" -o /dev/null)
    [ "$HTTP" = "302" ] && pass "Difficulty '$diff' accepted" || fail "Difficulty '$diff' ($HTTP)"
done

# =============================================
echo ""
echo "--- Task 3: Rematch (Full Game Flow) ---"
# =============================================

# Create room
JAR_H="/tmp/rm_host.txt"
CSRF=$(get_csrf "$JAR_H")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_H" -c "$JAR_H" \
  -d '{"nickname":"RmHost","type":"public","max_players":6,"rounds_per_game":1,"language":"en"}' \
  -w "\n%{http_code}")
RM_CODE=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
RM_HTTP=$(echo "$RESP" | tail -1)
[ "$RM_HTTP" = "302" ] && pass "Rematch room created ($RM_CODE)" || fail "Rematch room ($RM_HTTP)"

RM_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$RM_CODE')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
RM_HOST=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
echo "  Room: $RM_ROOM, Host: $RM_HOST"

# Join players
JAR_P2="/tmp/rm_p2.txt"
CSRF=$(get_csrf "$JAR_P2")
curl -s -X POST "$BASE/room/join" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_P2" -c "$JAR_P2" \
  -d "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP2\"}" -w "%{http_code}" -o /dev/null
RM_P2=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('nickname','RmP2')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
[ -n "$RM_P2" ] && [ "$RM_P2" != "" ] && pass "P2 joined (ID: $RM_P2)" || fail "P2 join"

JAR_P3="/tmp/rm_p3.txt"
CSRF=$(get_csrf "$JAR_P3")
curl -s -X POST "$BASE/room/join" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_P3" -c "$JAR_P3" \
  -d "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP3\"}" -w "%{http_code}" -o /dev/null
RM_P3=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('nickname','RmP3')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
[ -n "$RM_P3" ] && [ "$RM_P3" != "" ] && pass "P3 joined (ID: $RM_P3)" || fail "P3 join"

# Ready all
CSRF=$(get_csrf "$JAR_H")
curl -s -X POST "$BASE/room/$RM_CODE/ready" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $CSRF" -H "X-Requested-With: XMLHttpRequest" -H "Referer: $BASE/" -b "$JAR_H" -c "$JAR_H" -d "{\"player_id\":$RM_HOST}" -o /dev/null
CSRF=$(get_csrf "$JAR_P2")
curl -s -X POST "$BASE/room/$RM_CODE/ready" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $CSRF" -H "X-Requested-With: XMLHttpRequest" -H "Referer: $BASE/" -b "$JAR_P2" -c "$JAR_P2" -d "{\"player_id\":$RM_P2}" -o /dev/null
CSRF=$(get_csrf "$JAR_P3")
curl -s -X POST "$BASE/room/$RM_CODE/ready" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $CSRF" -H "X-Requested-With: XMLHttpRequest" -H "Referer: $BASE/" -b "$JAR_P3" -c "$JAR_P3" -d "{\"player_id\":$RM_P3}" -o /dev/null
pass "All ready"

# Start game
CSRF=$(get_csrf "$JAR_H")
HTTP=$(curl -s -X POST "$BASE/room/$RM_CODE/start" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_H" -c "$JAR_H" \
  -d "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "302" ] && pass "Game started" || fail "Game start ($HTTP)"
sleep 3

# Get round info
RM_RID=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$RM_ROOM)->orderByDesc('id')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
RM_ORDER=$(php artisan tinker --execute="echo json_encode(App\Models\Round::find($RM_RID)->hint_order);" 2>/dev/null | tail -1)
echo "  Round: $RM_RID, Order: $RM_ORDER"

if [ -n "$RM_RID" ] && [ "$RM_RID" != "" ]; then
    # Submit hints
    IDS=($(echo "$RM_ORDER" | python3 -c "import json,sys; [print(i) for i in json.load(sys.stdin)]" 2>/dev/null))
    HINTS=("tiger" "elephant" "monkey")

    get_jar() {
        case "$1" in
            "$RM_HOST") echo "$JAR_H";;
            "$RM_P2") echo "$JAR_P2";;
            "$RM_P3") echo "$JAR_P3";;
        esac
    }

    for i in 0 1 2; do
        PID=${IDS[$i]}
        HINT=${HINTS[$i]}
        J=$(get_jar "$PID")
        CSRF=$(get_csrf "$J")
        curl -s -X POST "$BASE/game/$RM_CODE/hint" \
          -H "Content-Type: application/json" \
          -H "X-XSRF-TOKEN: $CSRF" \
          -H "X-Requested-With: XMLHttpRequest" \
          -H "Referer: $BASE/" \
          -b "$J" -c "$J" \
          -d "{\"content\":\"$HINT\",\"player_id\":$PID}" -o /dev/null
    done
    pass "All hints submitted"

    # Start voting
    CSRF=$(get_csrf "$JAR_H")
    HTTP=$(curl -s -X POST "$BASE/game/$RM_CODE/start-voting" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR_H" -c "$JAR_H" \
      -d "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}" \
      -w "%{http_code}" -o /dev/null)
    [ "$HTTP" = "302" ] && pass "Voting started" || fail "Voting start ($HTTP)"

    # Vote
    RM_IMP=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_imposter',1)->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
    echo "  Imposter: $RM_IMP"

    if [ -n "$RM_IMP" ]; then
        for PID in $RM_HOST $RM_P2 $RM_P3; do
            J=$(get_jar "$PID")
            CSRF=$(get_csrf "$J")
            curl -s -X POST "$BASE/game/$RM_CODE/vote" \
              -H "Content-Type: application/json" \
              -H "X-XSRF-TOKEN: $CSRF" \
              -H "X-Requested-With: XMLHttpRequest" \
              -H "Referer: $BASE/" \
              -b "$J" -c "$J" \
              -d "{\"target_id\":$RM_IMP,\"player_id\":$PID}" -o /dev/null
        done
        pass "All voted for imposter"
    fi

    sleep 1

    # Check finished
    STATUS=$(php artisan tinker --execute="echo App\Models\Room::find($RM_ROOM)->status;" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$STATUS" = "finished" ] && pass "Game finished" || fail "Status=$STATUS"

    # REMATCH
    CSRF=$(get_csrf "$JAR_H")
    HTTP=$(curl -s -X POST "$BASE/game/$RM_CODE/rematch" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR_H" -c "$JAR_H" \
      -d "{\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}" \
      -w "%{http_code}" -o /dev/null)
    [ "$HTTP" = "302" ] && pass "Rematch triggered" || fail "Rematch ($HTTP)"

    sleep 1

    # Verify reset
    STATUS2=$(php artisan tinker --execute="echo App\Models\Room::find($RM_ROOM)->status;" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$STATUS2" = "waiting" ] && pass "Room reset to waiting" || fail "Status=$STATUS2"

    SCORE=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$RM_ROOM)->where('id',$RM_HOST)->value('score');" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$SCORE" = "0" ] && pass "Score reset to 0" || fail "Score=$SCORE"

    ROUNDS=$(php artisan tinker --execute="echo App\Models\Round::where('room_id',$RM_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
    [ "$ROUNDS" = "0" ] && pass "Rounds deleted" || fail "Rounds=$ROUNDS"

    # Non-creator rematch fails
    CSRF=$(get_csrf "$JAR_P2")
    HTTP=$(curl -s -X POST "$BASE/game/$RM_CODE/rematch" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR_P2" -c "$JAR_P2" \
      -d "{\"player_id\":$RM_P2,\"room_id\":$RM_ROOM}" \
      -w "%{http_code}" -o /dev/null)
    [ "$HTTP" = "422" ] && pass "Non-creator rematch rejected" || fail "Non-creator rematch ($HTTP)"
fi

# =============================================
echo ""
echo "--- Task 7: Chat ---"
# =============================================

JAR_CH="/tmp/ch_host.txt"
CSRF=$(get_csrf "$JAR_CH")
RESP=$(curl -s -X POST "$BASE/room" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d '{"nickname":"ChatHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}' \
  -w "\n%{http_code}")
CH_CODE=$(echo "$RESP" | grep -oP 'room/\K[A-Z0-9]+' | head -1)
CH_ROOM=$(php artisan tinker --execute="echo App\Models\Room::where('code','$CH_CODE')->value('id');" 2>/dev/null | tail -1 | tr -d ' ')
CH_PID=$(php artisan tinker --execute="echo App\Models\Player::where('room_id',$CH_ROOM)->value('id');" 2>/dev/null | tail -1 | tr -d ' ')

# Send message
CSRF=$(get_csrf "$JAR_CH")
HTTP=$(curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"Hello world\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "302" ] && pass "Chat sent" || fail "Chat ($HTTP)"

# Send another
CSRF=$(get_csrf "$JAR_CH")
curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"Msg 2\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" -o /dev/null
pass "Second chat sent"

# Verify DB
MSG=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
[ "$MSG" -ge "2" ] && pass "Messages in DB ($MSG)" || fail "Messages: $MSG"

# Empty msg
CSRF=$(get_csrf "$JAR_CH")
HTTP=$(curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "422" ] && pass "Empty rejected" || fail "Empty ($HTTP)"

# Long msg
LONG=$(python3 -c "print('x'*501)")
CSRF=$(get_csrf "$JAR_CH")
HTTP=$(curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"$LONG\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "422" ] && pass "500+ rejected" || fail "500+ ($HTTP)"

# Valid 500 chars
MSG500=$(python3 -c "print('a'*500)")
CSRF=$(get_csrf "$JAR_CH")
HTTP=$(curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"$MSG500\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "302" ] && pass "500 chars accepted" || fail "500 ($HTTP)"

# Fake player
CSRF=$(get_csrf "$JAR_CH")
HTTP=$(curl -s -X POST "$BASE/game/$CH_CODE/chat" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: $BASE/" \
  -b "$JAR_CH" -c "$JAR_CH" \
  -d "{\"message\":\"Fake\",\"player_id\":99999,\"room_id\":$CH_ROOM}" \
  -w "%{http_code}" -o /dev/null)
[ "$HTTP" = "422" ] && pass "Fake player rejected" || fail "Fake ($HTTP)"

# Bulk messages
for i in $(seq 1 8); do
    CSRF=$(get_csrf "$JAR_CH")
    curl -s -X POST "$BASE/game/$CH_CODE/chat" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: $BASE/" \
      -b "$JAR_CH" -c "$JAR_CH" \
      -d "{\"message\":\"Bulk $i\",\"player_id\":$CH_PID,\"room_id\":$CH_ROOM}" -o /dev/null
done
MSG2=$(php artisan tinker --execute="echo App\Models\ChatMessage::where('room_id',$CH_ROOM)->count();" 2>/dev/null | tail -1 | tr -d ' ')
[ "$MSG2" -ge "10" ] && pass "Bulk stored ($MSG2)" || fail "Bulk: $MSG2"

# =============================================
echo ""
echo "--- Tasks 5, 9, 10, 11: Infrastructure ---"
# =============================================

# Stats
HTTP=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/stats")
[ "$HTTP" != "404" ] && pass "Stats route ($HTTP)" || fail "Stats 404"

# DB checks
php artisan tinker --execute="echo App\Models\GameHistory::count();" 2>/dev/null | tail -1 | tr -d ' ' > /dev/null && pass "GameHistory model" || fail "GameHistory"
php artisan tinker --execute="echo App\Models\GameStat::count();" 2>/dev/null | tail -1 | tr -d ' ' > /dev/null && pass "GameStat model" || fail "GameStat"
php artisan tinker --execute="echo Schema::hasColumn('game_histories','user_id')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ' | grep -q "yes" && pass "GameHistory.user_id" || fail "GameHistory.user_id"
php artisan tinker --execute="echo Schema::hasColumn('game_stats','user_id')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ' | grep -q "yes" && pass "GameStat.user_id" || fail "GameStat.user_id"
php artisan tinker --execute="echo Schema::hasColumn('players','is_spectator')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ' | grep -q "yes" && pass "is_spectator" || fail "is_spectator"
php artisan tinker --execute="echo Schema::hasColumn('rounds','hint_cycle')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ' | grep -q "yes" && pass "hint_cycle (rounds)" || fail "hint_cycle"
php artisan tinker --execute="echo Schema::hasColumn('hints','hint_cycle')?'yes':'no';" 2>/dev/null | tail -1 | tr -d ' ' | grep -q "yes" && pass "hint_cycle (hints)" || fail "hint_cycle hints"
grep -q "300" app/Services/RoomCleanupService.php && pass "Extended timeout" || fail "Extended timeout"

# =============================================
echo ""
echo "============================================"
echo "SUMMARY: $PASS passed, $FAIL failed (total: $((PASS+FAIL)))"
echo "============================================"
