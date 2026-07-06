<?php
/**
 * mail.php — Email Configuration & Template
 * 
 * SECURITY NOTES:
 * - Mail credentials HARUS berasal dari .env file, BUKAN hardcoded
 * - Gunakan App Password untuk Gmail, bukan password akun utama
 * - Jangan expose error messages yang mengandung SMTP details ke user
 */

declare(strict_types=1);

// Load mail configuration from .env
$mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
$mailPort = (int)($_ENV['MAIL_PORT'] ?? 587);
$mailUsername = $_ENV['MAIL_USERNAME'] ?? '';
$mailPassword = $_ENV['MAIL_PASSWORD'] ?? '';
$mailFrom = $_ENV['MAIL_FROM'] ?? '';
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'SAPAcara';
$appName = $_ENV['APP_NAME'] ?? 'SAPAcara';

// Validate mail configuration
if (empty($mailUsername) || empty($mailPassword) || empty($mailFrom)) {
    error_log('[WARNING] Mail configuration incomplete in .env file');
}

// Define mail constants
define('MAIL_HOST', $mailHost);
define('MAIL_PORT', $mailPort);
define('MAIL_USERNAME', $mailUsername);
define('MAIL_PASSWORD', $mailPassword);
define('MAIL_FROM', $mailFrom);
define('MAIL_FROM_NAME', $mailFromName);
define('APP_NAME', $appName);

// Enable mail logging
define('MAIL_DEBUG', PHP_SAPI !== 'cli' && !empty($_ENV['MAIL_DEBUG']));

// ────────────────────────────────────────────────────────────────────────────
// Send email with PHPMailer
// ────────────────────────────────────────────────────────────────────────────

