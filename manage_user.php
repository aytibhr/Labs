<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

$is_edit_mode = isset($_GET['id']) && !empty($_GET['id']);
$user_data = ['full_name' => '', 'username' => '', 'email' => '', 'branch_id' => ''];
$page_title = "Add New Admin";
$form_action = "manage_user.php";
$error = null;

if ($is_edit_mode) {
    $user_id = (int)$_GET['id'];
    $page_title = "Edit Admin";
    $form_action = "manage_user.php?id=" . $user_id;
    
    $result = $conn->query("SELECT full_name, username, email, branch_id FROM users WHERE id = $user_id AND role = 'admin'");
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error = "Admin user not found.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $branch_id = (int)$_POST['branch_id'];

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!$is_edit_mode && empty($password)) {
        $error = "Password is required for a new admin.";
    }

    if ($error === null) {
        if ($is_edit_mode) {
            // Update existing admin
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, email=?, password=?, branch_id=? WHERE id=?");
                $stmt->bind_param("ssssii", $full_name, $phone, $email, $hashed_password, $branch_id, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, email=?, branch_id=? WHERE id=?");
                $stmt->bind_param("sssii", $full_name, $phone, $email, $branch_id, $user_id);
            }
        } else {
            // Create new admin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $full_name, $phone, $email, $hashed_password, $role, $branch_id);
        }

        if ($stmt->execute()) {
            header("Location: users.php?status=success");
            exit;
        } else {
            $error = "Error saving admin: " . $stmt->error;
        }
    }
}

$branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $page_title; ?></h3>
        <a href="users.php" class="btn btn-secondary">Back to Admin List</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($form_action); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (Username)</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" pattern="[0-9]{10}" title="Must be 10 digits" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" <?php if (!$is_edit_mode) echo 'required'; ?>>
                    <?php if ($is_edit_mode): ?><small>Leave blank to keep current password.</small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" <?php if (!$is_edit_mode) echo 'required'; ?>>
                </div>
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
            <div class="form-actions">
                 <a href="users.php" class="btn btn-secondary">Cancel</a>
                 <button type="submit" class="btn btn-primary">Save Admin</button>
            </div>
        </form>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

