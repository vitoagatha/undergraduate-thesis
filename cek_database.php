<?php

declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

$pageTitle = 'Pemeriksaan Database';
$tables = ['matakuliah', 'dosen', 'kelas', 'komparasi_dosen_mk', 'priority_matrix_kelas', 'rekomendasi_dosen', 'rekomendasi_kelas', 'statistik_korelasi_dosen', 'statistik_korelasi_kelas', 'statistik_mean_kelas'];
$views = ['view_ringkasan_dashboard', 'view_kelas_lengkap', 'view_dosen_lengkap', 'view_priority_matrix_detail'];
$status = [];
foreach ($tables as $table) {
    $exists = table_exists($table);
    $count = $exists ? (int) (fetch_one('SELECT COUNT(*) AS total FROM ' . $table)['total'] ?? 0) : null;
    $status[] = ['table' => $table, 'exists' => $exists, 'count' => $count];
}
$viewStatus = [];
foreach ($views as $view) {
    $viewStatus[] = ['view' => $view, 'exists' => table_exists($view)];
}
$integrity = [];
if (table_exists('kelas') && table_exists('dosen')) {
    $integrity['Kelas tanpa dosen valid'] = (int) (fetch_one('SELECT COUNT(*) AS total FROM kelas k LEFT JOIN dosen d ON d.dosen_id = k.dosen_id WHERE d.dosen_id IS NULL')['total'] ?? 0);
}
if (table_exists('priority_matrix_kelas')) {
    $integrity['Kelas dengan jumlah aspek bukan 14'] = (int) (fetch_one('SELECT COUNT(*) AS total FROM (SELECT kelas_id FROM priority_matrix_kelas GROUP BY kelas_id HAVING COUNT(*) <> 14) x')['total'] ?? 0);
}
if (table_exists('kelas') && table_exists('rekomendasi_kelas')) {
    $integrity['Kelas tanpa rekomendasi'] = (int) (fetch_one('SELECT COUNT(*) AS total FROM kelas k LEFT JOIN rekomendasi_kelas r ON r.kelas_id = k.kelas_id WHERE r.kelas_id IS NULL')['total'] ?? 0);
}
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4"><div class="mb-4"><div class="d-flex gap-3 mb-2"><a href="javascript:history.back()" class="text-decoration-none">Kembali</a><a href="index.php" class="text-decoration-none">Beranda</a></div><h1 class="h3 mt-2">Pemeriksaan Database</h1><p class="text-secondary">Gunakan halaman ini untuk memastikan skema dump terbaru telah terpasang.</p></div><section class="card content-card mb-4"><div class="card-body p-4"><h2 class="h5">Tabel yang diperlukan</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tabel</th><th>Status</th><th>Jumlah baris</th></tr></thead><tbody><?php foreach ($status as $row): ?><tr><td><code><?= e($row['table']) ?></code></td><td><span class="badge <?= $row['exists'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $row['exists'] ? 'Tersedia' : 'Tidak ditemukan' ?></span></td><td><?= $row['exists'] ? format_integer($row['count']) : '-' ?></td></tr><?php endforeach; ?></tbody></table></div></div></section><section class="card content-card mb-4"><div class="card-body p-4"><h2 class="h5">View pendukung</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>View</th><th>Status</th></tr></thead><tbody><?php foreach ($viewStatus as $row): ?><tr><td><code><?= e($row['view']) ?></code></td><td><span class="badge <?= $row['exists'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $row['exists'] ? 'Tersedia' : 'Opsional / belum ada' ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></section><section class="card content-card"><div class="card-body p-4"><h2 class="h5">Pemeriksaan integritas</h2><?php if ($integrity === []): ?><div class="alert alert-light border mb-0">Pemeriksaan belum dapat dilakukan karena tabel utama belum tersedia.</div><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($integrity as $label => $value): ?><li class="list-group-item d-flex justify-content-between px-0"><span><?= e($label) ?></span><strong><?= format_integer($value) ?></strong></li><?php endforeach; ?></ul><?php endif; ?></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
