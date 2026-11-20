#!/usr/bin/env bash
#
# Express POS: onboard one client in one go, for the admin portal. Installed
# by root as /usr/local/sbin/expresspos-provision (root-owned, 755); the
# portal's worker user may run exactly this file with sudo, nothing else.
#
#   expresspos-provision <slug> <business> <branch> <branch-phone> <address> \
#       <lat> <lng> <opens HH:MM> <closes HH:MM> <owner-name> <owner-email> \
#       <owner-phone|-> <support-email|-> <modules, comma-separated|->
#
# 1. Creates the install at <slug>.<DOMAIN_SUFFIX> (expresspos-new-install).
# 2. Registers it: staging deploys update it (/etc/expresspos/installs) and
#    it joins the nightly backups (/etc/expresspos/backup.conf).
# 3. Fills in the business (artisan expresspos:new-client --json) and prints
#    the result on a last line starting "EXPRESSPOS_RESULT ".
#
# Every value reaches artisan as one --option=value argument, never through
# a shell, and artisan validates it again. The slug is the only thing used
# in paths or names, and it must be lowercase letters and digits.
#
# /etc/expresspos/provision.conf:  DOMAIN_SUFFIX="pos.solusysdigital.com"

set -euo pipefail

CONF=/etc/expresspos/provision.conf
DOMAIN_SUFFIX=""
# shellcheck source=/dev/null
[[ -r "$CONF" ]] && source "$CONF"

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
[[ $# -eq 14 ]] || fail "Expected 14 arguments, got $#."
[[ -n "$DOMAIN_SUFFIX" ]] || fail "DOMAIN_SUFFIX is not set in $CONF."

SLUG="$1"; BUSINESS="$2"; BRANCH="$3"; BRANCH_PHONE="$4"; ADDRESS="$5"; LAT="$6"; LNG="$7"
OPENS="$8"; CLOSES="$9"; OWNER_NAME="${10}"; OWNER_EMAIL="${11}"; OWNER_PHONE="${12}"; SUPPORT_EMAIL="${13}"; MODULES="${14}"

[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "The web address name must be 2-21 lowercase letters and digits."
[[ "$MODULES" =~ ^(-|[a-z_]+(,[a-z_]+)*)$ ]] || fail "Modules must be comma-separated keys."
[[ "$OPENS" =~ ^[0-2][0-9]:[0-5][0-9]$ && "$CLOSES" =~ ^[0-2][0-9]:[0-5][0-9]$ ]] || fail "Opening times must be HH:MM."

DOMAIN="$SLUG.$DOMAIN_SUFFIX"
SLUG_DIR="expresspos-$SLUG"
DIR="/var/www/$SLUG_DIR"

echo "==> Creating the install at https://$DOMAIN"
/usr/local/sbin/expresspos-new-install "$SLUG" "$DOMAIN" || fail "Creating the install failed; see the log above."

echo "==> Registering it for updates and backups"
mkdir -p /etc/expresspos
touch /etc/expresspos/installs
grep -qx "$SLUG_DIR" /etc/expresspos/installs || echo "$SLUG_DIR" >> /etc/expresspos/installs
if [[ -f /etc/expresspos/backup.conf ]] && ! grep -q "expresspos_$SLUG" /etc/expresspos/backup.conf; then
  sed -i "s/^DATABASES=\"\(.*\)\"/DATABASES=\"\1 expresspos_$SLUG\"/" /etc/expresspos/backup.conf
fi

echo "==> Filling in the business"
ARGS=(
  "--business=$BUSINESS" "--branch=$BRANCH" "--branch-phone=$BRANCH_PHONE" "--address=$ADDRESS"
  "--lat=$LAT" "--lng=$LNG" "--opens=$OPENS" "--closes=$CLOSES"
  "--owner-name=$OWNER_NAME" "--owner-email=$OWNER_EMAIL"
  "--modules=$([[ "$MODULES" == "-" ]] && echo "" || echo "$MODULES")"
  --json --no-interaction
)
[[ "$OWNER_PHONE" != "-" ]] && ARGS+=("--owner-phone=$OWNER_PHONE")
[[ "$SUPPORT_EMAIL" != "-" ]] && ARGS+=("--support-email=$SUPPORT_EMAIL")

cd "$DIR"
RESULT="$(sudo -u www-data php artisan expresspos:new-client "${ARGS[@]}" | tail -1)" || true
echo "EXPRESSPOS_RESULT $RESULT"
[[ "$RESULT" == *'"ok":true'* ]]
