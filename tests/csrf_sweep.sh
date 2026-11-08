#!/usr/bin/env bash
# Usage: bash tests/csrf_sweep.sh /tmp/admin.jar   (a logged-in, verified admin cookie jar)
JAR="${1:?give a cookie jar}"
BASE=http://clf.local
n=0; bad=0
while read -r path; do
  p=$(echo "$path" | sed 's/{id}/1/g')
  args=(-b "$JAR")
  case "$p" in /login|/register) args=();; esac       # these redirect when you are already logged in
  code=$(curl -s -o /dev/null -w '%{http_code}' "${args[@]}" -X POST "$BASE$p")
  n=$((n+1))
  if [ "$code" != "403" ]; then echo "NOT 403: POST $p -> $code"; bad=$((bad+1)); fi
done < <(grep -o "\['POST', *'[^']*'" /var/www/clf/app/routes.php | sed "s/.*'\(\/[^']*\)'/\1/")
echo "checked $n POST routes, $bad problems"