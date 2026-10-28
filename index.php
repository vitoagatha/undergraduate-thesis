<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$pageTitle = 'Beranda - Dashboard Rekomendasi Pembelajaran';
$requiredTables = ['matakuliah', 'dosen', 'kelas', 'priority_matrix_kelas', 'rekomendasi_kelas', 'statistik_mean_kelas', 'statistik_korelasi_kelas', 'statistik_korelasi_dosen'];
$missingTables = array_values(array_filter($requiredTables, static fn (string $table): bool => !table_exists($table)));
$summary = ['total_kelas' => 0, 'total_dosen' => 0, 'total_matakuliah' => 0, 'kelas_dengan_rekomendasi' => 0, 'kelas_perlu_perbaikan' => 0];
$classes = [];
$lecturers = [];
$categoryOptions = [];
$selectedCategory = '';
$priorityClasses = [];
$recommendationListTitle = 'Kelas yang memerlukan perhatian';
$recommendationListDescription = 'Ringkasan kelas dengan kategori prioritas atau masalah.';
$recommendationPerPage = 25;
$recommendationPage = 1;
$recommendationTotal = 0;
$recommendationTotalPages = 1;
$recommendationStart = 0;
$recommendationEnd = 0;
$recommendationBaseQuery = [];

if ($missingTables === []) {
    $validClassWhere = 'k.jumlah_mahasiswa BETWEEN 1 AND 999999 AND k.mean_nilai IS NOT NULL';
    $summary = fetch_one("SELECT (SELECT COUNT(*) FROM kelas k WHERE {$validClassWhere}) AS total_kelas, (SELECT COUNT(DISTINCT k.dosen_id) FROM kelas k WHERE {$validClassWhere}) AS total_dosen, (SELECT COUNT(DISTINCT k.matakuliah_id) FROM kelas k WHERE {$validClassWhere}) AS total_matakuliah, (SELECT COUNT(*) FROM rekomendasi_kelas r JOIN kelas k ON k.kelas_id = r.kelas_id WHERE {$validClassWhere}) AS kelas_dengan_rekomendasi, (SELECT COUNT(*) FROM rekomendasi_kelas r JOIN kelas k ON k.kelas_id = r.kelas_id WHERE {$validClassWhere} AND (r.main_category LIKE '%priority%' OR r.main_category LIKE '%issue%')) AS kelas_perlu_perbaikan") ?? $summary;
    $classes = fetch_all("SELECT k.kelas_id, COALESCE(k.nama_kelas, CONCAT('Kelas ', k.kelas_id)) AS nama_kelas, m.nama_matakuliah, d.nama_dosen FROM kelas k JOIN matakuliah m ON m.matakuliah_id = k.matakuliah_id JOIN dosen d ON d.dosen_id = k.dosen_id WHERE {$validClassWhere} ORDER BY k.kelas_id");
    $lecturers = fetch_all('SELECT dosen_id, nama_dosen FROM dosen ORDER BY nama_dosen, dosen_id');
    $categoryOptions = fetch_all("SELECT r.main_category, COUNT(*) AS total FROM rekomendasi_kelas r JOIN kelas k ON k.kelas_id = r.kelas_id WHERE {$validClassWhere} AND r.main_category IS NOT NULL AND r.main_category <> '' GROUP BY r.main_category ORDER BY r.main_category");
    $requestedCategory = trim((string) ($_GET['category'] ?? ''));
    $availableCategories = array_column($categoryOptions, 'main_category');
    $selectedCategory = in_array($requestedCategory, $availableCategories, true) ? $requestedCategory : '';
    $recommendationWhere = "{$validClassWhere} AND (r.main_category LIKE '%priority%' OR r.main_category LIKE '%issue%')";
    $recommendationParams = [];
    if ($selectedCategory !== '') {
        $recommendationWhere = "{$validClassWhere} AND r.main_category = :category";
        $recommendationParams['category'] = $selectedCategory;
        $recommendationListTitle = 'Kelas dengan kategori ' . category_label($selectedCategory);
        $recommendationListDescription = 'Hasil filter berdasarkan kategori rekomendasi peningkatan yang dipilih.';
        $recommendationBaseQuery['category'] = $selectedCategory;
    }
    $recommendationPageValue = filter_var($_GET['recommendation_page'] ?? null, FILTER_VALIDATE_INT);
    $recommendationPage = $recommendationPageValue === false || $recommendationPageValue === null ? 1 : max(1, (int) $recommendationPageValue);
    $recommendationTotal = (int) (fetch_one("SELECT COUNT(*) AS total FROM rekomendasi_kelas r JOIN kelas k ON k.kelas_id = r.kelas_id WHERE {$recommendationWhere}", $recommendationParams)['total'] ?? 0);
    $recommendationTotalPages = max(1, (int) ceil($recommendationTotal / $recommendationPerPage));
    $recommendationPage = min($recommendationPage, $recommendationTotalPages);
    $recommendationOffset = ($recommendationPage - 1) * $recommendationPerPage;
    $recommendationStart = $recommendationTotal === 0 ? 0 : $recommendationOffset + 1;
    $recommendationEnd = min($recommendationTotal, $recommendationPage * $recommendationPerPage);
    $priorityClasses = fetch_all("SELECT k.kelas_id, COALESCE(k.nama_kelas, CONCAT('Kelas ', k.kelas_id)) AS nama_kelas, m.nama_matakuliah, r.main_q, r.main_category, r.main_recommendation FROM rekomendasi_kelas r JOIN kelas k ON k.kelas_id = r.kelas_id JOIN matakuliah m ON m.matakuliah_id = k.matakuliah_id WHERE {$recommendationWhere} ORDER BY k.kelas_id LIMIT {$recommendationPerPage} OFFSET {$recommendationOffset}", $recommendationParams);
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4 py-lg-5">
    <section class="hero-panel p-4 p-lg-5 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7"><p class="text-uppercase small fw-semibold mb-2 opacity-75">Dashboard hasil penelitian</p><h1 class="display-6 fw-bold">Rekomendasi Peningkatan Pembelajaran</h1><p class="lead mb-0 opacity-90">Telusuri hasil analisis sentimen, pemetaan aspek, korelasi, dan rekomendasi berdasarkan kelas atau dosen.</p></div>
            <div class="col-lg-5"><div class="bg-white text-dark rounded-4 p-4 shadow-sm"><form data-search-form>
                <div class="mb-3"><label class="form-label fw-semibold">Cari berdasarkan</label><div class="d-flex gap-4"><div class="form-check"><input class="form-check-input" type="radio" name="search_type" id="typeKelas" value="kelas" checked><label class="form-check-label" for="typeKelas">Kelas</label></div><div class="form-check"><input class="form-check-input" type="radio" name="search_type" id="typeDosen" value="dosen"><label class="form-check-label" for="typeDosen">Dosen</label></div></div></div>
                <div class="mb-3" data-kelas-field><label class="form-label" for="kelasId">Pilih kelas</label><select class="form-select search-select" id="kelasId" name="kelas_id"><option value="">- Pilih kelas -</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['kelas_id'] ?>"><?= e($class['nama_kelas']) ?> - <?= e($class['nama_matakuliah']) ?> - <?= e($class['nama_dosen'] ?: 'Dosen belum tersedia') ?></option><?php endforeach; ?></select></div>
                <div class="mb-3 d-none" data-dosen-field><label class="form-label" for="dosenId">Pilih dosen</label><select class="form-select search-select" id="dosenId" name="dosen_id"><option value="">- Pilih dosen -</option><?php foreach ($lecturers as $lecturer): ?><option value="<?= (int) $lecturer['dosen_id'] ?>"><?= e($lecturer['nama_dosen']) ?> (ID <?= (int) $lecturer['dosen_id'] ?>)</option><?php endforeach; ?></select></div>
                <button class="btn btn-primary w-100" type="submit">Tampilkan hasil</button>
            </form></div></div>
        </div>
    </section>
    <?php if ($missingTables !== []): ?>
        <div class="alert alert-danger"><h2 class="h5">Struktur database belum lengkap</h2><p class="mb-2">Tabel berikut belum ditemukan:</p><ul class="mb-2"><?php foreach ($missingTables as $table): ?><li><code><?= e($table) ?></code></li><?php endforeach; ?></ul><a href="cek_database.php" class="alert-link">Buka pemeriksaan database</a>.</div>
    <?php else: ?>
        <section class="row g-3 mb-4"><?php foreach ([['Kelas', $summary['total_kelas'] ?? 0], ['Dosen', $summary['total_dosen'] ?? 0], ['Mata kuliah', $summary['total_matakuliah'] ?? 0], ['Kelas dengan rekomendasi', $summary['kelas_dengan_rekomendasi'] ?? 0], ['Kelas perlu perhatian', $summary['kelas_perlu_perbaikan'] ?? 0]] as $card): ?><div class="col-6 col-lg"><div class="card metric-card h-100"><div class="card-body"><div class="small-label mb-2"><?= e($card[0]) ?></div><div class="metric-value"><?= format_integer($card[1]) ?></div></div></div></div><?php endforeach; ?></section>
        <section class="card content-card"><div class="card-body p-4"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 mb-1"><?= e($recommendationListTitle) ?></h2><p class="text-secondary mb-0"><?= e($recommendationListDescription) ?></p><?php if ($recommendationTotal > 0): ?><p class="small text-secondary mb-0 mt-1">Menampilkan <?= format_integer($recommendationStart) ?>-<?= format_integer($recommendationEnd) ?> dari <?= format_integer($recommendationTotal) ?> kelas.</p><?php endif; ?></div><form class="d-flex flex-wrap gap-2 no-print" method="get"><select class="form-select" name="category" aria-label="Filter kategori rekomendasi"><option value="">Prioritas dan issue</option><?php foreach ($categoryOptions as $option): ?><option value="<?= e($option['main_category']) ?>" <?= $selectedCategory === $option['main_category'] ? 'selected' : '' ?>><?= e(category_label($option['main_category'])) ?> (<?= format_integer($option['total']) ?>)</option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Filter</button><?php if ($selectedCategory !== ''): ?><a class="btn btn-outline-secondary" href="index.php">Reset</a><?php endif; ?></form></div><?php if ($priorityClasses === []): ?><div class="alert alert-light border mb-0">Belum ada data kategori yang dapat ditampilkan.</div><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Kelas</th><th>Mata kuliah</th><th>Aspek</th><th>Kategori</th><th>Rekomendasi</th><th></th></tr></thead><tbody><?php foreach ($priorityClasses as $row): ?><tr><td><?= e($row['nama_kelas']) ?></td><td><?= e($row['nama_matakuliah']) ?></td><td><?= e($row['main_q'] ?: '-') ?></td><td><span class="badge <?= e(category_badge($row['main_category'])) ?>"><?= e(category_label($row['main_category'])) ?></span></td><td><?= e($row['main_recommendation'] ?: 'Belum tersedia') ?></td><td><a class="btn btn-sm btn-outline-primary" href="detail_kelas.php?id=<?= (int) $row['kelas_id'] ?>">Lihat</a></td></tr><?php endforeach; ?></tbody></table></div><?php if ($recommendationTotalPages > 1): ?><nav class="no-print" aria-label="Navigasi tabel rekomendasi"><ul class="pagination justify-content-end mb-0"><li class="page-item <?= $recommendationPage <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="index.php?<?= e(http_build_query($recommendationBaseQuery + ['recommendation_page' => max(1, $recommendationPage - 1)])) ?>">Sebelumnya</a></li><li class="page-item disabled"><span class="page-link">Halaman <?= format_integer($recommendationPage) ?> dari <?= format_integer($recommendationTotalPages) ?></span></li><li class="page-item <?= $recommendationPage >= $recommendationTotalPages ? 'disabled' : '' ?>"><a class="page-link" href="index.php?<?= e(http_build_query($recommendationBaseQuery + ['recommendation_page' => min($recommendationTotalPages, $recommendationPage + 1)])) ?>">Berikutnya</a></li></ul></nav><?php endif; ?><?php endif; ?></div></section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
