<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/image.php';

$pdo = db();
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'Only admin can manage gallery media.');
        redirect('gallery.php');
    }

    try {
        $action = normalize_text($_POST['action'] ?? 'upload');

        if ($action === 'delete_image') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0) {
                throw new InvalidArgumentException('Invalid image selected.');
            }

            $imageRowStmt = $pdo->prepare('SELECT image FROM gallery WHERE id = :id LIMIT 1');
            $imageRowStmt->execute([':id' => $imageId]);
            $imageRow = $imageRowStmt->fetch();
            if (!$imageRow) {
                throw new InvalidArgumentException('Image not found.');
            }

            $deleteImageStmt = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
            $deleteImageStmt->execute([':id' => $imageId]);
            delete_image_file('gallery', (string) $imageRow['image']);

            set_flash('success', 'Image deleted successfully.');
            redirect('gallery.php');
        }

        if ($action === 'delete_video') {
            $videoId = (int) ($_POST['video_id'] ?? 0);
            if ($videoId <= 0) {
                throw new InvalidArgumentException('Invalid video selected.');
            }

            $videoRowStmt = $pdo->prepare('SELECT video FROM event_video WHERE id = :id LIMIT 1');
            $videoRowStmt->execute([':id' => $videoId]);
            $videoRow = $videoRowStmt->fetch();
            if (!$videoRow) {
                throw new InvalidArgumentException('Video not found.');
            }

            $deleteVideoStmt = $pdo->prepare('DELETE FROM event_video WHERE id = :id');
            $deleteVideoStmt->execute([':id' => $videoId]);
            delete_image_file('videos', (string) $videoRow['video']);

            set_flash('success', 'Video deleted successfully.');
            redirect('gallery.php');
        }

        $mediaType = normalize_text($_POST['media_type'] ?? 'image');
        $title = normalize_text($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Please enter a title.');
        }

        if ($mediaType === 'video') {
            $videoName = upload_video_clip($_FILES['video'] ?? [], 'videos');
            if ($videoName === null) {
            throw new InvalidArgumentException('Please choose a video file to upload.');
            }

            $videoStmt = $pdo->prepare(
                'INSERT INTO event_video (title, video, uploaded_at) VALUES (:title, :video, :uploaded_at)'
            );
            $videoStmt->execute([
                ':title' => $title,
                ':video' => $videoName,
                ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ]);
            set_flash('success', 'Video added successfully.');
            redirect('gallery.php');
        }

        $imageFiles = $_FILES['image'] ?? [];
        $hasMultipleFiles = isset($imageFiles['name']) && is_array($imageFiles['name']);
        $uploadedCount = 0;

        if ($hasMultipleFiles) {
            foreach ($imageFiles['name'] as $index => $unusedName) {
                $singleFile = [
                    'name' => $imageFiles['name'][$index] ?? '',
                    'type' => $imageFiles['type'][$index] ?? '',
                    'tmp_name' => $imageFiles['tmp_name'][$index] ?? '',
                    'error' => $imageFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $imageFiles['size'][$index] ?? 0,
                ];

                $imageName = upload_and_resize_image($singleFile, 'gallery', 1400, 1400);
                if ($imageName === null) {
                    continue;
                }

                $imageStmt = $pdo->prepare(
                    'INSERT INTO gallery (title, image, uploaded_at) VALUES (:title, :image, :uploaded_at)'
                );
                $imageStmt->execute([
                    ':title' => $title,
                    ':image' => $imageName,
                    ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ]);
                $uploadedCount++;
            }
        } else {
            $imageName = upload_and_resize_image($imageFiles, 'gallery', 1400, 1400);
            if ($imageName !== null) {
                $imageStmt = $pdo->prepare(
                    'INSERT INTO gallery (title, image, uploaded_at) VALUES (:title, :image, :uploaded_at)'
                );
                $imageStmt->execute([
                    ':title' => $title,
                    ':image' => $imageName,
                    ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ]);
                $uploadedCount = 1;
            }
        }

        if ($uploadedCount === 0) {
            throw new InvalidArgumentException('Please choose an image file to upload.');
        }
        set_flash('success', $uploadedCount === 1
            ? 'Image uploaded to gallery successfully.'
            : $uploadedCount . ' images uploaded to gallery successfully.');
        redirect('gallery.php');
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        redirect('gallery.php');
    }
}

