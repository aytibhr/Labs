<?php
require_once "includes/header.php";
redirect_if_not_logged_in();
if (!is_super_admin()) {
    echo "<div class='card'><p>You do not have permission to access this page.</p></div>";
    require_once "includes/footer.php";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $settings_to_update = [
        'lab_name' => $_POST['lab_name'],
        'lab_address' => $_POST['lab_address'],
        'lab_phone' => $_POST['lab_phone'],
        'invoice_footer_note' => $_POST['invoice_footer_note']
    ];
    foreach ($settings_to_update as $key => $value) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }

    if (isset($_FILES["lab_logo"]) && $_FILES["lab_logo"]["error"] == 0) {
        $target_dir = "assets/img/";
        $new_logo_name = "logo." . pathinfo($_FILES["lab_logo"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $new_logo_name;
        if (move_uploaded_file($_FILES["lab_logo"]["tmp_name"], $target_file)) {
            $conn->query("UPDATE settings SET setting_value = '$target_file' WHERE setting_key = 'lab_logo_path'");
        }
    }
    $success_message = "Settings updated successfully!";
}

$settings = [];
$result = $conn->query("SELECT * FROM settings");
while($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<div class="card">
    <div class="card-header">
        <h3>Application Settings</h3>
    </div>
    <div class="card-body">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Lab Name</label>
                    <input type="text" name="lab_name" class="form-control" value="<?php echo htmlspecialchars($settings['lab_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Lab Phone</label>
                    <input type="text" name="lab_phone" class="form-control" value="<?php echo htmlspecialchars($settings['lab_phone']); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Lab Address</label>
                <textarea name="lab_address" class="form-control"><?php echo htmlspecialchars($settings['lab_address']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Invoice Footer Note</label>
                <input type="text" name="invoice_footer_note" class="form-control" value="<?php echo htmlspecialchars($settings['invoice_footer_note']); ?>">
            </div>
            <div class="form-group">
                <label>Update Lab Logo</label>
                <input type="file" name="lab_logo" class="form-control" accept="image/png, image/jpeg">
                <small>Current logo: <img src="<?php echo htmlspecialchars($settings['lab_logo_path']); ?>" alt="logo" style="height: 30px; vertical-align: middle; margin-left: 10px;"></small>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>