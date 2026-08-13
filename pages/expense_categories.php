<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['cat_description']);
    $datetime = date('Y-m-d H:i:s');
    if(trim($category_name) == '') {
        $error = "Category name is required.";
    } else {
        mysqli_query($conn, "INSERT INTO expense_categories (category_name, description, created_datetime) VALUES ('$category_name', '$description', '$datetime')");
        $success = "Category added successfully!";
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id']);
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['cat_description']);
    if(trim($category_name) == '') {
        $error = "Category name is required.";
    } else {
        mysqli_query($conn, "UPDATE expense_categories SET category_name='$category_name', description='$description' WHERE id=$id");
        $success = "Category updated successfully!";
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expenses WHERE expense_category=$id"))['cnt'];
    if($used > 0) {
        $error = "Cannot delete this category because it is used by $used expense(s).";
    } else {
        mysqli_query($conn, "DELETE FROM expense_categories WHERE id=$id");
        header("Location: expense_categories.php?msg=deleted");
        exit();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success = "Category deleted successfully!";

$categories = mysqli_query($conn, "SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.expense_category = c.id) as usage_count FROM expense_categories c ORDER BY c.category_name");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.cat-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.cat-table td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}
.cat-table tr:hover {
    background: #f8f9fa;
}
.cat-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
    white-space: nowrap;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-tags me-2" style="color: #A04657;"></i> Expense Categories
        </h2>
        <div class="d-flex gap-2 no-print">
            <a href="expenses.php" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back to Expenses
            </a>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus-circle me-2"></i> Add Category
            </button>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Categories Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 rounded-4 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list-alt me-2" style="color: #A04657;"></i> Category List</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table cat-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th style="width:130px" class="text-center">Expenses</th>
                            <th style="width:180px" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($categories && mysqli_num_rows($categories) > 0):
                            $sno = 1;
                            while($cat = mysqli_fetch_assoc($categories)): ?>
                            <tr>
                                <td class="text-muted"><?php echo $sno++; ?></td>
                                <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                                <td><?php echo $cat['description'] ? htmlspecialchars($cat['description']) : '—'; ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $cat['usage_count'] > 0 ? 'bg-primary' : 'bg-secondary'; ?> rounded-pill px-3">
                                        <?php echo $cat['usage_count']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-xs btn-warning editCatBtn" title="Edit"
                                            data-id="<?php echo $cat['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES); ?>"
                                            data-description="<?php echo htmlspecialchars($cat['description'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $cat['id']; ?>" class="btn btn-xs btn-danger" title="Delete" onclick="return confirm('Delete this category?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-tags fa-3x mb-2 d-block opacity-25"></i>
                                    No categories yet. Click "Add Category" to create one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add Expense Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name *</label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g., Fuel, Electricity..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="cat_description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary rounded-pill px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="category_id" id="edit_cat_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name *</label>
                        <input type="text" name="category_name" id="edit_cat_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="cat_description" id="edit_cat_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary rounded-pill px-4">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.editCatBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_cat_id').value = this.getAttribute('data-id');
            document.getElementById('edit_cat_name').value = this.getAttribute('data-name');
            document.getElementById('edit_cat_description').value = this.getAttribute('data-description');
            var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
