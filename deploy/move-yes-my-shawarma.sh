#!/usr/bin/env bash
#
# Express POS MVP release (deploy/MVP-CUTOVER.md): move Yes My Shawarma from
# its original install into an ordinary Express POS install. Run as root on
# the server:
#
#   bash deploy/move-yes-my-shawarma.sh --rehearsal   a gated, scrubbed copy at
#                                                     ymsrehearsal.<suffix>; live untouched
#   bash deploy/move-yes-my-shawarma.sh --remove-rehearsal
#   bash deploy/move-yes-my-shawarma.sh --go          the real move, on release night
#   bash deploy/move-yes-my-shawarma.sh --rollback <keep folder>
#
# --go: fresh backup; the old install put in maintenance and its scheduler
# and services paused (kept, as the rollback); a new install
# yesmyshawarma.<suffix> on the release code; the database and every file
# copied; the old .env's keys carried over (APP_KEY, Paystack, SMS, Web
# Push, mail, Sentry, Turnstile: everything but what's this install's own);
# migrations; yesmyshawarma.com (and www.) connected as its website domain;
# staff./stock./rider. redirects pointed at the new POS address;
# staging.yesmyshawarma.com redirected to the new staging; registered for
# updates, backups and Express POS Admin; /etc/expresspos/released created.
# Everything it replaces is kept in /root/yms-cutover-<time>/, which
# --rollback puts back. If --go fails before the new site takes over, the
# old one is brought straight back up.

set -euo pipefail

MODE="${1:-}"
SUFFIX="pos.solusysdigital.com"
OLD_DIR=/var/www/yes-my-shawarma
OLD_DB=yes_my_shawarma
WEBSITE=yesmyshawarma.com
PHP_VERSION=8.3
STAGING_SOURCE=/var/www/yes-my-shawarma-staging

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }

case "$MODE" in
  --rehearsal|--remove-rehearsal) NAME=ymsrehearsal ;;
  --go|--rollback) NAME=yesmyshawarma ;;
  *) sed -n '2,25p' "$0"; exit 1 ;;
esac
SLUG_DIR="expresspos-$NAME"
NEW_DIR="/var/www/$SLUG_DIR"
NEW_DB="expresspos_$NAME"
DOMAIN="$NAME.$SUFFIX"
HTPASSWD="/etc/nginx/$SLUG_DIR.htpasswd"
step() { echo; echo "==> $*"; }

remove_install() {
  systemctl disable --now "$SLUG_DIR-queue.service" "$SLUG_DIR-reverb.service" >/dev/null 2>&1 || true
  rm -f "/etc/systemd/system/$SLUG_DIR-queue.service" "/etc/systemd/system/$SLUG_DIR-reverb.service"
  systemctl daemon-reload
  rm -f "/etc/nginx/sites-enabled/$SLUG_DIR" "/etc/nginx/sites-available/$SLUG_DIR" "$HTPASSWD"
  nginx -t >/dev/null 2>&1 && systemctl reload nginx
  certbot delete --non-interactive --cert-name "$DOMAIN" >/dev/null 2>&1 || true
  rm -f "/etc/php/$PHP_VERSION/fpm/pool.d/$SLUG_DIR.conf"; systemctl reload "php$PHP_VERSION-fpm"
  rm -f "/etc/cron.d/$SLUG_DIR" "/etc/sudoers.d/deploy-$SLUG_DIR"
  mysql -e "DROP DATABASE IF EXISTS \`$NEW_DB\`; DROP USER IF EXISTS '$NEW_DB'@'localhost';"
  rm -rf "$NEW_DIR"
}

if [[ "$MODE" == --remove-rehearsal ]]; then
  step "Removing the rehearsal at https://$DOMAIN"
  remove_install
  echo "Done."
  exit 0
fi

