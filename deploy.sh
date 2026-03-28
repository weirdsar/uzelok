#!/usr/bin/env bash
# Deploy current project structure to Beget via SCP/SSH.
#
# Credentials: create .beget.env in this directory (see comments inside) or export:
#   BEGET_SSH_HOST, BEGET_SSH_USER, BEGET_REMOTE_DIR
#   BEGET_API_KEY - Beget API password from the panel (optional: API checks / tooling)
#
# Usage:
#   ./deploy.sh
#
# Requires: ssh, scp (OpenSSH). Uses your SSH key (~/.ssh/id_rsa or agent).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

ENV_FILE="${SCRIPT_DIR}/.beget.env"
if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck source=/dev/null
  source "$ENV_FILE"
  set +a
  echo "Loaded configuration from .beget.env (secrets are not printed)."
fi

BEGET_LOGIN="${BEGET_LOGIN:-}"
BEGET_SSH_HOST="${BEGET_SSH_HOST:-}"
BEGET_SSH_USER="${BEGET_SSH_USER:-}"
BEGET_REMOTE_DIR="${BEGET_REMOTE_DIR:-/home/w/user_name/uzelok64.ru}"
# Exported for child processes / future Beget API calls; not used by scp/ssh.
export BEGET_API_KEY="${BEGET_API_KEY:-}"

# Fallbacks: if only BEGET_LOGIN is set, derive common SSH settings.
if [[ -z "$BEGET_SSH_USER" && -n "$BEGET_LOGIN" ]]; then
  BEGET_SSH_USER="$BEGET_LOGIN"
fi
if [[ -z "$BEGET_SSH_HOST" && -n "$BEGET_LOGIN" ]]; then
  BEGET_SSH_HOST="${BEGET_LOGIN}.beget.com"
fi

if [[ -z "$BEGET_SSH_HOST" || -z "$BEGET_SSH_USER" ]]; then
  echo "Error: Set BEGET_SSH_HOST and BEGET_SSH_USER (SSH/SFTP host and username)." >&2
  echo "Add them to .beget.env or export them in the shell." >&2
  exit 1
fi

REMOTE="${BEGET_SSH_USER}@${BEGET_SSH_HOST}"

# Accept both project root (/.../uzelok64.ru) and docroot (/.../uzelok64.ru/public_html)
REMOTE_PUBLIC_DIR="$BEGET_REMOTE_DIR"
REMOTE_ROOT_DIR="$BEGET_REMOTE_DIR"
if [[ "${BEGET_REMOTE_DIR%/}" == */public_html ]]; then
  REMOTE_ROOT_DIR="${BEGET_REMOTE_DIR%/public_html}"
fi
REMOTE_PUBLIC_DIR="${REMOTE_ROOT_DIR}/public_html"

ROOT_ITEMS=(
  "core"
  "templates"
  "cron"
  "database/migrations"
  "config/config.example.php"
  "composer.json"
  "composer.lock"
  "vendor"
)

PUBLIC_ITEMS=(
  "public_html/admin"
  "public_html/assets"
  "public_html/index.php"
  "public_html/submit-form.php"
  "public_html/robots.txt"
  "public_html/sitemap.xml"
)

# Non-interactive by default so SSH does not hang waiting for a password when no key is loaded.
# Set BEGET_SSH_INTERACTIVE=1 in .beget.env (or export) to allow password prompts.
SSH_BATCH=(-o BatchMode=yes -o ConnectTimeout=20)
if [[ "${BEGET_SSH_INTERACTIVE:-}" == "1" ]]; then
  SSH_BATCH=(-o ConnectTimeout=60)
fi

echo "Ensuring remote directories exist..."
ssh "${SSH_BATCH[@]}" "$REMOTE" "mkdir -p '${REMOTE_ROOT_DIR}' '${REMOTE_PUBLIC_DIR}' '${REMOTE_ROOT_DIR}/database' '${REMOTE_ROOT_DIR}/config'"

echo "Uploading root items to ${REMOTE}:${REMOTE_ROOT_DIR}/"
for item in "${ROOT_ITEMS[@]}"; do
  if [[ ! -e "$item" ]]; then
    echo "Skip missing: $item"
    continue
  fi
  echo "  -> ${item}"
  scp "${SSH_BATCH[@]}" -r "$item" "${REMOTE}:${REMOTE_ROOT_DIR}/"
done

echo "Uploading public items to ${REMOTE}:${REMOTE_PUBLIC_DIR}/"
for item in "${PUBLIC_ITEMS[@]}"; do
  if [[ ! -e "$item" ]]; then
    echo "Skip missing: $item"
    continue
  fi
  echo "  -> ${item}"
  scp "${SSH_BATCH[@]}" -r "$item" "${REMOTE}:${REMOTE_PUBLIC_DIR}/"
done

# Dotfiles are not included by wildcard uploads, copy explicitly.
if [[ -f "public_html/.htaccess" ]]; then
  echo "  -> public_html/.htaccess"
  scp "${SSH_BATCH[@]}" "public_html/.htaccess" "${REMOTE}:${REMOTE_PUBLIC_DIR}/.htaccess"
fi

echo "Setting safe file permissions where possible..."
ssh "${SSH_BATCH[@]}" "$REMOTE" "chmod 644 '${REMOTE_PUBLIC_DIR}/.htaccess' 2>/dev/null || true"

echo "Done."
echo "Check: https://uzelok64.ru/"
