#!/bin/sh
# RNF-2 evidence: two librarians lend the LAST copy of a book at the same time over real HTTP.
# Expected: exactly one 201 and one 409 BOOK_NOT_AVAILABLE. Needs a running API and curl.
# Usage: API_URL=http://localhost:8000/api/v1 ADMIN_USERNAME=admin ADMIN_PASSWORD=... sh scripts/rnf2-concurrency.sh
set -e
API="${API_URL:-http://localhost:8000/api/v1}"
J='Content-Type: application/json'
token() { curl -s -X POST "$API/auth/login" -H "$J" -d "{\"username\":\"$ADMIN_USERNAME\",\"password\":\"$ADMIN_PASSWORD\"}" | sed 's/.*"access_token":"\([^"]*\)".*/\1/'; }
# Extracts the FIRST top-level "id" (responses start with {"id":"..."}).
field() { sed 's/^{"id":"\([^"]*\)".*/\1/'; }
T=$(token); A="Authorization: Bearer $T"
SUFFIX="$(date +%s)$(printf "%03d" $((RANDOM % 1000)))"
ISBN="9$(echo "$SUFFIX" | cut -c1-12)"
BOOK=$(curl -s -X POST "$API/books" -H "$J" -H "$A" -d "{\"title\":\"Concurrency $SUFFIX\",\"author\":\"Test\",\"isbn\":\"$ISBN\",\"category_id\":\"11111111-1111-4111-8111-111111111111\",\"total_copies\":1}" | field)
M1=$(curl -s -X POST "$API/members" -H "$J" -H "$A" -d "{\"full_name\":\"Race One\",\"document_number\":\"R1-$SUFFIX\"}" | field)
M2=$(curl -s -X POST "$API/members" -H "$J" -H "$A" -d "{\"full_name\":\"Race Two\",\"document_number\":\"R2-$SUFFIX\"}" | field)
for M in "$M1" "$M2"; do
  curl -s -o "/tmp/rnf2-$M.out" -w "%{http_code}\n" -X POST "$API/loans" -H "$J" -H "$A" \
    -d "{\"member_id\":\"$M\",\"items\":[{\"book_id\":\"$BOOK\"}]}" > "/tmp/rnf2-$M.code" &
done
wait
C1=$(cat "/tmp/rnf2-$M1.code"); C2=$(cat "/tmp/rnf2-$M2.code")
echo "member 1 -> $C1 ; member 2 -> $C2"
if { [ "$C1" = 201 ] && [ "$C2" = 409 ]; } || { [ "$C1" = 409 ] && [ "$C2" = 201 ]; }; then echo "RNF-2 OK: one winner, one 409"; else echo "RNF-2 FAILED"; exit 1; fi
