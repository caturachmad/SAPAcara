<?php
// E2E test script for SAPAcara — creates event, approvals, uploads RAB, triggers bendahara approval, invites panitia.
// Run from project root. This script WILL write to the database and send real emails as configured in config/mail.php.

chdir(__DIR__);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';

$result = ['created'=>[], 'emails'=>[], 'errors'=>[]];
try {
    // 1) pick a PIC (first active user)
    $u = $pdo->query("SELECT id,nama,email,divisi FROM users WHERE status='aktif' LIMIT 1")->fetch();
    if (!$u) throw new Exception('No active users found');
    $picId = (int)$u['id'];

    // 2) create event
    $judul = 'E2E Test Event ' . date('YmdHis');
    $level = 'TK';
    $tgl_mulai = date('Y-m-d', strtotime('+7 days'));
    $tgl_selesai = date('Y-m-d', strtotime('+7 days'));
    $lokasi = 'Tes Aula';
    $deskripsi = 'Test end-to-end flow';

    $ins = $pdo->prepare("INSERT INTO events (judul, level, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, pic_id) VALUES (?,?,?,?,?,?,?,?)");
    $ins->execute([$judul,$level,$tgl_mulai,$tgl_selesai,$lokasi,$deskripsi,'draft',$picId]);
    $eventId = (int)$pdo->lastInsertId();
    $result['created']['event_id'] = $eventId;

    // insert PIC
    $pdo->prepare("INSERT INTO event_panitia (event_id, user_id, peran_acara, is_event_admin, status_konfirmasi) VALUES (?,?,?,?,?)")
        ->execute([$eventId, $picId, 'pic', 1, 'bersedia']);

    // 3) find or create manager for TK
    $approverId = null;
    $q = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = ? AND divisi = ? AND status='aktif' LIMIT 1");
    $q->execute(['manager_tk', 'TK']); $r = $q->fetch();
    if ($r) $approverId = (int)$r['id'];
    if (!$approverId) {
        $q2 = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = ? AND status='aktif' LIMIT 1");
        $q2->execute(['manager_tk']); $r2 = $q2->fetch();
        if ($r2) $approverId = (int)$r2['id'];
    }
    if (!$approverId) {
        // create temporary manager
        $emailM = 'e2e_manager_' . time() . '@example.invalid';
        $pdo->prepare("INSERT INTO users (nama,email,password,divisi,role_sistem,jabatan_sistem,status) VALUES (?,?,?,?,?,?,?)")
            ->execute(['E2E Manager',$emailM,password_hash('password',PASSWORD_DEFAULT),'TK','staff','manager_tk','aktif']);
        $approverId = (int)$pdo->lastInsertId();
        $result['created']['manager_id'] = $approverId;
    }

    // 4) create approval record and send email
    $pdo->prepare("INSERT INTO approvals (event_id, approver_id, tipe_approver, urutan) VALUES (?,?,?,?)")
        ->execute([$eventId,$approverId,'manager_tk',1]);
    $result['created']['approval_manager'] = $pdo->lastInsertId();

    $apUser = $pdo->prepare("SELECT * FROM users WHERE id=?"); $apUser->execute([$approverId]); $apRow = $apUser->fetch();
    $html = mailTemplateApproval($apRow, ['judul'=>$judul,'tanggal_mulai'=>$tgl_mulai,'level'=>$level], 'manager_tk');
    $sent = sendMail($apRow['email'], $apRow['nama'], 'Permintaan Approval: ' . $judul, $html);
    $result['emails'][] = ['to'=>$apRow['email'],'type'=>'manager_approval','sent'=>$sent];

    // 5) simulate manager approval
    $pdo->prepare("UPDATE approvals SET status='approved', catatan='OK via E2E', approved_at=NOW() WHERE event_id=? AND approver_id=? AND tipe_approver=?")
        ->execute([$eventId,$approverId,'manager_tk']);
    // set event status
    $pdo->prepare("UPDATE events SET status='disetujui_manager' WHERE id=?")->execute([$eventId]);

    // 6) simulate upload RAB (insert file)
    $pdo->prepare("INSERT INTO event_files (event_id,nama_file,deskripsi,file_path,file_original,file_type,file_size,visibility,can_edit_by,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([$eventId,'RAB E2E','RAB test','events/'.$eventId.'/e2e_rab.pdf','e2e_rab.pdf','rab',1234,'inti','inti',$picId]);
    $result['created']['file_rab'] = $pdo->lastInsertId();

    // 7) find or create bendahara
    $bendId = null;
    $bq = $pdo->prepare("SELECT id FROM users WHERE jabatan_sistem = 'bendahara_tertinggi' AND status='aktif' LIMIT 1");
    $bq->execute(); $br = $bq->fetch(); if ($br) $bendId = (int)$br['id'];
    if (!$bendId) {
        $emailB = 'e2e_bendahara_' . time() . '@example.invalid';
        $pdo->prepare("INSERT INTO users (nama,email,password,divisi,role_sistem,jabatan_sistem,status) VALUES (?,?,?,?,?,?,?)")
            ->execute(['E2E Bendahara',$emailB,password_hash('password',PASSWORD_DEFAULT),'Umum','staff','bendahara_tertinggi','aktif']);
        $bendId = (int)$pdo->lastInsertId(); $result['created']['bendahara_id'] = $bendId;
    }

    // create approval for bendahara
    $pdo->prepare("INSERT INTO approvals (event_id, approver_id, tipe_approver, urutan) VALUES (?,?,?,?)")
        ->execute([$eventId,$bendId,'bendahara',1]);
    $apprId = $pdo->lastInsertId(); $result['created']['approval_bendahara'] = $apprId;

    $bendUser = $pdo->prepare("SELECT * FROM users WHERE id=?"); $bendUser->execute([$bendId]); $bendRow = $bendUser->fetch();
    $html2 = mailTemplateApproval($bendRow, ['judul'=>$judul,'tanggal_mulai'=>$tgl_mulai,'level'=>$level], 'bendahara');
    $sent2 = sendMail($bendRow['email'], $bendRow['nama'], 'Permintaan Pencairan Dana (RAB): ' . $judul, $html2);
    $result['emails'][] = ['to'=>$bendRow['email'],'type'=>'bendahara_approval','sent'=>$sent2];

    // 8) simulate bendahara approve
    $pdo->prepare("UPDATE approvals SET status='approved', catatan='Dana disetujui via E2E', approved_at=NOW() WHERE id=?")->execute([$apprId]);
    // set event status to disetujui
    $pdo->prepare("UPDATE events SET status='disetujui' WHERE id=?")->execute([$eventId]);

    // 9) invite two panitia (if available) and send invitations
    $cands = $pdo->query("SELECT id,nama,email FROM users WHERE status='aktif' AND id NOT IN (SELECT user_id FROM event_panitia WHERE event_id={$eventId}) LIMIT 2")->fetchAll();
    $invited = 0;
    foreach ($cands as $c) {
        $tok = bin2hex(random_bytes(16)); $exp = date('Y-m-d H:i:s', strtotime('+7 days'));
        $pdo->prepare("INSERT INTO event_panitia (event_id,user_id,peran_acara,bagian,token_konfirmasi,token_expires_at,status_konfirmasi) VALUES (?,?,?,?,?,?,?)")
            ->execute([$eventId,$c['id'],'panitia_support','Teknis',$tok,$exp,'pending']);
        $html3 = mailTemplatePanitia($c, ['judul'=>$judul,'tanggal_mulai'=>$tgl_mulai,'tanggal_selesai'=>$tgl_selesai,'lokasi'=>$lokasi,'level'=>$level], 'Teknis', $tok);
        $sent3 = sendMail($c['email'], $c['nama'], 'Undangan Panitia: ' . $judul, $html3);
        addNotif($pdo, $c['id'], 'Undangan Panitia', "Kamu diundang menjadi panitia acara {$judul}", BASE_URL.'/modules/events/workspace.php?id='.$eventId, 'info');
        $result['emails'][] = ['to'=>$c['email'],'type'=>'invite_panitia','sent'=>$sent3];
        $invited++;
    }
    $result['created']['invited'] = $invited;

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()], JSON_PRETTY_PRINT);
}
