<?php if (empty($noSidebar)): ?>
            </div><!-- /.container-fluid -->
        </div><!-- /.main-content -->

<?php endif; ?>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger mb-1">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Are you sure you want to delete this record? This action cannot be undone.
                        </p>
                        <p class="text-muted small mb-0" id="deleteModalItemName"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form id="deleteForm" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap 5.3 Bundle JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Chart.js 4.x -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <!-- Local App JS -->
        <script src="/medicarehub/assets/js/app.js"></script>

        <?php if (isset($loadCharts)): ?>
            <script src="/medicarehub/assets/js/charts.js"></script>
        <?php endif; ?>

        <?php if (isset($loadBooking)): ?>
            <script src="/medicarehub/assets/js/booking.js"></script>
        <?php endif; ?>
    </body>
</html>
