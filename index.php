<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

$currentWinnerStmt = $pdo->query(
    "SELECT w.id, w.month, w.year, w.reasons,
            w.image AS winner_photo,
            s.name AS staff_name, s.photo AS staff_photo, s.rank AS staff_rank,
            d.name AS department_name
     FROM winner w
     INNER JOIN staff s ON s.id = w.staff_id
     INNER JOIN department d ON d.id = s.department_id
     ORDER BY w.year DESC, w.month DESC
     LIMIT 1"
);
$currentWinner = $currentWinnerStmt->fetch() ?: null;
$currentWinnerBackgroundImage = $currentWinner
    ? ($currentWinner['winner_photo'] ? upload_url('winners', $currentWinner['winner_photo']) : upload_url('staff', $currentWinner['staff_photo']))
    : null;

$pastWinnerSql = "SELECT w.id, w.month, w.year, w.reasons,
                         w.image AS winner_photo,
                         s.name AS staff_name, s.photo AS staff_photo, s.rank AS staff_rank,
                         d.name AS department_name
                  FROM winner w
                  INNER JOIN staff s ON s.id = w.staff_id
                  INNER JOIN department d ON d.id = s.department_id";
$pastWinnerParams = [];
if ($currentWinner) {
    $pastWinnerSql .= " WHERE w.id <> :current_id";
    $pastWinnerParams[':current_id'] = (int) $currentWinner['id'];
}
$pastWinnerSql .= " ORDER BY w.year DESC, w.month DESC LIMIT 8";
$pastWinnerStmt = $pdo->prepare($pastWinnerSql);
$pastWinnerStmt->execute($pastWinnerParams);
$pastWinners = $pastWinnerStmt->fetchAll();

$galleryStmt = $pdo->query('SELECT id, image, title, uploaded_at FROM gallery ORDER BY uploaded_at DESC LIMIT 40');
$galleryImages = $galleryStmt->fetchAll();

$videoStmt = $pdo->query('SELECT id, video, title, uploaded_at FROM event_video ORDER BY uploaded_at DESC LIMIT 6');
$eventVideos = $videoStmt->fetchAll();

$committeeStmt = $pdo->query('SELECT id, name, role, photo FROM committee ORDER BY created_at DESC');
$committeeMembers = $committeeStmt->fetchAll();

$departmentInfo = $pdo->query('SELECT vision, mission, objectives, image FROM department_info ORDER BY updated_at DESC LIMIT 1')->fetch();
if (!empty($departmentInfo['image'])) {
    $departmentInfoImage = upload_url('gallery', $departmentInfo['image']);
} else {
    $departmentInfoImage = app_url('assets/img/placeholder.svg');
}
$submissionSchedule = $pdo->query(
    'SELECT opens_at, closes_at, is_enabled, note, updated_at FROM submission_schedule WHERE id = 1 LIMIT 1'
)->fetch();
$scheduleEnabled = $submissionSchedule ? ((int) $submissionSchedule['is_enabled'] === 1) : false;
$scheduleState = get_submission_window_state(
    $submissionSchedule['opens_at'] ?? null,
    $submissionSchedule['closes_at'] ?? null,
    $scheduleEnabled
);
$homepageSettings = $pdo->query('SELECT homepage_background_color, school_name, school_badge FROM homepage_settings WHERE id = 1 LIMIT 1')->fetch() ?: [];
$homepageBackgroundImages = $pdo->query('SELECT image FROM homepage_background_image ORDER BY sort_order ASC, id ASC LIMIT 10')->fetchAll();
$homepageBackgroundUrls = array_map(static fn($image) => upload_url('gallery', $image['image']), $homepageBackgroundImages);
$homepageBackgroundColor = normalize_hex_color((string) ($homepageSettings['homepage_background_color'] ?? ''));
$homepageBackgroundOverlay = hex_color_with_alpha($homepageBackgroundColor);
$schoolName = normalize_text((string) ($homepageSettings['school_name'] ?? '')) ?: APP_NAME;
$schoolBadgeUrl = !empty($homepageSettings['school_badge']) ? upload_url('gallery', $homepageSettings['school_badge']) : null;

render_header('Home');
?>
<div data-home-page="true" class="homepage-background" data-background-images="<?= e((string) json_encode($homepageBackgroundUrls, JSON_UNESCAPED_SLASHES)) ?>" style="--homepage-background-color: <?= e($homepageBackgroundColor ?? 'transparent') ?>; --homepage-background-overlay: <?= e($homepageBackgroundOverlay ?? 'rgba(244, 246, 249, 0.84)') ?>;<?php if ($homepageBackgroundUrls): ?> --homepage-background-image: url('<?= e($homepageBackgroundUrls[0]) ?>');<?php endif; ?>">
<section class="school-identity-banner mb-4 reveal-on-scroll" aria-label="School identity">
    <?php if ($schoolBadgeUrl): ?>
        <img src="<?= e($schoolBadgeUrl) ?>" alt="<?= e($schoolName) ?> badge" class="school-identity-badge">
    <?php else: ?>
        <span class="school-identity-badge school-identity-badge-fallback" aria-hidden="true">FN</span>
    <?php endif; ?>
    <h1 class="school-identity-name mb-0"><?= e($schoolName) ?></h1>
