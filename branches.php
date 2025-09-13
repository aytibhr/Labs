<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

$is_edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
$branch_data = ['name' => '', 'location' => '', 'address' => ''];
$form_action = "branches.php";
$form_title = "Add New Branch";
$button_text = "Add Branch";
$error = null;
$success_message = '';

// Handle form submission for adding or editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_name = trim($_POST['branch_name']);
    $branch_location = trim($_POST['branch_location']);
    $branch_address = trim($_POST['branch_address']);

    if (isset($_POST['add_branch'])) {
        $stmt = $conn->prepare("INSERT INTO branches (name, location, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $branch_name, $branch_location, $branch_address);
        if ($stmt->execute()) {
            header("Location: branches.php?success=added");
            exit;
        } else {
            $error = "Error adding branch: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        $stmt = $conn->prepare("UPDATE branches SET name = ?, location = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $branch_name, $branch_location, $branch_address, $branch_id);
        if ($stmt->execute()) {
            header("Location: branches.php?success=updated");
            exit;
        } else {
            $error = "Error updating branch: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $branch_id_to_delete = (int)$_GET['delete'];
    
    // Check if any users are assigned to this branch before deleting
    $check_users = $conn->prepare("SELECT id FROM users WHERE branch_id = ?");
    $check_users->bind_param("i", $branch_id_to_delete);
    $check_users->execute();
    $result = $check_users->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Cannot delete branch. It has admin users assigned to it. Please reassign or delete the admins first from the 'Manage Admins' page.";
    } else {
        $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->bind_param("i", $branch_id_to_delete);
        if ($stmt->execute()) {
            header("Location: branches.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting branch.";
        }
        $stmt->close();
    }
    $check_users->close();
}

// Check for success message to show modal
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Branch added successfully.';
            break;
        case 'updated':
            $success_message = 'Branch updated successfully.';
            break;
        case 'deleted':
            $success_message = 'Branch deleted successfully.';
            break;
    }
}

// If in edit mode, fetch the branch data to populate the form
if ($is_edit_mode) {
    $branch_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT name, location, address FROM branches WHERE id = $branch_id");
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

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.modal-overlay.visible {
    display: flex;
    opacity: 1;
}
.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}
.modal-overlay.visible .modal-content {
    transform: scale(1);
}
.modal-content h3 {
    margin-top: 0;
    color: #16a34a; /* green-600 */
}
.modal-content p {
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    color: #334155;
}
.modal-content .btn {
    min-width: 100px;
}
</style>

<div id="successModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Success!</h3>
        <p id="modalMessage"></p>
        <button id="closeModal" class="btn btn-primary">OK</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><?php echo $form_title; ?></h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($form_action); ?>">
            <?php if ($is_edit_mode): ?>
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="branch_name">Branch Name</label>
                    <input type="text" id="branch_name" name="branch_name" class="form-control" value="<?php echo htmlspecialchars($branch_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="branch_location">Location</label>
                    <input type="text" id="branch_location" name="branch_location" class="form-control" value="<?php echo htmlspecialchars($branch_data['location']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="branch_address">Address</label>
                <textarea id="branch_address" name="branch_address" class="form-control" required><?php echo htmlspecialchars($branch_data['address']); ?></textarea>
            </div>
            <div class="form-actions">
                <?php if ($is_edit_mode): ?>
                    <a href="branches.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_branch" class="btn btn-primary"><?php echo $button_text; ?></button>
                <?php else: ?>
                    <button type="submit" name="add_branch" class="btn btn-primary"><?php echo $button_text; ?></button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Existing Branches</h3>
    </div>
    <div class="card-body">
        <div class="responsive-table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($branch = $branches->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Branch Name"><?php echo htmlspecialchars($branch['name']); ?></td>
                            <td data-label="Location"><?php echo htmlspecialchars($branch['location']); ?></td>
                            <td data-label="Address"><?php echo htmlspecialchars($branch['address']); ?></td>
                            <td data-label="Actions" class="actions">
                                <a href="branches.php?edit=<?php echo $branch['id']; ?>" class="icon-btn edit" title="Edit">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" /></svg>
                                </a>
                                <a href="branches.php?delete=<?php echo $branch['id']; ?>" class="icon-btn delete" title="Delete" onclick="return confirm('Are you sure you want to delete this branch?');">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" /></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = '<?php echo addslashes($success_message); ?>';
    const modal = document.getElementById('successModal');
    const modalMessage = document.getElementById('modalMessage');
    const closeModalBtn = document.getElementById('closeModal');

    const closeModal = () => {
        modal.classList.remove('visible');
        // Clean the URL without reloading the page
        window.history.pushState({}, document.title, window.location.pathname);
    };

    if (successMessage) {
        modalMessage.textContent = successMessage;
        modal.classList.add('visible');
    }

    if(closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    
    if(modal) {
        // Close modal if overlay is clicked
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
});
</script>

<?php require_once "includes/footer.php"; ?>

