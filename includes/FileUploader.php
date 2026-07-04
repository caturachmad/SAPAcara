<?php
/**
 * FileUploader.php — Validasi keamanan file upload (MIME + magic bytes).
 *
 * Dipakai oleh modules/files/upload.php untuk memastikan extension file
 * COCOK dengan isi file yang sebenarnya (bukan cuma nama file), jadi file
 * berbahaya yang di-rename (mis. shell.php → shell.jpg) akan ditolak.
 *
 * Extension check yang lama (di upload.php) TETAP dipakai di awal untuk
 * pesan error yang cepat; class ini menambah lapisan verifikasi isi file.
 */
declare(strict_types=1);

class FileUploader
{
    /** Mapping extension → MIME type yang valid (harus cocok salah satu) */
    private const ALLOWED_MIME_TYPES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'zip'  => ['application/zip'],
        'rar'  => ['application/x-rar-compressed', 'application/x-rar', 'application/vnd.rar'],
    ];

    /** Magic bytes (hex, awal file) per extension untuk deteksi file palsu */
    private const MAGIC_BYTES = [
        'pdf'  => '25504446',
        'jpg'  => 'ffd8ff',
        'jpeg' => 'ffd8ff',
        'png'  => '89504e47',
        'zip'  => '504b0304',   // juga dipakai docx/xlsx/pptx (format ZIP)
        'doc'  => 'd0cf11e0',
        'xls'  => 'd0cf11e0',
        'ppt'  => 'd0cf11e0',
    ];

    /**
     * Cek apakah MIME type & magic bytes file sesuai dengan extension-nya.
     * Return array error (kosong = valid).
     */
    public static function validateContent(string $tmpPath, string $ext): array
    {
        $ext = strtolower($ext);
        $errors = [];

        if (!isset(self::ALLOWED_MIME_TYPES[$ext])) {
            // Extension di luar whitelist sudah ditangani upload.php,
            // di sini cukup lolos supaya tidak double pesan error.
            return $errors;
        }

        // --- Cek MIME type asli file (bukan dari nama/Content-Type client) ---
        $actualMime = self::detectMime($tmpPath);
        if ($actualMime !== null && !in_array($actualMime, self::ALLOWED_MIME_TYPES[$ext], true)) {
            $errors[] = "Isi file tidak sesuai dengan extension .$ext (terdeteksi: $actualMime). "
                      . "Kemungkinan file di-rename dari format lain.";
        }

        // --- Cek magic bytes (signature) di awal file ---
        $zipBased = in_array($ext, ['docx', 'xlsx', 'pptx'], true);
        $checkExt = $zipBased ? 'zip' : $ext;

        if (isset(self::MAGIC_BYTES[$checkExt])) {
            $handle = @fopen($tmpPath, 'rb');
            if ($handle !== false) {
                $bytes = fread($handle, 8);
                fclose($handle);
                $hex = bin2hex((string)$bytes);

                if (strpos($hex, self::MAGIC_BYTES[$checkExt]) !== 0) {
                    $errors[] = "File terindikasi rusak atau bukan format .$ext yang valid (signature tidak cocok).";
                }
            }
        }

        return $errors;
    }

    private static function detectMime(string $tmpPath): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                return $mime ?: null;
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpPath);
            return $mime ?: null;
        }
        // finfo & mime_content_type tidak tersedia — skip validasi MIME,
        // magic bytes check di atas tetap jalan sebagai lapisan kedua.
        return null;
    }
}