</section>
<section class="mb-4 reveal-on-scroll schedule-window-zone">
    <div class="card card-soft anim-lift submission-window-card schedule-state-<?= e($scheduleState) ?>">
        <span class="window-glow window-glow-a" aria-hidden="true"></span>
        <span class="window-glow window-glow-b" aria-hidden="true"></span>
        <div class="card-body submission-window-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Submission window for staff of the month nominations.</h2>
                <span class="badge text-bg-<?= e(get_submission_window_badge_class($scheduleState)) ?> submission-window-badge">
                    <span class="status-ping" aria-hidden="true"></span>
                    <?= e(get_submission_window_label($scheduleState)) ?>
                </span>
            </div>
            <p class="mb-1"><strong>Opens:</strong> <?= e(format_datetime_human($submissionSchedule['opens_at'] ?? null)) ?></p>
            <p class="mb-1"><strong>Closes:</strong> <?= e(format_datetime_human($submissionSchedule['closes_at'] ?? null)) ?></p>
            <p class="mb-1 small text-muted">All times shown in Africa/Lagos.</p>
            <?php if (!empty($submissionSchedule['note'])): ?>
                <p class="mb-0 text-muted small"><strong>Note:</strong> <?= e($submissionSchedule['note']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="hero-card winner-hero-card p-4 p-lg-5 mb-4 reveal-on-scroll" data-reveal="right" id="current-winner"<?php if ($currentWinnerBackgroundImage): ?> style="--winner-background-image: url('<?= e($currentWinnerBackgroundImage) ?>');"<?php endif; ?>>
    <span class="winner-hero-accent winner-hero-accent-a" aria-hidden="true"></span>
    <span class="winner-hero-accent winner-hero-accent-b" aria-hidden="true"></span>
    <div class="row align-items-center g-4 home-stagger position-relative">
        <div class="col-lg-5">
            <div class="winner-frame-wrap">
                <div class="winner-ribbon">Staff Of The Month</div>
                <div class="winner-frame">
                    <span class="winner-flower winner-flower-tl" aria-hidden="true"></span>
                    <span class="winner-flower winner-flower-tr" aria-hidden="true"></span>
                    <span class="winner-flower winner-flower-bl" aria-hidden="true"></span>
                    <span class="winner-flower winner-flower-br" aria-hidden="true"></span>
                    <span class="winner-corner winner-corner-tl" aria-hidden="true"></span>
                    <span class="winner-corner winner-corner-tr" aria-hidden="true"></span>
                    <span class="winner-corner winner-corner-bl" aria-hidden="true"></span>
                    <span class="winner-corner winner-corner-br" aria-hidden="true"></span>
                    <span class="winner-photo-glow winner-photo-glow-a" aria-hidden="true"></span>
                    <span class="winner-photo-glow winner-photo-glow-b" aria-hidden="true"></span>
                    <div class="winner-frame-inner">
                        <img src="<?= e($currentWinnerBackgroundImage ?? app_url('assets/img/placeholder.svg')) ?>"
                             alt="Current winner photo"
                             class="winner-photo">
                        <div class="winner-photo-overlay" aria-hidden="true"></div>
                        <div class="winner-photo-caption">
                            <span class="winner-photo-caption-chip">Featured</span>
                            <span class="winner-photo-caption-text">Monthly recognition portrait</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <span class="badge text-bg-warning winner-hero-badge mb-3">Current Winner</span>
            <?php if ($currentWinner): ?>
                <h1 class="display-6 mb-2"><?= e($currentWinner['staff_name']) ?></h1>
                <p class="mb-1 fw-semibold"><?= e($currentWinner['staff_rank']) ?>, <?= e($currentWinner['department_name']) ?></p>
                <p class="text-muted mb-3"><?= e(format_month_year((int) $currentWinner['month'], (int) $currentWinner['year'])) ?></p>
                <div class="winner-hero-quote">
                    <p class="mb-0"><?= e($currentWinner['reasons']) ?></p>
                </div>
            <?php else: ?>
                <h1 class="display-6 mb-2">No winner selected yet</h1>
                <p class="mb-0 text-muted">The dashboard will show the monthly winner once one is selected.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell" data-reveal="left">
    <h2 class="section-title">Committee Members</h2>
    <?php if ($committeeMembers): ?>
        <div class="row g-3 home-stagger">
            <?php foreach ($committeeMembers as $member): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-soft h-100 anim-lift">
                        <img src="<?= e(upload_url('committee', $member['photo'])) ?>" alt="<?= e($member['name']) ?>" class="committee-photo">
                        <div class="card-body">
                            <h3 class="h6 mb-1"><?= e($member['name']) ?></h3>
                            <p class="text-muted mb-0 small"><?= e($member['role']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">Committee list is currently empty.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell" id="past-winners">
    <h2 class="section-title">Past Winners</h2>
    <?php if ($pastWinners): ?>
        <div id="pastWinnersCarousel" class="carousel slide home-stagger" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach ($pastWinners as $index => $winner): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <div class="card card-soft anim-lift">
                            <div class="card-body p-3 p-md-4">
                                <div class="past-winner-profile">
                                    <div class="past-winner-frame">
                                        <div class="past-winner-frame-inner">
                                            <img src="<?= e($winner['winner_photo'] ? upload_url('winners', $winner['winner_photo']) : upload_url('staff', $winner['staff_photo'])) ?>" alt="<?= e($winner['staff_name']) ?>" class="winner-photo">
                                        </div>
                                    </div>
                                    <div class="past-winner-copy">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                            <span class="badge text-bg-warning">Winner Spotlight</span>
                                            <span class="past-winner-date"><?= e(format_month_year((int) $winner['month'], (int) $winner['year'])) ?></span>
                                        </div>
                                        <h3 class="h4 mb-1"><?= e($winner['staff_name']) ?></h3>
                                        <p class="past-winner-meta mb-3"><?= e($winner['staff_rank']) ?>, <?= e($winner['department_name']) ?></p>
                                        <div class="past-winner-reason">
                                            <p class="mb-0"><?= e($winner['reasons']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#pastWinnersCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#pastWinnersCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No past winners yet.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell" data-reveal="right">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="section-title mb-0">Event Gallery</h2>
        <div class="home-action-buttons">
            <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('lecture.php')) ?>">Lecture Notes</a>
            <a class="btn btn-primary btn-sm" href="<?= e(app_url('gallery.php')) ?>">Open Gallery</a>
        </div>
    </div>
    <?php if ($galleryImages): ?>
        <div class="row g-3 home-stagger">
            <?php foreach ($galleryImages as $image): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="<?= e(upload_url('gallery', $image['image'])) ?>"
                       class="js-glightbox"
                       data-gallery="event-gallery"
                       data-title="<?= e($image['title']) ?>">
                        <img src="<?= e(upload_url('gallery', $image['image'])) ?>" alt="<?= e($image['title']) ?>" class="gallery-image">
                    </a>
                    <p class="small mt-2 mb-0"><?= e($image['title']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">Gallery images will appear here after upload.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell">
    <h2 class="section-title">Event Video Highlights</h2>
    <?php if ($eventVideos): ?>
        <div class="row g-3 home-stagger">
            <?php foreach ($eventVideos as $video): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 anim-lift p-2">
                        <video controls preload="metadata" class="event-video-player mb-2">
                            <source src="<?= e(upload_url('videos', $video['video'])) ?>">
                            Your browser does not support video playback.
                        </video>
                        <h3 class="h6 mb-1"><?= e($video['title']) ?></h3>
                        <p class="small text-muted mb-0"><?= e(format_datetime_human($video['uploaded_at'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No event videos uploaded yet.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell">
    <h2 class="section-title">Department Information</h2>
    <div class="row g-3 home-stagger">
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Vision illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Vision</h3>
                        <p class="mb-0"><?= e($departmentInfo['vision'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Mission illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Mission</h3>
                        <p class="mb-0"><?= e($departmentInfo['mission'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Objectives illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Objectives</h3>
                        <p class="mb-0"><?= e($departmentInfo['objectives'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="how-to-use" class="mb-4 reveal-on-scroll home-section-shell" data-reveal="right">
    <div class="card card-soft border-0 shadow-sm">
        <div class="card-body p-4">
            <h2 class="section-title mb-3">How to use the portal</h2>
            <div class="row g-3 home-stagger">
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">1</span>
                        <h3 class="h6 mt-2">Sign in or register a department</h3>
                        <p class="mb-0 text-muted">Use the login page for access, or register a department if you are new.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">2</span>
                        <h3 class="h6 mt-2">Check the submission window</h3>
                        <p class="mb-0 text-muted">Nominations are accepted only during the open period shown above.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">3</span>
                        <h3 class="h6 mt-2">Explore winners and updates</h3>
                        <p class="mb-0 text-muted">Review past winners, gallery items, videos, and department information anytime.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-card hero-intro-card p-4 p-lg-5 mb-4 reveal-on-scroll home-section-shell" data-reveal="left">
    <span class="hero-orb hero-orb-a" aria-hidden="true"></span>
    <span class="hero-orb hero-orb-b" aria-hidden="true"></span>
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge text-bg-primary mb-2">Welcome</span>
            <h1 class="display-6 mb-3">A simple portal for recognition, submissions, and department updates.</h1>
            <p class="lead mb-3">
                View winners, submit staff of the month nominations, browse gallery highlights, and read department information in one place.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= e(app_url('login.php')) ?>">Login to Continue</a>
                <a class="btn btn-outline-primary" href="#how-it-works">How It Works</a>
                <a class="btn btn-outline-secondary" href="<?= e(app_url('gallery.php')) ?>">View Gallery</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-soft border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Who can use this portal?</h2>
                    <ul class="list-unstyled mb-0 portal-audience-list">
                        <li>Admin: manage winners, schedules, reports, and content.</li>
                        <li>Departments: submit nominations and review messages.</li>
                        <li>Everyone: explore winners, gallery, videos, and department vision.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
</div>
<?php
render_footer();
