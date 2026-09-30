<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/image.php';

$pdo = db();
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'Only admin can manage lectures.');
        redirect('lecture.php');
    }

    try {
        $action = normalize_text((string) ($_POST['action'] ?? 'upload'));
        if ($action === 'delete') {
            $lectureId = (int) ($_POST['lecture_id'] ?? 0);
            if ($lectureId <= 0) {
                throw new InvalidArgumentException('Invalid lecture selected.');
            }

            $lectureStmt = $pdo->prepare('SELECT file_name FROM lecture WHERE id = :id LIMIT 1');
            $lectureStmt->execute([':id' => $lectureId]);
            $lecture = $lectureStmt->fetch();
            if (!$lecture) {
                throw new RuntimeException('Lecture not found.');
            }

            $pdo->prepare('DELETE FROM lecture WHERE id = :id')->execute([':id' => $lectureId]);
            delete_image_file('lectures', $lecture['file_name']);
            set_flash('success', 'Lecture removed successfully.');
            redirect('lecture.php');
        }

        if ($action !== 'upload') {
            throw new InvalidArgumentException('Unsupported lecture action.');
        }

        $title = normalize_text($_POST['title'] ?? '');
        if ($title === '' || strlen($title) > 180) {
            throw new InvalidArgumentException('Please enter a lecture title of up to 180 characters.');
        }

        $fileName = upload_lecture_file($_FILES['lecture_file'] ?? [], 'lectures');
        if ($fileName === null) {
            throw new InvalidArgumentException('Please choose a lecture file to upload.');
        }

        $stmt = $pdo->prepare('INSERT INTO lecture (title, file_name, uploaded_at) VALUES (:title, :file_name, :uploaded_at)');
        $stmt->execute([
            ':title' => $title,
            ':file_name' => $fileName,
            ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        set_flash('success', 'Lecture uploaded successfully.');
        redirect('lecture.php');
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        redirect('lecture.php');
    }
}

$lectures = $pdo->query('SELECT id, title, file_name, uploaded_at FROM lecture ORDER BY uploaded_at DESC LIMIT 100')->fetchAll();

render_header('Lecture');
?>
<section class="mb-4">
    <h1 class="h4 mb-3">Lectures</h1>
    <?php if ($isAdmin): ?>
        <div class="card card-soft responsive-upload-card">
            <div class="card-body">
                <h2 class="h5 mb-3">Add Lecture</h2>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="upload">
                    <div class="col-md-6">
                        <label class="form-label">Lecture Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Lecture File</label>
                        <input type="file" name="lecture_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Lecture</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">Everyone can view and download lectures. Only admin can add new files.</div>
    <?php endif; ?>
</section>

<section>
    <h2 class="section-title">Available Lectures</h2>
    <?php if ($lectures): ?>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Uploaded At</th>
                    <th>File</th>
                    <?php if ($isAdmin): ?>
                        <th class="text-end">Manage</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lectures as $lecture): ?>
                    <tr>
                        <td><?= e($lecture['title']) ?></td>
                        <td><?= e(format_datetime_human($lecture['uploaded_at'])) ?></td>
                        <td>
                            <a class="btn btn-outline-primary btn-sm" href="<?= e(upload_url('lectures', $lecture['file_name'])) ?>" target="_blank" rel="noopener">
                                View / Download
                            </a>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td class="text-end">
                                <form method="post" class="d-inline" onsubmit="return confirm('Remove this lecture permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="lecture_id" value="<?= e((string) $lecture['id']) ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No lectures uploaded yet.</div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
