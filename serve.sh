#!/bin/bash
# Kill any PHP built-in server on the test port, then start a fresh one.
PORT=8099
for d in /proc/[0-9]*; do
  if [ -r "$d/cmdline" ]; then
    c=$(tr '\0' ' ' < "$d/cmdline" 2>/dev/null)
    case "$c" in
      *"php -S 127.0.0.1:$PORT"*) pid=$(basename "$d"); echo "killing $pid"; kill -9 "$pid" 2>/dev/null ;;
    esac
  fi
done
sleep 1

# ✅ التعديل: استخدام المسار النسبي بدلاً من المسار الثابت
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

setsid nohup php -S 127.0.0.1:$PORT -t . > /tmp/php-server.log 2>&1 < /dev/null &
sleep 3
echo "server started on http://127.0.0.1:$PORT"
