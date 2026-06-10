# Setup SAPAcara

## Persyaratan
- PHP 8.1+
- MySQL 8.0+
- Composer

## Langkah Instalasi

### 1. Install dependencies
```bash
composer install
```

### 2. Konfigurasi environment
```bash
cp .env.example .env
```
Edit `.env` dan isi dengan kredensial database kamu:
```
DB_HOST=localhost
DB_USER=nama_user_db
DB_PASS=password_db
DB_NAME=nama_database
BASE_URL=http://localhost/siakad
```

### 3. Setup database — jalankan SQL secara berurutan

```sql
-- Urutan wajib:
-- 1. database.sql          → tabel-tabel dasar
-- 2. migration_v2_admin_role.sql  → role admin, jabatan sistem, settings, permissions
-- 3. migration_v3_missing_tables.sql → tabel-tabel yang hilang dari v1
```

Di MySQL/phpMyAdmin, jalankan ketiga file tersebut sesuai urutan di atas.
Semua migration aman dijalankan ulang (`IF NOT EXISTS`).

### 4. Hak akses folder uploads
```bash
chmod -R 755 uploads/
```

### 5. Akses aplikasi
Buka `BASE_URL` yang sudah kamu set di `.env`.

---

## Catatan Keamanan
- File `.env` dan `config/db.php` sudah di-gitignore — jangan pernah commit kredensial
- Gunakan password database yang kuat di production
- Aktifkan HTTPS di production
