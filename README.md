# Dashboard Rekomendasi Pembelajaran - PHP Native 8.2

Aplikasi sederhana untuk menampilkan hasil analisis evaluasi pembelajaran berdasarkan kelas dan dosen. Versi ini sudah disesuaikan dengan dump database terbaru db_rekomendasi_pembelajaran.

## Tabel yang digunakan

- matakuliah
- dosen
- kelas
- komparasi_dosen_mk
- priority_matrix_kelas
- rekomendasi_dosen
- rekomendasi_kelas
- statistik_korelasi_dosen
- statistik_korelasi_kelas
- statistik_mean_kelas

View pendukung yang akan dipakai bila tersedia:

- view_ringkasan_dashboard
- view_kelas_lengkap
- view_dosen_lengkap
- view_priority_matrix_detail

## Halaman

- index.php: pencarian kelas/dosen dan ringkasan dashboard.
- detail_kelas.php: identitas kelas, sentimen, rekomendasi, dan detail Q1-Q14 dari priority_matrix_kelas.
- detail_dosen.php: ringkasan dosen dan daftar kelas dari relasi kelas.dosen_id.
- detail_matakuliah.php: statistik mata kuliah, daftar dosen pengampu, daftar kelas, dan grafik komparasi antar dosen pada mata kuliah yang sama.
- statistik.php: grafik kelas/dosen dari priority_matrix_kelas dan statistik_korelasi_dosen.
- cek_database.php: pemeriksaan tabel, view, dan integritas dasar.

## Instalasi

1. Import dump database terbaru ke MySQL/MariaDB.
2. Salin folder aplikasi ini ke web root, misalnya C:\xampp\htdocs\dashboard_rekomendasi_php.
3. Sesuaikan config/database.php bila nama database, user, atau password berbeda.
4. Buka http://localhost/dashboard_rekomendasi_php/.
5. Buka http://localhost/dashboard_rekomendasi_php/cek_database.php untuk memeriksa skema.

## Catatan

Database terbaru tidak memakai kelas_dosen, statistik_kelas, atau analisis_aspek_kelas. Aplikasi ini sudah diarahkan ke tabel pengganti: kelas.dosen_id, statistik_mean_kelas, statistik_korelasi_kelas, statistik_korelasi_dosen, dan priority_matrix_kelas.
