#!/usr/bin/env bash
#
# Makes ONE real, GSIS-registered TAXISnet redirect_uri domain point at your own
# machine, so you can test a client_id whose registered domain isn't yours to modify (a
# partner's production app, a client's staging server, anything you can observe but
# shouldn't touch).
#
# Why this is necessary: GSIS validates redirect_uri strictly against whatever's actually
# registered for the client_id you're using, but doesn't reject a mismatch up front. The
# /oauth/authorize step always renders the real login form regardless of what redirect_uri
# you send, and the failure only shows up later, at the authentication step, as a generic
# "User Authentication Failed" with no further detail, indistinguishable from a wrong
# password or an inactive test user. There's no server-side cooperation to lean on, so the
# only thing you fully control is your own machine - this script makes GSIS's fixed
# callback resolve there directly instead, by stacking three things:
#   1. Overriding DNS for that one hostname, on your machine only (/etc/hosts, see below).
#   2. Terminating TLS locally with a certificate your own browser trusts, via Caddy's own
#      built-in local CA - no real CA-signed cert needed, since this only has to convince
#      your own machine.
#   3. Reverse-proxying the now-decrypted request straight to your local Laravel dev server.
#
# GSIS then redirects to whatever path is actually registered for that client, not
# whatever path you'd prefer. The package handles this itself: see
# TaxisnetOAuth::localDomainBounceUrl() and the redirect_uri_resolves_locally config flag
# it reads for how it auto-registers a matching route and keeps the CSRF state's session
# cookie scoped correctly across the bounce. Switching which real client you're testing is
# then just an .env change, never a route to hand-add.
#
# Usage:
#   taxisnet-local-domain.sh <domain> [local-port]
#
# Example:
#   taxisnet-local-domain.sh registered.domain.gr 80
#
# Safe by design: this script NEVER edits /etc/hosts or runs sudo itself. It prints the
# exact command for you to run, then exits so you can review it first.

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <domain> [local-port]" >&2
    echo "Example: $0 registered.domain.gr 80" >&2
    exit 1
fi

DOMAIN="$1"
PORT="${2:-8000}"
HOSTS_MARKER="# taxisnet-local-domain: ${DOMAIN}"

if ! command -v caddy >/dev/null 2>&1; then
    echo "caddy is not installed. Install it, then re-run this script:" >&2
    echo "  brew install caddy" >&2
    exit 1
fi

# Resolve via the real system resolver (not a raw /etc/hosts grep) so this also recognizes
# an entry someone added by hand, without our comment marker or in a different format.
resolves_to_localhost() {
    if command -v dscacheutil >/dev/null 2>&1; then
        dscacheutil -q host -a name "$DOMAIN" 2>/dev/null | grep -qE '^ip_address: 127\.0\.0\.1$'
    elif command -v getent >/dev/null 2>&1; then
        getent hosts "$DOMAIN" 2>/dev/null | grep -qE '^127\.0\.0\.1\b'
    else
        grep -qE "^[[:space:]]*127\.0\.0\.1[[:space:]].*\b${DOMAIN}\b" /etc/hosts
    fi
}

if ! resolves_to_localhost; then
    echo "Add this line to /etc/hosts, then re-run this script:" >&2
    echo >&2
    echo "  echo '127.0.0.1  ${DOMAIN}  ${HOSTS_MARKER}' | sudo tee -a /etc/hosts" >&2
    echo >&2
    echo "To remove it later:" >&2
    echo "  sudo sed -i '' '/${HOSTS_MARKER//\//\\/}/d' /etc/hosts" >&2
    exit 1
fi

CA_CERT="$HOME/Library/Application Support/Caddy/pki/authorities/local/root.crt"

# Caddy's own attempt to install its local CA into the system trust store can silently
# fail (observed: it also tries every detected Java install via keytool, and appears to
# abort the whole install routine, never reaching the system keychain, if that fails on
# a permission-protected JBR like Android Studio's bundled one). Checking it landed can't
# happen before starting Caddy on a truly fresh machine, since the CA doesn't exist until
# Caddy generates it on first use. So this starts Caddy in the background, gives it a
# moment to attempt cert generation and trust install, and only then checks, killing it
# and reporting the fix instead of leaving you to hit a confusing browser cert error with
# no link back to the real cause.
echo "Proxying https://${DOMAIN} -> http://localhost:${PORT} (Ctrl+C to stop)"
echo
# --to localhost (not 127.0.0.1): "php artisan serve"/"php -S" often bind the IPv6 loopback
# (::1) under the name "localhost". Targeting 127.0.0.1 explicitly can 502 if so.
# --internal-certs: caddy issues its own certificate for $DOMAIN from its built-in local CA.
# No mkcert needed.
caddy reverse-proxy --from "${DOMAIN}:443" --to "localhost:${PORT}" --internal-certs &
CADDY_PID=$!
# Ctrl+C (or closing the terminal) just stops proxying; the hosts entry above and
# Caddy's trusted local CA both persist for next time. To remove the CA entirely: caddy untrust
trap 'kill "$CADDY_PID" 2>/dev/null' EXIT INT TERM

if command -v security >/dev/null 2>&1; then
    for _ in $(seq 1 25); do
        [[ -f "$CA_CERT" ]] && break
        sleep 0.2
    done

    if [[ -f "$CA_CERT" ]] && [[ -z "$(security find-certificate -a -c "Caddy Local Authority" /Library/Keychains/System.keychain 2>/dev/null)" ]]; then
        echo >&2
        echo "Caddy's local CA isn't trusted by this system. Its own auto-install attempt" >&2
        echo "failed. Install it yourself, then re-run this script:" >&2
        echo >&2
        echo "  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain \\" >&2
        echo "    \"${CA_CERT}\"" >&2
        echo >&2
        echo "This is one-time per machine. Once trusted, it applies to every future domain" >&2
        echo "this script proxies, not just the one you're testing right now." >&2
        exit 1
    fi
fi

wait "$CADDY_PID"
