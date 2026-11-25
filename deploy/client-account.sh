#!/usr/bin/env bash
#
# Express POS: suspend, unsuspend or delete a client's install, for the admin
# portal (owner, 2026-11-24). Installed by root as
# /usr/local/sbin/expresspos-client-account (root-owned, 755); the portal's
# worker user may run it with sudo.
#
#   expresspos-client-account <slug> suspend     every page shows "temporarily
#                                                unavailable" (artisan down);
#                                                Paystack confirmations still
#                                                arrive; data untouched
#   expresspos-client-account <slug> unsuspend   back exactly as it was
#   expresspos-client-account <slug> delete      only when suspended: a final
#                                                backup kept 30 days, then the
#                                                install removed completely
#
# A suspended install stays suspended through code updates (the deploy never
# brings it up), so it's current when unsuspended. Delete removes the code,
# database and its user, nginx site and certificate (own website domain
# included), PHP pool, queue and Reverb services, scheduler, sudoers lines,
# its sandbox, and its lines in the update and backup lists; its nightly
# backups go too, replaced by the final backup in
# /var/backups/expresspos-deleted/, which a daily job destroys after 30 days.
# Last line: "EXPRESSPOS_RESULT {json}".

set -euo pipefail

PHP_VERSION=8.3
FINAL=/var/backups/expresspos-deleted

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
[[ $# -eq 2 ]] || fail "Expected a client and suspend, unsuspend or delete."
SLUG="$1"; ACTION="$2"
[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "Unknown client."
[[ "$ACTION" =~ ^(suspend|unsuspend|delete)$ ]] || fail "The action must be suspend, unsuspend or delete."

NAME="expresspos-$SLUG"
DIR="/var/www/$NAME"
DB="expresspos_$SLUG"
[[ -f "$DIR/.env" ]] || fail "No install for this client."
DOWN_FILE="$DIR/storage/framework/down"

case "$ACTION" in
  suspend)
    echo "==> Suspending $NAME"
    cd "$DIR"
    sudo -u www-data php artisan down --render="errors::suspended" --retry=3600
    echo "EXPRESSPOS_RESULT {\"ok\":true,\"state\":\"suspended\"}"
    ;;

  unsuspend)
    echo "==> Bringing $NAME back"
    cd "$DIR"
    sudo -u www-data php artisan up
    systemctl restart "$NAME-queue.service" "$NAME-reverb.service"
    echo "EXPRESSPOS_RESULT {\"ok\":true,\"state\":\"active\"}"
    ;;

  delete)
    [[ -f "$DOWN_FILE" ]] || fail "Suspend this client before deleting it."

    echo "==> Final backup (kept 30 days)"
    mkdir -p "$FINAL"
    chmod 700 "$FINAL"
    STAMP="$(date +%Y%m%d-%H%M)"
    mysqldump --single-transaction --quick --no-tablespaces "$DB" | gzip > "$FINAL/$SLUG-$STAMP.sql.gz"
    tar -czf "$FINAL/$SLUG-$STAMP-files.tar.gz" -C "$DIR/storage" app
    chmod 600 "$FINAL/$SLUG-$STAMP".*
    # Destroyed after 30 days by a daily job.
    echo "15 3 * * * root find $FINAL -type f -mtime +30 -delete" > /etc/cron.d/expresspos-deleted-purge
    chmod 644 /etc/cron.d/expresspos-deleted-purge

    echo "==> Removing its sandbox, if any"
    [[ -d "/var/www/expresspos-${SLUG}sbx" ]] && /usr/local/sbin/expresspos-sandbox "$SLUG" delete >/dev/null || true

    echo "==> Removing the install"
    # Every certificate this site used (its POS address and any own website domain).
    CERTS="$(grep -ho '/etc/letsencrypt/live/[^/]*/' "/etc/nginx/sites-available/$NAME" 2>/dev/null | sort -u | cut -d/ -f5)"
    systemctl disable --now "$NAME-queue.service" "$NAME-reverb.service" >/dev/null 2>&1 || true
    rm -f "/etc/systemd/system/$NAME-queue.service" "/etc/systemd/system/$NAME-reverb.service"
    systemctl daemon-reload
    rm -f "/etc/nginx/sites-enabled/$NAME" "/etc/nginx/sites-available/$NAME"
    nginx -t && systemctl reload nginx
    for cert in $CERTS; do certbot delete --non-interactive --cert-name "$cert" >/dev/null 2>&1 || true; done
    rm -f "/etc/php/$PHP_VERSION/fpm/pool.d/$NAME.conf"
    systemctl reload "php$PHP_VERSION-fpm"
    rm -f "/etc/cron.d/$NAME" "/etc/sudoers.d/deploy-$NAME"
    mysql -e "DROP DATABASE IF EXISTS \`$DB\`; DROP USER IF EXISTS '$DB'@'localhost';"
    rm -rf "$DIR"

    echo "==> Taking it off the update and backup lists"
    for list in /etc/expresspos/installs /etc/expresspos/staging-installs; do
      [[ -f "$list" ]] && sed -i "/^$NAME\$/d" "$list"
    done
    [[ -f /etc/expresspos/backup.conf ]] && sed -i -E "s/ ?\b$DB\b//" /etc/expresspos/backup.conf
    # Its nightly backups (backup-databases.sh's folder): the final backup replaces them.
    [[ -d "/var/backups/expresspos/$DB" ]] && rm -rf "/var/backups/expresspos/$DB"

    echo "EXPRESSPOS_RESULT {\"ok\":true,\"state\":\"deleted\",\"final_backup\":\"$FINAL/$SLUG-$STAMP.sql.gz\"}"
    ;;
esac
