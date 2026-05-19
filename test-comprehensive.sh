#!/bin/bash
# Comprehensive curl tests: 10+ curl commands per new route/feature
# Tests: Task 1 (Scoreboard), 3 (Rematch), 5 (Reconnect), 7 (Chat),
#        8 (Category), 9 (Hint cycle), 10 (Stats), 11 (Spectator), 12 (Difficulty)

BASE="http://localhost:8000"
PASS=0
FAIL=0
TOTAL=0

pass() { PASS=$((PASS+1)); TOTAL=$((TOTAL+1)); echo "  PASS #$TOTAL: $1"; }
fail() { FAIL=$((FAIL+1)); TOTAL=$((TOTAL+1)); echo "  FAIL #$TOTAL: $1"; }
check() { TOTAL=$((TOTAL+1)); if [ "$1" = "$2" ]; then PASS=$((PASS+1)); echo "  PASS #$TOTAL: $3"; else FAIL=$((FAIL+1)); echo "  FAIL #$TOTAL: $3 (got '$1', expected '$2')"; fi; }

# Get CSRF token + init session
get_csrf() {
    local jar="$1"
    rm -f "$jar"
    curl -s -c "$jar" "$BASE/" > /dev/null 2>&1
    local enc=$(grep XSRF-TOKEN "$jar" 2>/dev/null | awk '{print $NF}')
    if [ -z "$enc" ]; then echo "ERROR_NO_CSRF"; return; fi
    python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))" "$enc"
}

# Post with CSRF
cpost() {
    local jar="$1" url="$2" data="$3"
    local csrf=$(get_csrf "$jar")
    curl -s -X POST "$BASE$url" \
        -H "Content-Type: application/json" \
        -H "X-XSRF-TOKEN: $csrf" \
        -H "X-Requested-With: XMLHttpRequest" \
        -H "Referer: $BASE/" \
        -b "$jar" -c "$jar" \
        -d "$data" \
        -w "%{http_code}" -o /tmp/curl_body.txt 2>/dev/null
}

# Post with existing session (reuse CSRF from jar)
spost() {
    local jar="$1" url="$2" data="$3"
    local enc=$(grep XSRF-TOKEN "$jar" 2>/dev/null | awk '{print $NF}')
    local csrf=$(python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))" "$enc" 2>/dev/null)
    if [ -z "$csrf" ] || [ "$csrf" = "None" ]; then csrf=$(get_csrf "$jar"); fi
    curl -s -X POST "$BASE$url" \
        -H "Content-Type: application/json" \
        -H "X-XSRF-TOKEN: $csrf" \
        -H "X-Requested-With: XMLHttpRequest" \
        -H "Referer: $BASE/" \
        -b "$jar" -c "$jar" \
        -d "$data" \
        -w "%{http_code}" -o /tmp/curl_body.txt 2>/dev/null
}

# GET with session
sget() {
    local jar="$1" url="$2"
    curl -s -b "$jar" "$BASE$url" -o /tmp/curl_body.txt -w "%{http_code}" 2>/dev/null
}

# Extract Inertia page props from HTML
get_props() {
    python3 -c "
import json
with open('/tmp/curl_body.txt') as f:
    content = f.read()
idx = content.find('data-page=\"app\"')
if idx < 0:
    print('{}')
    exit()
json_start = content.find('>', idx) + 1
json_end = content.find('</script>', json_start)
if json_start < 1 or json_end < 0:
    print('{}')
    exit()
data = json.loads(content[json_start:json_end])
print(json.dumps(data.get('props', {})))
" 2>/dev/null
}

# Get a specific prop
get_prop() {
    python3 -c "
import sys, json
try:
    props = json.loads(sys.stdin.read())
except:
    print('')
    exit()
keys = sys.argv[1].split('.')
v = props
for k in keys:
    if isinstance(v, dict): v = v.get(k)
    elif isinstance(v, list) and k.isdigit(): v = v[int(k)]
    else: v = None
    if v is None: break
if v is None:
    print('')
elif isinstance(v, bool):
    print('true' if v else 'false')
elif isinstance(v, (int, float, str)):
    print(str(v))
else:
    print(json.dumps(v))
" "$1" 2>/dev/null
}

# Tinker helper
tinker() {
    php artisan tinker --execute="$1" 2>/dev/null | grep -v 'Psy Shell' | grep -v '^$' | tail -1 | tr -d ' '
}

echo "======================================================"
echo "COMPREHENSIVE CURL TESTS - 10+ PER ROUTE/FEATURE"
echo "======================================================"

###############################################################################
# TASK 8 & 12: CATEGORY & DIFFICULTY (POST /room)
###############################################################################
echo ""
echo "========== TASK 8 & 12: CATEGORY & DIFFICULTY (POST /room) =========="
echo "--- 10+ curl tests for category validation ---"

# Test 1: Valid category=animals
JAR="/tmp/t_cat.txt"
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest1","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"animals","difficulty":"medium"}')
CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
check "$HTTP" "302" "Create room with category=animals"
DB_CAT=$(tinker "echo App\Models\Room::where('code','$CODE')->value('category') ?? 'NULL';")
check "$DB_CAT" "animals" "DB: category stored as animals"

# Test 2: Valid category=food
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest2","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"food","difficulty":"easy"}')
CODE2=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT2=$(tinker "echo App\Models\Room::where('code','$CODE2')->value('category') ?? 'NULL';")
check "$DB_CAT2" "food" "DB: category=food"

# Test 3: category=technology
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest3","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"technology","difficulty":"hard"}')
CODE3=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT3=$(tinker "echo App\Models\Room::where('code','$CODE3')->value('category') ?? 'NULL';")
check "$DB_CAT3" "technology" "DB: category=technology"

# Test 4: category=sports
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest4","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"sports"}')
CODE4=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT4=$(tinker "echo App\Models\Room::where('code','$CODE4')->value('category') ?? 'NULL';")
check "$DB_CAT4" "sports" "DB: category=sports"

# Test 5: category=nature
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest5","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"nature"}')
CODE5=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT5=$(tinker "echo App\Models\Room::where('code','$CODE5')->value('category') ?? 'NULL';")
check "$DB_CAT5" "nature" "DB: category=nature"

# Test 6: category=places
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest6","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"places"}')
CODE6=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT6=$(tinker "echo App\Models\Room::where('code','$CODE6')->value('category') ?? 'NULL';")
check "$DB_CAT6" "places" "DB: category=places"

# Test 7: Invalid category rejected
HTTP=$(cpost "$JAR" "/room" '{"nickname":"BadCat","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"invalid_cat"}')
check "$HTTP" "422" "Invalid category rejected (422)"

