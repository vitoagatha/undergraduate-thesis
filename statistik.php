<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$type = strtolower((string) ($_GET['type'] ?? ''));
$id = get_int_query('id');
if (!in_array($type, ['kelas', 'dosen'], true)) {
    http_response_code(400);
    exit('Jenis statistik tidak valid.');
}

$labels = $means = $correlations = $mentions = $positiveCounts = $neutralCounts = $negativeCounts = [];
$sentiment = ['positive' => 0.0, 'neutral' => 0.0, 'negative' => 0.0];
$classComparison = [];
$backUrl = 'index.php';

if ($type === 'kelas') {
    $entity = table_exists('view_kelas_lengkap') ? fetch_one('SELECT * FROM view_kelas_lengkap WHERE kelas_id = :id', ['id' => $id]) : fetch_one("SELECT k.*, m.nama_matakuliah FROM kelas k JOIN matakuliah m ON m.matakuliah_id = k.matakuliah_id WHERE k.kelas_id = :id", ['id' => $id]);
    if ($entity === null) {
        http_response_code(404);
        exit('Kelas tidak ditemukan.');
    }
    $rows = fetch_all('SELECT q AS aspek, mean_likert, correlation, mention_count, positive_count, neutral_count, negative_count FROM priority_matrix_kelas WHERE kelas_id = :id ORDER BY ' . aspect_order_sql('q'), ['id' => $id]);
    $sentiment = normalize_sentiment_distribution($entity);
    $title = $entity['nama_kelas'] ?: 'Kelas ' . $id;
    $subtitle = $entity['nama_matakuliah'] ?? '';
    $backUrl = 'detail_kelas.php?id=' . $id;
} else {
    $entity = table_exists('view_dosen_lengkap') ? fetch_one('SELECT * FROM view_dosen_lengkap WHERE dosen_id = :id', ['id' => $id]) : fetch_one('SELECT * FROM dosen WHERE dosen_id = :id', ['id' => $id]);
    if ($entity === null) {
        http_response_code(404);
        exit('Dosen tidak ditemukan.');
    }
    $rows = fetch_all("SELECT scd.q AS aspek, scd.mean_likert, scd.mean_corr AS correlation, COALESCE(SUM(pm.mention_count), 0) AS mention_count, COALESCE(SUM(pm.positive_count), 0) AS positive_count, COALESCE(SUM(pm.neutral_count), 0) AS neutral_count, COALESCE(SUM(pm.negative_count), 0) AS negative_count FROM statistik_korelasi_dosen scd LEFT JOIN kelas k ON k.dosen_id = scd.dosen_id LEFT JOIN priority_matrix_kelas pm ON pm.kelas_id = k.kelas_id AND pm.q = scd.q WHERE scd.dosen_id = :id GROUP BY scd.q, scd.mean_likert, scd.mean_corr ORDER BY " . aspect_order_sql('scd.q'), ['id' => $id]);
    if ($rows === []) {
        $rows = fetch_all("SELECT pm.q AS aspek, AVG(pm.mean_likert) AS mean_likert, AVG(pm.correlation) AS correlation, SUM(pm.mention_count) AS mention_count, SUM(pm.positive_count) AS positive_count, SUM(pm.neutral_count) AS neutral_count, SUM(pm.negative_count) AS negative_count FROM kelas k JOIN priority_matrix_kelas pm ON pm.kelas_id = k.kelas_id WHERE k.dosen_id = :id GROUP BY pm.q ORDER BY " . aspect_order_sql('pm.q'), ['id' => $id]);
    }
    $classes = table_exists('view_kelas_lengkap') ? fetch_all('SELECT * FROM view_kelas_lengkap WHERE dosen_id = :id ORDER BY kelas_id', ['id' => $id]) : fetch_all('SELECT * FROM kelas WHERE dosen_id = :id ORDER BY kelas_id', ['id' => $id]);
    $sentiment = weighted_dosen_sentiment($classes);
    foreach ($classes as $class) {
        $dist = normalize_sentiment_distribution($class);
        $classComparison[] = ['label' => $class['nama_kelas'] ?: 'Kelas ' . $class['kelas_id'], 'positive' => $dist['positive'], 'neutral' => $dist['neutral'], 'negative' => $dist['negative']];
    }
    $title = $entity['nama_dosen'];
    $subtitle = 'Ringkasan deskriptif seluruh kelas yang diampu';
    $backUrl = 'detail_dosen.php?id=' . $id;
}

