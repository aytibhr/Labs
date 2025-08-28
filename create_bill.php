<?php
// --- DEBUGGING STEP ---
// These lines force PHP to display errors on the screen instead of a generic 500 error.
// This is essential for finding the root cause.
// REMOVE these two lines once the application is working correctly.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// It is critical to require files first.
require_once "includes/functions.php";

// --- SAFER LOGIC FLOW ---
// 1. Redirect immediately if the user is not logged in. This ensures no other code runs without a valid session.
redirect_if_not_logged_in();

// 2. Check for the database connection after we know the user is logged in.
if ($conn->connect_error) {
    // Store the error message to display it gracefully in the HTML body.
    $error = "Database Connection Failed: " . $conn->connect_error;
} else {
    // 3. Only process the form submission if the DB connection is valid and the request is a POST.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_patient'])) {
        
        // --- FIX for 'branch_id cannot be null' ---
        // If the user is a superadmin, get the branch_id from the form's dropdown.
        // Otherwise, get it from the regular admin's session.
        if (is_super_admin()) {
            $branch_id = $_POST['branch_id'];
        } else {
            $branch_id = get_user_branch_id();
        }

        // Fetch session-dependent variables only when they are needed.
        $user_id = get_user_id();

        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        $dob_str = $_POST['dob_year'] . '-' . $_POST['dob_month'] . '-' . $_POST['dob_day'];
        $dob = date('Y-m-d', strtotime($dob_str));

        $stmt = $conn->prepare("INSERT INTO patients (name, dob, phone, email, address, created_by, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssii", $name, $dob, $phone, $email, $address, $user_id, $branch_id);
        
        if ($stmt->execute()) {
            $patient_id = $stmt->insert_id;
            $stmt->close();
            header("Location: create_bill.php?step=2&patient_id=" . $patient_id);
            exit; 
        } else {
            $error = "Error creating patient: " . $stmt->error;
        }
    }
}

// Now we can safely include the header and start outputting the page.
require_once "includes/header.php";

$step = $_GET['step'] ?? '1';
?>

