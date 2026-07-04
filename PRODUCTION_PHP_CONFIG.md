# PHP Configuration for Production — SAPAcara

This file contains recommended PHP settings for secure production deployment.

## Critical php.ini Settings (Production)

```ini
; ══════════════════════════════════════════════════════════════
; Security: Error Handling
; ══════════════════════════════════════════════════════════════

; NEVER show errors to users in production
display_errors = Off
display_startup_errors = Off

; Log errors to file outside web root
error_log = /var/log/php/sapacara.log
log_errors = On

; Report all errors internally
error_reporting = E_ALL

; ══════════════════════════════════════════════════════════════
; Security: File Upload
; ══════════════════════════════════════════════════════════════

; Maximum upload size (matches SAPAcara 20MB limit)
upload_max_filesize = 20M
post_max_size = 25M

; Restrict file uploads to temp directory only
file_uploads = On
upload_tmp_dir = /tmp/php_upload

; ══════════════════════════════════════════════════════════════
; Security: Session Management
; ══════════════════════════════════════════════════════════════

; Use secure session storage
session.save_path = /var/lib/php/sessions
session.save_handler = files

; Secure cookies (HTTPS only + HttpOnly + SameSite)
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Strict
session.use_strict_mode = On

; Session timeout (1 hour)
session.gc_maxlifetime = 3600

; Regenerate session ID on login
; (PHP code: session_regenerate_id(true))

; ══════════════════════════════════════════════════════════════
; Security: Function Restrictions
; ══════════════════════════════════════════════════════════════

; Disable dangerous functions
disable_functions = proc_open,proc_close,passthru,shell_exec,system,exec,popen,escapeshellcmd,escapeshellarg,pcntl_exec,pcntl_fork

; ══════════════════════════════════════════════════════════════
; Performance: Caching
; ══════════════════════════════════════════════════════════════

; Enable OpCache (PHP 5.5+)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0

; ══════════════════════════════════════════════════════════════
; Database
; ══════════════════════════════════════════════════════════════

; PDO MySQL connection timeout
pdo_mysql.default_socket = /var/run/mysqld/mysqld.sock

; ══════════════════════════════════════════════════════════════
; Database Limits (prevent resource exhaustion)
; ══════════════════════════════════════════════════════════════

; Max execution time
max_execution_time = 30

; Max input time (for file uploads)
max_input_time = 300

; Max memory per script
memory_limit = 256M
```

## Server Configuration Checklist

- [ ] Error logs stored outside web root: `/var/log/php/`
- [ ] Upload temp directory: `/tmp/php_upload` (0700 permissions, not web-accessible)
- [ ] Session directory: `/var/lib/php/sessions` (0700 permissions)
- [ ] HTTPS enabled with valid SSL certificate
- [ ] PHP-FPM running as separate user (not www-data for production)
- [ ] OPCache enabled and tuned for performance
- [ ] MySQL connection using Unix socket (not TCP)

## Nginx Configuration (if applicable)

```nginx
# Disable PHP execution in upload directory
location /uploads/ {
    location ~ \.php$ {
        deny all;
    }
}

# Security headers
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# HSTS (1 year, include subdomains)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

## Startup Script Check

Before starting PHP/application:
1. Verify `.env` file exists and is NOT in web root
2. Verify database credentials are correct
3. Verify `uploads/` directory exists with proper permissions
4. Verify mail configuration works (test email sending)
5. Run database migrations
6. Verify error logs are writable
