<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$matakuliahId = get_int_query('id');
$course = fetch_one('SELECT * FROM matakuliah WHERE matakuliah_id = :id', ['id' => $matakuliahId]);

if ($course === null) {
    http_response_code(404);
    exit('Data mata kuliah tidak ditemukan.');
}

$comparisons = fetch_all(
    "SELECT kdm.*, d.nama_dosen
     FROM komparasi_dosen_mk kdm
     JOIN dosen d ON d.dosen_id = kdm.dosen_id
     WHERE kdm.matakuliah_id = :id
     ORDER BY d.nama_dosen, d.dosen_id",
    ['id' => $matakuliahId]
);

$classes = fetch_all(
    "SELECT
        k.kelas_id,
        COALESCE(k.nama_kelas, CONCAT('Kelas ', k.kelas_id)) AS nama_kelas,
        k.jumlah_essay,
        k.rasio_positive,
        k.rasio_neutral,
        k.rasio_negative,
        k.sentimen_dominan,
        d.dosen_id,
        d.nama_dosen,
        r.total_essays,
        r.main_q,
        r.main_category,
        r.main_recommendation
     FROM kelas k
     JOIN dosen d ON d.dosen_id = k.dosen_id
     LEFT JOIN rekomendasi_kelas r ON r.kelas_id = k.kelas_id
     WHERE k.matakuliah_id = :id
     ORDER BY d.nama_dosen, k.kelas_id",
    ['id' => $matakuliahId]
);

$totalClasses = count($classes);
$lecturerIds = array_unique(array_map(static fn (array $row): int => (int) $row['dosen_id'], $classes));
$totalLecturers = count($lecturerIds);
$totalEssays = array_sum(array_map(static fn (array $row): int => (int) ($row['total_essays'] ?? $row['jumlah_essay'] ?? 0), $classes));
$sentiment = weighted_dosen_sentiment($classes);

$lecturerLabels = [];
$lecturerPositive = [];
$lecturerNeutral = [];
$lecturerNegative = [];
$lecturerEssays = [];
$lecturerClassCounts = [];
$priorityCounts = [];

foreach ($comparisons as $row) {
    $lecturerLabels[] = $row['nama_dosen'] . ' (ID ' . $row['dosen_id'] . ')';
    $lecturerPositive[] = ratio_to_percent($row['rasio_positive'] ?? 0);
    $lecturerNeutral[] = ratio_to_percent($row['rasio_neutral'] ?? 0);
    $lecturerNegative[] = ratio_to_percent($row['rasio_negative'] ?? 0);
    $lecturerEssays[] = (int) ($row['total_essay'] ?? 0);
    $lecturerClassCounts[] = (int) ($row['jumlah_kelas'] ?? 0);
    $priority = (string) ($row['priority_q_dominan'] ?? 'Belum tersedia');
    $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
}

