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

<div class="page-loader" id="pageLoader" aria-hidden="true" aria-live="assertive" role="status">
  <div class="page-loader-card">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div class="loader-title">Memproses...</div>
    <div class="loader-subtitle">Tunggu sebentar, tindakan Anda sedang diproses.</div>
  </div>
</div>

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

/* ── Loading overlay and submit buffering ── */
(function () {
  const loader = document.getElementById('pageLoader');
  const setLoading = function (active) {
    if (!loader) return;
    loader.classList.toggle('show', active);
    loader.setAttribute('aria-hidden', active ? 'false' : 'true');
  };

  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.method.toLowerCase() !== 'post') return;
    if (form.dataset.noBuffer === 'true') return;

    const submit = form.querySelector('button[type=submit], input[type=submit]');
    const restore = function () {
      if (!submit) return;
      submit.disabled = false;
      if (submit.tagName === 'INPUT') {
        submit.value = submit.dataset.originalText || submit.value;
      } else {
        submit.innerHTML = submit.dataset.originalText || submit.innerHTML;
      }
      setLoading(false);
    };

    if (submit && !submit.disabled) {
      const isInput = submit.tagName === 'INPUT';
      submit.disabled = true;
      submit.dataset.originalText = isInput ? submit.value : submit.innerHTML;
      if (isInput) {
        submit.value = 'Memproses...';
      } else {
        submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
      }
    }
    setLoading(true);

    window.requestAnimationFrame(() => {
      if (e.defaultPrevented) {
        restore();
      }
    });
  }, true);

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      setLoading(false);
    }
  });
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