$galleryImages = $pdo->query('SELECT id, image, title, uploaded_at FROM gallery ORDER BY uploaded_at DESC LIMIT 80')->fetchAll();
$eventVideos = $pdo->query('SELECT id, video, title, uploaded_at FROM event_video ORDER BY uploaded_at DESC LIMIT 50')->fetchAll();

render_header('Gallery');
?>
<section class="mb-4">
    <h1 class="h4 mb-3">Gallery</h1>
    <?php if ($isAdmin): ?>
        <div class="card card-soft responsive-upload-card">
            <div class="card-body">
                <h2 class="h5 mb-3">Add Media</h2>
                <form method="post" enctype="multipart/form-data" class="row g-3 mb-4">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="media_type" value="image">
                    <div class="col-md-6">
                        <label class="form-label">Picture Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Choose Picture</label>
                        <input type="file" name="image[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif" multiple required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Picture</button>
                    </div>
                </form>

                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="media_type" value="video">
                    <div class="col-md-6">
                        <label class="form-label">Video Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Choose Video</label>
                        <input type="file" name="video" class="form-control" accept=".mp4,.webm,.ogv,.mov" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Video</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">Everyone can view the gallery. Only admin can add new pictures and videos.</div>
    <?php endif; ?>
</section>

<section class="mb-4">
    <h2 class="section-title">Pictures</h2>
    <?php if ($galleryImages): ?>
        <?php 
        $groupedGallery = [];
        foreach ($galleryImages as $img) {
            $month = date('F Y', strtotime($img['uploaded_at']));
            $groupedGallery[$month][] = $img;
        }
        ?>
        <div class="mb-4">
            <label for="galleryMonthFilter" class="form-label visually-hidden">Filter by Month</label>
            <select id="galleryMonthFilter" class="form-select w-auto d-inline-block">
                <option value="all">All Months</option>
                <?php foreach (array_keys($groupedGallery) as $month): ?>
                    <option value="<?= md5($month) ?>"><?= e($month) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="galleryContainer">
            <?php foreach ($groupedGallery as $month => $images): ?>
                <div class="gallery-month-group" data-month="<?= md5($month) ?>">
                    <h3 class="h5 mb-3 text-muted border-bottom pb-2"><?= e($month) ?></h3>
                    <div class="row g-3 mb-4">
                        <?php foreach ($images as $image): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <a href="<?= e(upload_url('gallery', $image['image'])) ?>"
                                   class="js-glightbox"
                                   data-gallery="event-gallery-<?= md5($month) ?>"
                                   data-title="<?= e($image['title']) ?>">
                                    <img src="<?= e(upload_url('gallery', $image['image'])) ?>" alt="<?= e($image['title']) ?>" class="gallery-image">
                                </a>
                                <p class="small mt-2 mb-0"><?= e($image['title']) ?></p>
                                <?php if ($isAdmin): ?>
                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="action" value="delete_image">
                                        <input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 js-confirm" data-confirm="Delete this picture?">Delete Picture</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var filter = document.getElementById('galleryMonthFilter');
                if (filter) {
                    filter.addEventListener('change', function() {
                        var selected = this.value;
                        var groups = document.querySelectorAll('.gallery-month-group');
                        groups.forEach(function(group) {
                            if (selected === 'all' || group.getAttribute('data-month') === selected) {
                                group.style.display = 'block';
                            } else {
                                group.style.display = 'none';
                            }
                        });
                    });
                }
            });
        </script>
    <?php else: ?>
        <div class="alert alert-secondary">No pictures have been added yet.</div>
    <?php endif; ?>
</section>

<section>
    <h2 class="section-title">Videos</h2>
    <?php if ($eventVideos): ?>
        <div class="row g-3">
            <?php foreach ($eventVideos as $video): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 anim-lift p-2">
                        <video controls preload="metadata" class="event-video-player mb-2">
                            <source src="<?= e(upload_url('videos', $video['video'])) ?>">
                            Your browser does not support video playback.
                        </video>
                        <h3 class="h6 mb-1"><?= e($video['title']) ?></h3>
                        <p class="small text-muted mb-0"><?= e(format_datetime_human($video['uploaded_at'])) ?></p>
                        <?php if ($isAdmin): ?>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="delete_video">
                                <input type="hidden" name="video_id" value="<?= e((string) $video['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 js-confirm" data-confirm="Delete this video?">Delete Video</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No videos have been added yet.</div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
