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
    $departmentName = normalize_text($_POST['department_name'] ?? '');
    $hodName = normalize_text($_POST['hod_name'] ?? '');
    $username = normalize_text($_POST['username'] ?? '');
    $email = normalize_text($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($departmentName === '' || $hodName === '' || $username === '' || $password === '') {
        set_flash('error', 'Please complete all required fields.');
        redirect('register_department.php');
    }

    if ($password !== $confirmPassword) {
        set_flash('error', 'Password confirmation does not match.');
        redirect('register_department.php');
    }

    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters.');
        redirect('register_department.php');
    }

    try {
        register_department_user($departmentName, $hodName, $username, $password, $email);
        set_flash('success', 'Department account created successfully. You can now log in.');
        redirect('login.php');
    } catch (Throwable $exception) {
        set_flash('error', 'Unable to register department: ' . $exception->getMessage());
        redirect('register_department.php');
    }
}

render_header('Department Registration');
?>
<section class="row justify-content-center fade-rise">
    <div class="col-lg-7">
        <div class="card card-soft">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Register a Department</h1>
                <p class="text-muted mb-4">Create a department login with the HOD name, username, and a secure password.</p>
                <form method="post" action="<?= e(app_url('register_department.php')) ?>" class="row g-3">
                    <div class="col-md-6">
                        <label for="department_name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" autocomplete="organization" required>
                    </div>
                    <div class="col-md-6">
                        <label for="hod_name" class="form-label">HOD Name</label>
                        <input type="text" class="form-control" id="hod_name" name="hod_name" autocomplete="name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">Login Username</label>
                        <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email (Optional)</label>
                        <input type="email" class="form-control" id="email" name="email" autocomplete="email">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-primary">Create Department Account</button>
                    </div>
                </form>
                <div class="mt-3">
                    <a href="<?= e(app_url('login.php')) ?>" class="small">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
render_footer();
