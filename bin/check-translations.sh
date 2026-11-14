#!/usr/bin/env bash
# Fails when a source string in the code has no Dutch translation. Run from the plugin root.
set -uo pipefail
PO=languages/recipe-community-nl_NL.po
TMP=$(mktemp -d)
xgettext --language=PHP --from-code=UTF-8 --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_attr__ --keyword=esc_html_e --keyword=_n:1,2 \
  --add-comments=translators -o "$TMP/new.pot" recipe-community.php includes/*.php admin/*.php 2>/dev/null
fail=0
missing=$(msgcmp "$PO" "$TMP/new.pot" 2>&1 | grep -c "used but not defined")
if [ "$missing" -gt 0 ]; then
  echo "$missing source strings are not in $PO (run xgettext, add translations):"
  msgcmp "$PO" "$TMP/new.pot" 2>&1 | grep "used but not defined" | head -20
  fail=1
fi
untranslated=$(msgattrib --untranslated --no-obsolete "$PO" | grep -c '^msgid "[^"]')
fuzzy=$(msgattrib --only-fuzzy --no-obsolete "$PO" | grep -c '^msgid "[^"]')
if [ "$untranslated" -gt 0 ] || [ "$fuzzy" -gt 0 ]; then
  echo "$untranslated untranslated and $fuzzy fuzzy entries in $PO"
  fail=1
fi
rm -rf "$TMP"
if [ "$fail" -ne 0 ]; then echo "FAIL: translations incomplete"; exit 1; fi
echo "OK: every source string is translated"
