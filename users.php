<?php
// (Optional during debugging)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once "includes/header.php";
redirect_if_not_logged_in();

if (!is_super_admin()) {
    echo "<div class='card'><div class='card-body'><p>You do not have permission to access this page.</p></div></div>";
    require_once "includes/footer.php";
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid !== ($_SESSION['id'] ?? -1)) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        $message = "Admin deleted.";
    } else {
        $error = "You cannot delete your own account.";
    }
}

// Initial list
$sql = "SELECT u.id, u.full_name, u.username, b.name AS branch_name
        FROM users u
        JOIN branches b ON u.branch_id = b.id
        WHERE u.role = 'admin'
        ORDER BY u.full_name ASC";
$result   = $conn->query($sql);
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
?>
<div class="card">
  <div class="card-header"><h3>Manage Admins</h3></div>
  <div class="card-body">
    <?php if(!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if(!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="filter-bar">
        <input type="text" id="admin-search" placeholder="Search by name or username" class="form-control" />
        <select id="admin-branch-filter" class="form-control">
            <option value="">All Branches</option>
            <?php while($b = $branches->fetch_assoc()): ?>
                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <a href="manage_user.php" class="btn btn-primary">+ Add Admin</a>
    </div>

    <div class="table-container">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Branch</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="admins-table-body">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td data-label="Name"><?= htmlspecialchars($row['full_name']) ?></td>
                <td data-label="Username"><?= htmlspecialchars($row['username']) ?></td>
                <td data-label="Branch"><?= htmlspecialchars($row['branch_name']) ?></td>
                <td data-label="Actions" class="actions">
                  <a class="btn-link" href="manage_user.php?edit=<?= (int)$row['id'] ?>" title="Edit"><i class="la la-edit"></i></a>
                  <form method="POST" action="users.php" onsubmit="return confirm('Delete this admin?')" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" name="delete_user" class="btn-link-danger" title="Delete"><i class="la la-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">No admin users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// --- Admins AJAX Search ---
document.addEventListener('DOMContentLoaded', function() {
  const adminSearch  = document.getElementById('admin-search');
  const adminBranch  = document.getElementById('admin-branch-filter');
  const adminsTbody  = document.getElementById('admins-table-body');

  function performAdminSearch() {
    if (!adminsTbody) return;
    const q = adminSearch ? adminSearch.value.trim() : '';
    const b = adminBranch && adminBranch.value ? adminBranch.value : '';
    fetch(`ajax_search.php?page=users&query=${encodeURIComponent(q)}&branch_id=${encodeURIComponent(b)}`)
      .then(r => r.text())
      .then(html => {
        adminsTbody.innerHTML = html || "<tr><td colspan='4' style='text-align:center;'>No results.</td></tr>";
      });
  }

  if (adminSearch) adminSearch.addEventListener('input', performAdminSearch);
  if (adminBranch) adminBranch.addEventListener('change', performAdminSearch);
});
</script>

<?php require_once "includes/footer.php"; ?>
