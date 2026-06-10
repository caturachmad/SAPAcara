# Progress

**What works**

- Form pembuatan acara diperbarui: pilihan kepanitiaan inti (Bendahara, Sekretaris, Kehumasan) ditambahkan.
- Alur approval otomatis: acara baru membuat approval manager berdasarkan level (TK/SD/SMP).
- Upload dokumen dibatasi sampai approval manager selesai untuk dokumen penting (proposal, RAB, jobdesk, perijinan).
- Saat RAB diupload, approval untuk `bendahara_tertinggi` dibuat otomatis dan notifikasi + email dikirim.
- Email templating disiapkan di `config/mail.php` dan dipakai untuk approval & undangan panitia.
- Pengundangan panitia diperbarui agar menggunakan `bagian` dinamis sebagai peran/divisi sebenarnya, tanpa memaksa kategori `Panitia Inti` atau `Panitia Support`.
- UI undang panitia ditingkatkan: dropdown bagian dengan opsi custom, dan pemilihan banyak SDM sekaligus.
- Duplikasi konfirmasi dihapus dari `assets/js/main.js`; modal konfirmasi sekarang menjadi satu-satunya mekanisme konfirmasi aksi.
- Tab SWOT di workspace acara sekarang tampil lebih luas untuk status `disetujui`, `berlangsung`, dan `selesai`.
- Halaman event detail sekarang menampilkan langkah pra-rilis yang jelas: upload proposal/RAB, ajukan manager, undang panitia, atur WA/dokumentasi.
- Workspace event sekarang memiliki progress card yang menjelaskan status langkah kerja acara dan memandu PIC ke action berikutnya.
- Halaman detail event kini menampilkan langkah pra-rilis dengan CTA tombol langsung ke upload dokumen, approval, dan tim.
- Daftar acara menampilkan hint tindakan selanjutnya agar PIC tahu langkah berikutnya tanpa membuka detail.
- Approval manager duplikat dicegah jika sudah ada permintaan yang menunggu.
- End-to-end test script (`e2e_test.php`) dibuat dan dijalankan pada environment lokal untuk memverifikasi alur.

**Not started / backlog**

- Penyempurnaan UI/UX pada form approval dan indikator status di halaman acara.
- Menambahkan validasi akses lebih ketat (mis. pastikan hanya PIC/event admin yang dapat memicu langkah tertentu).
- Penambahan audit/log perubahan untuk approval dan upload file.

**Known issues / notes**

- E2E test membuat entri dummy di DB dan mengirim email nyata sesuai konfigurasi `config/mail.php` (script masih ada di repo: `e2e_test.php`).
- Perlu verifikasi lingkungan SMTP (App Password) di server produksi sebelum penggunaan nyata.

_Keep bullets factual and small; link issues or PRs when useful._
