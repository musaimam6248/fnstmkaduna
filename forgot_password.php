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
    $hodKey = normalize_text($_POST['hod_key'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($username === '' || $hodKey === '' || $newPassword === '' || $confirmPassword === '') {
        set_flash('error', 'Please complete all fields.');
        redirect('forgot_password.php');
    }

    if ($newPassword !== $confirmPassword) {
        set_flash('error', 'Password confirmation does not match.');
        redirect('forgot_password.php');
    }

    if (strlen($newPassword) < 8) {
        set_flash('error', 'New password must be at least 8 characters.');
        redirect('forgot_password.php');
    }

    try {
        $reset = reset_department_password_with_hod_key($username, $hodKey, $newPassword);
        if (!$reset) {
            set_flash('error', 'Reset failed. Check username and HOD key.');
            redirect('forgot_password.php');
        }

        set_flash('success', 'Password reset successful. You can now log in.');
        redirect('login.php');
    } catch (Throwable $exception) {
        set_flash('error', 'Unable to reset password: ' . $exception->getMessage());
        redirect('forgot_password.php');
    }
}

render_header('Forgot Password');
?>
<section class="row justify-content-center fade-rise">
    <div class="col-lg-6">
        <div class="card card-soft">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Forgot Password (HOD)</h1>
                <p class="text-muted mb-4">Use your HOD name as key to reset your department login password.</p>

                <form method="post" action="<?= e(app_url('forgot_password.php')) ?>" class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="username">Department Username</label>
                        <input class="form-control" type="text" id="username" name="username" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="hod_key">HOD Key (HOD Name)</label>
                        <input class="form-control" type="text" id="hod_key" name="hod_key" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">New Password</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
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
