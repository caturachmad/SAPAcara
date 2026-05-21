<?php
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USERNAME',  'wiwid8a@gmail.com');  // ← ganti
define('MAIL_PASSWORD',  'zbzg zjzw qlkn wchd');     // ← App Password Gmail
define('MAIL_FROM',      'wiwid8a@gmail.com');  // ← ganti
define('MAIL_FROM_NAME', 'SAPAcara – Manajemen Acara Sekolah');
define('APP_NAME',       'SAPAcara');

function sendMail(string $to, string $toName, string $subject, string $html): bool {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[SAPAcara Mail Error] ' . $mail->ErrorInfo . ' / ' . $e->getMessage());
        if (sendMailFallback($to, $toName, $subject, $html)) {
            error_log('[SAPAcara Mail Fallback] Sent using PHP mail() fallback to ' . $to);
            return true;
        }
        return false;
    }
}

function sendMailFallback(string $to, string $toName, string $subject, string $html): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    return mail($to, $subject, $html, $headers);
}

function mailTemplatePanitia(array $user, array $event, string $bagian, string $token): string {
    $tglMulai   = date('d M Y', strtotime($event['tanggal_mulai']));
    $tglSelesai = date('d M Y', strtotime($event['tanggal_selesai']));
    $tglInfo    = $tglMulai === $tglSelesai ? $tglMulai : "$tglMulai – $tglSelesai";
    $urlYa      = BASE_URL . '/modules/panitia/confirm.php?token=' . urlencode($token) . '&jawab=bersedia';
    $urlTidak   = BASE_URL . '/modules/panitia/confirm.php?token=' . urlencode($token) . '&jawab=tidak_bisa';
    $bagianText = $bagian ? htmlspecialchars($bagian) : 'Panitia';
    return mailLayout('Undangan Panitia: ' . $event['judul'], "
        <p>Halo, <strong>" . htmlspecialchars($user['nama']) . "</strong>!</p>
        <p>Kamu diundang sebagai <strong style='color:#1a3a5c'>" . $bagianText . "</strong> dalam acara:</p>
        <div style='background:#f0f4f8;border-left:4px solid #245a8a;padding:16px;border-radius:0 8px 8px 0;margin:16px 0'>
            <h3 style='margin:0 0 8px;color:#1a3a5c'>" . htmlspecialchars($event['judul']) . "</h3>
            <p style='margin:4px 0'>📅 $tglInfo</p>
            <p style='margin:4px 0'>📍 " . htmlspecialchars($event['lokasi'] ?? '-') . "</p>
            <p style='margin:4px 0'>🏫 Level: <strong>" . $event['level'] . "</strong></p>
        </div>
        <p>Konfirmasi kesediaanmu:</p>
        <div style='text-align:center;margin:24px 0'>
            <a href='$urlYa' target='_blank' rel='noopener noreferrer' style='background:#10b981;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;margin:4px'>✅ Saya Bersedia</a>
            <a href='$urlTidak' target='_blank' rel='noopener noreferrer' style='background:#ef4444;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;margin:4px'>❌ Tidak Bisa</a>
        </div>
        <p style='font-size:12px;color:#64748b;text-align:center;margin-bottom:4px'>Jika tombol tidak berfungsi, silakan salin dan tempel link berikut di browser:</p>
        <p style='font-size:12px;color:#0f172a;text-align:center;word-break:break-all'><a href='$urlYa' target='_blank' rel='noopener noreferrer' style='color:#1a3a5c;text-decoration:underline'>$urlYa</a></p>
        <p style='font-size:12px;color:#94a3b8;text-align:center'>Link berlaku 7 hari sejak email ini diterima.</p>
    ");
}

function mailTemplateApproval(array $approver, array $event, string $tipe): string {
    $url = BASE_URL . '/modules/approvals/';
    return mailLayout('Permintaan Approval: ' . $event['judul'], "
        <p>Halo, <strong>" . htmlspecialchars($approver['nama']) . "</strong>!</p>
        <p>Kamu mendapat permintaan approval sebagai <strong>$tipe</strong> untuk:</p>
        <div style='background:#f0f4f8;border-left:4px solid #f59e0b;padding:16px;border-radius:0 8px 8px 0;margin:16px 0'>
            <h3 style='margin:0 0 8px;color:#1a3a5c'>" . htmlspecialchars($event['judul']) . "</h3>
            <p style='margin:4px 0'>📅 " . date('d M Y', strtotime($event['tanggal_mulai'])) . "</p>
            <p style='margin:4px 0'>🏫 Level: <strong>" . $event['level'] . "</strong></p>
        </div>
        <div style='text-align:center;margin:24px 0'>
            <a href='$url' style='background:#1a3a5c;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700'>Buka SAPAcara & Review</a>
        </div>
    ");
}

function mailTemplateSWOT(array $user, array $event): string {
    $url = BASE_URL . '/modules/evaluasi/';
    return mailLayout('Evaluasi Acara: ' . $event['judul'], "
        <p>Halo, <strong>" . htmlspecialchars($user['nama']) . "</strong>!</p>
        <p>Acara <strong>" . htmlspecialchars($event['judul']) . "</strong> telah selesai.</p>
        <p>Mohon isi form evaluasi yang sudah disiapkan oleh PIC untuk membantu perbaikan ke depan.</p>
        <div style='text-align:center;margin:24px 0'>
            <a href='$url' style='background:#7c3aed;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700'>📝 Isi Evaluasi Acara</a>
        </div>
    ");
}

function mailLayout(string $title, string $content): string {
    return "<!DOCTYPE html><html><body style='font-family:Inter,Arial,sans-serif;background:#f1f5f9;padding:20px;margin:0'>
    <div style='max-width:560px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)'>
        <div style='background:#1a3a5c;padding:24px;text-align:center'>
            <h2 style='color:#fff;margin:0;font-size:1.3rem'>📅 " . APP_NAME . "</h2>
            <p style='color:rgba(255,255,255,.65);margin:6px 0 0;font-size:13px'>Sistem Manajemen Acara Sekolah</p>
        </div>
        <div style='padding:28px'>$content</div>
        <div style='background:#f8fafc;padding:16px;text-align:center;border-top:1px solid #e2e8f0'>
            <p style='margin:0;font-size:11px;color:#94a3b8'>Email ini dikirim otomatis oleh " . APP_NAME . ". Jangan balas email ini.</p>
        </div>
    </div></body></html>";
}
