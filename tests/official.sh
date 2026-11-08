#!/usr/bin/env bash
# Official test run T-01..T-20. Usage: bash tests/official.sh
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 1
source "$ROOT/tests/helpers.sh"

[ -n "${MYSQL_PWD:-}" ] || { read -rsp "DB password: " MYSQL_PWD; echo; }
export MYSQL_PWD
q()     { mysql -N -u clf_user clf_db -e "$1"; }
dberr() { mysql -u clf_user clf_db -e "$1" 2>&1; }

read -rp "Admin email (used only for CLI actions): " AEMAIL
AEMAIL=${AEMAIL//\'/}
ADMIN=$(q "SELECT user_id FROM users WHERE university_email='$AEMAIL' AND role='admin'")
[ -n "$ADMIN" ] || { echo "No admin found with that email."; exit 1; }

PASS=0; FAILN=0
check() {   # check ID "description" expected actual
  if [ "$3" = "$4" ]; then echo "  PASS  $1  $2"; PASS=$((PASS+1))
  else echo "  FAIL  $1  $2   (expected '$3', got '$4')"; FAILN=$((FAILN+1)); fi
}
head_of() { echo "${1%%:*}"; }          # "REFUSED: message" -> "REFUSED"

RUN=$(date +%s)
YESTERDAY=$(date -d yesterday +%F)
HASH=$(php -r 'echo password_hash("TestPass123", PASSWORD_DEFAULT);')
q "DELETE FROM rate_limits;"

mk_user() { q "INSERT INTO users (full_name, university_email, password_hash, role, verification_status, account_status) VALUES ('OT $1','ot${RUN}_$1@pucit.edu.pk','$HASH','$2','$3','$4'); SELECT LAST_INSERT_ID();"; }
mk_item() { q "INSERT INTO items (reporter_id, report_type, title, category_id, location_id, description, incident_date, item_status, is_high_value) VALUES ($1,'$2','OT $RUN $3',1,1,'Official test listing',CURDATE(),'${4:-open}',${5:-0}); SELECT LAST_INSERT_ID();"; }
mk_claim() { q "INSERT INTO claims (item_id, claimant_id, ownership_details, claim_status) VALUES ($1,$2,'Official test ownership statement','${3:-pending}'); SELECT LAST_INSERT_ID();"; }

echo "== Setting up test users =="
P=$(mk_user poster student verified active);   C1=$(mk_user claim1 student verified active)
C2=$(mk_user claim2 student verified active);  C3=$(mk_user claim3 student verified active)
S=$(mk_user stranger student verified active); K1=$(mk_user cust1 custodian verified active)
K2=$(mk_user cust2 custodian verified active); U=$(mk_user unverified student pending active)
X=$(mk_user suspended student verified suspended); V=$(mk_user victim student verified active)
for id in $P $C1 $C2 $C3 $S $K1 $K2 $U $X $V; do
  [[ "$id" =~ ^[0-9]+$ ]] || { echo "Could not create test users. Is the schema loaded?"; exit 1; }
done
for n in poster claim1 claim2 claim3 stranger cust1 unverified suspended; do
  clf_login /tmp/ot_$n.jar "ot${RUN}_$n@pucit.edu.pk" TestPass123 > /dev/null
  [ "$(clf_code /tmp/ot_$n.jar $BASE/dashboard)" = "200" ] || { echo "Login failed for $n"; exit 1; }
done
echo "Users ready (run id $RUN)."

# tok JAR -> sets CS (csrf) and FT (single-use form token)
tok() {
  CS=$(clf_field "$1" $BASE/profile csrf_token)
  FT=$(clf_field "$1" $BASE/items/new form_token)
  [ -n "$FT" ] || FT=00000000000000000000000000000000
}
claim_post() { tok "$1"; curl -s -b "$1" -c "$1" -o /dev/null -w '%{http_code}' -d "csrf_token=$CS" -d "form_token=$FT" --data-urlencode "ownership_details=Official test statement with details" "$BASE/items/$2/claim"; }
msg_post()   { tok "$1"; curl -s -b "$1" -c "$1" -o /dev/null -w '%{http_code}' -d "csrf_token=$CS" -d "form_token=$FT" --data-urlencode "message_text=official test message" "$BASE/messages/$2/$3"; }

echo; echo "== T-01 Register with a non-university email =="
rm -f /tmp/ot_g.jar
G=$(curl -s -c /tmp/ot_g.jar -b /tmp/ot_g.jar $BASE/register | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
reg() { curl -s -b /tmp/ot_g.jar -c /tmp/ot_g.jar -o /dev/null -w '%{http_code}' -d "csrf_token=$G" --data-urlencode "full_name=Reg Test" --data-urlencode "email=$1" --data-urlencode "password=RegTest123" --data-urlencode "password_confirm=RegTest123" $BASE/register; }
check T-01 "gmail address rejected"               422 "$(reg ot${RUN}@gmail.com)"
check T-01 "look-alike domain rejected"           422 "$(reg ot${RUN}@pucit.edu.pk.evil.com)"
check T-01 "no account row was created"           0   "$(q "SELECT COUNT(*) FROM users WHERE university_email LIKE 'ot${RUN}@%'")"

echo; echo "== T-02 Unverified user posts a listing =="
tok /tmp/ot_unverified.jar
check T-02 "form page redirects an unverified user" 302 "$(clf_code /tmp/ot_unverified.jar $BASE/items/new)"
check T-02 "direct POST is blocked" 302 "$(curl -s -b /tmp/ot_unverified.jar -o /dev/null -w '%{http_code}' -d "csrf_token=$CS" -d "form_token=$FT" -d report_type=lost --data-urlencode "title=OT $RUN unverified" -d category_id=1 -d location_id=1 -d "incident_date=$YESTERDAY" -d "description=Unverified direct post attempt" $BASE/items/new)"
check T-02 "no listing row was created" 0 "$(q "SELECT COUNT(*) FROM items WHERE reporter_id=$U")"

echo; echo "== T-03 Suspended user claims by direct POST =="
F3=$(mk_item $P found t03)
check T-03 "direct claim POST is refused (redirect)" 302 "$(claim_post /tmp/ot_suspended.jar $F3)"
check T-03 "no claim row was created" 0 "$(q "SELECT COUNT(*) FROM claims WHERE claimant_id=$X")"

echo; echo "== T-04 Poster edits a claim_pending listing =="
F4=$(mk_item $P found t04 claim_pending)
check T-04 "edit form is refused" 302 "$(clf_code /tmp/ot_poster.jar $BASE/items/$F4/edit)"
tok /tmp/ot_poster.jar
curl -s -b /tmp/ot_poster.jar -o /dev/null -d "csrf_token=$CS" -d "form_token=$FT" --data-urlencode "title=CHANGED" -d category_id=1 -d location_id=1 -d "incident_date=$YESTERDAY" -d "description=Changed description text here" $BASE/items/$F4/edit
check T-04 "title was not changed" "OT $RUN t04" "$(q "SELECT title FROM items WHERE item_id=$F4")"

echo; echo "== T-05 Claim a lost listing, or your own found item =="
L5=$(mk_item $P lost t05lost);  F5=$(mk_item $P found t05own)
claim_post /tmp/ot_claim1.jar $L5 > /dev/null
claim_post /tmp/ot_poster.jar $F5 > /dev/null
check T-05 "no claim on the lost listing" 0 "$(q "SELECT COUNT(*) FROM claims WHERE item_id=$L5")"
check T-05 "no claim on your own found item" 0 "$(q "SELECT COUNT(*) FROM claims WHERE item_id=$F5")"

echo; echo "== T-06 Same user claims the same item twice =="
F6=$(mk_item $P found t06)
check T-06 "first claim accepted" 302 "$(claim_post /tmp/ot_claim1.jar $F6)"
claim_post /tmp/ot_claim1.jar $F6 > /dev/null
check T-06 "second claim created no row" 1 "$(q "SELECT COUNT(*) FROM claims WHERE item_id=$F6 AND claimant_id=$C1")"
check T-06 "database UNIQUE key also rejects it (error 1062)" 1 "$(dberr "INSERT INTO claims (item_id, claimant_id, ownership_details) VALUES ($F6,$C1,'dup')" | grep -c 1062)"

echo; echo "== T-07 Evidence opened by the wrong people =="
F7=$(mk_item $P found t07 claim_pending)
CL1=$(mk_claim $F7 $C1); mk_claim $F7 $C2 > /dev/null
PFILE="ot_${RUN}.jpg"
php -r '$i=imagecreatetruecolor(20,20); imagejpeg($i,$argv[1]);' "$ROOT/storage/evidence/$PFILE"
chmod 660 "$ROOT/storage/evidence/$PFILE"
EVD=$(q "INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description) VALUES ($F7,NULL,'item_detail',$P,'Serial OTSECRET$RUN'); SELECT LAST_INSERT_ID();")
EVP=$(q "INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description, file_reference) VALUES ($F7,$CL1,'claim_proof',$C1,'Receipt','$PFILE'); SELECT LAST_INSERT_ID();")
audit_n() { q "SELECT COUNT(*) FROM audit_logs WHERE action='evidence_viewed' AND target_id IN ($EVD,$EVP)"; }
A0=$(audit_n); bad=0
for combo in "claim2 $EVP" "claim2 $EVP/file" "claim2 $EVD" "claim1 $EVD" "poster $EVP" "poster $EVP/file" "stranger $EVP" "stranger $EVD"; do
  set -- $combo
  [ "$(clf_code /tmp/ot_$1.jar $BASE/evidence/$2)" = "404" ] || { echo "     not 404: $1 -> /evidence/$2"; bad=$((bad+1)); }
done
check T-07 "all 8 forbidden evidence requests returned 404" 0 "$bad"
check T-07 "forbidden requests wrote no audit rows" "$A0" "$(audit_n)"
check T-07 "guest is sent to login" 302 "$(clf_code /dev/null $BASE/evidence/$EVP)"
check T-07 "direct /storage URL is not served" 404 "$(curl -s -o /dev/null -w '%{http_code}' $BASE/storage/evidence/$PFILE)"
check T-07 "evidence file is not under the web root" 0 "$(find "$ROOT/public" -name "$PFILE" | wc -l)"
check T-07 "claimant can open their own proof" 200 "$(clf_code /tmp/ot_claim1.jar $BASE/evidence/$EVP)"
check T-07 "finder can open their own hidden detail" 200 "$(clf_code /tmp/ot_poster.jar $BASE/evidence/$EVD)"

echo; echo "== T-08 Custodian opens a claim proof =="
A1=$(audit_n)
check T-08 "custodian sees the proof page" 200 "$(clf_code /tmp/ot_cust1.jar $BASE/evidence/$EVP)"
check T-08 "custodian gets the file" "200 image/jpeg" "$(curl -s -b /tmp/ot_cust1.jar -o /dev/null -w '%{http_code} %{content_type}' $BASE/evidence/$EVP/file)"
check T-08 "two evidence_viewed rows were logged" $((A1+2)) "$(audit_n)"
check T-08 "rows name the custodian as actor" 2 "$(q "SELECT COUNT(*) FROM audit_logs WHERE action='evidence_viewed' AND target_id=$EVP AND actor_id=$K1")"

echo; echo "== T-09 Reviewer is the claimant =="
F9=$(mk_item $P found t09); CL9=$(mk_claim $F9 $K1)
check T-09 "database CHECK rejects reviewed_by = claimant (3819)" 1 "$(dberr "UPDATE claims SET reviewed_by=claimant_id WHERE claim_id=$CL9" | grep -c 3819)"
check T-09 "approval is refused" REFUSED "$(head_of "$(php tests/approve_cli.php $CL9 $K1)")"
check T-09 "claim is still pending" pending "$(q "SELECT claim_status FROM claims WHERE claim_id=$CL9")"

echo; echo "== T-10 Two custodians approve different claims at once =="
F10=$(mk_item $P found t10 claim_pending)
A10=$(mk_claim $F10 $C1); B10=$(mk_claim $F10 $C2)
mysql -u clf_user clf_db -e "START TRANSACTION; SELECT item_id FROM items WHERE item_id=$F10 FOR UPDATE; SELECT SLEEP(4); COMMIT;" > /dev/null &
sleep 1
php tests/approve_cli.php $A10 $K1 > /tmp/ot_ra &
php tests/approve_cli.php $B10 $K2 > /tmp/ot_rb &
wait
check T-10 "exactly one APPROVED and one REFUSED" "APPROVED REFUSED " "$(cat /tmp/ot_ra /tmp/ot_rb | cut -d: -f1 | sort | tr '\n' ' ')"
check T-10 "one claim approved, one rejected" "approved,rejected" "$(q "SELECT GROUP_CONCAT(claim_status ORDER BY claim_status) FROM claims WHERE item_id=$F10")"
check T-10 "item is ready_for_handover" ready_for_handover "$(q "SELECT item_status FROM items WHERE item_id=$F10")"

echo; echo "== T-11 Approve A while B and C are active =="
F11=$(mk_item $P found t11 claim_pending)
A11=$(mk_claim $F11 $C1); mk_claim $F11 $C2 > /dev/null; mk_claim $F11 $C3 > /dev/null
check T-11 "approval succeeds" APPROVED "$(head_of "$(php tests/approve_cli.php $A11 $K1)")"
check T-11 "A approved, B and C rejected" "approved,rejected,rejected" "$(q "SELECT GROUP_CONCAT(claim_status ORDER BY claim_id) FROM claims WHERE item_id=$F11")"
check T-11 "item is ready_for_handover" ready_for_handover "$(q "SELECT item_status FROM items WHERE item_id=$F11")"
check T-11 "rejected claims say why" "another claim approved" "$(q "SELECT DISTINCT review_reason FROM claims WHERE item_id=$F11 AND claim_status='rejected'")"

echo; echo "== T-12 Complete a handover that must be refused =="
F12a=$(mk_item $P found t12a ready_for_handover); CL12a=$(mk_claim $F12a $C1 rejected)
check T-12 "claim that is not approved is refused" REFUSED "$(head_of "$(php tests/handover_cli.php complete $CL12a $K1 1 'ID seen')")"
F12=$(mk_item $P found t12b ready_for_handover 1); CL12=$(mk_claim $F12 $C1 approved)
q "UPDATE claims SET reviewed_by=$K2 WHERE claim_id=$CL12; INSERT INTO handovers (claim_id, custodian_id, scheduled_at) VALUES ($CL12,$K1,NOW() + INTERVAL 1 DAY);"
check T-12 "ID box not ticked is refused" REFUSED "$(head_of "$(php tests/handover_cli.php complete $CL12 $K1 0 'ID seen')")"
check T-12 "high-value item with empty notes is refused" REFUSED "$(head_of "$(php tests/handover_cli.php complete $CL12 $K1 1 '')")"
check T-12 "nothing changed after the refusals" "ready_for_handover scheduled" "$(q "SELECT CONCAT(i.item_status,' ',h.handover_status) FROM items i JOIN handovers h ON h.claim_id=$CL12 WHERE i.item_id=$F12")"
check T-12 "with notes it succeeds" OK "$(head_of "$(php tests/handover_cli.php complete $CL12 $K1 1 'Student card checked in person')")"
check T-12 "item is now recovered" recovered "$(q "SELECT item_status FROM items WHERE item_id=$F12")"

echo; echo "== T-13 Revoke an approval before handover =="
F13=$(mk_item $P found t13 ready_for_handover); CL13=$(mk_claim $F13 $C1 approved)
q "UPDATE claims SET reviewed_by=$K2 WHERE claim_id=$CL13; INSERT INTO handovers (claim_id, custodian_id, scheduled_at) VALUES ($CL13,$K1,NOW() + INTERVAL 1 DAY);"
check T-13 "revoke succeeds" OK "$(head_of "$(php tests/handover_cli.php revoke $CL13 $K1 'no-show')")"
check T-13 "claim rejected, item open, handover cancelled" "rejected open cancelled" "$(q "SELECT CONCAT(c.claim_status,' ',i.item_status,' ',h.handover_status) FROM claims c JOIN items i ON i.item_id=c.item_id JOIN handovers h ON h.claim_id=c.claim_id WHERE c.claim_id=$CL13")"
check T-13 "the action was logged" 1 "$(q "SELECT COUNT(*) FROM audit_logs WHERE action='claim_revoked' AND target_id=$CL13 AND actor_id=$K1")"

echo; echo "== T-14 Messaging rules =="
F14=$(mk_item $P found t14 claim_pending); mk_claim $F14 $C1 > /dev/null
msg_post /tmp/ot_stranger.jar $F14 $P > /dev/null
check T-14 "stranger cannot message the finder" 0 "$(q "SELECT COUNT(*) FROM messages WHERE item_id=$F14 AND sender_id=$S")"
msg_post /tmp/ot_claim1.jar $F14 $P > /dev/null
check T-14 "control: a claimant can (test is not vacuous)" 1 "$(q "SELECT COUNT(*) FROM messages WHERE item_id=$F14 AND sender_id=$C1")"
L14=$(mk_item $P lost t14rec recovered)
msg_post /tmp/ot_stranger.jar $L14 $P > /dev/null
check T-14 "nobody can message on a recovered item" 0 "$(q "SELECT COUNT(*) FROM messages WHERE item_id=$L14")"

echo; echo "== T-15 Six failed logins for an unknown email =="
rm -f /tmp/ot_lj; codes=""
for i in 1 2 3 4 5 6; do
  T=$(curl -s -c /tmp/ot_lj -b /tmp/ot_lj $BASE/login | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
  c=$(curl -s -c /tmp/ot_lj -b /tmp/ot_lj -o /dev/null -w '%{http_code}' -d "csrf_token=$T" -d "email=nobody${RUN}@pucit.edu.pk" -d "password=wrongpass1" $BASE/login)
  codes="${codes:+$codes }$c"
done
check T-15 "attempts 1-5 are 401, the sixth is 429" "401 401 401 401 401 429" "$codes"
check T-15 "that email has no user row" 0 "$(q "SELECT COUNT(*) FROM users WHERE university_email='nobody${RUN}@pucit.edu.pk'")"
q "DELETE FROM rate_limits;"

echo; echo "== T-16 Script renamed to .jpg =="
printf '<?php echo "pwned"; ?>' > /tmp/ot_evil.jpg
FILES_BEFORE=$(ls public/uploads/items | wc -l)
tok /tmp/ot_poster.jar
check T-16 "upload is rejected" 422 "$(curl -s -b /tmp/ot_poster.jar -o /dev/null -w '%{http_code}' -F "csrf_token=$CS" -F "form_token=$FT" -F report_type=lost -F "title=OT $RUN evil" -F category_id=1 -F location_id=1 -F "incident_date=$YESTERDAY" -F "description=Fake image upload attack" -F "images[]=@/tmp/ot_evil.jpg;type=image/jpeg;filename=evil.jpg" $BASE/items/new)"
check T-16 "no listing row was created" 0 "$(q "SELECT COUNT(*) FROM items WHERE title='OT $RUN evil'")"
check T-16 "no file was stored" "$FILES_BEFORE" "$(ls public/uploads/items | wc -l)"

echo; echo "== T-17 Double click on a new listing =="
tok /tmp/ot_poster.jar
for i in 1 2; do
  curl -s -b /tmp/ot_poster.jar -o /dev/null -w '%{http_code}\n' -d "csrf_token=$CS" -d "form_token=$FT" -d report_type=lost --data-urlencode "title=OT $RUN double" -d category_id=1 -d location_id=1 -d "incident_date=$YESTERDAY" -d "description=Double click protection test" $BASE/items/new > /tmp/ot_d$i &
done; wait
check T-17 "both requests answered with a redirect" "302 302" "$(cat /tmp/ot_d1 /tmp/ot_d2 | tr '\n' ' ' | sed 's/ $//')"
check T-17 "only one row was created" 1 "$(q "SELECT COUNT(*) FROM items WHERE title='OT $RUN double'")"

echo; echo "== T-18 Suspend the only active admin =="
NADM=$(q "SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='active' AND verification_status='verified'")
check T-18 "precondition: exactly one active admin" 1 "$NADM"
check T-18 "suspending the only admin is refused" REFUSED "$(head_of "$(php tests/admin_cli.php suspend $ADMIN $ADMIN 'official test' 7)")"
check T-18 "demoting the only admin is refused" REFUSED "$(head_of "$(php tests/admin_cli.php role $ADMIN $ADMIN custodian)")"
check T-18 "admin is still active" "admin active" "$(q "SELECT CONCAT(role,' ',account_status) FROM users WHERE user_id=$ADMIN")"

echo; echo "== T-19 Suspend a user who has a pending claim =="
F19=$(mk_item $P found t19 claim_pending); CL19=$(mk_claim $F19 $V)
check T-19 "suspension succeeds" OK "$(head_of "$(php tests/admin_cli.php suspend $V $ADMIN 'official test' 7)")"
check T-19 "claim withdrawn, item reopened, user suspended" "withdrawn open suspended" "$(q "SELECT CONCAT(c.claim_status,' ',i.item_status,' ',u.account_status) FROM claims c JOIN items i ON i.item_id=c.item_id JOIN users u ON u.user_id=$V WHERE c.claim_id=$CL19")"
check T-19 "suspension logged" 1 "$(q "SELECT COUNT(*) FROM audit_logs WHERE action='user_suspended' AND target_id=$V")"
check T-19 "claim withdrawal logged" 1 "$(q "SELECT COUNT(*) FROM audit_logs WHERE action='claim_withdrawn' AND target_id=$CL19")"

echo; echo "== T-20 Search shows only open and claim_pending, no private fields =="
OPENID=""
for s in open claim_pending ready_for_handover recovered closed removed; do
  id=$(mk_item $P found "otsearch$RUN $s" $s); [ "$s" = open ] && OPENID=$id
done
q "INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description) VALUES ($OPENID,NULL,'item_detail',$P,'SECRETSERIAL$RUN');"
PAGE=$(curl -s -b /tmp/ot_stranger.jar "$BASE/items?q=otsearch$RUN")
for s in open claim_pending; do check T-20 "'$s' listing is shown" 1 "$(echo "$PAGE" | grep -c "otsearch$RUN $s</a>")"; done
for s in ready_for_handover recovered closed removed; do check T-20 "'$s' listing is hidden" 0 "$(echo "$PAGE" | grep -c "otsearch$RUN $s</a>")"; done
check T-20 "private detail not in search results" 0 "$(echo "$PAGE" | grep -c SECRETSERIAL)"
check T-20 "private detail not searchable" 0 "$(curl -s -b /tmp/ot_stranger.jar "$BASE/items?q=SECRETSERIAL$RUN" | grep -c SECRETSERIAL)"
check T-20 "private detail not on the item page" 0 "$(curl -s -b /tmp/ot_stranger.jar "$BASE/items/$OPENID" | grep -c SECRETSERIAL)"

echo; echo "=============================================="
echo " PASS: $PASS    FAIL: $FAILN"
echo "=============================================="
rm -f /tmp/ot_*.jar /tmp/ot_ra /tmp/ot_rb /tmp/ot_d1 /tmp/ot_d2 /tmp/ot_lj /tmp/ot_evil.jpg
[ "$FAILN" -eq 0 ]