$priorityLabels = array_keys($priorityCounts);
$priorityValues = array_values($priorityCounts);
$pageTitle = 'Detail Mata Kuliah ' . $course['nama_matakuliah'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex gap-3 no-print mb-2"><a href="javascript:history.back()" class="text-decoration-none">Kembali</a><a href="index.php" class="text-decoration-none">Beranda</a></div>
            <h1 class="h3 mt-2 mb-1"><?= e($course['nama_matakuliah']) ?></h1>
            <p class="text-secondary mb-0">ID mata kuliah <?= (int) $matakuliahId ?> - Semester <?= e($course['semester'] ?? '-') ?> - SKS <?= e($course['sks'] ?? '-') ?></p>
        </div>
        <button class="btn btn-outline-secondary no-print" type="button" onclick="window.print()">Cetak</button>
    </div>

    <section class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="small-label">Jumlah kelas</div><div class="metric-value mt-2"><?= format_integer($totalClasses ?: ($course['jumlah_kelas'] ?? 0)) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="small-label">Jumlah dosen</div><div class="metric-value mt-2"><?= format_integer($totalLecturers ?: ($course['jumlah_dosen'] ?? 0)) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="small-label">Total esai</div><div class="metric-value mt-2"><?= format_integer($totalEssays) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="small-label">Sentimen dominan</div><div class="fw-semibold mt-2"><?= e($sentiment['positive'] >= $sentiment['negative'] ? 'Positif' : 'Negatif') ?></div></div></div></div>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-lg-5"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Distribusi sentimen mata kuliah</h2><div class="chart-box"><canvas id="courseSentimentChart"></canvas></div></div></div></div>
        <div class="col-lg-7"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Komparasi sentimen antar dosen</h2><div class="chart-box"><canvas id="lecturerSentimentChart"></canvas></div></div></div></div>
        <div class="col-lg-6"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Jumlah esai per dosen</h2><div class="chart-box"><canvas id="lecturerEssayChart"></canvas></div></div></div></div>
        <div class="col-lg-6"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Jumlah kelas per dosen</h2><div class="chart-box"><canvas id="lecturerClassChart"></canvas></div></div></div></div>
        <?php if ($priorityLabels !== []): ?>
            <div class="col-12"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Sebaran aspek prioritas dominan</h2><div class="chart-box"><canvas id="priorityChart"></canvas></div></div></div></div>
        <?php endif; ?>
    </section>

    <section class="card content-card mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-1">Dosen pengampu mata kuliah</h2>
            <p class="text-secondary">Komparasi ini berasal dari tabel <code>komparasi_dosen_mk</code>.</p>
            <?php if ($comparisons === []): ?>
                <div class="alert alert-light border mb-0">Belum ada data komparasi dosen untuk mata kuliah ini.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Dosen</th><th>Kelas</th><th>Total esai</th><th>Positif</th><th>Netral</th><th>Negatif</th><th>Prioritas dominan</th><th></th></tr></thead><tbody><?php foreach ($comparisons as $row): ?><tr><td><a href="detail_dosen.php?id=<?= (int) $row['dosen_id'] ?>" class="text-decoration-none"><?= e($row['nama_dosen']) ?> <span class="text-secondary">(ID <?= (int) $row['dosen_id'] ?>)</span></a></td><td><?= format_integer($row['jumlah_kelas']) ?></td><td><?= format_integer($row['total_essay']) ?></td><td><?= format_percent($row['rasio_positive']) ?></td><td><?= format_percent($row['rasio_neutral']) ?></td><td><?= format_percent($row['rasio_negative']) ?></td><td><?= e($row['priority_q_dominan'] ?? '-') ?></td><td><a class="btn btn-sm btn-outline-primary" href="detail_dosen.php?id=<?= (int) $row['dosen_id'] ?>">Lihat dosen</a></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card content-card">
        <div class="card-body p-4">
            <h2 class="h5 mb-1">Daftar kelas dalam mata kuliah</h2>
            <p class="text-secondary">Setiap kelas tetap dapat dibuka untuk melihat rekomendasi detail Q1-Q14.</p>
            <?php if ($classes === []): ?>
                <div class="alert alert-light border mb-0">Belum ada kelas untuk mata kuliah ini.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Kelas</th><th>Dosen</th><th>Esai</th><th>Sentimen</th><th>Aspek utama</th><th>Kategori</th><th>Rekomendasi</th><th></th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><?= e($class['nama_kelas']) ?></td><td><a href="detail_dosen.php?id=<?= (int) $class['dosen_id'] ?>" class="text-decoration-none"><?= e($class['nama_dosen']) ?> <span class="text-secondary">(ID <?= (int) $class['dosen_id'] ?>)</span></a></td><td><?= format_integer($class['total_essays'] ?? $class['jumlah_essay'] ?? 0) ?></td><td><span class="badge <?= e(sentiment_badge($class['sentimen_dominan'] ?? null)) ?>"><?= e(ucfirst((string) ($class['sentimen_dominan'] ?? '-'))) ?></span></td><td><?= e($class['main_q'] ?? '-') ?></td><td><span class="badge <?= e(category_badge($class['main_category'] ?? null)) ?>"><?= e(category_label($class['main_category'] ?? null)) ?></span></td><td><?= e($class['main_recommendation'] ?? '-') ?></td><td><a class="btn btn-sm btn-outline-primary" href="detail_kelas.php?id=<?= (int) $class['kelas_id'] ?>">Lihat kelas</a></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
const courseSentiment = <?= json_encode($sentiment, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerLabels = <?= json_encode($lecturerLabels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerPositive = <?= json_encode($lecturerPositive, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerNeutral = <?= json_encode($lecturerNeutral, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerNegative = <?= json_encode($lecturerNegative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerEssays = <?= json_encode($lecturerEssays, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const lecturerClassCounts = <?= json_encode($lecturerClassCounts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const priorityLabels = <?= json_encode($priorityLabels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const priorityValues = <?= json_encode($priorityValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;

document.addEventListener('DOMContentLoaded', () => {
    createChart('courseSentimentChart', {type: 'doughnut', data: {labels: ['Positif', 'Netral', 'Negatif'], datasets: [{data: [courseSentiment.positive, courseSentiment.neutral, courseSentiment.negative]}]}, options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}});
    createChart('lecturerSentimentChart', {type: 'bar', data: {labels: lecturerLabels, datasets: [{label: 'Positif (%)', data: lecturerPositive}, {label: 'Netral (%)', data: lecturerNeutral}, {label: 'Negatif (%)', data: lecturerNegative}]}, options: {responsive: true, maintainAspectRatio: false, scales: {x: {stacked: true}, y: {stacked: true, beginAtZero: true, max: 100}}}});
    createChart('lecturerEssayChart', {type: 'bar', data: {labels: lecturerLabels, datasets: [{label: 'Total esai', data: lecturerEssays}]}, options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true}}}});
    createChart('lecturerClassChart', {type: 'bar', data: {labels: lecturerLabels, datasets: [{label: 'Jumlah kelas', data: lecturerClassCounts}]}, options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true}}}});
    if (priorityLabels.length > 0) {
        createChart('priorityChart', {type: 'bar', data: {labels: priorityLabels, datasets: [{label: 'Jumlah dosen', data: priorityValues}]}, options: {responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: {x: {beginAtZero: true}}}});
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