<div class="card">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- STEP 1: Patient Information -->
    <?php if ($step == '1'): ?>
    <div class="card-header"><h3>Step 1: Onboard Patient</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>Search Existing Patient (by Name or Phone)</label>
            <input type="text" id="patient-search" class="form-control" placeholder="Start typing to search...">
            <div id="patient-search-results"></div>
        </div>
        <hr style="margin: 2rem 0;">
        <h4>Or, Create New Patient</h4>
        <form method="POST" action="create_bill.php">
            <input type="hidden" name="create_patient" value="1">

            <?php // --- ADDED: Branch selection dropdown for Super Admin ---
            if (is_super_admin()): 
                $branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
            ?>
            <div class="form-group">
                <label for="branch_id">Select Branch for Patient</label>
                <select name="branch_id" id="branch_id" class="form-control" required>
                    <option value="">-- Choose a Branch --</option>
                    <?php while($branch = $branches->fetch_assoc()): ?>
                        <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           pattern="[0-9]{10}" title="Please enter a 10-digit phone number." required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <div style="display: flex; gap: 10px;">
                        <!-- Day Dropdown -->
                        <select name="dob_day" id="dob_day" class="form-control" required>
                            <option value="">Day</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                            <?php endfor; ?>
                        </select>
                        <!-- Month Dropdown -->
                        <select name="dob_month" id="dob_month" class="form-control" required>
                            <option value="">Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <!-- Year Dropdown -->
                        <select name="dob_year" id="dob_year" class="form-control" required>
                            <option value="">Year</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= 1920; $y--): 
                            ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="age">Age (auto-calculated)</label>
                    <input type="text" id="age" class="form-control" disabled>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email Address (Optional)</label>
                <input type="email" id="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label for="address">Address (Optional)</label>
                <textarea id="address" name="address" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Next: Select Tests</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- STEP 2: Test Selection (No changes here) -->
    <?php if ($step == '2' && isset($_GET['patient_id'])):
        $patient_id = (int)$_GET['patient_id'];
        $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
        // Use the patient's branch_id for fetching tests, not the admin's
        $branch_id_for_tests = $patient['branch_id'];
        $tests = $conn->query("SELECT * FROM lab_tests WHERE branch_id = $branch_id_for_tests");
    ?>
    <div class="card-header"><h3>Step 2: Select Lab Tests for <?php echo htmlspecialchars($patient['name']); ?></h3></div>
    <div class="card-body">
        <form action="create_bill.php?step=3&patient_id=<?php echo $patient_id; ?>" method="POST">
            <input type="hidden" name="selected_tests" id="selected_tests_input">
            <div class="test-selection-grid">
                <?php while($test = $tests->fetch_assoc()): ?>
                <div class="test-card" data-id="<?php echo $test['id']; ?>" data-name="<?php echo htmlspecialchars($test['test_name']); ?>" data-price="<?php echo $test['price']; ?>">
                    <img src="<?php echo htmlspecialchars($test['image_path']); ?>" alt="<?php echo htmlspecialchars($test['test_name']); ?>">
                    <h5><?php echo htmlspecialchars($test['test_name']); ?></h5>
                    <p class="price">₹<?php echo number_format($test['price'], 2); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
            <div style="text-align: right; margin-top: 2rem;">
                 <button type="submit" class="btn btn-primary">Next: Review & Payment</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- STEP 3: Summary & Payment (No changes here) -->
    <?php if ($step == '3' && isset($_GET['patient_id']) && isset($_POST['selected_tests'])):
        $patient_id = (int)$_GET['patient_id'];
        $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
        $test_ids = explode(',', $_POST['selected_tests']);
        if (!empty($test_ids) && $test_ids[0] != '') {
            $test_ids_safe = array_map('intval', $test_ids);
            $test_ids_str = implode(',', $test_ids_safe);
            $selected_tests_result = $conn->query("SELECT * FROM lab_tests WHERE id IN ($test_ids_str)");
        } else {
            $selected_tests_result = false;
        }
        $total_amount = 0;
    ?>
     <div class="card-header"><h3>Step 3: Invoice Summary</h3></div>
     <form action="generate_invoice.php" method="POST">
        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
        <input type="hidden" name="test_ids" value="<?php echo isset($test_ids_str) ? $test_ids_str : ''; ?>">
        <input type="hidden" name="total_amount" id="total_amount_input">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <div>
                <h4>Patient Details</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                <p><strong>Age:</strong> <?php echo date_diff(date_create($patient['dob']), date_create('today'))->y; ?> years</p>
                <h4 style="margin-top: 1.5rem;">Payment Method</h4>
                <div class="payment-options">
                    <input type="radio" id="pay_cash" name="payment_method" value="Cash" required>
                    <label for="pay_cash">Cash</label>
                    <input type="radio" id="pay_upi" name="payment_method" value="UPI">
                    <label for="pay_upi">UPI</label>
                </div>
                <div id="cash-details">
                    <div class="form-group">
                        <label for="cash_received">Cash Received (₹)</label>
                        <input type="number" step="0.01" id="cash_received" name="cash_received" class="form-control">
                    </div>
                    <div class="balance">Balance to Return: <span id="balance-amount">₹0.00</span></div>
                </div>
            </div>
            <div class="invoice-summary">
                <h4>Tests Selected</h4>
                <ul id="selected-tests-list">
                    <?php if ($selected_tests_result): ?>
                        <?php while($test = $selected_tests_result->fetch_assoc()): $total_amount += $test['price']; ?>
                            <li><span><?php echo htmlspecialchars($test['test_name']); ?></span><strong>₹<?php echo number_format($test['price'], 2); ?></strong></li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li>No tests were selected.</li>
                    <?php endif; ?>
                </ul>
                <div class="total">
                    <span>TOTAL</span>
                    <strong id="total-amount">₹<?php echo number_format($total_amount, 2); ?></strong>
                </div>
            </div>
        </div>
        <div style="text-align: right; margin-top: 2rem;">
            <button type="submit" name="generate_invoice" class="btn btn-primary" <?php if ($total_amount == 0) echo 'disabled'; ?>>Generate Invoice</button>
        </div>
     </form>
     <script>document.getElementById('total_amount_input').value = '<?php echo $total_amount; ?>';</script>
    <?php endif; ?>
</div>
<?php require_once "includes/footer.php"; ?>