foreach ($rows as $row) {
    $labels[] = $row['aspek'];
    $means[] = $row['mean_likert'] === null ? null : round((float) $row['mean_likert'], 4);
    $correlations[] = $row['correlation'] === null ? null : round((float) $row['correlation'], 4);
    $mentions[] = (int) ($row['mention_count'] ?? 0);
    $positiveCounts[] = (int) ($row['positive_count'] ?? 0);
    $neutralCounts[] = (int) ($row['neutral_count'] ?? 0);
    $negativeCounts[] = (int) ($row['negative_count'] ?? 0);
}

$pageTitle = 'Statistik ' . $title;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><div class="d-flex gap-3 no-print mb-2"><a href="<?= e($backUrl) ?>" class="text-decoration-none">Kembali ke detail</a><a href="index.php" class="text-decoration-none">Beranda</a></div><h1 class="h3 mt-2 mb-1">Statistik <?= e($title) ?></h1><p class="text-secondary mb-0"><?= e($subtitle) ?></p></div></div><?php if ($type === 'dosen'): ?><div class="alert alert-info">Mean Likert dan korelasi tingkat dosen diambil dari statistik_korelasi_dosen. Frekuensi aspek dan sentimen aspek dijumlahkan dari seluruh kelas dosen tersebut.</div><?php endif; ?><section class="row g-4"><div class="col-lg-5"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Distribusi sentimen</h2><div class="chart-box"><canvas id="sentimentChart"></canvas></div></div></div></div><div class="col-lg-7"><div class="card content-card h-100"><div class="card-body p-4"><h2 class="h5">Frekuensi pemetaan aspek</h2><div class="chart-box"><canvas id="mentionChart"></canvas></div></div></div></div><div class="col-12"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Rata-rata skor Likert Q1-Q14</h2><div class="chart-box"><canvas id="meanChart"></canvas></div></div></div></div><div class="col-12"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Korelasi Spearman Q1-Q14 dengan nilai akademik</h2><div class="chart-box"><canvas id="correlationChart"></canvas></div></div></div></div><div class="col-12"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Sentimen pada setiap aspek</h2><div class="chart-box"><canvas id="aspectSentimentChart"></canvas></div></div></div></div><?php if ($type === 'dosen' && $classComparison !== []): ?><div class="col-12"><div class="card content-card"><div class="card-body p-4"><h2 class="h5">Perbandingan sentimen antarkelas</h2><div class="chart-box"><canvas id="classComparisonChart"></canvas></div></div></div></div><?php endif; ?></section></main>
<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const means = <?= json_encode($means, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const correlations = <?= json_encode($correlations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const mentions = <?= json_encode($mentions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const positiveCounts = <?= json_encode($positiveCounts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const neutralCounts = <?= json_encode($neutralCounts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const negativeCounts = <?= json_encode($negativeCounts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const sentiment = <?= json_encode($sentiment, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
const classComparison = <?= json_encode($classComparison, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
document.addEventListener('DOMContentLoaded', () => {
    createChart('sentimentChart', {type: 'doughnut', data: {labels: ['Positif', 'Netral', 'Negatif'], datasets: [{data: [sentiment.positive, sentiment.neutral, sentiment.negative]}]}, options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}});
    createChart('mentionChart', {type: 'bar', data: {labels, datasets: [{label: 'Jumlah esai', data: mentions}]}, options: {responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: {x: {beginAtZero: true}}}});
    createChart('meanChart', {type: 'bar', data: {labels, datasets: [{label: 'Mean Likert', data: means}]}, options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true, max: 4}}}});
    createChart('correlationChart', {type: 'bar', data: {labels, datasets: [{label: 'Korelasi Spearman', data: correlations}]}, options: {responsive: true, maintainAspectRatio: false, scales: {y: {min: -1, max: 1}}}});
    createChart('aspectSentimentChart', {type: 'bar', data: {labels, datasets: [{label: 'Positif', data: positiveCounts}, {label: 'Netral', data: neutralCounts}, {label: 'Negatif', data: negativeCounts}]}, options: {responsive: true, maintainAspectRatio: false, scales: {x: {stacked: true}, y: {stacked: true, beginAtZero: true}}}});
    if (classComparison.length > 0) { createChart('classComparisonChart', {type: 'bar', data: {labels: classComparison.map(item => item.label), datasets: [{label: 'Positif (%)', data: classComparison.map(item => item.positive)}, {label: 'Netral (%)', data: classComparison.map(item => item.neutral)}, {label: 'Negatif (%)', data: classComparison.map(item => item.negative)}]}, options: {responsive: true, maintainAspectRatio: false, scales: {x: {stacked: true}, y: {stacked: true, beginAtZero: true, max: 100}}}}); }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