if [[ "$MODE" == --rollback ]]; then
  KEEP="${2:-}"
  [[ -d "$KEEP" && -f "$KEEP/root.crontab" ]] || { echo "Give the /root/yms-cutover-<time> folder the move made." >&2; exit 1; }
  step "Rolling back to the original install"
  [[ -d "$NEW_DIR" ]] && (cd "$NEW_DIR" && sudo -u www-data php artisan down || true)
  for site in "$KEEP"/nginx/*; do cp "$site" "/etc/nginx/sites-available/$(basename "$site")"; ln -sf "/etc/nginx/sites-available/$(basename "$site")" /etc/nginx/sites-enabled/; done
  # The new install answers on its POS address only again.
  sed -i -E "s/^(\s*server_name) .*;/\1 $DOMAIN;/" "/etc/nginx/sites-available/$SLUG_DIR" 2>/dev/null || true
  nginx -t && systemctl reload nginx
  crontab -u root "$KEEP/root.crontab"
  systemctl enable --now yes-my-shawarma-queue.service yes-my-shawarma-reverb.service
  (cd "$OLD_DIR" && sudo -u www-data php artisan up)
  sed -i "/^$SLUG_DIR\$/d" /etc/expresspos/installs
  rm -f /etc/expresspos/released
  echo "Rolled back: $WEBSITE is served by $OLD_DIR again. Also revert the Main merge before the next deploy."
  exit 0
fi

# --rehearsal or --go
[[ -f "$OLD_DIR/.env" ]] || { echo "$OLD_DIR/.env not found." >&2; exit 1; }
[[ ! -e "$NEW_DIR" ]] || { echo "$NEW_DIR already exists." >&2; exit 1; }
[[ -d "$STAGING_SOURCE/.git" ]] || { echo "No staging checkout at $STAGING_SOURCE." >&2; exit 1; }
STAMP="$(date +%Y%m%d-%H%M)"
KEEP="/root/yms-cutover-$STAMP"

if [[ "$MODE" == --go ]]; then
  echo "Release code: $(git -C "$STAGING_SOURCE" log -1 --format='%h %s')"
  mkdir -p "$KEEP/nginx"
  crontab -u root -l > "$KEEP/root.crontab"

  step "Final backup"
  /usr/local/bin/expresspos-backup

  step "Closing the original install (kept as the rollback)"
  (cd "$OLD_DIR" && sudo -u www-data php artisan down --retry=60)
  crontab -u root -l | sed "s#^\(.*$OLD_DIR/artisan schedule:run.*\)#\# paused by the MVP move: \1#" | crontab -u root -
  systemctl disable --now yes-my-shawarma-queue.service yes-my-shawarma-reverb.service

  # Until the new site has taken over, any failure brings the old one back.
  trap 'echo "!! The move stopped. Bringing the original install back up."; crontab -u root "$KEEP/root.crontab"; systemctl enable --now yes-my-shawarma-queue.service yes-my-shawarma-reverb.service; (cd "$OLD_DIR" && sudo -u www-data php artisan up); echo "The new install at $NEW_DIR is left for inspection; remove it before trying again."' ERR
else
  trap 'echo "!! The rehearsal stopped; removing it."; remove_install' ERR
fi

step "New install at https://$DOMAIN (release code: staging)"
/usr/local/sbin/expresspos-new-install "$NAME" "$DOMAIN" staging

if [[ "$MODE" == --rehearsal ]]; then
  step "Password gate"
  PASSWORD="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 16)"
  printf '%s:%s\n' rehearsal "$(openssl passwd -apr1 "$PASSWORD")" > "$HTPASSWD"
  chown root:www-data "$HTPASSWD"; chmod 640 "$HTPASSWD"
  SITE="/etc/nginx/sites-available/$SLUG_DIR"
  sed -i "0,\#index index.php;#s##index index.php;\n    auth_basic \"Rehearsal\";\n    auth_basic_user_file $HTPASSWD;#" "$SITE"
  sed -i "s#location /apps/ {#location /apps/ {\n        auth_basic off;#; s#location /app/ {#location /app/ {\n        auth_basic off;#" "$SITE"
  nginx -t && systemctl reload nginx
fi

step "Copying the database"
# Emptied first: the new install migrated it to the newest schema, and the
# old database is older (renamed tables keep their old key names), so it
# goes in exactly as it was and the migrations below bring it forward. The
# install's user keeps its grants on the database name.
mysql -e "DROP DATABASE \`$NEW_DB\`; CREATE DATABASE \`$NEW_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysqldump --single-transaction --quick --no-tablespaces "$OLD_DB" | mysql "$NEW_DB"

step "Copying files"
if [[ "$MODE" == --go ]]; then
  rsync -a "$OLD_DIR/storage/app/" "$NEW_DIR/storage/app/"
else
  # A rehearsal takes the menu photos and logo, never receipts or damage photos.
  rsync -a "$OLD_DIR/storage/app/public/" "$NEW_DIR/storage/app/public/"
fi
chown -R deploy:www-data "$NEW_DIR/storage/app"
chmod -R ug+rwX "$NEW_DIR/storage/app"

step "Carrying the keys over"
# Everything in the old .env except what belongs to this install itself.
# A rehearsal takes only APP_KEY (stored data is encrypted with it).
ONLY_APP_KEY=$([[ "$MODE" == --rehearsal ]] && echo 1 || echo 0)
OLD_ENV="$OLD_DIR/.env" NEW_ENV="$NEW_DIR/.env" ONLY_APP_KEY="$ONLY_APP_KEY" php -r '
    $own = "/^(APP_ENV|APP_URL|APP_NAME|DB_|REVERB_|VITE_|LOG_|SESSION_|BROADCAST_CONNECTION|QUEUE_CONNECTION|CACHE_STORE|FILESYSTEM_DISK)/";
    $read = fn ($f) => array_filter(array_map("trim", file($f)), fn ($l) => $l !== "" && $l[0] !== "#" && str_contains($l, "="));
    $new = []; foreach ($read(getenv("NEW_ENV")) as $l) { [$k, $v] = explode("=", $l, 2); $new[$k] = $v; }
    $carried = [];
    foreach ($read(getenv("OLD_ENV")) as $l) {
        [$k, $v] = explode("=", $l, 2);
        if (getenv("ONLY_APP_KEY") === "1" ? $k !== "APP_KEY" : preg_match($own, $k)) { continue; }
        $new[$k] = $v; $carried[] = $k;
    }
    $out = ""; foreach ($new as $k => $v) { $out .= $k."=".$v."\n"; }
    file_put_contents(getenv("NEW_ENV"), $out);
    echo "Carried over: ".implode(", ", $carried)."\n";
'
chown deploy:www-data "$NEW_DIR/.env"; chmod 640 "$NEW_DIR/.env"
[[ "$MODE" == --rehearsal ]] && sed -i 's/^APP_ENV=.*/APP_ENV=staging/' "$NEW_DIR/.env"

