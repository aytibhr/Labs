<?php
require_once "includes/header.php";
redirect_if_not_logged_in();

$message = null;
$error = null;
$is_edit_mode = false;
$test_data = ['test_code' => '', 'test_name' => '', 'price' => '', 'is_outsourced' => 0];
$form_action = "tests.php";
$form_title = "Add New Lab Test";
$button_text = "Add Test";

// --- Superadmin Actions Handler ---
if (is_super_admin()) {
    $is_edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $test_code = trim($_POST['test_code']);
        $test_name = trim($_POST['test_name']);
        $price = (float)$_POST['price'];
        $is_outsourced = isset($_POST['is_outsourced']) ? 1 : 0;

        if (isset($_POST['add_test'])) {
            $stmt = $conn->prepare("INSERT INTO lab_tests (test_code, test_name, price, is_outsourced) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdi", $test_code, $test_name, $price, $is_outsourced);
            if ($stmt->execute()) {
                header("Location: tests.php?success=added");
                exit;
            } else {
                $error = "Error: " . $stmt->error;
            }
        } elseif (isset($_POST['update_test'])) {
            $test_id = (int)$_POST['test_id'];
            $stmt = $conn->prepare("UPDATE lab_tests SET test_code = ?, test_name = ?, price = ?, is_outsourced = ? WHERE id = ?");
            $stmt->bind_param("ssdii", $test_code, $test_name, $price, $is_outsourced, $test_id);
            if ($stmt->execute()) {
                header("Location: tests.php?success=updated");
                exit;
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    }

    if (isset($_GET['delete'])) {
        $test_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM lab_tests WHERE id = ?");
        $stmt->bind_param("i", $test_id);
        if ($stmt->execute()) {
            header("Location: tests.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting test. It might be linked to existing invoices.";
        }
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
    
    if(isset($_GET['success'])) {
        if($_GET['success'] == 'added') $message = "Test added successfully.";
        if($_GET['success'] == 'updated') $message = "Test updated successfully.";
        if($_GET['success'] == 'deleted') $message = "Test deleted successfully.";
    }
}

// Data fetching for display (for both admin roles)
$tests_result = $conn->query("SELECT * FROM lab_tests ORDER BY test_name ASC");
?>

<?php if (is_super_admin()): ?>
<div class="card">
    <div class="card-header"><h3><?php echo $form_title; ?></h3></div>
    <div class="card-body">
        <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
        <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST" action="<?php echo $form_action; ?>">
            <?php if($is_edit_mode): ?>
                <input type="hidden" name="test_id" value="<?php echo (int)$test_data['id']; ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Short Name</label>
                    <input type="text" name="test_code" class="form-control" value="<?php echo htmlspecialchars($test_data['test_code']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="test_name" class="form-control" value="<?php echo htmlspecialchars($test_data['test_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Price (₹)</label>
                    <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($test_data['price']); ?>" required>
                </div>
                <div class="form-group" style="align-self: center;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_outsourced" value="1" <?php if(!empty($test_data['is_outsourced']) && $test_data['is_outsourced']) echo 'checked'; ?>>
                        Outsourced Test
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($is_edit_mode): ?>
                    <a href="tests.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_test" class="btn btn-primary"><?php echo $button_text; ?></button>
                <?php else: ?>
                    <button type="submit" name="add_test" class="btn btn-primary"><?php echo $button_text; ?></button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?php echo is_super_admin() ? 'All Lab Tests' : 'Available Lab Tests'; ?></h3></div>
    <div class="card-body">
        <div class="filter-bar">
            <div class="form-group">
                <input type="text" id="ajax-search" class="form-control" placeholder="Search by full name or short name...">
            </div>
        </div>
        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Short Name</th>
                        <th>Price (₹)</th>
                        <?php if (is_super_admin()) echo '<th>Outsourced</th><th>Actions</th>'; ?>
                    </tr>
                </thead>
                <tbody id="tests-table-body">
                    <?php if ($tests_result && $tests_result->num_rows > 0): ?>
                        <?php while($test = $tests_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Full Name"><?php echo htmlspecialchars($test['test_name']); ?></td>
                                <td data-label="Short Name"><?php echo htmlspecialchars($test['test_code']); ?></td>
                                <td data-label="Price">₹<?php echo number_format($test['price'], 2); ?></td>
                                <?php if (is_super_admin()): ?>
                                    <td data-label="Outsourced"><?= $test['is_outsourced'] ? 'Yes' : 'No' ?></td>
                                    <td data-label="Actions" class="actions">
                                        <div class="actions-group">
                                            <a href="tests.php?edit=<?php echo $test['id']; ?>" class="icon-btn edit" title="Edit">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" /></svg>
                                            </a>
                                            <a href="tests.php?delete=<?php echo $test['id']; ?>" class="icon-btn delete" title="Delete" onclick="return confirm('Are you sure you want to delete this test?');">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" /></svg>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo is_super_admin() ? '5' : '3'; ?>" class="text-center">No tests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('ajax-search');
    const tableBody = document.getElementById('tests-table-body');
    const colspan = tableBody.closest('table').querySelector('thead th').length;

    function loadTests(page = 1) {
        if (!tableBody) return;
        const query = searchInput ? searchInput.value : '';

        // Add loading state
        tableBody.classList.add('loading');

        const url = `ajax_search.php?page=tests&query=${encodeURIComponent(query)}`;

        fetch(url)
            .then(response => response.json()) // <-- CORRECTED: Parse JSON
            .then(data => {
                if (data && data.table_html) {
                    tableBody.innerHTML = data.table_html;
                } else {
                    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No tests found.</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Error fetching test data:', error);
                tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">An error occurred. Please try again.</td></tr>`;
            })
            .finally(() => {
                // Remove loading state
                tableBody.classList.remove('loading');
            });
    }

    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadTests(1);
            }, 300); // Debounce search
        });
    }
});
</script>

<?php require_once "includes/footer.php"; ?>

