# Express POS: setting up a client install by hand

Normally clients are onboarded in **Express POS Admin** (the
`winning18/expresspos-admin` repo), which runs `provision.sh` for you. This
page is the same thing by hand, for when the portal isn't available. Until the MVP release, client installs run the `staging`
code, and the staging deploy updates them too.

You need: the client's short name (lowercase, e.g. `mamaput`), and DNS access
for solusysdigital.com.

## 1. DNS (in your DNS provider)

Add an **A record**: `mamaput.pos` → `161.35.170.51`.
Wait until it resolves. On your Mac: `dig +short mamaput.pos.solusysdigital.com`
should print `161.35.170.51`.

## 2. Create the install (on the server)

From your Mac's Terminal:

```sh
ssh root@161.35.170.51
cd /var/www/yes-my-shawarma-staging
bash deploy/new-install.sh mamaput mamaput.pos.solusysdigital.com
```

It creates the folder, database, web server site with SSL, queue worker,
real-time server and scheduler, then migrates. It stops without changing
anything if the folder already exists or the DNS isn't pointing here yet.

## 3. Fill in the business (still on the server)

```sh
cd /var/www/expresspos-mamaput
sudo -u www-data php artisan expresspos:new-client
```

It asks for the business name, first branch (with its map position), the
owner's name and email, an optional Solusys Digital support email, and which
modules to switch on. It prints each account's temporary password once:
send the owner theirs privately.

## 4. Keep it updated

GitHub → the repository → Settings → Secrets and variables → Actions →
**Variables** → `EXPRESSPOS_EXTRA_INSTALLS` = `expresspos-mamaput`
(space-separated for more than one). Every push to `staging` then updates it.

## 5. Nightly backups (once per server)

```sh
ssh root@161.35.170.51
install -m 755 /var/www/yes-my-shawarma-staging/deploy/backup-databases.sh /usr/local/bin/expresspos-backup
mkdir -p /etc/expresspos && touch /etc/expresspos/backup.conf && chmod 600 /etc/expresspos/backup.conf
nano /etc/expresspos/backup.conf
```

with:

```sh
DATABASES="expresspos_mamaput"
KEEP_DAYS=14
OFFSITE="spaces:expresspos-backups"
```

then:

```sh
echo '30 2 * * * root /usr/local/bin/expresspos-backup >> /var/log/expresspos-backup.log 2>&1' > /etc/cron.d/expresspos-backup
/usr/local/bin/expresspos-backup     # one run now, to check
```

Off-site copies use rclone with a DigitalOcean Spaces bucket (`apt install
rclone`, then `rclone config` with the bucket's access key and secret).
Leave `OFFSITE` empty until that's set up.

## Restoring a backup

```sh
gunzip -c /var/backups/expresspos/expresspos_mamaput/expresspos_mamaput-2026-12-01-0230.sql.gz | mysql expresspos_mamaput
```

Only ever into the install's own database, and only after taking a fresh backup first.
