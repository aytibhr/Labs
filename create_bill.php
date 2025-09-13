<?php
// Enable error reporting for diagnostics
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "includes/functions.php";
redirect_if_not_logged_in();

if ($conn->connect_error) {
    $error = "Database Connection Failed: " . $conn->connect_error;
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_patient'])) {
        if (is_super_admin()) {
            $branch_id = $_POST['branch_id'];
        } else {
            $branch_id = get_user_branch_id();
        }
        $user_id = get_user_id();
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $gender = trim($_POST['gender']);
        $dob_str = $_POST['dob_year'] . '-' . $_POST['dob_month'] . '-' . $_POST['dob_day'];
        $dob = date('Y-m-d', strtotime($dob_str));
        $stmt = $conn->prepare("INSERT INTO patients (name, dob, gender, phone, email, address, created_by, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssii", $name, $dob, $gender, $phone, $email, $address, $user_id, $branch_id);
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

require_once "includes/header.php";
$step = $_GET['step'] ?? '1';
?>

<style>
    /* Styles for Patient Search Results */
    #patient-search-results {
        position: absolute;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0 0 12px 12px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        z-index: 100;
        margin-top: -1px;
    }
    .search-results-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .search-results-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
    }
     .search-results-list li:hover { background-color: #f8fafc; }
     .search-results-list li .btn { min-width: 80px; }


    /* Styles for the new Test Selection UI */
    .test-selection-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: flex-start;
    }
    @media (max-width: 991px) {
        .test-selection-layout {
            grid-template-columns: 1fr;
        }
    }
    .test-search-panel, .test-summary-panel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
    }
    #test-search-results {
        list-style: none;
        padding: 0;
        margin-top: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }
    #test-search-results li {
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    #test-search-results li:hover {
        background-color: #f1f5f9;
    }
    #test-search-results li strong {
        display: block;
        font-weight: 600;
        color: #1e293b;
    }
    #test-search-results li span {
        font-size: 0.85rem;
        color: #64748b;
    }
    #selected-tests-display {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    #selected-tests-display li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    #selected-tests-display li:last-child {
        border-bottom: none;
    }
    #selected-tests-display .item-details {
        display: flex;
        flex-direction: column;
    }
    #selected-tests-display .item-details strong { font-weight: 600; }
    #selected-tests-display .item-details small { color: #64748b; }
    #selected-tests-display .item-price { font-weight: 600; }
    #selected-tests-display .remove-item {
        color: #ef4444;
        cursor: pointer;
        font-weight: bold;
        padding: 2px 8px;
    }
    .no-items {
        color: #94a3b8;
        padding: 2rem 0;
        text-align: center;
    }

    /* Styles for Invoice Summary List */
    .invoice-summary-list {
        list-style: none;
        padding: 0;
        margin: 0;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .invoice-summary-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
    }
     .invoice-summary-list li:last-child { border-bottom: none; }
    .invoice-summary-list li:nth-child(odd) { background: #f8fafc; }
    .invoice-summary-list .item-name {
        display: flex;
        flex-direction: column;
    }
    .invoice-summary-list .item-name small {
        font-size: 0.8rem;
        color: #64748b;
    }
</style>

<div class="card">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- STEP 1: Patient Information -->
    <?php if ($step == '1'): ?>
    <div class="card-header"><h3>Step 1: Onboard Patient</h3></div>
    <div class="card-body">
        <div class="form-group" style="position: relative;">
            <label>Search Existing Patient (by Name or Phone)</label>
            <input type="text" id="patient-search" class="form-control" placeholder="Start typing to search...">
            <div id="patient-search-results"></div>
        </div>
        <hr style="margin: 2rem 0;">
        <h4>Or, Create New Patient</h4>
        <form method="POST" action="create_bill.php">
            <input type="hidden" name="create_patient" value="1">

            <?php if (is_super_admin()): 
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
                    <input type="tel" id="phone" name="phone" class="form-control" pattern="[0-9]{10}" title="Please enter a 10-digit phone number." required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="dob_day" id="dob_day" class="form-control" required><option value="">Day</option><?php for ($d = 1; $d <= 31; $d++): ?><option value="<?php echo $d; ?>"><?php echo $d; ?></option><?php endfor; ?></select>
                        <select name="dob_month" id="dob_month" class="form-control" required><option value="">Month</option><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option><?php endfor; ?></select>
                        <select name="dob_year" id="dob_year" class="form-control" required><option value="">Year</option><?php $currentYear = date('Y'); for ($y = $currentYear; $y >= 1920; $y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?></select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select name="gender" id="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
             <div class="form-group">
                    <label for="age">Age (auto-calculated)</label>
                    <input type="text" id="age" class="form-control" disabled>
            </div>
            <div class="form-group">
                <label for="email">Email Address (Optional)</label>
                <input type="email" id="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label for="address">Address (Optional)</label>
                <textarea id="address" name="address" class="form-control"></textarea>
            </div>
             <div class="form-actions">
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Next: Select Tests</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- STEP 2: Test Selection -->
    <?php if ($step == '2' && isset($_GET['patient_id'])):
        $patient_id = (int)$_GET['patient_id'];
        $patient_res = $conn->query("SELECT * FROM patients WHERE id = $patient_id");
        if($patient_res->num_rows === 0) {
            echo "<div class='alert alert-danger'>Patient not found.</div>";
        } else {
            $patient = $patient_res->fetch_assoc();
    ?>
    <div class="card-header"><h3>Step 2: Select Lab Tests for <?php echo htmlspecialchars($patient['name']); ?></h3></div>
    <div class="card-body">
        <form action="create_bill.php?step=3&patient_id=<?php echo $patient_id; ?>" method="POST">
            <input type="hidden" name="selected_tests" id="selected_tests_input">
            
            <div class="test-selection-layout">
                <div class="test-search-panel">
                    <div class="form-group">
                        <label for="test-search-input">Search by Test Name or Code</label>
                        <input type="text" id="test-search-input" class="form-control" placeholder="e.g., CBC or LFT..." autocomplete="off">
                    </div>
                    <ul id="test-search-results" class="search-results-list">
                         <li class="no-items">Start typing to find tests.</li>
                    </ul>
                </div>
                
                <div class="test-summary-panel">
                    <h4>Selected Tests</h4>
                    <ul id="selected-tests-display" class="selected-items-list">
                        <li class="no-items">No tests selected yet.</li>
                    </ul>
                </div>
            </div>

            <div class="form-actions">
                <a href="create_bill.php?step=1" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Next: Review & Payment</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('test-search-input');
        const resultsList = document.getElementById('test-search-results');
        const selectedList = document.getElementById('selected-tests-display');
        const hiddenInput = document.getElementById('selected_tests_input');
        
        let selectedTests = new Map();

        function updateSelectedList() {
            selectedList.innerHTML = '';
            let ids = [];
            if (selectedTests.size === 0) {
                const noItemsLi = document.createElement('li');
                noItemsLi.className = 'no-items';
                noItemsLi.textContent = 'No tests selected yet.';
                selectedList.appendChild(noItemsLi);
            } else {
                for (let [id, test] of selectedTests) {
                    ids.push(id);
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <div class="item-details">
                            <strong>${test.name}</strong>
                            <small>${test.code}</small>
                        </div>
                        <div class="item-price">₹${parseFloat(test.price).toFixed(2)}</div>
                        <span class="remove-item" data-id="${id}" title="Remove">×</span>
                    `;
                    selectedList.appendChild(li);
                }
            }
            hiddenInput.value = ids.join(',');
        }
        
        function fetchTests(query = '') {
            if (query.length < 2) {
                resultsList.innerHTML = '<li class="no-items">Start typing to find tests.</li>';
                return;
            }
            resultsList.innerHTML = '<li class="no-items">Loading...</li>';
            fetch(`ajax_search_tests.php?query=${encodeURIComponent(query)}`)
                .then(response => response.text())
                .then(html => {
                    resultsList.innerHTML = html || '<li class="no-items">No tests found.</li>';
                });
        }

        searchInput.addEventListener('keyup', function() {
            fetchTests(searchInput.value);
        });

        resultsList.addEventListener('click', function(e) {
            const li = e.target.closest('li[data-id]');
            if (li) {
                const test = {
                    id: li.dataset.id,
                    name: li.dataset.name,
                    code: li.dataset.code,
                    price: li.dataset.price
                };
                if (!selectedTests.has(test.id)) {
                    selectedTests.set(test.id, test);
                    updateSelectedList();
                }
            }
        });

        selectedList.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                const id = e.target.dataset.id;
                selectedTests.delete(id);
                updateSelectedList();
            }
        });

        updateSelectedList();
    });
    </script>
    <?php } endif; ?>

    <!-- STEP 3: Summary & Payment -->
    <?php if ($step == '3' && isset($_GET['patient_id']) && isset($_POST['selected_tests'])):
        $patient_id = (int)$_GET['patient_id'];
        $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
        $test_ids = explode(',', $_POST['selected_tests']);
        $selected_tests_result = false;
        if (!empty($test_ids) && $test_ids[0] != '') {
            $test_ids_safe = array_map('intval', $test_ids);
            $test_ids_str = implode(',', $test_ids_safe);
            $selected_tests_result = $conn->query("SELECT id, test_code, test_name, price FROM lab_tests WHERE id IN ($test_ids_str)");
        }
        $total_amount = 0;
    ?>
     <div class="card-header"><h3>Step 3: Invoice Summary</h3></div>
     <form action="generate_invoice.php" method="POST">
        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
        <input type="hidden" name="test_ids" value="<?php echo isset($test_ids_str) ? $test_ids_str : ''; ?>">
        <input type="hidden" name="total_amount" id="total_amount_input" value="0">
        
        <div class="test-selection-layout">
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
                <div id="cash-details" style="display:none; margin-top: 1rem;">
                    <div class="form-group">
                        <label for="cash_received">Cash Received (₹)</label>
                        <input type="number" step="0.01" id="cash_received" name="initial_payment" class="form-control">
                    </div>
                    <div class="balance">Balance Due: <span id="balance-amount">₹0.00</span></div>
                </div>
            </div>
            <div class="invoice-summary">
                <h4>Tests Selected</h4>
                <ul class="invoice-summary-list">
                    <?php if ($selected_tests_result && $selected_tests_result->num_rows > 0): ?>
                        <?php while($test = $selected_tests_result->fetch_assoc()): $total_amount += $test['price']; ?>
                            <li>
                                <span class="item-name">
                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                    <small><?php echo htmlspecialchars($test['test_code']); ?></small>
                                </span>
                                <strong>₹<?php echo number_format($test['price'], 2); ?></strong>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li>No tests were selected.</li>
                    <?php endif; ?>
                </ul>
                <div class="total" style="text-align:right; margin-top:1rem; font-size:1.2rem;">
                    <span>TOTAL: </span>
                    <strong id="total-amount">₹<?php echo number_format($total_amount, 2); ?></strong>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <a href="create_bill.php?step=2&patient_id=<?= $patient_id ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="generate_invoice" class="btn btn-primary" <?php if ($total_amount == 0) echo 'disabled'; ?>>Generate Invoice</button>
        </div>
     </form>
     <script>
        document.addEventListener('DOMContentLoaded', function() {
            const totalAmountInput = document.getElementById('total_amount_input');
            totalAmountInput.value = '<?php echo $total_amount; ?>';
            
            const totalAmt = parseFloat(totalAmountInput.value) || 0;
            const cashReceivedInput = document.getElementById('cash_received');
            const balanceAmountSpan = document.getElementById('balance-amount');
            const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
            const cashDetailsDiv = document.getElementById('cash-details');

            paymentMethodRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'Cash') {
                        cashDetailsDiv.style.display = 'block';
                    } else {
                        cashDetailsDiv.style.display = 'none';
                        cashReceivedInput.value = '';
                        if(balanceAmountSpan) balanceAmountSpan.textContent = `₹${totalAmt.toFixed(2)}`;
                    }
                });
            });

            if(cashReceivedInput) {
                cashReceivedInput.addEventListener('input', function() {
                    const received = parseFloat(this.value) || 0;
                    const balance = totalAmt - received;
                    if(balanceAmountSpan) {
                         balanceAmountSpan.textContent = `₹${balance >= 0 ? balance.toFixed(2) : totalAmt.toFixed(2)}`;
                    }
                });
            }
            if(balanceAmountSpan) {
                balanceAmountSpan.textContent = `₹${totalAmt.toFixed(2)}`;
            }
        });
     </script>
    <?php endif; ?>
</div>

<?php require_once "includes/footer.php"; ?>

