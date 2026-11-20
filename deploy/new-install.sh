#!/usr/bin/env bash
#
# Express POS: set up one more client install on this server, by hand
# (step 10's pilot; step 6 automates this later). Run as root on the server:
#
#   sudo bash deploy/new-install.sh <name> <domain>
#   e.g. sudo bash deploy/new-install.sh mamaput mamaput.pos.solusysdigital.com
#
# Before running: the domain's DNS A record must point at this server
# (certbot needs it). The install tracks the `staging` branch until the
# MVP release, and the staging deploy updates it (EXPRESSPOS_EXTRA_INSTALLS).
#
# What it creates, all named expresspos-<name>:
#   /var/www/expresspos-<name>            the code (cloned from staging's checkout)
#   MySQL database + user                 expresspos_<name>
#   PHP-FPM pool, nginx site, SSL         <domain>
#   systemd queue worker + Reverb server  expresspos-<name>-queue / -reverb
#   scheduler                             /etc/cron.d/expresspos-<name>
#   sudoers lines for the deploy user     /etc/sudoers.d/deploy-expresspos-<name>
# Then it migrates the database. The business itself is filled in after,
# with: php artisan expresspos:new-client
#
# Nothing else on the server is touched; nginx is only reloaded after
# `nginx -t` passes.

set -euo pipefail

NAME="${1:-}"
DOMAIN="${2:-}"
SOURCE=/var/www/yes-my-shawarma-staging
PHP_VERSION=8.3

if [[ $EUID -ne 0 ]]; then echo "Run as root." >&2; exit 1; fi
if [[ ! "$NAME" =~ ^[a-z][a-z0-9]{1,20}$ ]]; then echo "Name: lowercase letters and digits, 2-21 characters (e.g. mamaput)." >&2; exit 1; fi
if [[ ! "$DOMAIN" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]]; then echo "Give the install's domain, e.g. $NAME.pos.solusysdigital.com." >&2; exit 1; fi

SLUG="expresspos-$NAME"
DIR="/var/www/$SLUG"
DB="expresspos_$NAME"
SOCK="/run/php/php$PHP_VERSION-fpm-$SLUG.sock"

if [[ -e "$DIR" ]]; then echo "$DIR already exists: this install is already set up." >&2; exit 1; fi
if [[ "$(getent ahostsv4 "$DOMAIN" | awk 'NR==1 {print $1}')" != "$(curl -4 -s https://ifconfig.me)" ]]; then
  echo "$DOMAIN doesn't point at this server yet. Add the DNS A record first." >&2; exit 1
fi

# The next free Reverb port from 8010 (staging uses 8003).
PORT=8010
while ss -ltn | awk '{print $4}' | grep -q ":$PORT\$" || grep -rqs -- "--port=$PORT" /etc/systemd/system/; do PORT=$((PORT + 1)); done

random() { openssl rand -hex "$1"; }
DB_PASSWORD="$(random 20)"
REVERB_ID="$((RANDOM * 100 + RANDOM))"
REVERB_KEY="$(random 10)"
REVERB_SECRET="$(random 20)"

echo "==> Code: $DIR"
mkdir "$DIR"
chown deploy:www-data "$DIR"
sudo -u deploy git clone --quiet --branch staging "$SOURCE" "$DIR"
sudo -u deploy git -C "$DIR" remote set-url origin "$(sudo -u deploy git -C "$SOURCE" remote get-url origin)"
sudo -u deploy git -C "$DIR" fetch --quiet origin
sudo -u deploy git -C "$DIR" reset --quiet --hard origin/staging
for part in vendor public/build public/downloads; do
  sudo -u deploy mkdir -p "$DIR/$part"
  sudo -u deploy rsync -a --delete "$SOURCE/$part/" "$DIR/$part/"
done
chown -R deploy:www-data "$DIR/storage" "$DIR/bootstrap/cache"
chmod -R ug+rwX "$DIR/storage" "$DIR/bootstrap/cache"
find "$DIR/storage" "$DIR/bootstrap/cache" -type d -exec chmod g+s {} +
# One log file the web server owns, as on staging.
install -o www-data -g www-data -m 664 /dev/null "$DIR/storage/logs/laravel.log"

