  </div><!-- .content-wrap -->
</div><!-- #main -->

<!-- ── Confirm Modal ── -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center py-4 px-4">
        <i class="bi bi-exclamation-triangle-fill text-warning d-block mb-2" style="font-size:2rem"></i>
        <p class="mb-0 fw-500" id="confirmModalMsg">Yakin?</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-3 gap-2">
        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm px-4" id="confirmModalOk">Ya, Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>

<script>
/* ── CSRF: auto-inject token ke semua form POST ── */
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) return;
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.method.toLowerCase() !== 'post') return;
    if (!form.querySelector('input[name="csrf_token"]')) {
      const inp = Object.assign(document.createElement('input'), {
        type: 'hidden', name: 'csrf_token', value: token
      });
      form.appendChild(inp);
    }
  }, true);
})();

/* ── Confirm Modal ── */
(function () {
  const modalEl  = document.getElementById('confirmModal');
  const modalMsg = document.getElementById('confirmModalMsg');
  const modalOk  = document.getElementById('confirmModalOk');
  const bsModal  = new bootstrap.Modal(modalEl);

  let pendingForm = null;
  let pendingHref = null;
  let pendingCb   = null;

  // API publik: bisa dipanggil dari JS manapun
  window.showConfirmModal = function (msg, callback) {
    pendingForm = null;
    pendingHref = null;
    pendingCb   = callback;
    modalMsg.textContent = msg;
    bsModal.show();
  };

  // Tangkap klik pada elemen ber-data-confirm (button dalam form ATAU <a> navigasi)
  document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;
    e.preventDefault();

    pendingCb   = null;
    pendingForm = el.closest('form') || null;
    pendingHref = !pendingForm ? (el.getAttribute('href') || null) : null;

    modalMsg.textContent = el.dataset.confirm;
    bsModal.show();
  }, true);

  // Eksekusi setelah konfirmasi
  modalOk.addEventListener('click', function () {
    bsModal.hide();
    if (pendingCb)   { pendingCb(); }
    else if (pendingForm) {
      pendingForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: false }));
      pendingForm.submit();
    }
    else if (pendingHref) { window.location.href = pendingHref; }
    pendingForm = pendingHref = pendingCb = null;
  });

  // Reset saat modal ditutup via Batal / X
  modalEl.addEventListener('hidden.bs.modal', function () {
    pendingForm = pendingHref = pendingCb = null;
  });
})();
</script>
</body>
</html>
