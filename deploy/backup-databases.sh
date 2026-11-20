#!/usr/bin/env bash
#
# Express POS: nightly database backups for every install on this server.
# Installed by root as /usr/local/bin/expresspos-backup and run from
# /etc/cron.d/expresspos-backup at 02:30 Accra time:
#
#   30 2 * * * root /usr/local/bin/expresspos-backup >> /var/log/expresspos-backup.log 2>&1
#
# Settings in /etc/expresspos/backup.conf (root only, chmod 600):
#
#   DATABASES="expresspos_mamaput"          # space-separated
#   KEEP_DAYS=14                            # on this server
#   OFFSITE="spaces:expresspos-backups"     # optional: an rclone remote:bucket
#
# Each database is dumped (consistent, without locking the tills) and
# gzipped to /var/backups/expresspos/<database>/<database>-<date>.sql.gz,
# copies older than KEEP_DAYS removed. With OFFSITE set, the night's files
# are also copied there with rclone (kept there until you delete them).
# A failed dump stops with an error in the log; the other databases still run.

set -uo pipefail

CONF=/etc/expresspos/backup.conf
ROOT=/var/backups/expresspos
DATABASES=""
KEEP_DAYS=14
OFFSITE=""

# shellcheck source=/dev/null
[[ -r "$CONF" ]] && source "$CONF"

if [[ -z "$DATABASES" ]]; then echo "$(date -Is) no DATABASES in $CONF, nothing to back up"; exit 0; fi

STAMP="$(TZ=Africa/Accra date +%Y-%m-%d-%H%M)"
FAILED=0

for DB in $DATABASES; do
  DIR="$ROOT/$DB"
  FILE="$DIR/$DB-$STAMP.sql.gz"
  mkdir -p "$DIR"
  chmod 700 "$ROOT" "$DIR"

  if mysqldump --single-transaction --quick --routines --triggers --no-tablespaces "$DB" | gzip -9 > "$FILE.part" \
      && gzip -t "$FILE.part"; then
    mv "$FILE.part" "$FILE"
    chmod 600 "$FILE"
    echo "$(date -Is) $DB backed up: $FILE ($(du -h "$FILE" | cut -f1))"
  else
    rm -f "$FILE.part"
    echo "$(date -Is) ERROR: $DB backup failed"
    FAILED=1
    continue
  fi

  find "$DIR" -name "$DB-*.sql.gz" -mtime +"$KEEP_DAYS" -delete

  if [[ -n "$OFFSITE" ]]; then
    if command -v rclone > /dev/null && rclone copy --quiet "$FILE" "$OFFSITE/$DB/"; then
      echo "$(date -Is) $DB copied off-site to $OFFSITE/$DB/"
    else
      echo "$(date -Is) ERROR: $DB off-site copy failed (is rclone set up for $OFFSITE?)"
      FAILED=1
    fi
  fi
done

exit $FAILED