echo "==> Database: $DB"
mysql <<SQL
CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> .env"
cat > "$DIR/.env" <<ENV
APP_NAME="Express POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$DB
DB_PASSWORD=$DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=720
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@$DOMAIN"
MAIL_FROM_NAME="\${APP_NAME}"

REVERB_APP_ID=$REVERB_ID
REVERB_APP_KEY=$REVERB_KEY
REVERB_APP_SECRET=$REVERB_SECRET
REVERB_HOST="$DOMAIN"
REVERB_PORT=443
REVERB_SCHEME=https

# The client's own, filled in when a module needs them:
# PAYSTACK_SECRET_KEY= / PAYSTACK_PUBLIC_KEY=   (Paystack module)
# ARKESEL_API_KEY= / ARKESEL_SENDER_ID=         (Customer SMS module)
# RESEND_API_KEY= then MAIL_MAILER=resend       (password-reset emails)
# SENTRY_LARAVEL_DSN=                           (error tracking)
ENV
chown deploy:www-data "$DIR/.env"
chmod 640 "$DIR/.env"
sudo -u deploy php "$DIR/artisan" key:generate --force --quiet

echo "==> PHP-FPM pool"
cat > "/etc/php/$PHP_VERSION/fpm/pool.d/$SLUG.conf" <<POOL
[$SLUG]
user = www-data
group = www-data
listen = $SOCK
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
POOL
php-fpm$PHP_VERSION -t
systemctl reload "php$PHP_VERSION-fpm"

echo "==> nginx: $DOMAIN"
cat > "/etc/nginx/sites-available/$SLUG" <<NGINX
server {
    server_tokens off;
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $DIR/public;
    index index.php;
    client_max_body_size 20M;

    access_log /var/log/nginx/$SLUG.access.log;
    error_log  /var/log/nginx/$SLUG.error.log;

    location /apps/ {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 60s;
    }

    location /app/ {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 60s;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:$SOCK;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -s "/etc/nginx/sites-available/$SLUG" "/etc/nginx/sites-enabled/$SLUG"
if ! nginx -t; then
  rm "/etc/nginx/sites-enabled/$SLUG"
  echo "nginx config test failed; the new site was taken out again and nginx was not reloaded." >&2
  exit 1
fi
systemctl reload nginx
certbot --nginx --non-interactive --agree-tos --redirect --register-unsafely-without-email -d "$DOMAIN"

echo "==> Queue worker and Reverb (port $PORT)"
for kind in queue reverb; do
  if [[ $kind == queue ]]; then
    START="/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600"; LABEL="queue worker"
  else
    START="/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=$PORT"; LABEL="Reverb websocket server"
  fi
  cat > "/etc/systemd/system/$SLUG-$kind.service" <<UNIT
[Unit]
Description=Express POS $NAME - $LABEL
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$DIR
ExecStart=$START
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT
done

cat > "/etc/sudoers.d/deploy-$SLUG" <<SUDO
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart $SLUG-queue.service
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart $SLUG-reverb.service
SUDO
chmod 440 "/etc/sudoers.d/deploy-$SLUG"
visudo -cf "/etc/sudoers.d/deploy-$SLUG"

echo "* * * * * www-data /usr/bin/php $DIR/artisan schedule:run >> /dev/null 2>&1" > "/etc/cron.d/$SLUG"

echo "==> Migrate"
cd "$DIR"
sudo -u deploy php artisan migrate --force
sudo -u deploy php artisan storage:link
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache

systemctl daemon-reload
systemctl enable --now "$SLUG-queue.service" "$SLUG-reverb.service"

cat <<DONE

Done: https://$DOMAIN

Next:
  1. Fill in the business:  cd $DIR && sudo -u www-data php artisan expresspos:new-client
  2. Add this install to the staging deploy: GitHub repo → Settings → Variables →
     EXPRESSPOS_EXTRA_INSTALLS, add "$SLUG" (space-separated).
  3. Add $DB to the nightly backups: /etc/expresspos/backup.conf.
DONE