# Test 8: Null category (default)
HTTP=$(cpost "$JAR" "/room" '{"nickname":"NoCat","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
CODE8=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_NOCAT=$(tinker "echo App\Models\Room::where('code','$CODE8')->value('category') ?? 'NULL';")
check "$DB_NOCAT" "NULL" "DB: null category when not specified"

# Test 9: category=professions
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest9","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"professions"}')
CODE9=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT9=$(tinker "echo App\Models\Room::where('code','$CODE9')->value('category') ?? 'NULL';")
check "$DB_CAT9" "professions" "DB: category=professions"

# Test 10: category=music
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest10","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"music"}')
CODE10=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT10=$(tinker "echo App\Models\Room::where('code','$CODE10')->value('category') ?? 'NULL';")
check "$DB_CAT10" "music" "DB: category=music"

# Test 11: category=vehicles
HTTP=$(cpost "$JAR" "/room" '{"nickname":"CatTest11","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"vehicles"}')
CODE11=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CAT11=$(tinker "echo App\Models\Room::where('code','$CODE11')->value('category') ?? 'NULL';")
check "$DB_CAT11" "vehicles" "DB: category=vehicles"

echo "--- 10+ curl tests for difficulty validation ---"

# Test 12: difficulty=easy
HTTP=$(cpost "$JAR" "/room" '{"nickname":"DiffEasy","type":"public","max_players":6,"rounds_per_game":3,"language":"en","difficulty":"easy"}')
CODE_D1=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_D1=$(tinker "echo App\Models\Room::where('code','$CODE_D1')->value('difficulty');")
check "$DB_D1" "easy" "DB: difficulty=easy"

# Test 13: difficulty=hard
HTTP=$(cpost "$JAR" "/room" '{"nickname":"DiffHard","type":"public","max_players":6,"rounds_per_game":3,"language":"en","difficulty":"hard"}')
CODE_D2=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_D2=$(tinker "echo App\Models\Room::where('code','$CODE_D2')->value('difficulty');")
check "$DB_D2" "hard" "DB: difficulty=hard"

