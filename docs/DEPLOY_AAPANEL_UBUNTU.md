# Deploy on Ubuntu with aaPanel

## Requirements

- Ubuntu VPS
- aaPanel
- Nginx or Apache
- PHP 8.2+
- MySQL 8 or MariaDB
- Composer
- Node.js 20+

Recommended PHP extensions:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `json`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `tokenizer`
- `xml`
- `zip`

Install/refresh CA certificates so provider HTTPS requests validate correctly:

```bash
sudo apt update
sudo apt install -y ca-certificates
sudo update-ca-certificates
```

Do not disable SSL verification for provider APIs. If the server has broken CA certificates, FutIA logs cURL error 60 as:

```txt
SSL certificate validation failed. Check CA certificates on the server.
```

## Database

Create a database and user in aaPanel, then configure:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=futia_data_hub
DB_USERNAME=futia_user
DB_PASSWORD=change-me
ADMIN_EMAIL=admin@your-domain.com
ADMIN_PASSWORD=change-this-before-seed
```

## Install

```bash
cd /www/wwwroot/futia-data-hub
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Permissions

```bash
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Adjust the `www` user/group if your aaPanel PHP-FPM user is different.

## Nginx

Point the site root to:

```txt
/www/wwwroot/futia-data-hub/public
```

Use a Laravel-friendly rewrite:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Enable SSL in aaPanel and do not disable certificate verification for outbound provider APIs.

## Scheduler Cron

```bash
* * * * * cd /www/wwwroot/futia-data-hub && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler calls `php artisan futia:sync:run`, which creates due scheduled jobs from `sync_schedules` and then processes pending sync jobs.

## Supervisor Queue Worker

Create a Supervisor program in aaPanel:

```ini
[program:futia-data-hub-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /www/wwwroot/futia-data-hub/artisan queue:work database --sleep=3 --tries=1 --timeout=1200
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/futia-data-hub/storage/logs/worker.log
stopwaitsecs=1300
```

## Maintenance

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up
```

## Production Monitoring

Use the admin dashboard to monitor:

- provider health
- request logs and response times
- 429/rate-limit hits
- cooldown API keys
- failed sync jobs
- data coverage by league and season

For a failed sync, open the job detail page and inspect `last_error`, item errors, related request logs, and response excerpts. SSL/cURL certificate problems are logged with a friendly CA certificate message.

Operational exports are available from the admin:

- request logs CSV
- sync jobs CSV
- sync job items CSV

Use Supervisor for queue workers when moving long sync runs off the web request path.

Useful queue and sync commands:

```bash
php artisan queue:work database --sleep=3 --tries=1 --timeout=1200
php artisan queue:restart
php artisan futia:sync:run
php artisan futia:sync:run --sync
php artisan futia:sync:recover-stale --minutes=60
```
