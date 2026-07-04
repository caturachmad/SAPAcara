# 🚀 SAPAcara Production Deployment Checklist

**Last Updated:** 2026-07-04  
**Status:** 🔴 CRITICAL ISSUES MUST BE FIXED FIRST

---

## ⚠️ CRITICAL: Must Fix Before Production

- [ ] **Move MAIL credentials to .env** ← SECURITY BREACH
  - Remove hardcoded credentials from `config/mail.php`
  - Move to `.env` file (already implemented)
  - Verify `.env` is in `.gitignore`

- [ ] **Review database password** ← SECURITY BREACH  
  - Current: `Siakad@2026` (change to strong password)
  - Must be 12+ chars, alphanumeric + special chars
  - Update `.env` and MySQL user

- [ ] **Verify CSRF protection on ALL forms**
  - ✅ Added to: workspace.php, approvals/index.php, files/edit.php
  - TODO: Verify on all POST forms

- [ ] **Fix race condition in approvals**
  - ✅ Implemented database lock in `modules/approvals/index.php`
  - Test with concurrent approval submissions

- [ ] **Add file content validation on edit**
  - ✅ Added FileUploader validation to `modules/files/edit.php`
  - Test file replacement security

- [ ] **Add path traversal protection**
  - ✅ Added to download.php and preview.php
  - Test with malicious paths

---

## 🔒 Security Configuration

### Server-Level

- [ ] **HTTPS/SSL Setup**
  - [ ] Valid SSL certificate installed
  - [ ] HSTS header configured (1 year minimum)
  - [ ] Redirect HTTP → HTTPS

- [ ] **PHP Security Settings**
  - [ ] `display_errors = Off` (production)
  - [ ] `error_log` configured outside web root
  - [ ] `disable_functions` configured
  - [ ] `upload_max_filesize = 20M` (matches app)
  - [ ] `session.cookie_secure = On`
  - [ ] `session.cookie_httponly = On`
  - [ ] `session.cookie_samesite = Strict`

- [ ] **File Permissions**
  - [ ] `.env` file: `600` (owner only)
  - [ ] `config/` directory: `755`
  - [ ] `uploads/` directory: `755`, owner = web server user
  - [ ] PHP error log directory: `700`, writable by PHP-FPM

### Application-Level

- [ ] **config/security-headers.php included**
  - [ ] Add to `includes/layout/header.php`
  - Ensures X-Frame-Options, CSP, etc. sent

- [ ] **Environment Variables**
  - [ ] `MAIL_HOST` configured correctly
  - [ ] `MAIL_USERNAME` & `MAIL_PASSWORD` set
  - [ ] `MAIL_FROM` set to valid email
  - [ ] `DB_PASSWORD` changed from default
  - [ ] `BASE_URL` set to production domain

---

## 🗄️ Database

- [ ] **Run all migrations in order**
  ```sql
  -- Run in this exact order:
  -- 1. database.sql
  -- 2. migration_PR10_indexes.sql
  -- (others if applicable)
  ```

- [ ] **Database Backups**
  - [ ] Automated daily backups configured
  - [ ] Backups stored outside web root
  - [ ] Test restore process

- [ ] **Database User**
  - [ ] Create dedicated `siakad_user` (not root)
  - [ ] Limit privileges to `siakad_db` only
  - [ ] Strong password (12+ chars)

---

## 📧 Email Configuration

- [ ] **SMTP Credentials Verified**
  - [ ] Gmail: Use "App Password" (not account password)
  - [ ] Other providers: SMTP host/port/credentials tested
  - [ ] Send test email manually

- [ ] **Email Templates**
  - [ ] Test approval notification emails
  - [ ] Test panitia invitation emails
  - [ ] Test SWOT evaluation emails
  - [ ] Verify links in emails work correctly

- [ ] **Email Logging**
  - [ ] Failed emails logged to `error_log`
  - [ ] Monitor mailbox delivery status

---

## 📁 File Upload Security

