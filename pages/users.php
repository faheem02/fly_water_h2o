<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");
if (!is_admin()) header("Location: customer_view.php");

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $datetime = date('Y-m-d H:i:s');

        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE username='$username'"));
        if ($check['cnt'] > 0) {
            $message = "<div class='alert alert-danger'>Username already exists. Please choose another.</div>";
        } else {
            $res = mysqli_query($conn, "INSERT INTO users (username, password, full_name, role, created_datetime) VALUES ('$username', '$password', '$full_name', '$role', '$datetime')");
            if ($res) {
                $message = "<div class='alert alert-success'>User added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to add user: " . mysqli_error($conn) . "</div>";
            }
        }
    }
    elseif (isset($_POST['edit_user'])) {
        $id = intval($_POST['user_id']);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $new_password = mysqli_real_escape_string($conn, $_POST['password']);

        if ($new_password !== '') {
            $res = mysqli_query($conn, "UPDATE users SET full_name='$full_name', role='$role', password='$new_password' WHERE id=$id");
        } else {
            $res = mysqli_query($conn, "UPDATE users SET full_name='$full_name', role='$role' WHERE id=$id");
        }
        if ($res) {
            $message = "<div class='alert alert-success'>User updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update user: " . mysqli_error($conn) . "</div>";
        }
    }
    elseif (isset($_POST['delete_user'])) {
        $id = intval($_POST['user_id']);
        if ($id == $_SESSION['admin_id']) {
            $message = "<div class='alert alert-danger'>You cannot delete your own account.</div>";
        } else {
            mysqli_query($conn, "DELETE FROM users WHERE id=$id");
            $message = "<div class='alert alert-success'>User deleted successfully!</div>";
        }
    }
}

$users_list = mysqli_query($conn, "SELECT * FROM users ORDER BY id");
?>
<?php include '../includes/header.php'; ?>

<style>
.users-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
    font-size: 13px;
}
.users-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.users-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
}
.role-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-user-cog me-2" style="color: #A04657;"></i> User Management
        </h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i> Add New User
        </button>
    </div>

    <?php echo $message; ?>

    <!-- Users Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-users me-2" style="color: #A04657;"></i> All Users</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover users-table mb-0" id="usersTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Created Date</th>
                            <th style="width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while($u = mysqli_fetch_assoc($users_list)):
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td>
                                <?php if($u['role'] == 'admin'): ?>
                                    <span class="role-badge bg-primary text-white">Admin</span>
                                <?php else: ?>
                                    <span class="role-badge" style="background: #fff3e0; color: #e65100;">Salesman</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d-m-Y H:i', strtotime($u['created_datetime'])); ?></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-xs btn-warning editUserBtn"
                                    data-id="<?php echo $u['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                    data-name="<?php echo htmlspecialchars($u['full_name']); ?>"
                                    data-role="<?php echo htmlspecialchars($u['role']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($u['id'] != $_SESSION['admin_id']): ?>
                                <button type="button" class="btn btn-xs btn-danger deleteUserBtn"
                                    data-id="<?php echo $u['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($u['full_name']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password *</label>
                        <input type="text" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="salesman">Salesman</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary rounded-pill px-4">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" id="edit_username" class="form-control" readonly style="background:#f5f5f5;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="text" name="password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role *</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="salesman">Salesman</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary rounded-pill px-4">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <p class="mb-0">Are you sure you want to delete <strong id="delete_user_name"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.editUserBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.getAttribute('data-id');
            document.getElementById('edit_username').value = this.getAttribute('data-username');
            document.getElementById('edit_full_name').value = this.getAttribute('data-name');
            document.getElementById('edit_role').value = this.getAttribute('data-role');
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        });
    });

    document.querySelectorAll('.deleteUserBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('delete_user_id').value = this.getAttribute('data-id');
            document.getElementById('delete_user_name').textContent = this.getAttribute('data-name');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        });
    });
});
</script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']]
    });
});
</script>

<?php include '../includes/footer.php'; ?>
