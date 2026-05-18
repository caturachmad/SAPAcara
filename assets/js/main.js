/* ═══════════════════════════════════
   SAPAcara — Main JavaScript
   ═══════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── 1. Sidebar mobile ── */
  const toggler = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  let overlay   = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
  }
  const openSB  = () => { sidebar?.classList.add('open'); overlay.classList.add('show'); document.body.style.overflow = 'hidden'; };
  const closeSB = () => { sidebar?.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; };
  toggler?.addEventListener('click', openSB);
  overlay.addEventListener('click', closeSB);

  /* ── 2. Notification Panel (custom, no BS conflict) ── */
  const notifBtn   = document.getElementById('notifBtn');
  const notifPanel = document.getElementById('notifPanel');

  notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notifPanel?.classList.toggle('open');
  });

  // Tutup saat klik di luar
  document.addEventListener('click', (e) => {
    if (!notifBtn?.contains(e.target) && !notifPanel?.contains(e.target)) {
      notifPanel?.classList.remove('open');
    }
  });

  // Tutup saat tekan Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') notifPanel?.classList.remove('open');
  });

  /* ── 3. Auto-dismiss flash alert ── */
  document.querySelectorAll('.alert-auto').forEach(el => {
    const closeBtn = el.querySelector('.btn-close');
    const dismiss = () => {
      el.style.transition = 'opacity .3s, max-height .3s, margin .3s, padding .3s';
      el.style.opacity = '0';
      el.style.maxHeight = '0';
      el.style.padding = '0';
      el.style.margin = '0';
      el.style.overflow = 'hidden';
      setTimeout(() => el.remove(), 320);
    };
    const timer = setTimeout(dismiss, 5000);
    closeBtn?.addEventListener('click', () => { clearTimeout(timer); dismiss(); });
  });

  /* ── 4. Active nav link ── */
  const path = window.location.pathname;
  document.querySelectorAll('#sidebar .nav-link').forEach(link => {
    const href = link.getAttribute('href') || '';
    const seg  = href.split('/siakad')[1] || '';
    if (seg && seg.length > 1 && path.includes(seg)) {
      link.classList.add('active');
    }
  });

  /* ── 5. Confirm sebelum aksi ── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
  });

  /* ── 6. Tab aktif dari URL hash ── */
  const hash = window.location.hash;
  if (hash) {
    const tabTrigger = document.querySelector(`[data-bs-target="${hash}"]`);
    if (tabTrigger) new bootstrap.Tab(tabTrigger).show();
  }
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', e => {
      const target = e.target.getAttribute('data-bs-target') || e.target.getAttribute('href');
      if (target) history.replaceState(null, '', target);
    });
  });

  /* ── 7. Tooltip ── */
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});

/* ── Utility ── */
window.formatRupiah = (n) => 'Rp ' + parseInt(n || 0).toLocaleString('id-ID');
window.sisaHari = (tgl) => Math.ceil((new Date(tgl) - new Date()) / 86400000);
