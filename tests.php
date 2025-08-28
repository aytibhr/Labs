<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

// Superadmin-only actions
if (is_super_admin()) {
    $is_edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
    $test_data = ['test_code' => '', 'test_name' => '', 'price' => '', 'branch_id' => ''];
    $form_action = "tests.php";
    $form_title = "Add New Lab Test";
    $button_text = "Add Test";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_code = trim($_POST['test_code']);
        $test_name = trim($_POST['test_name']);
        $price = (float)$_POST['price'];
        $branch_id = (int)$_POST['branch_id'];

        if (isset($_POST['add_test'])) {
            $stmt = $conn->prepare("INSERT INTO lab_tests (test_code, test_name, price, branch_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdi", $test_code, $test_name, $price, $branch_id);
            if ($stmt->execute()) $message = "Test added successfully.";
            else $error = "Error: " . $stmt->error;
        } elseif (isset($_POST['update_test'])) {
            $test_id = (int)$_POST['test_id'];
            $stmt = $conn->prepare("UPDATE lab_tests SET test_code = ?, test_name = ?, price = ?, branch_id = ? WHERE id = ?");
            $stmt->bind_param("ssdii", $test_code, $test_name, $price, $branch_id, $test_id);
            if ($stmt->execute()) $message = "Test updated successfully.";
            else $error = "Error: " . $stmt->error;
        }
    }

    if (isset($_GET['delete'])) {
        $test_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM lab_tests WHERE id = ?");
        $stmt->bind_param("i", $test_id);
        if ($stmt->execute()) $message = "Test deleted successfully.";
        else $error = "Error deleting test. It might be linked to existing invoices.";
    }

    if ($is_edit_mode) {
        $test_id = (int)$_GET['edit'];
        $result = $conn->query("SELECT * FROM lab_tests WHERE id = $test_id");
        if ($result->num_rows > 0) {
            $test_data = $result->fetch_assoc();
            $form_action = "tests.php?edit=" . $test_id;
            $form_title = "Edit Lab Test";
            $button_text = "Update Test";
        }
    }
}

// Data fetching for display
$sql = "SELECT t.*, b.name as branch_name FROM lab_tests t JOIN branches b ON t.branch_id = b.id";
if (!is_super_admin()) {
    $sql .= " WHERE t.branch_id = " . get_user_branch_id();
}
$sql .= " ORDER BY t.test_name ASC";
$tests_result = $conn->query($sql);
$branches = is_super_admin() ? $conn->query("SELECT id, name FROM branches ORDER BY name") : null;
?>

<?php if (is_super_admin()): ?>
<div class="card">
    <div class="card-header"><h3><?php echo $form_title; ?></h3></div>
    <div class="card-body">
        <?php if(isset($message)) echo "<div class='alert alert-success'>$message</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST" action="<?php echo $form_action; ?>">
    <?php if($is_edit_mode): ?>
        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
    <?php endif; ?>
    <div class="form-grid">
        <div class="form-group">
            <label>Test Code</label>
            <input type="text" name="test_code" class="form-control" value="<?php echo htmlspecialchars($test_data['test_code']); ?>" required>
        </div>
        <div class="form-group">
            <label>Test Name</label>
            <input type="text" name="test_name" class="form-control" value="<?php echo htmlspecialchars($test_data['test_name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Price (₹)</label>
            <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($test_data['price']); ?>" required>
        </div>
        <div class="form-group">
            <label>Branch</label>
            <select name="branch_id" class="form-control" required>
                <option value="">Select Branch</option>
                <?php mysqli_data_seek($branches, 0); while($branch = $branches->fetch_assoc()): ?>
                    <option value="<?php echo $branch['id']; ?>" <?php echo ($test_data['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <?php if ($is_edit_mode): ?>
        <button type="submit" name="update_test" class="btn btn-primary">Update Test</button>
        <a href="tests.php" class="btn btn-secondary">Cancel</a>
    <?php else: ?>
        <button type="submit" name="add_test" class="btn btn-primary">Add Test</button>
    <?php endif; ?>
</form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?php echo is_super_admin() ? 'All Lab Tests' : 'Available Lab Tests'; ?></h3></div>
    <div class="card-body">
        <div class="filter-bar">
            <div class="form-group">
                <input type="text" id="ajax-search" data-page="tests" class="form-control" placeholder="Search by test name or code...">
            </div>
        </div>
        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Price (₹)</th>
                        <?php if (is_super_admin()) echo '<th>Branch</th><th>Actions</th>'; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tests_result && $tests_result->num_rows > 0): ?>
                        <?php while($test = $tests_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Test Name"><?php echo htmlspecialchars($test['test_name']); ?></td>
                                <td data-label="Price">₹<?php echo number_format($test['price'], 2); ?></td>
                                <?php if (is_super_admin()): ?>
                                    <td data-label="Branch"><?php echo htmlspecialchars($test['branch_name']); ?></td>
                                    <td data-label="Actions" class="actions">
                                        <a href="tests.php?edit=<?php echo $test['id']; ?>" title="Edit">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                              <path d="M12 20h9" />
                                              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                                            </svg>
                                        </a>
                                        <a href="tests.php?delete=<?php echo $test['id']; ?>" title="Delete" class="btn-link-danger" onclick="return confirm('Are you sure you want to delete this test?');">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                            <line x1="10" y1="11" x2="10" y2="17" />
                                            <line x1="14" y1="11" x2="14" y2="17" />
                                            <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                                            </svg>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo is_super_admin() ? '4' : '2'; ?>" style="text-align: center;">No tests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>