<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

$is_edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
$branch_data = ['name' => '', 'address' => ''];
$form_action = "branches.php";
$form_title = "Add New Branch";
$button_text = "Add Branch";

// Handle form submission for adding or editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_name = trim($_POST['branch_name']);
    $branch_address = trim($_POST['branch_address']);

    if (isset($_POST['add_branch'])) {
        $stmt = $conn->prepare("INSERT INTO branches (name, address) VALUES (?, ?)");
        $stmt->bind_param("ss", $branch_name, $branch_address);
        if ($stmt->execute()) {
            $message = "Branch added successfully.";
        } else {
            $error = "Error adding branch: " . $stmt->error;
        }
    } elseif (isset($_POST['update_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        $stmt = $conn->prepare("UPDATE branches SET name = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssi", $branch_name, $branch_address, $branch_id);
        if ($stmt->execute()) {
            $message = "Branch updated successfully.";
        } else {
            $error = "Error updating branch: " . $stmt->error;
        }
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $branch_id_to_delete = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
    $stmt->bind_param("i", $branch_id_to_delete);
    if ($stmt->execute()) {
        $message = "Branch deleted successfully.";
    } else {
        $error = "Error deleting branch. It might be in use by users or patients.";
    }
}

// If in edit mode, fetch the branch data to populate the form
if ($is_edit_mode) {
    $branch_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT name, address FROM branches WHERE id = $branch_id");
    if ($result->num_rows > 0) {
        $branch_data = $result->fetch_assoc();
        $form_action = "branches.php?edit=" . $branch_id;
        $form_title = "Edit Branch";
        $button_text = "Update Branch";
    }
}

// Fetch all branches to display in the list
$branches = $conn->query("SELECT * FROM branches ORDER BY name ASC");
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $form_title; ?></h3>
    </div>
    <div class="card-body">
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo $form_action; ?>">
            <?php if ($is_edit_mode): ?>
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="branch_name">Branch Name</label>
                <input type="text" id="branch_name" name="branch_name" class="form-control" value="<?php echo htmlspecialchars($branch_data['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="branch_address">Branch Address</label>
                <textarea id="branch_address" name="branch_address" class="form-control" required><?php echo htmlspecialchars($branch_data['address']); ?></textarea>
            </div>
            <?php if ($is_edit_mode): ?>
                <button type="submit" name="update_branch" class="btn btn-primary"><?php echo $button_text; ?></button>
                <a href="branches.php" class="btn btn-secondary">Cancel Edit</a>
            <?php else: ?>
                <button type="submit" name="add_branch" class="btn btn-primary"><?php echo $button_text; ?></button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Existing Branches</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($branch = $branches->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($branch['name']); ?></td>
                            <td><?php echo htmlspecialchars($branch['address']); ?></td>
                            <td class="actions">
                                <a href="branches.php?edit=<?php echo $branch['id']; ?>">Edit</a>
                                <a href="branches.php?delete=<?php echo $branch['id']; ?>" class="btn-link-danger" onclick="return confirm('Are you sure you want to delete this branch? This could affect existing users and invoices.');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
