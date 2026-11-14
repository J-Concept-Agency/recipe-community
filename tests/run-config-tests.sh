#!/usr/bin/env bash
# Usage: RCOM_WP=/path/to/wp-wrapper tests/run-config-tests.sh   (from the plugin root; no real HTTP, no database writes)
WP="${RCOM_WP:-wp} --skip-plugins=bricks-laposta"; f=0  # the real Bricks Laposta is replaced by a stub
chk() { # name expected env...
  local n=$1 e=$2; shift 2
  local r; r=$(env "$@" $WP eval-file "$PWD/tests/test-rcom-config.php" 2>/dev/null | grep -v Deprecated | tail -${TAIL:-1})
  if [ "$r" = "$e" ]; then echo "OK   $n"; else echo "FAIL $n: got '$r' want '$e'"; f=1; fi
}
chk "RCOM constant wins over LAPOSTA constant, own and Bricks" "rcom_constant|RC-KEY-1111|own|OWN-SITE|own" T_RCOM=1 T_LP=1 OWN=x T_BRICKS=1
chk "LAPOSTA_API_KEY works for this plugin" "constant|LP-KEY-2222|bricks|BR-SITE|bricks" T_LP=1 T_BRICKS=1
chk "own key before Bricks Laposta; own Turnstile before Bricks" "own|x|own|OWN-SITE|own" OWN=x T_BRICKS=1
chk "Bricks Laposta read live; Bricks Turnstile" "bricks|BRICKS-KEY-3333|bricks|BR-SITE|bricks" T_BRICKS=1
chk "nothing set" "||||" OWN=
chk "Turnstile constants win" "||constant|CONST-SITE|constant" T_TS=1 OWN=
TAIL=1 chk "notification option off: not sent" 'OPT:{"upsert":"true","suppress_reactivation":"true"}' SUB=1 QUIET=0 T_LP=1
chk "notification option on: suppress_email_notification" 'OPT:{"upsert":"true","suppress_reactivation":"true","suppress_email_notification":"true"}' SUB=1 QUIET=1 T_LP=1
exit $f