- [ ] **Upload Directory Protected**
  - [ ] `uploads/` NOT in web root or 404-redirected
  - [ ] PHP execution disabled in uploads directory
    - Nginx: Add `location ~ \.php$` block
    - Apache: `.htaccess` with `php_flag engine off`
  - [ ] Directory listing disabled

- [ ] **File Validation**
  - [ ] Test upload of malicious files (blocked)
  - [ ] Test upload of valid files (accepted)
  - [ ] File type validation working
  - [ ] MIME type detection working
  - [ ] Magic bytes check working

- [ ] **Upload Limits**
  - [ ] `upload_max_filesize = 20M` (matches app)
  - [ ] Test file > 20MB (rejected)
  - [ ] Test disk space monitoring

---

## 🔑 Authentication & Authorization

- [ ] **Login Security**
  - [ ] Rate limiting active (5 attempts / 60 sec)
  - [ ] Test with failed login attempts
  - [ ] CSRF token validation on login form
  - [ ] Session regeneration on login

- [ ] **Role-Based Access Control**
  - [ ] Super Admin: All permissions
  - [ ] Admin: Appropriate permissions
  - [ ] Staff: Restricted permissions
  - [ ] Test permission matrix works

- [ ] **Session Security**
  - [ ] Session timeout: 1 hour (configurable)
  - [ ] Session storage: External DB or secure file
  - [ ] Logout clears all session data

---

## 🧪 Testing

- [ ] **Core Workflows**
  - [ ] [ ] Event creation workflow
  - [ ] [ ] File upload workflow
  - [ ] [ ] Approval workflow
  - [ ] [ ] Panitia confirmation workflow
  - [ ] [ ] SWOT evaluation workflow

- [ ] **Error Scenarios**
  - [ ] [ ] Database connection fails → graceful error
  - [ ] [ ] File upload fails → proper error message
  - [ ] [ ] Email sending fails → logged, fallback to PHP mail()
  - [ ] [ ] Invalid CSRF token → 403 error
  - [ ] [ ] Unauthorized access → 403 error

- [ ] **Load Testing**
  - [ ] Simulate concurrent users
  - [ ] Monitor database query performance
  - [ ] Monitor memory/CPU usage
  - [ ] Check for bottlenecks

---

## 📊 Monitoring & Alerting

- [ ] **Error Logging**
  - [ ] PHP error log monitored
  - [ ] Database error log monitored
  - [ ] Application logs in `/var/log/php/`

- [ ] **Health Checks**
  - [ ] Database connectivity test
  - [ ] SMTP connectivity test
  - [ ] File upload directory writable test
  - [ ] Disk space monitoring

- [ ] **Alerting**
  - [ ] Errors trigger alerts (email/Slack)
  - [ ] Disk space > 80% → alert
  - [ ] Database > 100MB → alert

---

## 📋 Post-Deployment Verification

Run these checks **after deploying to production**:

```bash
# 1. Check HTTPS is enforced
curl -I https://your-domain.com/

# 2. Check security headers present
curl -I https://your-domain.com/ | grep X-

# 3. Test login with valid credentials
# (Manual test in browser)

# 4. Check error logs for issues
tail -f /var/log/php/sapacara.log

# 5. Verify database connectivity
# (Check application logs)

# 6. Test file upload
# (Upload a test file via UI)

# 7. Verify email sending
# (Create test event, check inbox)

# 8. Monitor resources
top
df -h
```

---

## 📝 Deployment Notes

**Deployed By:** [Name]  
**Deployment Date:** [Date]  
**Environment:** Production  
**Domain:** [Domain URL]  
**Database Version:** MySQL 8.0+  
**PHP Version:** 8.1+  

### Issues Encountered:
- [ ] (none yet)

### Resolution:
- [ ] N/A

---

## ✅ Sign-off

- [ ] Security team approved
- [ ] DevOps team approved
- [ ] Product owner approved

**Ready for production:** 🟢 YES / 🔴 NO
