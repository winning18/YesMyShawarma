#!/usr/bin/env bash
#
# Express POS: change a client's brand colours (and, optionally, logo) from
# the admin portal (owner, 2026-11-24). Installed by root as
# /usr/local/sbin/expresspos-set-brand (root-owned, 755).
#
#   expresspos-set-brand <slug> <primary #rrggbb> <accent #rrggbb> <logo path|->
#
# A new logo must be in the portal's logo folder, named <slug>.<ext>.
# Last line: "EXPRESSPOS_RESULT {json}". The owner can also change these on
# their own Business profile page.

set -euo pipefail

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
[[ $# -eq 4 ]] || fail "Expected 4 arguments."
SLUG="$1"; PRIMARY="$2"; ACCENT="$3"; LOGO="$4"
LOGO_DIR=/var/www/expresspos-admin/storage/app/private/logos
[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "Unknown client."
DIR="/var/www/expresspos-$SLUG"
[[ -f "$DIR/.env" ]] || fail "No install for this client."
[[ "$PRIMARY" =~ ^#[0-9a-f]{6}$ && "$ACCENT" =~ ^#[0-9a-f]{6}$ ]] || fail "Brand colours must be #rrggbb."

ARGS=("--primary-colour=$PRIMARY" "--accent-colour=$ACCENT")
if [[ "$LOGO" != "-" ]]; then
  [[ "$LOGO" =~ ^$LOGO_DIR/$SLUG\.(png|jpg|jpeg|webp)$ && -f "$LOGO" ]] || fail "The logo wasn't found in the portal's logo folder."
  ARGS+=("--logo=$LOGO")
fi

cd "$DIR"
sudo -u www-data php artisan expresspos:brand "${ARGS[@]}" || fail "The install refused the brand change; see the log."
echo "EXPRESSPOS_RESULT {\"ok\":true}"
