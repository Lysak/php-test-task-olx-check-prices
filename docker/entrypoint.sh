#!/bin/bash
set -e

echo "Waiting for database connection..."
TIMEOUT=120
count=0
until nc -z mariadb 3306; do
  echo "Waiting for MariaDB..."
  sleep 3
  count=$((count+3))
  if [ $count -ge $TIMEOUT ]; then
    echo "MariaDB did not become ready in $TIMEOUT seconds"
    exit 1
  fi
done
echo "Database is up - continuing startup"

# -------------------------
# Ensure .env file exists
# -------------------------
if [ ! -f ".env" ]; then
  echo "Must create .env"
fi

# -------------------------
# Create required directories FIRST, then set permissions
# -------------------------
# User and group under which PHP-FPM/Nginx is running
PHP_USER=www-data
PHP_GROUP=www-data

# Create all directories upfront (mkdir -p is idempotent)
mkdir -p \
  storage/app/public \
  storage/app/private \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/supervisor \
  bootstrap/cache

# Give Laravel ownership
chown -R $PHP_USER:$PHP_GROUP storage bootstrap/cache

# Directories: rwxrwxr-x
find storage -type d -exec chmod 775 {} \;
find bootstrap/cache -type d -exec chmod 775 {} \;

# Files: rw-rw-r--
find storage -type f -exec chmod 664 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;

# Private files: rwxrwx--- (no public read)
find storage/app/private -type d -exec chmod 770 {} \;
find storage/app/private -type f -exec chmod 660 {} \;

# Supervisor dir: root owns it, www-data can write
chown -R root:$PHP_GROUP storage/supervisor
chmod -R 775 storage/supervisor

# -------------------------
# Optional: run tests (uncomment if needed)
# -------------------------

# -------------------------
# Run migrations
# -------------------------
echo "Running migrations..."
php artisan migrate --force

# -------------------------
# Start supervisord
# -------------------------
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
