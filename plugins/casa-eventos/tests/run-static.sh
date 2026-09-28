#!/usr/bin/env bash
# Static checks for casa-eventos (no WordPress runtime needed).
#
#   PHP=/path/to/php bash plugins/casa-eventos/tests/run-static.sh
#
# PHP defaults to `php` on PATH. On this machine LocalWP ships one at
# %APPDATA%/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe.
set -u
cd "$(dirname "$0")/.."
PHP="${PHP:-php}"
status=0

echo "== php -l"
lint_fail=0
while IFS= read -r -d '' f; do
	out=$("$PHP" -l "$f" 2>&1) || { echo "$out"; lint_fail=1; }
done < <(find . -name '*.php' -print0)
[ "$lint_fail" -eq 0 ] && echo "all PHP files lint clean" || status=1

echo "== node --check"
node --check assets/admin/event-panel.js && echo "event-panel.js syntax OK" || status=1

echo "== unit tests"
"$PHP" tests/run.php || status=1

echo "== architecture"
"$PHP" tests/check-architecture.php || status=1

echo "== panel script"
node tests/event-panel.test.cjs || status=1

exit $status
