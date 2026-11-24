#!/usr/bin/env bash
#
# Express POS: a client's sandbox (owner, 2026-11-24), for the admin portal.
# Installed by root as /usr/local/sbin/expresspos-sandbox (root-owned, 755);
# the portal's worker user may run it with sudo.
#
#   expresspos-sandbox <slug> create    a copy at <slug>-staging.<DOMAIN_SUFFIX>
#   expresspos-sandbox <slug> delete    removes it completely
#
# create: a new install (expresspos-new-install) behind a sign-in gate, the
# client's database and photos copied in, the newest code's migrations run,
# then expresspos:scrub-sandbox replaces customers' personal data and
# disconnects their website domain. It has no Paystack or SMS keys and sends
# no email. Staff accounts are kept so the owner can sign in to review work.
# Registered for the staging code's updates (/etc/expresspos/staging-installs),
# never for backups.
# If anything fails, whatever was made is removed again.
#
# The live install is only read from (mysqldump, rsync), never changed.
# Last line: "EXPRESSPOS_RESULT {json}" with the address and the gate's
# username and password (the portal shows those once, then forgets them).
#
# /etc/expresspos/provision.conf:  DOMAIN_SUFFIX="pos.solusysdigital.com"

set -euo pipefail

CONF=/etc/expresspos/provision.conf
DOMAIN_SUFFIX=""
# shellcheck source=/dev/null
[[ -r "$CONF" ]] && source "$CONF"
PHP_VERSION=8.3

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
[[ $# -eq 2 ]] || fail "Expected a client and create or delete."
SLUG="$1"; ACTION="$2"
[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "Unknown client."
[[ "$ACTION" == create || "$ACTION" == delete ]] || fail "The action must be create or delete."
[[ -n "$DOMAIN_SUFFIX" ]] || fail "DOMAIN_SUFFIX is not set in $CONF."

LIVE_DIR="/var/www/expresspos-$SLUG"
LIVE_DB="expresspos_$SLUG"
NAME="${SLUG}sbx"
SLUG_DIR="expresspos-$NAME"
DIR="/var/www/$SLUG_DIR"
DB="expresspos_$NAME"
DOMAIN="$SLUG-staging.$DOMAIN_SUFFIX"
HTPASSWD="/etc/nginx/$SLUG_DIR.htpasswd"
# MySQL user names stop at 32 characters.
(( ${#DB} <= 32 )) || fail "This client's name is too long for a sandbox (18 characters at most)."

remove() {
  systemctl disable --now "$SLUG_DIR-queue.service" "$SLUG_DIR-reverb.service" >/dev/null 2>&1 || true
  rm -f "/etc/systemd/system/$SLUG_DIR-queue.service" "/etc/systemd/system/$SLUG_DIR-reverb.service"
  systemctl daemon-reload
  rm -f "/etc/nginx/sites-enabled/$SLUG_DIR" "/etc/nginx/sites-available/$SLUG_DIR" "$HTPASSWD"
  nginx -t >/dev/null 2>&1 && systemctl reload nginx
  certbot delete --non-interactive --cert-name "$DOMAIN" >/dev/null 2>&1 || true
  rm -f "/etc/php/$PHP_VERSION/fpm/pool.d/$SLUG_DIR.conf"
  systemctl reload "php$PHP_VERSION-fpm"
  rm -f "/etc/cron.d/$SLUG_DIR" "/etc/sudoers.d/deploy-$SLUG_DIR"
  mysql -e "DROP DATABASE IF EXISTS \`$DB\`; DROP USER IF EXISTS '$DB'@'localhost';"
  rm -rf "$DIR"
  for list in /etc/expresspos/installs /etc/expresspos/staging-installs; do
    [[ -f "$list" ]] && sed -i "/^$SLUG_DIR\$/d" "$list"
  done
  return 0
}

if [[ "$ACTION" == delete ]]; then
  echo "==> Removing the sandbox at https://$DOMAIN"
  remove
  echo "EXPRESSPOS_RESULT {\"ok\":true}"
  exit 0
fi

[[ -f "$LIVE_DIR/.env" ]] || fail "No live install for this client."
[[ ! -e "$DIR" ]] || fail "A sandbox already exists for this client. Delete it first."

# From here on, a failure removes whatever was made.
trap 'echo "==> Something failed; removing the half-made sandbox"; remove; fail "Creating the sandbox failed; see the log above. Nothing was left behind."' ERR

echo "==> Creating the sandbox install at https://$DOMAIN"
/usr/local/sbin/expresspos-new-install "$NAME" "$DOMAIN" staging

echo "==> Sign-in gate"
USERNAME=preview
PASSWORD="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 16)"
printf '%s:%s\n' "$USERNAME" "$(openssl passwd -apr1 "$PASSWORD")" > "$HTPASSWD"
chown root:www-data "$HTPASSWD"
chmod 640 "$HTPASSWD"
SITE="/etc/nginx/sites-available/$SLUG_DIR"
# The whole site behind the gate; the real-time connections authenticate on their own.
# "#" as the delimiter: the password file's path is full of slashes.
sed -i "0,\#index index.php;#s##index index.php;\n    auth_basic \"Sandbox\";\n    auth_basic_user_file $HTPASSWD;#" "$SITE"
sed -i "s#location /apps/ {#location /apps/ {\n        auth_basic off;#; s#location /app/ {#location /app/ {\n        auth_basic off;#" "$SITE"
nginx -t
systemctl reload nginx

echo "==> Copying the client's data and photos"
mysqldump --single-transaction --quick --no-tablespaces "$LIVE_DB" | mysql "$DB"
if [[ -d "$LIVE_DIR/storage/app/public" ]]; then
  rsync -a "$LIVE_DIR/storage/app/public/" "$DIR/storage/app/public/"
  chown -R deploy:www-data "$DIR/storage/app/public"
fi

echo "==> Making it a sandbox: no live keys, customers' data replaced"
sed -i 's/^APP_ENV=.*/APP_ENV=staging/' "$DIR/.env"
cd "$DIR"
sudo -u deploy php artisan config:clear --quiet
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan expresspos:scrub-sandbox
sudo -u deploy php artisan config:cache --quiet
systemctl restart "$SLUG_DIR-queue.service"

echo "==> Registering it for the staging code's updates (not backups)"
mkdir -p /etc/expresspos
touch /etc/expresspos/staging-installs
grep -qx "$SLUG_DIR" /etc/expresspos/staging-installs || echo "$SLUG_DIR" >> /etc/expresspos/staging-installs

trap - ERR
echo "EXPRESSPOS_RESULT {\"ok\":true,\"url\":\"https://$DOMAIN\",\"credentials\":{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}}"
