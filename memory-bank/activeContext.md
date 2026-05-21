# Active context

**Current focus**: Implementasi alur pembuatan acara baru termasuk penugasan panitia dengan bagian/divisi dinamis (Bendahara, Sekretaris, Logistik, Dokumentasi, Konsumsi, Tim Medis, Tim Acara, dll), routing approval berbasis divisi, pembatasan upload dokumen kritis hingga approval manager, dan pengiriman email/undangan otomatis untuk bendahara dan panitia.

**In progress**:

- [x] Implementasi form pembuatan acara (kepanitiaan inti)
- [x] Approval otomatis untuk manager berdasarkan `level`
- [x] Pembatasan upload dokumen penting sebelum manager approve
- [x] Trigger approval + email untuk bendahara ketika RAB diupload
- [x] Pengiriman undangan email ke panitia teknis setelah pencairan disetujui
- [x] End-to-end testing (lokal)
- [ ] Dokumentasi akhir dan cleanup (hapus/arsip `e2e_test.php` jika diinginkan)

**Decisions (recent)**:

- Gunakan `jabatan_sistem` pada tabel `users` untuk menentukan approver (manager_tk/manager_sd/manager_smp, bendahara_tertinggi).
- Kirim email notifikasi ketika approval dibuat dan ketika RAB diajukan.

**Open questions**:

- Apakah `e2e_test.php` harus dihapus dari repo? (sekali dijalankan, meninggalkan data test di DB)
- Butuh penyesuaian lebih lanjut untuk urutan `urutan` approvals bila ada multi-step approval.

_Update when the task or branch focus changes._
