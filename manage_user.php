<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

// Determine if we are in "edit" or "add" mode
$is_edit_mode = isset($_GET['id']) && !empty($_GET['id']);
$user_data = [
    'full_name' => '',
    'username' => '',
    'branch_id' => ''
];
$page_title = "Add New Admin";
$form_action = "manage_user.php";

if ($is_edit_mode) {
    $user_id = (int)$_GET['id'];
    $page_title = "Edit Admin";
    $form_action = "manage_user.php?id=" . $user_id;
    
    $result = $conn->query("SELECT full_name, username, branch_id FROM users WHERE id = $user_id");
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error = "User not found.";
    }
}

// Handle form submission for both adding and editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $branch_id = $_POST['branch_id'];
    
    if ($is_edit_mode) {
        // --- UPDATE an existing user ---
        if (!empty($password)) {
            // If a new password is provided, hash it and update it
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, password = ?, branch_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $full_name, $username, $hashed_password, $branch_id, $user_id);
        } else {
            // If no new password, update everything else
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, branch_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $full_name, $username, $branch_id, $user_id);
        }
    } else {
        // --- INSERT a new user ---
        if (empty($password)) {
            $error = "Password is required for new admins.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin'; // All created users are admins
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $full_name, $username, $hashed_password, $role, $branch_id);
        }
    }

    if (empty($error) && $stmt->execute()) {
        header("Location: users.php?status=success");
        exit;
    } else {
        $error = $error ?? "Error: " . $stmt->error;
    }
}

// Fetch branches for the dropdown
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $page_title; ?></h3>
        <a href="users.php" class="btn btn-secondary">Back to Admin List</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo $form_action; ?>">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" <?php if (!$is_edit_mode) echo 'required'; ?>>
                <?php if ($is_edit_mode): ?>
                    <small>Leave blank to keep the current password.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="branch_id">Assign to Branch</label>
                <select name="branch_id" id="branch_id" class="form-control" required>
                    <option value="">-- Choose a Branch --</option>
                    <?php while($branch = $branches->fetch_assoc()): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php if ($branch['id'] == $user_data['branch_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save Admin</button>
        </form>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
