<?php
// Uncomment to debug
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once "includes/functions.php"; // starts session + loads $conn

// Already logged in? -> dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$logo_url = 'assets/img/logo.png';
if (!file_exists(__DIR__ . "/$logo_url")) {
    $logo_url = 'assets/img/logo.svg'; // Fallback if needed
}

$username = "";
$error = "";

/* Support bcrypt/argon + legacy md5/sha1/plain */
function verify_any_password($plain, $stored)
{
    if ($stored === '' || $stored === null) return false;
    $info = password_get_info($stored);
    if ($info && $info['algo'] !== 0 && password_verify($plain, $stored)) return true; // password_hash()
    if (hash_equals($stored, md5($plain)))  return true;
    if (hash_equals($stored, sha1($plain))) return true;
    if (hash_equals($stored, $plain))       return true; // plain
    return false;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both phone number and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, username, password, role, branch_id FROM users WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows === 1) {
                $row = $res->fetch_assoc();
                if (verify_any_password($password, $row['password'])) {
                    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                    session_regenerate_id(true);

                    $_SESSION['loggedin']     = true;
                    $_SESSION['id']           = (int)$row['id'];
                    $_SESSION['user_id']      = (int)$row['id'];
                    $_SESSION['full_name']    = $row['full_name'];
                    $_SESSION['username']     = $row['username'];
                    $_SESSION['role']         = $row['role'];
                    $_SESSION['branch_id']    = (int)$row['branch_id'];
                    $_SESSION['logged_in']    = true;
                    $_SESSION['is_logged_in'] = true;

                    header("Location: dashboard.php?login=1");
                    exit;
                }
            }
            $stmt->close();
        }
        $error = 'Invalid phone number or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - Namavi Labs</title>

    <link rel="stylesheet" href="assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    /* Page layout */
    body.login-page{
        margin:0; min-height:100vh; display:grid; place-items:center;
        background:
            radial-gradient(1200px 1200px at 10% -10%, rgba(79, 209, 197, .25), transparent 40%),
            radial-gradient(1000px 1000px at 90% 110%, rgba(125, 211, 252, .25), transparent 40%),
            linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
        font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .login-container{ width:100%; display:contents; }

    /* Card */
    .login-box{
        width:420px; max-width:92%;
        background:#fff;
        border:1px solid #E2E8F0;
        border-radius:16px;
        backdrop-filter: blur(8px) saturate(1.1);
        box-shadow:
            0 12px 28px rgba(15,23,42,.08),
            0 2px 6px rgba(15,23,42,.06),
            inset 0 1px 0 rgba(255,255,255,.6);
        padding:1.75rem 1.5rem 1.25rem;
        text-align:left;
    }

    /* Branding + title */
    .login-brand{ display:flex; justify-content:center; margin-bottom:.5rem; }
    .brand-logo{ height:56px; max-width:200px; object-fit:contain; filter: drop-shadow(0 2px 6px rgba(2, 132, 199, .12)); }
    .login-title{
        text-align:center;
        font-size:1.3rem; font-weight:800; margin:.5rem 0 .9rem; letter-spacing:.2px;
        color:#111827; /* dark heading */
    }

    /* Form & labels */
    .form-group{ margin-bottom:.85rem; }
    .input-label{
        display:block; margin:0 0 6px 2px;
        font-weight:700; letter-spacing:.2px;
        color:#111827 !important;
        text-align:left !important;
    }

    /* Inputs — visible & styled */
    .form-control{
        display:block !important;
        visibility:visible !important;
        opacity:1 !important;
        width:100% !important;
        height:48px !important;
        padding:.75rem .9rem !important;
        border-radius:12px !important;
        background:#ffffff !important;
        background-clip: padding-box !important;
        border:1.5px solid #D0D9E3 !important;
        color:#0F172A !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.8) !important;
        transition: border-color .15s ease, box-shadow .2s ease, transform .06s ease !important;
    }
    .form-control::placeholder{ color:#94A3B8 !important; opacity:1 !important; }
    .form-control:focus{
        outline:0 !important;
        border-color:#5EEAD4 !important;
        box-shadow:
            0 0 0 4px rgba(45,212,191,.18),
            0 6px 16px rgba(2,132,199,.12),
            inset 0 1px 0 rgba(255,255,255,.8) !important;
        transform: translateY(-1px);
    }

    /* Autofill fixes */
    input.form-control:-webkit-autofill{
        -webkit-text-fill-color:#0F172A !important;
        box-shadow: 0 0 0 1000px #ffffff inset !important;
        caret-color:#0F172A !important;
    }

    /* Button */
    .btn{
        width:100%; height:48px; border-radius:12px; border:0;
        font-weight:800; letter-spacing:.2px;
        background: linear-gradient(135deg,#4FD1C5 0%, #22C1C3 100%) !important;
        color:#0B2C2C !important;
        box-shadow:
            0 12px 22px rgba(79,209,197,.36),
            0 2px 6px rgba(0,0,0,.06);
        transition: transform .08s ease, box-shadow .12s ease, filter .12s ease;
        display:block;
    }
    .btn:hover{
        transform: translateY(-1px);
        box-shadow:
            0 16px 30px rgba(79,209,197,.42),
            0 4px 10px rgba(0,0,0,.08);
        filter: saturate(1.05);
    }

    .alert{ padding:.6rem .8rem; border-radius:10px; margin-bottom:.75rem; }
    .alert-danger{ background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-brand">
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Brand Logo" class="brand-logo">
            </div>
            <h2 class="login-title">Namavi Labs Login</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="login-username" class="input-label">Phone Number</label>
                    <input id="login-username" type="tel" name="username"
                           class="form-control" placeholder="Enter your 10-digit phone number"
                           required autocomplete="username"
                           value="<?php echo htmlspecialchars($username); ?>">
                </div>

                <div class="form-group">
                    <label for="login-password" class="input-label">Password</label>
                    <input id="login-password" type="password" name="password"
                           class="form-control" placeholder="Enter your password"
                           required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn">Login</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

