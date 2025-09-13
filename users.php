<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

$error = null;
// Handle delete via GET request
if (isset($_GET['delete'])) {
    $uid = (int)($_GET['delete']);
    if ($uid !== ($_SESSION['id'] ?? -1)) {
        // Check if the user is associated with any patients or invoices
        $check_patients = $conn->query("SELECT id FROM patients WHERE created_by = $uid LIMIT 1");
        $check_invoices = $conn->query("SELECT id FROM invoices WHERE created_by = $uid LIMIT 1");

        if ($check_patients->num_rows > 0 || $check_invoices->num_rows > 0) {
            $error = "Cannot delete admin. This user is associated with existing patient or invoice records.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();
            header("Location: users.php?success=deleted");
            exit;
        }
    } else {
        $error = "You cannot delete your own account.";
    }
}

$success_message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'deleted') {
        $success_message = "Admin deleted successfully.";
    }
}

// Initial list
$sql = "SELECT u.id, u.full_name, u.username, u.email, b.name AS branch_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'admin'
        ORDER BY u.full_name ASC";
$result   = $conn->query($sql);
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
?>
<div class="card">
  <div class="card-header">
    <h3>Manage Admins</h3>
    <a href="manage_user.php" class="btn btn-primary">+ Add Admin</a>
  </div>
  <div class="card-body">
    <?php if(!empty($success_message)): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if(!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="filter-bar">
        <input type="text" id="admin-search" class="form-control" placeholder="Search by name, phone, or email..." />
        <select id="admin-branch-filter" class="form-control">
            <option value="">All Branches</option>
            <?php mysqli_data_seek($branches, 0); while($b = $branches->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="table-container">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone (Username)</th>
            <th>Email</th>
            <th>Branch</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="admins-table-body">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td data-label="Name"><?= htmlspecialchars($row['full_name']) ?></td>
                <td data-label="Phone (Username)"><?= htmlspecialchars($row['username']) ?></td>
                <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                <td data-label="Branch"><?= htmlspecialchars($row['branch_name'] ?? 'N/A') ?></td>
                <td data-label="Actions" class="actions">
                    <div class="actions-group">
                        <a href="manage_user.php?id=<?php echo $row['id']; ?>" class="icon-btn edit" title="Edit">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" /></svg>
                        </a>
                        <a href="users.php?delete=<?php echo (int)$row['id']; ?>" class="icon-btn delete" title="Delete" onclick="return confirm('Delete this admin? This action cannot be undone.')">
                           <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" /></svg>
                        </a>
                    </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No admin users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const adminSearch  = document.getElementById('admin-search');
  const adminBranch  = document.getElementById('admin-branch-filter');
  const adminsTbody  = document.getElementById('admins-table-body');

  function performAdminSearch() {
    if (!adminsTbody) return;
    const q = adminSearch ? adminSearch.value.trim() : '';
    const b = adminBranch && adminBranch.value ? adminBranch.value : '';
    const colspan = adminsTbody.closest('table').querySelector('thead th').length;

    adminsTbody.classList.add('loading');

    fetch(`ajax_search.php?page=users&query=${encodeURIComponent(q)}&branch_id=${encodeURIComponent(b)}`)
        .then(response => response.json()) // Correctly parse JSON
        .then(data => {
            if (data && data.table_html) {
                adminsTbody.innerHTML = data.table_html;
            } else {
                adminsTbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No results.</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error fetching admin data:', error);
            adminsTbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">An error occurred.</td></tr>`;
        })
        .finally(() => {
            adminsTbody.classList.remove('loading');
        });
  }

    if(adminSearch) {
        let searchTimeout;
        adminSearch.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performAdminSearch, 300);
        });
    }
    if (adminBranch) {
        adminBranch.addEventListener('change', performAdminSearch);
    }
});
</script>

<?php require_once "includes/footer.php"; ?>