function sendMail(
    string $to,
    string $toName,
    string $subject,
    string $html,
    array $replyTo = []
): bool {
    if (empty(MAIL_USERNAME)) {
        error_log('[Mail] Mail configuration is incomplete. Email not sent to: ' . $to);
        return false;
    }
    
    // Validate email format
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[Mail] Invalid recipient email: ' . $to);
        return false;
    }
    
    if (!filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
        error_log('[Mail] Invalid sender email in configuration');
        return false;
    }
    
    // Sanitize subject and name
    $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $toName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Timeout settings
        $mail->Timeout = 10;
        $mail->SMTPKeepAlive = true;
        
        // Character set and encoding
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64;
        
        // Message setup
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        
        // Reply-to if provided
        if (!empty($replyTo)) {
            foreach ($replyTo as $email => $name) {
                $mail->addReplyTo($email, $name);
            }
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        
        // Send
        if ($mail->send()) {
            error_log('[Mail] Email sent successfully to: ' . $to);
            return true;
        } else {
            error_log('[Mail] Failed to send email to ' . $to . ': ' . $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log('[Mail] Exception while sending to ' . $to . ': ' . $e->getMessage());
        
        // Try fallback only if SMTP failed
        if (sendMailFallback($to, $toName, $subject, $html)) {
            error_log('[Mail] Sent using fallback mail() to: ' . $to);
            return true;
        }
        
        return false;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Fallback menggunakan mail() function
// ────────────────────────────────────────────────────────────────────────────

function sendMailFallback(
    string $to,
    string $toName,
    string $subject,
    string $html
): bool {
    // Validate email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Create headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>';
    $headers[] = 'Reply-To: ' . MAIL_FROM;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    // Sanitize inputs
    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    
    // Send using mail()
    return @mail($to, $subject, $html, implode("\r\n", $headers));
}

// ────────────────────────────────────────────────────────────────────────────
// Email Templates
// ────────────────────────────────────────────────────────────────────────────

function mailTemplatePanitia(
    array $user,
    array $event,
    string $bagian,
    string $token
): string {
    // Validate inputs
    $userId = (int)($user['id'] ?? 0);
    $eventId = (int)($event['id'] ?? 0);
    
    if ($userId <= 0 || $eventId <= 0) {
        error_log('[Mail Template] Invalid user or event ID');
        return '';
    }
    
    $nama = htmlspecialchars($user['nama'] ?? 'Panitia', ENT_QUOTES, 'UTF-8');
    $judul = htmlspecialchars($event['judul'] ?? 'Acara', ENT_QUOTES, 'UTF-8');
    $lokasi = htmlspecialchars($event['lokasi'] ?? '-', ENT_QUOTES, 'UTF-8');
    $level = htmlspecialchars($event['level'] ?? 'Umum', ENT_QUOTES, 'UTF-8');
    $bagian = htmlspecialchars($bagian ?: 'Panitia', ENT_QUOTES, 'UTF-8');
    
    $tglMulai = date('d M Y', strtotime($event['tanggal_mulai'] ?? 'now'));
    $tglSelesai = date('d M Y', strtotime($event['tanggal_selesai'] ?? 'now'));
    $tglInfo = $tglMulai === $tglSelesai ? $tglMulai : "$tglMulai – $tglSelesai";
    
    // Validate and encode token
    if (!preg_match('/^[a-f0-9]{32,}$/i', $token)) {
        error_log('[Mail Template] Invalid token format');
        return '';
    }
    
    $encodedToken = urlencode($token);
    $urlYa = BASE_URL . '/modules/panitia/confirm.php?token=' . $encodedToken . '&jawab=bersedia';
    $urlTidak = BASE_URL . '/modules/panitia/confirm.php?token=' . $encodedToken . '&jawab=tidak_bisa';
    
    $content = <<<HTML
        <p>Halo, <strong>$nama</strong>!</p>
        <p>Kamu diundang sebagai <strong style="color:#1a3a5c">$bagian</strong> dalam acara:</p>
        <div style="background:#f0f4f8;border-left:4px solid #245a8a;padding:16px;border-radius:0 8px 8px 0;margin:16px 0">
            <h3 style="margin:0 0 8px;color:#1a3a5c">$judul</h3>
            <p style="margin:4px 0">📅 $tglInfo</p>
            <p style="margin:4px 0">📍 $lokasi</p>
            <p style="margin:4px 0">🏫 Level: <strong>$level</strong></p>
        </div>
        <p>Konfirmasi kesediaanmu:</p>
        <div style="text-align:center;margin:24px 0">
            <a href="$urlYa" target="_blank" rel="noopener noreferrer" 
               style="background:#10b981;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;margin:4px">
                ✅ Saya Bersedia
            </a>
            <a href="$urlTidak" target="_blank" rel="noopener noreferrer" 
               style="background:#ef4444;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;margin:4px">
                ❌ Tidak Bisa
            </a>
        </div>
        <p style="font-size:12px;color:#64748b;text-align:center;margin-bottom:4px">
            Jika tombol tidak berfungsi, silakan salin dan tempel link berikut di browser:
        </p>
        <p style="font-size:12px;color:#0f172a;text-align:center;word-break:break-all">
            <a href="$urlYa" target="_blank" rel="noopener noreferrer" style="color:#1a3a5c;text-decoration:underline">
                $urlYa
            </a>
        </p>
        <p style="font-size:12px;color:#94a3b8;text-align:center">
            Link berlaku 7 hari sejak email ini diterima.
        </p>
    HTML;
    
    return mailLayout('Undangan Panitia: ' . $judul, $content);
}

function mailTemplateApproval(array $approver, array $event, string $tipe): string {
    $nama = htmlspecialchars($approver['nama'] ?? 'Approver', ENT_QUOTES, 'UTF-8');
    $judul = htmlspecialchars($event['judul'] ?? 'Acara', ENT_QUOTES, 'UTF-8');
    $level = htmlspecialchars($event['level'] ?? 'Umum', ENT_QUOTES, 'UTF-8');
    $tipe = htmlspecialchars($tipe, ENT_QUOTES, 'UTF-8');
    $tglMulai = date('d M Y', strtotime($event['tanggal_mulai'] ?? 'now'));
    
    $url = BASE_URL . '/modules/approvals/';
    
    $content = <<<HTML
        <p>Halo, <strong>$nama</strong>!</p>
        <p>Kamu mendapat permintaan approval sebagai <strong>$tipe</strong> untuk:</p>
        <div style="background:#f0f4f8;border-left:4px solid #f59e0b;padding:16px;border-radius:0 8px 8px 0;margin:16px 0">
            <h3 style="margin:0 0 8px;color:#1a3a5c">$judul</h3>
            <p style="margin:4px 0">📅 $tglMulai</p>
            <p style="margin:4px 0">🏫 Level: <strong>$level</strong></p>
        </div>
        <div style="text-align:center;margin:24px 0">
            <a href="$url" style="background:#1a3a5c;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700">
                Buka SAPAcara & Review
            </a>
        </div>
    HTML;
    
    return mailLayout('Permintaan Approval: ' . $judul, $content);
}

function mailTemplateSWOT(array $user, array $event): string {
    $nama = htmlspecialchars($user['nama'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8');
    $judul = htmlspecialchars($event['judul'] ?? 'Acara', ENT_QUOTES, 'UTF-8');
    
    $url = BASE_URL . '/modules/evaluasi/';
    
    $content = <<<HTML
        <p>Halo, <strong>$nama</strong>!</p>
        <p>Acara <strong>$judul</strong> telah selesai.</p>
        <p>Mohon isi form evaluasi yang sudah disiapkan oleh PIC untuk membantu perbaikan ke depan.</p>
        <div style="text-align:center;margin:24px 0">
            <a href="$url" style="background:#7c3aed;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700">
                📝 Isi Evaluasi Acara
            </a>
        </div>
    HTML;
    
    return mailLayout('Evaluasi Acara: ' . $judul, $content);
}

function mailTemplatePasswordReset(string $userName, string $resetLink): string {
    $userName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
        <p>Halo, <strong>$userName</strong>!</p>
        <p>Kami menerima permintaan untuk mereset password akun Anda.</p>
        <p>Klik link di bawah untuk membuat password baru:</p>
        <div style="text-align:center;margin:24px 0">
            <a href="$resetLink" target="_blank" rel="noopener noreferrer" 
               style="background:#3b82f6;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700">
                Reset Password
            </a>
        </div>
        <p style="font-size:12px;color:#64748b;text-align:center">
            Link berlaku 1 jam sejak email ini diterima.
        </p>
        <p style="font-size:12px;color:#ef4444">
            Jika Anda tidak meminta reset password, abaikan email ini dan password Anda tetap aman.
        </p>
    HTML;
    
    return mailLayout('Reset Password Akun Anda', $content);
}

// ────────────────────────────────────────────────────────────────────────────
// HTML Email Layout Template
// ────────────────────────────────────────────────────────────────────────────

function mailLayout(string $title, string $content): string {
    $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    
    return <<<'HTML'
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{{ title }}</title>
        </head>
        <body style="font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f1f5f9;padding:20px;margin:0">
            <div style="max-width:560px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
                <div style="background:#1a3a5c;padding:24px;text-align:center">
                    <h2 style="color:#fff;margin:0;font-size:1.3rem">📅 {{ appName }}</h2>
                    <p style="color:rgba(255,255,255,0.65);margin:6px 0 0;font-size:13px">Sistem Manajemen Acara Sekolah</p>
                </div>
                <div style="padding:28px">{{ content }}</div>
                <div style="background:#f8fafc;padding:16px;text-align:center;border-top:1px solid #e2e8f0">
                    <p style="margin:0;font-size:11px;color:#94a3b8">
                        Email ini dikirim otomatis oleh {{ appName }}. Jangan balas email ini.
                    </p>
                    <p style="margin:8px 0 0;font-size:11px;color:#94a3b8">
                        © 2024 {{ appName }}. Semua hak dilindungi.
                    </p>
                </div>
            </div>
        </body>
        </html>
    HTML;
    
    // Replace placeholders
    return strtr($content, [
        '{{ title }}' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
        '{{ appName }}' => htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'),
        '{{ content }}' => $content,
    ]);
}