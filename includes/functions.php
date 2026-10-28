<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_int_query(string $key): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < 1) {
        http_response_code(400);
        exit('Parameter tidak valid.');
    }
    return $value;
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function table_exists(string $table): bool
{
    $row = fetch_one('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table', ['table' => $table]);
    return (int) ($row['total'] ?? 0) > 0;
}

function ratio_to_percent(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    $number = (float) $value;
    if ($number >= -1.0 && $number <= 1.0) {
        $number *= 100.0;
    }
    return max(0.0, min(100.0, $number));
}

function format_percent(mixed $value, int $decimals = 1): string
{
    return number_format(ratio_to_percent($value), $decimals, ',', '.') . '%';
}

function format_decimal(mixed $value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return number_format((float) $value, $decimals, ',', '.');
}

function format_integer(mixed $value): string
{
    if ($value === null || $value === '') {
        return '0';
    }
    return number_format((int) $value, 0, ',', '.');
}

function category_label(?string $category): string
{
    $labels = [
        'priority_improvement' => 'Priority improvement',
        'priority_improvement_no_essay_support' => 'Priority improvement no essay support',
        'secondary_improvement' => 'Secondary improvement',
        'positive_but_low_impact' => 'Positive but low impact',
        'strength_to_maintain' => 'Strength to maintain',
        'monitor' => 'Monitor',
        'hidden_strength' => 'Hidden strength',
        'hidden_issue' => 'Hidden issue',
        'positive_aspect' => 'Positive aspect',
        'perception_issue' => 'Perception issue',
        'strength_no_essay_support' => 'Strength no essay support',
    ];
    if ($category === null || $category === '') {
        return '-';
    }
    return $labels[$category] ?? ucfirst(str_replace('_', ' ', $category));
}

function category_badge(?string $category): string
{
    if ($category === null || $category === '') {
        return 'text-bg-secondary';
    }
    if (str_contains($category, 'priority') || str_contains($category, 'issue')) {
        return 'text-bg-danger';
    }
    if (str_contains($category, 'strength') || str_contains($category, 'positive')) {
        return 'text-bg-success';
    }
    if (str_contains($category, 'secondary')) {
        return 'text-bg-warning';
    }
    return 'text-bg-secondary';
}

function sentiment_badge(?string $sentiment): string
{
    return match (strtolower((string) $sentiment)) {
        'positive' => 'text-bg-success',
        'negative' => 'text-bg-danger',
        'neutral' => 'text-bg-secondary',
        'mixed' => 'text-bg-warning',
        default => 'text-bg-light',
    };
}

function q_descriptions(): array
{
    return [
        'Q1' => 'Perencanaan materi pembelajaran',
        'Q2' => 'Kesesuaian materi dengan rencana pembelajaran',
        'Q3' => 'Pemanfaatan waktu dalam penyampaian materi',
        'Q4' => 'Kesesuaian bobot materi dengan kemampuan mahasiswa',
        'Q5' => 'Kelengkapan materi pembelajaran',
        'Q6' => 'Penguasaan materi oleh dosen',
        'Q7' => 'Cara penyampaian materi',
        'Q8' => 'Pengembalian dan pembahasan evaluasi',
        'Q9' => 'Bantuan terhadap kesulitan belajar',
        'Q10' => 'Ketersediaan waktu di luar kelas',
        'Q11' => 'Interaksi dan sikap dosen',
        'Q12' => 'Penampilan dosen',
        'Q13' => 'Kedisiplinan waktu',
        'Q14' => 'Kesesuaian jadwal perkuliahan',
        'Q15' => 'Komentar umum',
        'GENERAL' => 'Komentar umum',
    ];
}

function q_description(?string $q): string
{
    $q = strtoupper(trim((string) $q));
    return q_descriptions()[$q] ?? 'Aspek pembelajaran';
}

function normalize_sentiment_distribution(array $row): array
{
    $positive = ratio_to_percent($row['rasio_positive'] ?? 0);
    $neutral = ratio_to_percent($row['rasio_neutral'] ?? 0);
    $negative = ratio_to_percent($row['rasio_negative'] ?? 0);
    $total = $positive + $neutral + $negative;
    if ($total > 0 && abs($total - 100.0) > 0.5) {
        $positive = ($positive / $total) * 100;
        $neutral = ($neutral / $total) * 100;
        $negative = ($negative / $total) * 100;
    }
    return ['positive' => round($positive, 2), 'neutral' => round($neutral, 2), 'negative' => round($negative, 2)];
}

function weighted_dosen_sentiment(array $classes): array
{
    $weighted = ['positive' => 0.0, 'neutral' => 0.0, 'negative' => 0.0];
    $weightTotal = 0.0;
    foreach ($classes as $class) {
        $weight = (float) ($class['total_essays'] ?? $class['jumlah_essay'] ?? 0);
        if ($weight <= 0) {
            continue;
        }
        $distribution = normalize_sentiment_distribution($class);
        foreach ($weighted as $key => $value) {
            $weighted[$key] += $distribution[$key] * $weight;
        }
        $weightTotal += $weight;
    }
    if ($weightTotal <= 0) {
        return ['positive' => 0.0, 'neutral' => 0.0, 'negative' => 0.0];
    }
    foreach ($weighted as $key => $value) {
        $weighted[$key] = round($value / $weightTotal, 2);
    }
    return $weighted;
}

function aspect_order_sql(string $column = 'q'): string
{
    return "CAST(SUBSTRING({$column}, 2) AS UNSIGNED)";
}

function average_q_columns(array $row, string $prefix): ?float
{
    $values = [];
    for ($i = 1; $i <= 14; $i++) {
        $value = $row[$prefix . $i] ?? null;
        if ($value !== null && $value !== '') {
            $values[] = (float) $value;
        }
    }
    return $values === [] ? null : array_sum($values) / count($values);
}
