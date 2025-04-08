<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-5 fw-medium mb-0 mt-1">
                <i class="bi bi-grid me-1"></i>
                Manage Feeds
            </h3>
            <p class="text-secondary small mb-0">Add, edit, or remove feed sources</p>
        </div>
        <div>
            <a href="/admin/feeds/new" class="btn btn-primary d-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                Add New Feed
            </a>
        </div>
    </div>
    
    <?php if (empty($feeds)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0 mt-1">
                <i class="bi bi-exclamation-circle me-1"></i>
                No feeds found. Add your first feed using the button above.
            </p>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-uppercase">Feed</th>
                            <th scope="col" class="small text-uppercase">Type</th>
                            <th scope="col" class="small text-uppercase">Language</th>
                            <th scope="col" class="small text-uppercase">Status</th>
                            <th scope="col" class="small text-uppercase">Last Checked</th>
                            <th scope="col" class="small text-uppercase">Last Updated</th>
                            <th scope="col" class="small text-uppercase">Items</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $feed): ?>
                            <tr data-feed-id="<?= $feed['id'] ?>">
                                <td>
                                    <div>
                                        <div class="fw-medium">
                                            <a href="<?= $this->e($feed['site_url']) ?>" target="_blank" class="text-decoration-none">
                                                <?= $this->e($feed['title']) ?>
                                            </a>
                                        </div>
                                        <div class="small text-secondary text-truncate" style="max-width: 250px;">
                                            <a href="<?= $this->e($feed['feed_url']) ?>" target="_blank" class="text-decoration-none text-secondary">
                                                <?= $this->e($feed['feed_url']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle fw-medium">
                                    <?= $this->e(strtoupper($feed['feed_type'])) ?>
                                </td>
                                <td class="align-middle">
                                    <?php
                                        $languages = [
                                            'en' => 'English',
                                            'pt-BR' => 'Portuguese-Brasil',
                                            'es' => 'Spanish'
                                        ];
                                        echo $this->e($languages[$feed['language']] ?? $feed['language']);
                                    ?>
                                </td>
                                <td class="align-middle">
                                    <select class="form-select form-select-sm status-select" data-feed-id="<?= $feed['id'] ?>" data-original-value="<?= $feed['status'] ?>">
                                        <option value="online" <?= $feed['status'] === 'online' ? 'selected' : '' ?>>Online</option>
                                        <option value="offline" <?= $feed['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                        <option value="paused" <?= $feed['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                                    </select>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <?= $feed['last_checked'] ? date('M j, Y H:i', strtotime($feed['last_checked'])) : 'Never' ?>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <?= $feed['last_updated'] ? date('M j, Y H:i', strtotime($feed['last_updated'])) : 'Never' ?>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <?= $feed['item_count'] ?? 0 ?>
                                </td>
                                <td class="align-middle text-end">
                                    <a href="/admin/feeds/<?= $feed['id'] ?>/edit" class="d-inline-block btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="d-inline-block btn btn-sm btn-outline-danger delete-feed" data-feed-id="<?= $feed['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Delete Feed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex">
                    <div class="me-3">
                        <div class="bg-danger bg-opacity-10 p-2 rounded-circle">
                            <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                        </div>
                    </div>
                    <div>
                        <p class="mb-0">
                            Are you sure you want to delete this feed? All feed items will also be deleted. This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-delete" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirm-delete">
                    <i class="bi bi-trash me-1"></i>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const deleteModal = new bootstrap.Modal(document.getElementById('delete-modal'));
    
    // Buttons
    const cancelDeleteButton = document.getElementById('cancel-delete');
    const confirmDeleteButton = document.getElementById('confirm-delete');
    const deleteFeedButtons = document.querySelectorAll('.delete-feed');
    const statusSelects = document.querySelectorAll('.status-select');
    
    // Delete feed
    deleteFeedButtons.forEach(button => {
        button.addEventListener('click', function() {
            const feedId = this.dataset.feedId;
            confirmDeleteButton.dataset.feedId = feedId;
            deleteModal.show();
        });
    });
    
    // Confirm delete
    confirmDeleteButton.addEventListener('click', function() {
        const feedId = this.dataset.feedId;
        
        fetch(`/admin/feeds/${feedId}`, {
            method: 'DELETE',
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from the table
                const row = document.querySelector(`tr[data-feed-id="${feedId}"]`);
                row.remove();
                
                // Hide the modal
                deleteModal.hide();
                
                // If no feeds left, refresh the page to show the empty state
                if (document.querySelectorAll('tbody tr').length === 0) {
                    window.location.reload();
                }
            } else {
                alert('Error deleting feed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the feed.');
        });
    });
    
    // Update feed status
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const feedId = this.dataset.feedId;
            const newStatus = this.value;
            const originalValue = this.dataset.originalValue;
            
            fetch(`/admin/feeds/${feedId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: newStatus
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the data attribute
                    this.dataset.originalValue = newStatus;
                } else {
                    // Revert to original value
                    this.value = originalValue;
                    alert('Error updating feed status: ' + data.message);
                }
            })
            .catch(error => {
                // Revert to original value
                this.value = originalValue;
                console.error('Error:', error);
                alert('An error occurred while updating the feed status.');
            });
        });
    });
});
</script>
<?php $this->stop() ?>