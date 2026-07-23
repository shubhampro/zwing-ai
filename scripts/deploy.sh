#!/usr/bin/env bash
set -euo pipefail

# Manual deploy on Ubuntu app server (same steps as GitHub Actions).
APP_DIR="${DEPLOY_PATH:-/var/www/zwing-ai}"
BRANCH="${DEPLOY_BRANCH:-main}"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1

cd "$APP_DIR"

echo "==> Deploying $(basename "$APP_DIR") @ ${BRANCH}"

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/${BRANCH}"

if ! php -m | grep -qi '^mongodb$'; then
  echo "ERROR: PHP ext-mongodb missing. Install: sudo apt-get install -y php8.4-mongodb"
  exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
php artisan package:discover --ansi
# Stale route/config cache makes Wayfinder omit new routes during vite build.
php artisan optimize:clear --no-interaction

export NVM_DIR="${HOME}/.nvm"
if [ -s "${NVM_DIR}/nvm.sh" ]; then
  # shellcheck disable=SC1091
  . "${NVM_DIR}/nvm.sh"
fi
hash -r
command -v npm >/dev/null || {
  echo "ERROR: npm not found in non-interactive PATH. Install Node or fix nvm."
  echo "PATH=${PATH}"
  exit 1
}

npm ci
php artisan wayfinder:generate --no-interaction
npm run build
php artisan migrate --force
php artisan optimize
php artisan horizon:terminate

if command -v systemctl >/dev/null 2>&1; then
  if sudo -n systemctl reload php8.4-fpm 2>/dev/null; then
    echo "==> Reloaded php8.4-fpm"
  fi
fi

echo "==> Deploy done: $(git rev-parse --short HEAD)"
