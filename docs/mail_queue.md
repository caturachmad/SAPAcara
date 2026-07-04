Mail queue worker

Overview

The project uses `mail_queue` table and a CLI worker `worker/send_mail_queue.php` to send queued emails asynchronously.

Migration

Run the provided migration to create the table:

```sql
mysql -u <user> -p < siakad_db < database/migrations/20260704_create_mail_queue.sql
```

Worker usage

Run manually (process up to 20 emails):

```bash
php worker/send_mail_queue.php 20
```

Run continuously via cron (recommended)

Add a cron job for the webapp system user (runs every minute):

```cron
* * * * * /usr/bin/php /var/www/html/siakad/worker/send_mail_queue.php 50 >> /var/log/siakad_mail.log 2>&1
```

Or use a systemd timer or supervisor for more robust management.

Notes

- The worker uses `sendMail()` from `config/mail.php` and will use the fallback if SMTP fails.
- Monitor `/var/log/siakad_mail.log` for failures and `/var/www/html/siakad/worker/send_mail_queue.php` output during manual runs.