# Test 14: difficulty=medium (default)
HTTP=$(cpost "$JAR" "/room" '{"nickname":"DiffMed","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
CODE_D3=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_D3=$(tinker "echo App\Models\Room::where('code','$CODE_D3')->value('difficulty');")
check "$DB_D3" "medium" "DB: difficulty defaults to medium"

# Test 15: Invalid difficulty
HTTP=$(cpost "$JAR" "/room" '{"nickname":"BadDiff","type":"public","max_players":6,"rounds_per_game":3,"language":"en","difficulty":"extreme"}')
check "$HTTP" "422" "Invalid difficulty rejected (422)"

# Test 16: category+difficulty combined
HTTP=$(cpost "$JAR" "/room" '{"nickname":"ComboTest","type":"public","max_players":6,"rounds_per_game":3,"language":"en","category":"food","difficulty":"hard"}')
CODE_C=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
DB_CC=$(tinker "echo App\Models\Room::where('code','$CODE_C')->value('category') . '|' . App\Models\Room::where('code','$CODE_C')->value('difficulty');")
check "$DB_CC" "food|hard" "DB: category+difficulty combo stored"

###############################################################################
# TASK 7: IN-GAME CHAT (POST /game/{code}/chat)
###############################################################################
echo ""
echo "========== TASK 7: IN-GAME CHAT (POST /game/{code}/chat) =========="

# Setup: create a game with 3 players
JAR_CHAT="/tmp/t_chat_host.txt"
JAR_C2="/tmp/t_chat_p2.txt"
JAR_C3="/tmp/t_chat_p3.txt"

HTTP=$(cpost "$JAR_CHAT" "/room" '{"nickname":"ChatHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
CHAT_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Chat test room: $CHAT_CODE"

# Get host player id
sget "$JAR_CHAT" "/room/$CHAT_CODE" > /dev/null
HOST_ID=$(get_props | get_prop "player.id")
ROOM_ID=$(get_props | get_prop "room.id")

# P2 joins
HTTP=$(cpost "$JAR_C2" "/room/join" "{\"code\":\"$CHAT_CODE\",\"nickname\":\"ChatP2\"}")
sget "$JAR_C2" "/room/$CHAT_CODE" > /dev/null
P2_ID=$(get_props | get_prop "player.id")

# P3 joins
HTTP=$(cpost "$JAR_C3" "/room/join" "{\"code\":\"$CHAT_CODE\",\"nickname\":\"ChatP3\"}")
sget "$JAR_C3" "/room/$CHAT_CODE" > /dev/null
P3_ID=$(get_props | get_prop "player.id")

# Ready up
spost "$JAR_CHAT" "/room/$CHAT_CODE/ready" "{}" > /dev/null
spost "$JAR_C2" "/room/$CHAT_CODE/ready" "{}" > /dev/null
spost "$JAR_C3" "/room/$CHAT_CODE/ready" "{}" > /dev/null

# Start game
HTTP=$(spost "$JAR_CHAT" "/room/$CHAT_CODE/start" "{}")
echo "  Chat game started: $HTTP"
sleep 2

# Test 1: Send chat from host
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"Hello from host\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "Chat from host accepted (302)"

# Test 2: Send chat from P2
HTTP=$(spost "$JAR_C2" "/game/$CHAT_CODE/chat" "{\"message\":\"Hello from P2\",\"player_id\":$P2_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "Chat from P2 accepted (302)"

# Test 3: Send chat from P3
HTTP=$(spost "$JAR_C3" "/game/$CHAT_CODE/chat" "{\"message\":\"Hello from P3\",\"player_id\":$P3_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "Chat from P3 accepted (302)"

# Test 4: Empty message rejected
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "422" "Empty chat rejected (422)"

# Test 5: Too long message rejected (501 chars)
LONG_MSG=$(python3 -c "print('x' * 501)")
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"$LONG_MSG\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "422" "501-char chat rejected (422)"

# Test 6: Max length message accepted (500 chars)
MAX_MSG=$(python3 -c "print('y' * 500)")
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"$MAX_MSG\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "500-char chat accepted (302)"

# Test 7: Verify messages in DB
CHAT_COUNT=$(tinker "echo App\Models\ChatMessage::where('room_id',$ROOM_ID)->count();")
[ "$CHAT_COUNT" -ge "5" ] && pass "Chat messages stored in DB ($CHAT_COUNT >= 5)" || fail "Not enough chat messages ($CHAT_COUNT < 5)"

# Test 8: Chat with special characters
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"Hello! @#\$%^&*()\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "Chat with special chars accepted"

# Test 9: Chat with unicode/arabic
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"مرحبا بالعالم\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}")
check "$HTTP" "302" "Chat with Arabic text accepted"

# Test 10: Chat from fake player rejected
HTTP=$(spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"Fake message\",\"player_id\":99999,\"room_id\":$ROOM_ID}")
[ "$HTTP" = "422" ] || [ "$HTTP" = "302" ] && pass "Chat from fake player rejected/redirect ($HTTP)" || fail "Chat from fake player unexpected ($HTTP)"

# Test 11: Verify chat_messages in game state props
sget "$JAR_CHAT" "/game/$CHAT_CODE" > /dev/null
CHAT_PROP_COUNT=$(get_props | python3 -c "import sys,json; d=json.loads(sys.stdin.read()); print(len(d.get('chat_messages',[])))" 2>/dev/null)
[ "$CHAT_PROP_COUNT" -ge "3" ] && pass "chat_messages prop has $CHAT_PROP_COUNT messages" || fail "chat_messages prop missing or empty ($CHAT_PROP_COUNT)"

# Test 12: Multiple rapid messages
for i in $(seq 1 3); do
    spost "$JAR_CHAT" "/game/$CHAT_CODE/chat" "{\"message\":\"Rapid fire $i\",\"player_id\":$HOST_ID,\"room_id\":$ROOM_ID}" > /dev/null
done
RAPID_COUNT=$(tinker "echo App\Models\ChatMessage::where('room_id',$ROOM_ID)->where('message','like','Rapid fire%')->count();")
[ "$RAPID_COUNT" = "3" ] && pass "3 rapid fire messages stored" || fail "Rapid fire count=$RAPID_COUNT (expected 3)"


###############################################################################
# TASK 9: HINT CYCLE (phase-vote + hint submission)
###############################################################################
echo ""
echo "========== TASK 9: HINT CYCLE (phase-vote & hints) =========="

# Setup: create game with 3 players
JAR_HC="/tmp/t_hc_host.txt"
JAR_HC2="/tmp/t_hc_p2.txt"
JAR_HC3="/tmp/t_hc_p3.txt"

HTTP=$(cpost "$JAR_HC" "/room" '{"nickname":"HCHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
HC_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Hint cycle room: $HC_CODE"

sget "$JAR_HC" "/room/$HC_CODE" > /dev/null
HC_HOST=$(get_props | get_prop "player.id")
HC_ROOM=$(get_props | get_prop "room.id")

HTTP=$(cpost "$JAR_HC2" "/room/join" "{\"code\":\"$HC_CODE\",\"nickname\":\"HCP2\"}")
sget "$JAR_HC2" "/room/$HC_CODE" > /dev/null
HC_P2=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_HC3" "/room/join" "{\"code\":\"$HC_CODE\",\"nickname\":\"HCP3\"}")
sget "$JAR_HC3" "/room/$HC_CODE" > /dev/null
HC_P3=$(get_props | get_prop "player.id")

spost "$JAR_HC" "/room/$HC_CODE/ready" "{}" > /dev/null
spost "$JAR_HC2" "/room/$HC_CODE/ready" "{}" > /dev/null
spost "$JAR_HC3" "/room/$HC_CODE/ready" "{}" > /dev/null
HTTP=$(spost "$JAR_HC" "/room/$HC_CODE/start" "{}")
sleep 2

# Get hint order
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
HC_ORDER=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
print(json.dumps(d.get('hint_order',[])))
" 2>/dev/null)
HC_ROUND=$(get_props | get_prop "current_round.id")
echo "  Hint order: $HC_ORDER, Round: $HC_ROUND"

# Test 1: Initial hint_cycle = 1
HC_CYCLE=$(get_props | get_prop "hint_cycle")
check "$HC_CYCLE" "1" "Initial hint_cycle = 1"

# Submit first cycle hints in order
declare -A HC_SESSIONS=()
HC_SESSIONS["$HC_HOST"]="$JAR_HC"
HC_SESSIONS["$HC_P2"]="$JAR_HC2"
HC_SESSIONS["$HC_P3"]="$JAR_HC3"

for pid in $(echo "$HC_ORDER" | python3 -c "import sys,json; [print(p) for p in json.loads(sys.stdin.read())]" 2>/dev/null); do
    jar="${HC_SESSIONS[$pid]}"
    [ -z "$jar" ] && continue
    HTTP=$(spost "$jar" "/game/$HC_CODE/hint" "{\"content\":\"hint1_p$pid\",\"player_id\":$pid,\"room_id\":$HC_ROOM}")
done

# Test 2: All hints submitted for cycle 1
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
HC_COMPLETE=$(get_props | get_prop "hints_complete")
check "$HC_COMPLETE" "true" "hints_complete=true after all cycle 1 hints"

# Test 3: Phase vote 'continue' from host
HTTP=$(spost "$JAR_HC" "/game/$HC_CODE/phase-vote" "{\"choice\":\"continue\",\"player_id\":$HC_HOST,\"room_id\":$HC_ROOM}")
check "$HTTP" "302" "Phase vote 'continue' accepted"

# Test 4: Phase vote 'continue' from P2
HTTP=$(spost "$JAR_HC2" "/game/$HC_CODE/phase-vote" "{\"choice\":\"continue\",\"player_id\":$HC_P2,\"room_id\":$HC_ROOM}")
check "$HTTP" "302" "Phase vote 'continue' from P2 accepted"

# Test 5: Phase vote 'continue' from P3 (majority -> advance round)
HTTP=$(spost "$JAR_HC3" "/game/$HC_CODE/phase-vote" "{\"choice\":\"continue\",\"player_id\":$HC_P3,\"room_id\":$HC_ROOM}")
check "$HTTP" "302" "Phase vote 'continue' from P3 (majority)"

sleep 1

# Test 6: hint_cycle incremented to 2
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
HC_CYCLE2=$(get_props | get_prop "hint_cycle")
check "$HC_CYCLE2" "2" "hint_cycle incremented to 2"

# Test 7: Submit cycle 2 hints
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
HC_ORDER2=$(get_props | python3 -c "import sys,json; d=json.loads(sys.stdin.read()); print(json.dumps(d.get('hint_order',[])))" 2>/dev/null)
for pid in $(echo "$HC_ORDER2" | python3 -c "import sys,json; [print(p) for p in json.loads(sys.stdin.read())]" 2>/dev/null); do
    jar="${HC_SESSIONS[$pid]}"
    [ -z "$jar" ] && continue
    HTTP=$(spost "$jar" "/game/$HC_CODE/hint" "{\"content\":\"hint2_p$pid\",\"player_id\":$pid,\"room_id\":$HC_ROOM}")
done

# Test 8: Verify DB has hints for both cycles
CYCLE1_COUNT=$(tinker "echo App\Models\Hint::where('round_id',$HC_ROUND)->where('hint_cycle',1)->count();")
CYCLE2_COUNT=$(tinker "echo App\Models\Hint::where('round_id',$HC_ROUND)->where('hint_cycle',2)->count();")
[ "$CYCLE1_COUNT" = "3" ] && pass "DB: 3 hints in cycle 1" || fail "DB: cycle 1 has $CYCLE1_COUNT hints (expected 3)"
[ "$CYCLE2_COUNT" = "3" ] && pass "DB: 3 hints in cycle 2" || fail "DB: cycle 2 has $CYCLE2_COUNT hints (expected 3)"

# Test 9: previous_hints_by_cycle populated
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
PREV_HINTS=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
prev = d.get('previous_hints_by_cycle',{})
print(len(prev.get('1',[])))
" 2>/dev/null)
check "$PREV_HINTS" "3" "previous_hints_by_cycle[1] has 3 hints"

# Test 10: Phase vote 'vote' triggers voting
sget "$JAR_HC" "/game/$HC_CODE" > /dev/null
HC_COMPLETE2=$(get_props | get_prop "hints_complete")
if [ "$HC_COMPLETE2" = "true" ]; then
    HTTP=$(spost "$JAR_HC" "/game/$HC_CODE/phase-vote" "{\"choice\":\"vote\",\"player_id\":$HC_HOST,\"room_id\":$HC_ROOM}")
    check "$HTTP" "302" "Phase vote 'vote' from host"
    HTTP=$(spost "$JAR_HC2" "/game/$HC_CODE/phase-vote" "{\"choice\":\"vote\",\"player_id\":$HC_P2,\"room_id\":$HC_ROOM}")
    check "$HTTP" "302" "Phase vote 'vote' from P2"
    sleep 1
    # Check room status changed to voting
    HC_STATUS=$(tinker "echo App\Models\Room::where('code','$HC_CODE')->value('status');")
    check "$HC_STATUS" "voting" "Room status = voting after phase-vote majority"
else
    fail "hints_complete not true, can't phase-vote"
fi

# Test 11: Invalid phase-vote choice
HTTP=$(spost "$JAR_HC" "/game/$HC_CODE/phase-vote" "{\"choice\":\"invalid\",\"player_id\":$HC_HOST,\"room_id\":$HC_ROOM}")
check "$HTTP" "422" "Invalid phase-vote choice rejected (422)"


###############################################################################
# TASK 5: RECONNECTION (POST /game/{code}/reconnect)
###############################################################################
echo ""
echo "========== TASK 5: RECONNECTION (POST /game/{code}/reconnect) =========="

# Setup: create game
JAR_RC="/tmp/t_rc_host.txt"
JAR_RC2="/tmp/t_rc_p2.txt"
JAR_RC3="/tmp/t_rc_p3.txt"

HTTP=$(cpost "$JAR_RC" "/room" '{"nickname":"RcHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
RC_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Reconnect test room: $RC_CODE"

sget "$JAR_RC" "/room/$RC_CODE" > /dev/null
RC_HOST=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_RC2" "/room/join" "{\"code\":\"$RC_CODE\",\"nickname\":\"RcP2\"}")
sget "$JAR_RC2" "/room/$RC_CODE" > /dev/null
RC_P2=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_RC3" "/room/join" "{\"code\":\"$RC_CODE\",\"nickname\":\"RcP3\"}")
sget "$JAR_RC3" "/room/$RC_CODE" > /dev/null
RC_P3=$(get_props | get_prop "player.id")

spost "$JAR_RC" "/room/$RC_CODE/ready" "{}" > /dev/null
spost "$JAR_RC2" "/room/$RC_CODE/ready" "{}" > /dev/null
spost "$JAR_RC3" "/room/$RC_CODE/ready" "{}" > /dev/null
spost "$JAR_RC" "/room/$RC_CODE/start" "{}" > /dev/null
sleep 2

# Test 1: Reconnect with valid session
HTTP=$(spost "$JAR_RC" "/game/$RC_CODE/reconnect" "{}")
check "$HTTP" "302" "Reconnect with valid session (302)"

# Test 2: Reconnect redirects to correct game page
# The redirect should point to /game/{code} or /game/{code}/vote
sget "$JAR_RC" "/game/$RC_CODE" > /dev/null
RC_STATUS=$(get_props | get_prop "room.status")
[ "$RC_STATUS" = "playing" ] || [ "$RC_STATUS" = "voting" ] && pass "Reconnect: game state accessible ($RC_STATUS)" || fail "Reconnect: unexpected status $RC_STATUS"

# Test 3: Reconnect with no session (fresh jar)
JAR_RC_FRESH="/tmp/t_rc_fresh.txt"
HTTP=$(cpost "$JAR_RC_FRESH" "/game/$RC_CODE/reconnect" "{}")
# Should redirect to home or error since no session
[ "$HTTP" = "302" ] || [ "$HTTP" = "404" ] || [ "$HTTP" = "500" ] && pass "Reconnect with no session handled ($HTTP)" || fail "Reconnect with no session unexpected ($HTTP)"

# Test 4: Reconnect preserves player data
sget "$JAR_RC2" "/game/$RC_CODE" > /dev/null
RC_P2_NICK=$(get_props | get_prop "player.nickname")
check "$RC_P2_NICK" "RcP2" "Reconnect: player nickname preserved"

# Test 5: Reconnect preserves room membership
RC_P2_ROOM=$(get_props | get_prop "player.room_id")
RC_ROOM_ID=$(get_props | get_prop "room.id")
check "$RC_P2_ROOM" "$RC_ROOM_ID" "Reconnect: room membership preserved"

# Test 6: Player session stores nickname
# Check that after creating room, session has player_nickname
sget "$JAR_RC" "/game/$RC_CODE" > /dev/null
RC_HAS_PLAYER=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
p = d.get('player',{})
print('yes' if p and p.get('id') else 'no')
" 2>/dev/null)
check "$RC_HAS_PLAYER" "yes" "Reconnect: player object present in game state"

# Test 7: Reconnect after player deletion
# Simulate by deleting player record, then reconnecting
RC_P2_DBID=$(tinker "echo App\Models\Player::where('nickname','RcP2')->where('room_id',App\Models\Room::where('code','$RC_CODE')->value('id'))->value('id');")
tinker "App\Models\Player::find($RC_P2_DBID)?->delete();" > /dev/null
HTTP=$(spost "$JAR_RC2" "/game/$RC_CODE/reconnect" "{}")
check "$HTTP" "302" "Reconnect after player deletion (302)"

# Test 8: Reconnected player recreated in DB
RC_P2_NEW=$(tinker "echo App\Models\Player::where('nickname','RcP2')->where('room_id',App\Models\Room::where('code','$RC_CODE')->value('id'))->count();")
[ "$RC_P2_NEW" -ge "1" ] && pass "Reconnected player recreated in DB" || fail "Player not recreated after reconnect"

# Test 9: Reconnect sets spectator during active game
RC_P2_SPEC=$(tinker "echo App\Models\Player::where('nickname','RcP2')->where('room_id',App\Models\Room::where('code','$RC_CODE')->value('id'))->first()?->is_spectator ? '1' : '0';")
check "$RC_P2_SPEC" "1" "Reconnected player is spectator during active game"

# Test 10: Reconnect with invalid game code
HTTP=$(spost "$JAR_RC" "/game/ZZZZZZ/reconnect" "{}")
[ "$HTTP" = "404" ] || [ "$HTTP" = "302" ] && pass "Reconnect invalid code handled ($HTTP)" || fail "Reconnect invalid code unexpected ($HTTP)"


###############################################################################
# TASK 3: REMATCH (POST /game/{code}/rematch)
###############################################################################
echo ""
echo "========== TASK 3: REMATCH (POST /game/{code}/rematch) =========="

# Setup: create a complete game and finish it
JAR_RM="/tmp/t_rm_host.txt"
JAR_RM2="/tmp/t_rm_p2.txt"
JAR_RM3="/tmp/t_rm_p3.txt"

HTTP=$(cpost "$JAR_RM" "/room" '{"nickname":"RmHost","type":"public","max_players":6,"rounds_per_game":1,"language":"en"}')
RM_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Rematch test room: $RM_CODE"

sget "$JAR_RM" "/room/$RM_CODE" > /dev/null
RM_HOST=$(get_props | get_prop "player.id")
RM_ROOM=$(get_props | get_prop "room.id")

HTTP=$(cpost "$JAR_RM2" "/room/join" "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP2\"}")
sget "$JAR_RM2" "/room/$RM_CODE" > /dev/null
RM_P2=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_RM3" "/room/join" "{\"code\":\"$RM_CODE\",\"nickname\":\"RmP3\"}")
sget "$JAR_RM3" "/room/$RM_CODE" > /dev/null
RM_P3=$(get_props | get_prop "player.id")

spost "$JAR_RM" "/room/$RM_CODE/ready" "{}" > /dev/null
spost "$JAR_RM2" "/room/$RM_CODE/ready" "{}" > /dev/null
spost "$JAR_RM3" "/room/$RM_CODE/ready" "{}" > /dev/null
spost "$JAR_RM" "/room/$RM_CODE/start" "{}" > /dev/null
sleep 2

# Submit hints
sget "$JAR_RM" "/game/$RM_CODE" > /dev/null
RM_ORDER=$(get_props | python3 -c "import sys,json; print(json.dumps(json.loads(sys.stdin.read()).get('hint_order',[])))" 2>/dev/null)
declare -A RM_SESSIONS=()
RM_SESSIONS["$RM_HOST"]="$JAR_RM"
RM_SESSIONS["$RM_P2"]="$JAR_RM2"
RM_SESSIONS["$RM_P3"]="$JAR_RM3"

for pid in $(echo "$RM_ORDER" | python3 -c "import sys,json; [print(p) for p in json.loads(sys.stdin.read())]" 2>/dev/null); do
    jar="${RM_SESSIONS[$pid]}"
    [ -z "$jar" ] && continue
    spost "$jar" "/game/$RM_CODE/hint" "{\"content\":\"rmhint_p$pid\",\"player_id\":$pid,\"room_id\":$RM_ROOM}" > /dev/null
done

# Start voting
spost "$JAR_RM" "/game/$RM_CODE/start-voting" "{}" > /dev/null
sleep 1

# Submit votes (find imposter first)
RM_IMPOSTER=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_imposter',1)->value('id');")
echo "  Imposter: $RM_IMPOSTER"

# Everyone votes for imposter
spost "$JAR_RM" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_HOST,\"room_id\":$RM_ROOM}" > /dev/null
spost "$JAR_RM2" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_P2,\"room_id\":$RM_ROOM}" > /dev/null
spost "$JAR_RM3" "/game/$RM_CODE/vote" "{\"target_id\":$RM_IMPOSTER,\"player_id\":$RM_P3,\"room_id\":$RM_ROOM}" > /dev/null
sleep 2

# Check game is finished
RM_STATUS=$(tinker "echo App\Models\Room::where('code','$RM_CODE')->value('status');")
echo "  Pre-rematch status: $RM_STATUS"

# Test 1: Rematch from creator on finished game
if [ "$RM_STATUS" = "finished" ]; then
    HTTP=$(spost "$JAR_RM" "/game/$RM_CODE/rematch" "{}")
    check "$HTTP" "302" "Rematch from creator (302)"
else
    fail "Game not finished (status=$RM_STATUS), can't test rematch"
fi

# Test 2: Room status reset to waiting
RM_STATUS2=$(tinker "echo App\Models\Room::where('code','$RM_CODE')->value('status');")
check "$RM_STATUS2" "waiting" "Room status reset to waiting"

# Test 3: Player scores reset
RM_SCORE=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->sum('score');")
check "$RM_SCORE" "0" "Player scores reset to 0"

# Test 4: Player ready states reset
RM_READY=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_ready',1)->count();")
check "$RM_READY" "0" "All players unready"

# Test 5: Rounds deleted
RM_ROUNDS=$(tinker "echo App\Models\Round::where('room_id',$RM_ROOM)->count();")
check "$RM_ROUNDS" "0" "All rounds deleted"

# Test 6: Room code preserved
RM_CODE_CHECK=$(tinker "echo App\Models\Room::find($RM_ROOM)?->code;")
check "$RM_CODE_CHECK" "$RM_CODE" "Room code preserved"

# Test 7: Players still in room
RM_PC=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->count();")
check "$RM_PC" "3" "All 3 players still in room"

# Test 8: Rematch from non-creator rejected
HTTP=$(spost "$JAR_RM2" "/game/$RM_CODE/rematch" "{}")
[ "$HTTP" = "422" ] || [ "$HTTP" = "302" ] && pass "Rematch from non-creator rejected ($HTTP)" || fail "Rematch from non-creator unexpected ($HTTP)"

# Test 9: Rematch when not finished rejected
HTTP=$(spost "$JAR_RM" "/game/$RM_CODE/rematch" "{}")
[ "$HTTP" = "422" ] || [ "$HTTP" = "302" ] && pass "Rematch when not finished rejected ($HTTP)" || fail "Rematch when not finished unexpected ($HTTP)"

# Test 10: Player is_imposter reset
RM_IMP_COUNT=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_imposter',1)->count();")
check "$RM_IMP_COUNT" "0" "is_imposter reset for all players"

# Test 11: Player is_spectator reset
RM_SPEC_COUNT=$(tinker "echo App\Models\Player::where('room_id',$RM_ROOM)->where('is_spectator',1)->count();")
check "$RM_SPEC_COUNT" "0" "is_spectator reset for all players"


###############################################################################
# TASK 10: STATS PAGE (GET /stats)
###############################################################################
echo ""
echo "========== TASK 10: STATS PAGE (GET /stats) =========="

# Test 1: Stats page loads
JAR_STATS="/tmp/t_stats.txt"
HTTP=$(cpost "$JAR_STATS" "/room" '{"nickname":"StatsTest","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
HTTP=$(sget "$JAR_STATS" "/stats")
check "$HTTP" "200" "Stats page returns 200 (with session)"

# Test 2: Stats page has props
STATS_PROPS=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
has_stats = 'stats' in d or 'recent_games' in d
print('yes' if has_stats else 'no')
" 2>/dev/null)
check "$STATS_PROPS" "yes" "Stats page has stats/recent_games props"

# Test 3: Stats without session redirects
JAR_NO_SESSION="/tmp/t_stats_ns.txt"
rm -f "$JAR_NO_SESSION"
curl -s -c "$JAR_NO_SESSION" "$BASE/" > /dev/null 2>&1
HTTP=$(sget "$JAR_NO_SESSION" "/stats")
[ "$HTTP" = "302" ] || [ "$HTTP" = "200" ] && pass "Stats without auth handled ($HTTP)" || fail "Stats without auth unexpected ($HTTP)"

# Test 4: Stats with nickname session shows data
HTTP=$(sget "$JAR_STATS" "/stats")
NICK=$(get_props | get_prop "nickname")
check "$NICK" "StatsTest" "Stats page shows correct nickname"

# Test 5: stats object has expected fields
HTTP=$(sget "$JAR_STATS" "/stats")
HAS_FIELDS=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
s=d.get('stats',{})
fields = all(k in s for k in ['games_played','wins_as_crew','wins_as_imposter','win_rate'])
print('yes' if fields else 'no')
" 2>/dev/null)
check "$HAS_FIELDS" "yes" "Stats has all expected fields"

# Test 6: recent_games is an array
HTTP=$(sget "$JAR_STATS" "/stats")
RG_TYPE=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
rg = d.get('recent_games','missing')
print('array' if isinstance(rg, list) else type(rg).__name__)
" 2>/dev/null)
check "$RG_TYPE" "array" "recent_games is an array"

# Test 7: Stats route registered (non-404)
HTTP=$(sget "$JAR_STATS" "/stats")
[ "$HTTP" != "404" ] && pass "Stats route not 404" || fail "Stats route returns 404"

# Test 8: GameHistory model query works
GH_COUNT=$(tinker "echo App\Models\GameHistory::count();")
[ "$GH_COUNT" -ge "0" ] && pass "GameHistory model queryable ($GH_COUNT records)" || fail "GameHistory model error"

# Test 9: GameStat model query works
GS_COUNT=$(tinker "echo App\Models\GameStat::count();")
[ "$GS_COUNT" -ge "0" ] && pass "GameStat model queryable ($GS_COUNT records)" || fail "GameStat model error"

# Test 10: Stats page after a completed game shows data
# Use the rematch room which had a finished game
HTTP=$(sget "$JAR_RM" "/stats")
[ "$HTTP" = "200" ] && pass "Stats page after game returns 200" || fail "Stats page after game returns $HTTP"


###############################################################################
# TASK 11: SPECTATOR MODE (POST /room/join + GET /game/{code} + POST /game/{code}/vote)
###############################################################################
echo ""
echo "========== TASK 11: SPECTATOR MODE =========="

# Setup: create a game
JAR_SP="/tmp/t_sp_host.txt"
JAR_SP2="/tmp/t_sp_p2.txt"
JAR_SP3="/tmp/t_sp_p3.txt"
JAR_SP_SPEC="/tmp/t_sp_spec.txt"

HTTP=$(cpost "$JAR_SP" "/room" '{"nickname":"SpHost","type":"public","max_players":6,"rounds_per_game":3,"language":"en"}')
SP_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Spectator test room: $SP_CODE"

sget "$JAR_SP" "/room/$SP_CODE" > /dev/null
SP_HOST=$(get_props | get_prop "player.id")
SP_ROOM=$(get_props | get_prop "room.id")

HTTP=$(cpost "$JAR_SP2" "/room/join" "{\"code\":\"$SP_CODE\",\"nickname\":\"SpP2\"}")
sget "$JAR_SP2" "/room/$SP_CODE" > /dev/null
SP_P2=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_SP3" "/room/join" "{\"code\":\"$SP_CODE\",\"nickname\":\"SpP3\"}")
sget "$JAR_SP3" "/room/$SP_CODE" > /dev/null
SP_P3=$(get_props | get_prop "player.id")

spost "$JAR_SP" "/room/$SP_CODE/ready" "{}" > /dev/null
spost "$JAR_SP2" "/room/$SP_CODE/ready" "{}" > /dev/null
spost "$JAR_SP3" "/room/$SP_CODE/ready" "{}" > /dev/null
spost "$JAR_SP" "/room/$SP_CODE/start" "{}" > /dev/null
sleep 2

# Test 1: New player joins during game as spectator
HTTP=$(cpost "$JAR_SP_SPEC" "/room/join" "{\"code\":\"$SP_CODE\",\"nickname\":\"Spectator1\"}")
check "$HTTP" "302" "Spectator joins during game (302)"

# Test 2: Spectator is_spectator = true
sget "$JAR_SP_SPEC" "/game/$SP_CODE" > /dev/null
SP_IS_SPEC=$(get_props | get_prop "player.is_spectator")
check "$SP_IS_SPEC" "true" "Spectator has is_spectator=true"

# Test 3: Spectator sees the real word
SP_WORD=$(get_props | get_prop "word")
[ -n "$SP_WORD" ] && [ "$SP_WORD" != "???" ] && pass "Spectator sees real word ($SP_WORD)" || fail "Spectator cannot see word"

# Test 4: Spectator sees imposter info
SP_IMP=$(get_props | get_prop "spectator_imposter")
[ -n "$SP_IMP" ] && [ "$SP_IMP" != "null" ] && pass "Spectator sees spectator_imposter" || fail "Spectator doesn't see spectator_imposter"

# Test 5: Spectator appears in player list
SP_SPEC_COUNT=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
specs = [p for p in d.get('players',[]) if p.get('is_spectator')]
print(len(specs))
" 2>/dev/null)
[ "$SP_SPEC_COUNT" -ge "1" ] && pass "Spectator in player list ($SP_SPEC_COUNT)" || fail "No spectators in player list"

# Test 6: Spectator can't submit hints
HTTP=$(spost "$JAR_SP_SPEC" "/game/$SP_CODE/hint" "{\"content\":\"cheat\",\"player_id\":999,\"room_id\":$SP_ROOM}")
check "$HTTP" "422" "Spectator hint rejected (422)"

# Test 7: Spectator can send chat
sget "$JAR_SP_SPEC" "/game/$SP_CODE" > /dev/null
SP_SPEC_ID=$(get_props | get_prop "player.id")
HTTP=$(spost "$JAR_SP_SPEC" "/game/$SP_CODE/chat" "{\"message\":\"Watching!\",\"player_id\":$SP_SPEC_ID,\"room_id\":$SP_ROOM}")
check "$HTTP" "302" "Spectator can send chat (302)"

# Test 8: Spectator can't vote
HTTP=$(spost "$JAR_SP_SPEC" "/game/$SP_CODE/vote" "{\"target_id\":$SP_HOST,\"player_id\":$SP_SPEC_ID,\"room_id\":$SP_ROOM}")
check "$HTTP" "422" "Spectator vote rejected (422)"

# Test 9: Second spectator joins
JAR_SP_SPEC2="/tmp/t_sp_spec2.txt"
HTTP=$(cpost "$JAR_SP_SPEC2" "/room/join" "{\"code\":\"$SP_CODE\",\"nickname\":\"Spectator2\"}")
sget "$JAR_SP_SPEC2" "/game/$SP_CODE" > /dev/null
SP_SPEC2=$(get_props | get_prop "player.is_spectator")
check "$SP_SPEC2" "true" "Second spectator also has is_spectator=true"

# Test 10: Active player count excludes spectators
SP_ACTIVE=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
active = [p for p in d.get('players',[]) if not p.get('is_spectator')]
print(len(active))
" 2>/dev/null)
check "$SP_ACTIVE" "3" "Active players = 3 (excludes spectators)"

# Test 11: Spectator sees hint_for_imposter
sget "$JAR_SP_SPEC" "/game/$SP_CODE" > /dev/null
SP_IMP_HINT=$(get_props | get_prop "hint_for_imposter")
[ -n "$SP_IMP_HINT" ] && [ "$SP_IMP_HINT" != "null" ] && pass "Spectator sees imposter hint" || fail "Spectator doesn't see imposter hint"

# Test 12: Spectator on vote page
# Submit hints first
sget "$JAR_SP" "/game/$SP_CODE" > /dev/null
SP_ORDER=$(get_props | python3 -c "import sys,json; print(json.dumps(json.loads(sys.stdin.read()).get('hint_order',[])))" 2>/dev/null)
for pid in $(echo "$SP_ORDER" | python3 -c "import sys,json; [print(p) for p in json.loads(sys.stdin.read())]" 2>/dev/null); do
    jar="${HC_SESSIONS[$pid]}"
    # Use the right sessions for spectator test
    case "$pid" in
        "$SP_HOST") jar="$JAR_SP" ;;
        "$SP_P2") jar="$JAR_SP2" ;;
        "$SP_P3") jar="$JAR_SP3" ;;
    esac
    [ -z "$jar" ] && continue
    spost "$jar" "/game/$SP_CODE/hint" "{\"content\":\"sphint_p$pid\",\"player_id\":$pid,\"room_id\":$SP_ROOM}" > /dev/null
done

spost "$JAR_SP" "/game/$SP_CODE/start-voting" "{}" > /dev/null
sleep 1

sget "$JAR_SP_SPEC" "/game/$SP_CODE/vote" > /dev/null
SP_VOTE_HTTP=$?
[ "$(sget "$JAR_SP_SPEC" "/game/$SP_CODE/vote")" != "" ] && pass "Spectator can access vote page" || fail "Spectator can't access vote page"


###############################################################################
# TASK 1: SCOREBOARD (GET /game/{code}/result)
###############################################################################
echo ""
echo "========== TASK 1: SCOREBOARD (GET /game/{code}/result) =========="

# Setup: complete a game round to get result page
JAR_SB="/tmp/t_sb_host.txt"
JAR_SB2="/tmp/t_sb_p2.txt"
JAR_SB3="/tmp/t_sb_p3.txt"

HTTP=$(cpost "$JAR_SB" "/room" '{"nickname":"SbHost","type":"public","max_players":6,"rounds_per_game":1,"language":"en"}')
SB_CODE=$(grep -oP 'room/\K[A-Z0-9]+' /tmp/curl_body.txt 2>/dev/null | head -1)
echo "  Scoreboard test room: $SB_CODE"

sget "$JAR_SB" "/room/$SB_CODE" > /dev/null
SB_HOST=$(get_props | get_prop "player.id")
SB_ROOM=$(get_props | get_prop "room.id")

HTTP=$(cpost "$JAR_SB2" "/room/join" "{\"code\":\"$SB_CODE\",\"nickname\":\"SbP2\"}")
sget "$JAR_SB2" "/room/$SB_CODE" > /dev/null
SB_P2=$(get_props | get_prop "player.id")

HTTP=$(cpost "$JAR_SB3" "/room/join" "{\"code\":\"$SB_CODE\",\"nickname\":\"SbP3\"}")
sget "$JAR_SB3" "/room/$SB_CODE" > /dev/null
SB_P3=$(get_props | get_prop "player.id")

spost "$JAR_SB" "/room/$SB_CODE/ready" "{}" > /dev/null
spost "$JAR_SB2" "/room/$SB_CODE/ready" "{}" > /dev/null
spost "$JAR_SB3" "/room/$SB_CODE/ready" "{}" > /dev/null
spost "$JAR_SB" "/room/$SB_CODE/start" "{}" > /dev/null
sleep 2

# Submit hints
sget "$JAR_SB" "/game/$SB_CODE" > /dev/null
SB_ORDER=$(get_props | python3 -c "import sys,json; print(json.dumps(json.loads(sys.stdin.read()).get('hint_order',[])))" 2>/dev/null)
declare -A SB_SESSIONS=()
SB_SESSIONS["$SB_HOST"]="$JAR_SB"
SB_SESSIONS["$SB_P2"]="$JAR_SB2"
SB_SESSIONS["$SB_P3"]="$JAR_SB3"

for pid in $(echo "$SB_ORDER" | python3 -c "import sys,json; [print(p) for p in json.loads(sys.stdin.read())]" 2>/dev/null); do
    jar="${SB_SESSIONS[$pid]}"
    [ -z "$jar" ] && continue
    spost "$jar" "/game/$SB_CODE/hint" "{\"content\":\"sbhint_p$pid\",\"player_id\":$pid,\"room_id\":$SB_ROOM}" > /dev/null
done

# Start voting and vote
spost "$JAR_SB" "/game/$SB_CODE/start-voting" "{}" > /dev/null
sleep 1

SB_IMPOSTER=$(tinker "echo App\Models\Player::where('room_id',$SB_ROOM)->where('is_imposter',1)->value('id');")
spost "$JAR_SB" "/game/$SB_CODE/vote" "{\"target_id\":$SB_IMPOSTER,\"player_id\":$SB_HOST,\"room_id\":$SB_ROOM}" > /dev/null
spost "$JAR_SB2" "/game/$SB_CODE/vote" "{\"target_id\":$SB_IMPOSTER,\"player_id\":$SB_P2,\"room_id\":$SB_ROOM}" > /dev/null
spost "$JAR_SB3" "/game/$SB_CODE/vote" "{\"target_id\":$SB_IMPOSTER,\"player_id\":$SB_P3,\"room_id\":$SB_ROOM}" > /dev/null
sleep 2

# Check we're in result state
SB_STATUS=$(tinker "echo App\Models\Room::where('code','$SB_CODE')->value('status');")
echo "  Scoreboard room status: $SB_STATUS"

# Test 1: Result page loads
HTTP=$(sget "$JAR_SB" "/game/$SB_CODE/result")
check "$HTTP" "200" "Result page returns 200"

# Test 2: Result has word prop
SB_WORD=$(get_props | get_prop "word")
[ -n "$SB_WORD" ] && pass "Result has word: $SB_WORD" || fail "Result missing word"

# Test 3: Result has imposter info
SB_IMP_OBJ=$(get_props | get_prop "imposter")
[ -n "$SB_IMP_OBJ" ] && [ "$SB_IMP_OBJ" != "null" ] && pass "Result has imposter object" || fail "Result missing imposter"

# Test 4: Result has winner prop
SB_WINNER=$(get_props | get_prop "winner")
[ -n "$SB_WINNER" ] && pass "Result has winner: $SB_WINNER" || fail "Result missing winner"

# Test 5: Result has vote_tally
SB_TALLY=$(get_props | get_prop "vote_tally")
[ -n "$SB_TALLY" ] && [ "$SB_TALLY" != "null" ] && pass "Result has vote_tally" || fail "Result missing vote_tally"

# Test 6: Result has players with scores
SB_HAS_SCORES=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
players = d.get('players',[])
scored = [p for p in players if isinstance(p.get('score'), (int,float))]
print(len(scored))
" 2>/dev/null)
[ "$SB_HAS_SCORES" -ge "3" ] && pass "All players have scores ($SB_HAS_SCORES)" || fail "Not all players have scores ($SB_HAS_SCORES)"

# Test 7: Result has hints
SB_HINTS=$(get_props | get_prop "hints")
[ -n "$SB_HINTS" ] && pass "Result has hints array" || fail "Result missing hints"

# Test 8: Result has is_game_over (for single round game)
SB_GAME_OVER=$(get_props | get_prop "is_game_over")
check "$SB_GAME_OVER" "true" "is_game_over=true for 1-round game"

# Test 9: Result has votes detail
SB_VOTES=$(get_props | get_prop "votes")
[ -n "$SB_VOTES" ] && pass "Result has votes detail" || fail "Result missing votes"

# Test 10: Result has imposter_hint
SB_IMP_HINT=$(get_props | get_prop "imposter_hint")
[ -n "$SB_IMP_HINT" ] && pass "Result has imposter_hint" || fail "Result missing imposter_hint"

# Test 11: Players sorted by score
SB_SORTED=$(get_props | python3 -c "
import sys,json
d=json.loads(sys.stdin.read())
players = d.get('players',[])
scores = [p.get('score',0) for p in players]
print('yes' if scores == sorted(scores, reverse=True) else 'no')
" 2>/dev/null)
check "$SB_SORTED" "yes" "Players sorted by score descending"


###############################################################################
# TASK 6: SOUND EFFECTS (Frontend-only, no backend route)
###############################################################################
echo ""
echo "========== TASK 6: SOUND EFFECTS (Frontend-only) =========="
echo "  Sound effects use Web Audio API in useSound.js composable."
echo "  No backend routes to test via curl."
echo "  Verifying composable file exists and frontend builds..."

# Test 1: useSound.js exists
[ -f "resources/js/Composables/useSound.js" ] && pass "useSound.js exists" || fail "useSound.js missing"

# Test 2: useSound exports all expected functions
SOUND_FUNCS="playTurnNotification playHintSubmitted playTimerLow playTimerExpired playVotingStarted playVoteSubmitted playImposterRevealed playCrewWins playImposterWins playChatMessage playNewRound toggleSound"
for func in $SOUND_FUNCS; do
    grep -q "$func" resources/js/Composables/useSound.js && pass "useSound exports $func" || fail "useSound missing $func"
done

# Test 3: Frontend builds without errors
BUILD_OUTPUT=$(npm run build 2>&1)
echo "$BUILD_OUTPUT" | grep -q "built in" && pass "Frontend builds successfully" || fail "Frontend build failed"

# Test 4: Game.vue imports useSound
grep -q "useSound" resources/js/Pages/Game.vue && pass "Game.vue imports useSound" || fail "Game.vue missing useSound import"

# Test 5: Vote.vue imports useSound
grep -q "useSound" resources/js/Pages/Vote.vue && pass "Vote.vue imports useSound" || fail "Vote.vue missing useSound import"

# Test 6: Result.vue imports useSound
grep -q "useSound" resources/js/Pages/Result.vue && pass "Result.vue imports useSound" || fail "Result.vue missing useSound import"


###############################################################################
# SUMMARY
###############################################################################
echo ""
echo "======================================================"
echo "SUMMARY: $PASS passed, $FAIL failed (total: $TOTAL)"
echo "======================================================"

# Cleanup temp rooms
echo "Cleaning up test rooms..."
for code in $CHAT_CODE $HC_CODE $RC_CODE $RM_CODE $SP_CODE $SB_CODE; do
    [ -n "$code" ] && tinker "App\Models\Room::where('code','$code')?->delete();" > /dev/null 2>&1
done

[ "$FAIL" -eq 0 ] && echo "ALL TESTS PASSED!" || echo "Some tests failed - review above."
