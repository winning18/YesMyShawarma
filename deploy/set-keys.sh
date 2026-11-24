#!/usr/bin/env bash
#
# Express POS: set one client's payment and SMS keys, for the admin portal.
# Installed by root as /usr/local/sbin/expresspos-set-keys (root-owned, 755);
# the portal's worker user may run it with sudo.
#
#   echo '{"PAYSTACK_PUBLIC_KEY":"pk_live_…","PAYSTACK_SECRET_KEY":"sk_live_…"}' \
#     | expresspos-set-keys <slug>
#
# The keys arrive as JSON on standard input, never as arguments, so they
# never show in the process list or a log. Only these names are accepted:
# PAYSTACK_PUBLIC_KEY, PAYSTACK_SECRET_KEY, ARKESEL_API_KEY, ARKESEL_SENDER_ID.
# A key given as "" is removed. Each value must be one line of plain
# characters (no quotes, spaces or line breaks). The install's .env is
# updated in place, its settings cache refreshed and its worker restarted.
# Prints what changed (names and the last 4 characters only) and a last
# line "EXPRESSPOS_RESULT {json}".

set -euo pipefail

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
SLUG="${1:-}"
[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "Unknown client."
DIR="/var/www/expresspos-$SLUG"
[[ -f "$DIR/.env" ]] || fail "No install for this client."

INPUT="$(head -c 8192)"

# PHP does the careful part: parse, check, and rewrite the .env lines.
CHANGES="$(EXPRESSPOS_KEYS_INPUT="$INPUT" php -r '
    $allowed = ["PAYSTACK_PUBLIC_KEY", "PAYSTACK_SECRET_KEY", "ARKESEL_API_KEY", "ARKESEL_SENDER_ID"];
    $keys = json_decode(getenv("EXPRESSPOS_KEYS_INPUT"), true);
    if (! is_array($keys) || $keys === []) { fwrite(STDERR, "Nothing to set."); exit(2); }
    foreach ($keys as $name => $value) {
        if (! in_array($name, $allowed, true) || ! is_string($value) || ! preg_match("/^[A-Za-z0-9_\\-.]{0,200}$/", $value)) {
            fwrite(STDERR, "Rejected: $name"); exit(2);
        }
    }
    $file = $argv[1];
    $lines = explode("\n", rtrim(file_get_contents($file), "\n"));
    $out = [];
    foreach ($keys as $name => $value) {
        $lines = array_values(array_filter($lines, fn ($l) => ! str_starts_with($l, $name."=")));
        if ($value !== "") {
            $lines[] = $name."=".$value;
        }
        $out[] = $name.($value === "" ? " removed" : " set (ends ".substr($value, -4).")");
    }
    file_put_contents($file, rtrim(implode("\n", $lines), "\n")."\n");
    echo implode("; ", $out);
' "$DIR/.env" 2>&1)" || fail "${CHANGES//\"/}"

chown deploy:www-data "$DIR/.env"
chmod 640 "$DIR/.env"
cd "$DIR"
sudo -u deploy php artisan config:cache --quiet
systemctl restart "expresspos-$SLUG-queue.service" 2>/dev/null || true
systemctl reload php8.3-fpm

echo "$CHANGES"
echo "EXPRESSPOS_RESULT {\"ok\":true,\"changed\":\"${CHANGES//\"/}\"}"
