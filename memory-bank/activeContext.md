# Active context

**Current focus**: Menyelesaikan alur event end-to-end dengan task progress, validasi dokumen sebelum pengajuan manager, dan pengalaman pengguna yang lebih jelas di halaman detail/workspace.

**In progress**:

- [x] Implementasi form pembuatan acara (kepanitiaan inti)
- [x] Approval otomatis untuk manager berdasarkan `level`
- [x] Pembatasan upload dokumen penting sebelum manager approve
- [x] Perbaikan UI progress pada halaman detail dan workspace untuk menunjukkan langkah berikutnya
- [x] CTA tombol action di halaman detail dan overview agar pengguna tahu langkah yang harus dilakukan
- [x] Hint langkah selanjutnya di daftar acara untuk memperjelas proses event
- [x] Penambahan proteksi agar tidak membuat approval manager duplikat saat masih menunggu status
- [x] End-to-end testing (lokal)
- [ ] Dokumentasi akhir dan cleanup (hapus/arsip `e2e_test.php` jika diinginkan)

**Decisions (recent)**:

- Gunakan `jabatan_sistem` pada tabel `users` untuk menentukan approver (manager_tk/manager_sd/manager_smp, bendahara_tertinggi).
- Kirim email notifikasi ketika approval dibuat dan ketika RAB diajukan.

**Open questions**:

- Apakah `e2e_test.php` harus dihapus dari repo? (sekali dijalankan, meninggalkan data test di DB)
- Butuh penyesuaian lebih lanjut untuk urutan `urutan` approvals bila ada multi-step approval.

_Update when the task or branch focus changes._
