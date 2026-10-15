<?php
/**
 * Drivew - Shared HTML footer.
 */
?>
  </div><!-- /.app-content -->
</main><!-- /.app-main -->
</div><!-- /#appShell -->

<!-- Global toast container -->
<div id="toastArea" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000"></div>

<!-- Global modal shell for confirmation dialogs -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-semibold" id="confirmModalTitle">Confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalBody"></div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <form id="confirmModalForm" method="post" class="d-inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(\Drivew\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="btn btn-danger rounded-pill px-4" id="confirmModalBtn">Confirm</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($isAdmin) : ?>
<script src="assets/app.js?v=20261015"></script>
<link href="assets/style.css" rel="stylesheet">
<?php else : ?>
<script src="assets/js/app.js?v=20261015"></script>
<?php endif; ?>
<script>
window.DRIVEW = {
  csrf: '<?= htmlspecialchars(\Drivew\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>',
  is_admin: <?= $isAdmin ? 'true' : 'false' ?>
};
</script>
</body>
</html>
