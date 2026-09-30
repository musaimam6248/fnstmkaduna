<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user && $user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('department/dashboard.php');
}

if (is_post()) {
    $username = normalize_text($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        set_flash('error', 'Username and password are required.');
        redirect('login.php');
    }

    if (!login_user($username, $password)) {
        set_flash('error', 'Invalid login details.');
        redirect('login.php');
    }

    $user = current_user();
    set_flash('success', 'Welcome back, ' . ($user['username'] ?? 'User') . '!');
    if ($user && $user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('department/dashboard.php');
}

render_header('Login');
?>
<section class="row justify-content-center fade-rise">
    <div class="col-lg-5">
        <div class="card card-soft">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Sign in</h1>
                <p class="text-muted mb-4">Use your username and password to access the admin or department dashboard.</p>
                <form method="post" action="<?= e(app_url('login.php')) ?>" class="vstack gap-3">
                    <div>
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
                    </div>
                    <div>
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Continue</button>
                </form>
                <div class="mt-2">
                    <a href="<?= e(app_url('forgot_password.php')) ?>" class="small">Forgot password for department login?</a>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <p class="fw-semibold mb-1">Default admin access</p>
                    <p class="mb-1 small">Username: <code><?= e(DEFAULT_ADMIN_USERNAME) ?></code></p>
                    <p class="mb-0 small">Password: <code><?= e(DEFAULT_ADMIN_PASSWORD) ?></code></p>
                </div>
                <hr>
                <p class="mb-1 small">New department? Create an account first.</p>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(app_url('register_department.php')) ?>">
                    Register Department
                </a>
            </div>
        </div>
    </div>
</section>
<?php
render_footer();
