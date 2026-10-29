#!/bin/sh
set -eu

port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a numeric TCP port." >&2
        exit 1
        ;;
esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535." >&2
    exit 1
fi

sed -i "s/@@PORT@@/${port}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
mkdir -p /var/www/html/assets/uploads/products /var/www/html/assets/uploads/vip
chown www-data:www-data /var/www/html/assets/uploads \
    /var/www/html/assets/uploads/products \
    /var/www/html/assets/uploads/vip
chmod 0775 /var/www/html/assets/uploads \
    /var/www/html/assets/uploads/products \
    /var/www/html/assets/uploads/vip

exec apache2-foreground
