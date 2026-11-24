#!/usr/bin/env bash
#
# Express POS: put a client's customer website on its own domain, for the
# admin portal (owner, 2026-11-24). Installed by root as
# /usr/local/sbin/expresspos-add-website-domain (root-owned, 755); the
# portal's worker user may run it with sudo.
#
#   expresspos-add-website-domain <slug> <domain>       e.g. deboyes deboyeskitchen.com
#   expresspos-add-website-domain <slug> --remove
#
# Before it changes anything it checks the domain (and www., if it has a
# record) points at this server. Then: the domain joins the client's nginx
# site, its SSL certificate is issued (certbot, expanded from the POS
# address's own), nginx is reloaded only after `nginx -t` passes, and the
# install is told (artisan expresspos:website-domain). The POS address,
# <slug>.pos.solusysdigital.com, never changes. Last line:
# "EXPRESSPOS_RESULT {json}".

set -euo pipefail

CONF=/etc/expresspos/provision.conf
DOMAIN_SUFFIX=""
# shellcheck source=/dev/null
[[ -r "$CONF" ]] && source "$CONF"

fail() { echo "EXPRESSPOS_RESULT {\"ok\":false,\"error\":\"$1\"}"; exit 1; }

[[ $EUID -eq 0 ]] || fail "Must run as root."
SLUG="${1:-}"; DOMAIN="${2:-}"
[[ "$SLUG" =~ ^[a-z][a-z0-9]{1,20}$ ]] || fail "Unknown client."
[[ -n "$DOMAIN_SUFFIX" ]] || fail "DOMAIN_SUFFIX is not set in $CONF."
DIR="/var/www/expresspos-$SLUG"
SITE="/etc/nginx/sites-available/expresspos-$SLUG"
POS_HOST="$SLUG.$DOMAIN_SUFFIX"
[[ -f "$DIR/.env" && -f "$SITE" ]] || fail "No install for this client."

if [[ "$DOMAIN" == "--remove" ]]; then
  sed -i -E "s/^(\s*server_name) .*;/\1 $POS_HOST;/" "$SITE"
  nginx -t || fail "nginx config test failed."
  systemctl reload nginx
  cd "$DIR" && sudo -u www-data php artisan expresspos:website-domain --clear
  echo "EXPRESSPOS_RESULT {\"ok\":true,\"domain\":null}"
  exit 0
fi

DOMAIN="${DOMAIN,,}"; DOMAIN="${DOMAIN#www.}"
[[ "$DOMAIN" =~ ^[a-z0-9-]{1,63}(\.[a-z0-9-]{1,63})+$ ]] || fail "That isn't a domain name."
[[ "$DOMAIN" != *solusysdigital.com ]] || fail "Use the restaurant's own domain, not a solusysdigital.com address."

SERVER_IP="$(curl -4 -s https://ifconfig.me)"
# Points here directly, or is served through Cloudflare's proxy (its address
# is then Cloudflare's, never ours; Yes My Shawarma's is, 2026-11-24). Either
# way the certificate step below proves the domain really reaches this
# server, and puts everything back if it doesn't.
resolves() { [[ "$(getent ahostsv4 "$1" | awk 'NR==1 {print $1}')" == "$SERVER_IP" ]]; }
proxied() { curl -sI --max-time 10 "http://$1/" | grep -qi '^server: cloudflare'; }
reaches() { resolves "$1" || proxied "$1"; }
reaches "$DOMAIN" || fail "$DOMAIN does not point at this server yet ($SERVER_IP). Add the A record and try again in a few minutes."
proxied "$DOMAIN" && echo "$DOMAIN is served through Cloudflare; the certificate step checks it reaches this server."

NAMES=("$POS_HOST" "$DOMAIN")
reaches "www.$DOMAIN" && NAMES+=("www.$DOMAIN") || echo "www.$DOMAIN has no record pointing here; only $DOMAIN is added."

echo "==> nginx: ${NAMES[*]}"
cp "$SITE" "$SITE.bak"
sed -i -E "s/^(\s*server_name) .*;/\1 ${NAMES[*]};/" "$SITE"
if ! nginx -t; then
  mv "$SITE.bak" "$SITE"
  fail "nginx config test failed; nothing was changed."
fi
systemctl reload nginx

echo "==> SSL certificate"
CERT_ARGS=()
for name in "${NAMES[@]}"; do CERT_ARGS+=(-d "$name"); done
if ! certbot --nginx --non-interactive --agree-tos --redirect --register-unsafely-without-email --expand --cert-name "$POS_HOST" "${CERT_ARGS[@]}"; then
  mv "$SITE.bak" "$SITE"; nginx -t && systemctl reload nginx
  fail "The SSL certificate could not be issued; the site was put back as it was."
fi
rm -f "$SITE.bak"

echo "==> Telling the install"
cd "$DIR" && sudo -u www-data php artisan expresspos:website-domain "$DOMAIN"

echo "EXPRESSPOS_RESULT {\"ok\":true,\"domain\":\"$DOMAIN\"}"
