# Usage: source tests/helpers.sh
BASE=http://clf.local

# clf_login JAR EMAIL PASSWORD
clf_login() {
  rm -f "$1"
  local t
  t=$(curl -s -c "$1" -b "$1" $BASE/login | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -c "$1" -b "$1" -o /dev/null -w "login $2: %{http_code}\n" \
    -d "csrf_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" $BASE/login
}

# clf_field JAR URL FIELDNAME  -> value of a hidden input on that page
clf_field() {
  curl -s -b "$1" -c "$1" "$2" | grep -o "name=\"$3\" value=\"[a-f0-9]*\"" | head -1 | sed 's/.*value="//;s/"//'
}

# clf_code JAR URL -> prints the HTTP status
clf_code() { curl -s -o /dev/null -w "%{http_code}" -b "$1" "$2"; }
