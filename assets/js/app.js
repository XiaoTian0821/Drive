/**
 * Drivew - Global application JS.
 * ES6+, vanilla, Fetch API. No frameworks.
 */
(() => {
'use strict';

/* ---------- Helpers ---------- */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/** Show a Bootstrap-free toast. */
function toast(message, type = 'success') {
  const icons = { success: 'circle-check', danger: 'circle-xmark', warning: 'triangle-exclamation', info: 'circle-info' };
  const el = document.createElement('div');
  el.className = 'toast show';
  el.setAttribute('role', 'status');
  el.innerHTML = `<i class="fa-solid ${icons[type] || icons.info} toast-ic"></i><span>${message}</span>`;
  const area = $('#toastArea');
  if (area) area.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

/** Confirm dialog via the global #confirmModal. */
function confirmDialog({ title = 'Confirm', body = 'Are you sure?', actionUrl, actionId, actionValue, confirmLabel = 'Confirm' }) {
  $('#confirmModalTitle').textContent = title;
  $('#confirmModalBody').innerHTML = body;
  const form = $('#confirmModalForm');
  form.action = actionUrl;
  // build hidden fields
  $$('input[type=hidden][name!=csrf]', form).forEach(i => i.remove());
  const idInput = document.createElement('input');
  idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = actionId;
  form.appendChild(idInput);
  const actInput = document.createElement('input');
  actInput.type = 'hidden'; actInput.name = 'action'; actInput.value = actionValue;
  form.appendChild(actInput);
  $('#confirmModalBtn').textContent = confirmLabel;
  new bootstrap.Modal($('#confirmModal')).show();
}

/* ---------- File card / row actions ---------- */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-file-action]');
  if (!btn) return;
  const action = btn.dataset.fileAction;
  const container = btn.closest('[data-file-id]');
  const fileId = container ? container.dataset.fileId : '';
  const fileName = container ? container.dataset.name : '';
  const redirect = container ? (container.querySelector('[name=redirect]')?.value || 'files.php') : 'files.php';

  switch (action) {
    case 'download':
      window.location.href = 'download.php?id=' + fileId;
      break;

    case 'rename': {
      const m = $('#renameModal');
      $('#renameId').value = fileId;
      const inp = $('#renameInput');
      inp.value = fileName;
      // isolate extension to only rename the base part
      const dot = fileName.lastIndexOf('.');
      inp.focus();
      if (dot > 0) inp.setSelectionRange(0, dot);
      new bootstrap.Modal(m).show();
      break;
    }

    case 'move': {
      $('#moveId').value = fileId;
      new bootstrap.Modal($('#moveModal')).show();
      break;
    }

    case 'star': {
      const fd = new FormData();
      fd.append('csrf', window.DRIVEW.csrf);
      fd.append('action', 'star_file');
      fd.append('id', fileId);
      fd.append('redirect', redirect);
      fetch('files.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
          toast(j.message || 'Starred.', j.success ? 'success' : 'danger');
          // refresh so the star badge updates
          setTimeout(() => location.reload(), 600);
        })
        .catch(() => location.reload());
      break;
    }

    case 'share':
      window.location.href = 'preview.php?id=' + fileId + '#share';
      break;

    case 'delete':
      confirmDialog({
        title: 'Move to trash',
        body: `Move <strong>${fileName}</strong> to the trash? You can restore it later.`,
        actionUrl: 'files.php',
        actionId: fileId,
        actionValue: 'delete_file',
        confirmLabel: 'Move to trash'
      });
      // Also ensure redirect goes back to the right page
      break;
  }
});

// Submit the confirm modal form, including redirect context
$('#confirmModalForm')?.addEventListener('submit', (e) => {
  const id = $('#confirmModalForm input[name=id]');
  // no-op; the form POSTs directly. We rely on redirect set by caller or default.
});

/* ---------- Upload drag & drop + progress ---------- */
const dropZone = $('#dropZone');
const fileInput = $('#fileInput');
const fileQueue = $('#fileQueue');
const uploadBtn = $('#uploadBtn');
const uploadCount = $('#uploadCount');
const progressBar = $('#uploadProgress .progress-bar');
const progressWrap = $('#uploadProgress');

function queueFiles(fileList) {
  const arr = [...fileList];
  arr.forEach((f) => {
    const row = document.createElement('div');
    row.className = 'd-flex align-items-center gap-2 small p-2 rounded-3 bg-light mb-1';
    row.innerHTML = `<i class="fa-solid fa-file text-secondary"></i>
      <span class="text-truncate">${f.name}</span>
      <span class="text-secondary ms-auto" style="font-size:.75rem">${(f.size/1048576).toFixed(2)} MB</span>`;
    fileQueue.appendChild(row);
  });
  uploadCount.textContent = arr.length ? arr.length + ' file' + (arr.length > 1 ? 's' : '') : '';
  uploadBtn.classList.remove('d-none');
}

if (dropZone && fileInput) {
  dropZone.addEventListener('click', (e) => {
    if (e.target.closest('label') || e.target === fileInput) return;
    fileInput.click();
  });
  dropZone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });
  fileInput.addEventListener('change', () => { if (fileInput.files.length) queueFiles(fileInput.files); });

  ['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(ev => dropZone.addEventListener(ev, (e) => { e.preventDefault(); if (ev === 'dragleave' && e.target === dropZone) dropZone.classList.remove('dragover'); else dropZone.classList.remove('dragover'); }));
  dropZone.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) queueFiles(e.dataTransfer.files); });

  const form = $('#uploadForm');
  form.addEventListener('submit', (e) => {
    if (!fileInput.files.length) return;
    const fd = new FormData(form);
    progressWrap.classList.remove('d-none');
    progressBar.style.width = '0%';
    uploadBtn.disabled = true;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action + (form.action.includes('?') ? '&' : '?') + 'ajax=1');
    xhr.setRequestHeader('X-CSRF-Token', window.DRIVEW.csrf);
    const prog = xhr.upload.addEventListener('progress', (ev) => {
      if (ev.lengthComputable) progressBar.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
    });
    xhr.addEventListener('load', () => {
      xhr.upload.removeEventListener('progress', prog);
      uploadBtn.disabled = false;
      let res;
      try { res = JSON.parse(xhr.responseText); } catch { res = { success: false, message: 'Upload failed.' }; }
      progressWrap.classList.add('d-none');
      if (res.success) { toast(res.message, 'success'); location.reload(); }
      else {
        toast(res.message, 'danger');
        fileInput.value = ''; fileQueue.innerHTML = '';
        uploadBtn.classList.add('d-none');
      }
    });
    xhr.send(fd);
    e.preventDefault();
  });
}

/* ---------- View toggle ---------- */
$$('[data-view]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const mode = btn.dataset.view;
    const url = new URL(window.location.href);
    url.searchParams.set('view', mode);
    window.location.href = url.toString();
  });
});

/* ---------- Expose for inline handlers ---------- */
window.confirmDelete = function (fid, name) {
  if (!confirm(`Move "${name}" to trash?`)) return;
  const fd = new FormData();
  fd.append('csrf', window.DRIVEW.csrf);
  fd.append('action', 'delete_file');
  fd.append('id', fid);
  fd.append('redirect', 'preview.php?id=' + fid);
  fetch('files.php', { method: 'POST', body: fd }).then(() => location.href = 'files.php');
};
})();