step "Migrations on the release code"
cd "$NEW_DIR"
sudo -u deploy php artisan config:clear --quiet
sudo -u deploy php artisan migrate --force
if [[ "$MODE" == --rehearsal ]]; then
  sudo -u www-data php artisan expresspos:scrub-sandbox
fi
sudo -u deploy php artisan config:cache --quiet
sudo -u deploy php artisan route:cache --quiet
sudo -u deploy php artisan view:cache --quiet
systemctl restart "$SLUG_DIR-queue.service" "$SLUG_DIR-reverb.service"

if [[ "$MODE" == --rehearsal ]]; then
  trap - ERR
  echo
  echo "Rehearsal ready: https://$DOMAIN  (gate: rehearsal / $PASSWORD)"
  echo "Staff sign in with their real accounts. Customer data is scrubbed; no keys but APP_KEY."
  echo "When done: bash $0 --remove-rehearsal"
  exit 0
fi

step "Website domain: $WEBSITE moves to the new install"
cp "/etc/nginx/sites-available/yes-my-shawarma-domain" "$KEEP/nginx/"
rm -f /etc/nginx/sites-enabled/yes-my-shawarma-domain
nginx -t && systemctl reload nginx
/usr/local/sbin/expresspos-add-website-domain "$NAME" "$WEBSITE"
# From here the new site has taken over; a problem now means --rollback.
trap - ERR

step "staff., stock. and rider. go to the new POS address"
for site in yes-my-shawarma-staff yes-my-shawarma-stock yes-my-shawarma-rider; do
  [[ -f "/etc/nginx/sites-available/$site" ]] || continue
  cp "/etc/nginx/sites-available/$site" "$KEEP/nginx/"
  sed -i "s#return 301 https://$WEBSITE/#return 301 https://$DOMAIN/#" "/etc/nginx/sites-available/$site"
done

step "staging.yesmyshawarma.com goes to the new shared staging"
if [[ -f /etc/nginx/sites-available/yes-my-shawarma-staging ]]; then
  cp /etc/nginx/sites-available/yes-my-shawarma-staging "$KEEP/nginx/"
  sed -i "0,\#location / {#s##location / {\n        return 301 https://staging.$SUFFIX\$request_uri;#" /etc/nginx/sites-available/yes-my-shawarma-staging
fi
nginx -t && systemctl reload nginx

step "Registering: updates, backups, Express POS Admin"
grep -qx "$SLUG_DIR" /etc/expresspos/installs || echo "$SLUG_DIR" >> /etc/expresspos/installs
grep -q "$NEW_DB" /etc/expresspos/backup.conf || sed -i "s/^DATABASES=\"\(.*\)\"/DATABASES=\"\1 $NEW_DB\"/" /etc/expresspos/backup.conf
touch /etc/expresspos/released
DESCRIBED="$(mktemp)"
sudo -u www-data php artisan expresspos:describe > "$DESCRIBED"
chmod 644 "$DESCRIBED"
HOME_DIR="$(mktemp -d)"; chown xposadmin "$HOME_DIR"
(cd /var/www/expresspos-admin && sudo -u xposadmin env HOME="$HOME_DIR" php artisan clients:register-existing "$NAME" "$DESCRIBED") || echo "!! Registering in Express POS Admin failed; run clients:register-existing by hand."
rm -rf "$DESCRIBED" "$HOME_DIR"

echo
echo "Moved. Yes My Shawarma: POS https://$DOMAIN, website https://$WEBSITE"
echo "Kept for rollback: $KEEP  (bash $0 --rollback $KEEP)"
echo "Next: merge staging into Main, set the repository variable EXPRESSPOS_RELEASED=true,"
echo "then run the checks in deploy/MVP-CUTOVER.md."
