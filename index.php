<?php
// Menghubungkan ke database
$host = 'localhost';
$dbname = 'wisata-alam';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Koneksi gagal: " . $e->getMessage();
    exit;
}

// Matriks perbandingan AHP untuk kriteria (harga, rating, durasi, kota)
$matriks_perbandingan = [
    [1, 3, 5, 7],       // Harga lebih penting dibandingkan kriteria lainnya
    [1 / 3, 1, 3, 5],   // Rating lebih penting dibandingkan durasi dan kota
    [1 / 5, 1 / 3, 1, 3], // Durasi lebih penting dibandingkan kota
    [1 / 7, 1 / 5, 1 / 3, 1] // Kota paling kurang penting
];

// Normalisasi dan perhitungan bobot AHP
$sum_columns = [];
foreach ($matriks_perbandingan[0] as $colIndex => $_) {
    $sum_columns[$colIndex] = 0;
    foreach ($matriks_perbandingan as $row) {
        $sum_columns[$colIndex] += $row[$colIndex];
    }
}

$bobot = [];
foreach ($matriks_perbandingan as $row) {
    $normalized_row = [];
    foreach ($row as $key => $value) {
        $normalized_row[] = $value / $sum_columns[$key];
    }
    $bobot[] = array_sum($normalized_row) / count($row);
}

// Bobot AHP
$bobot_harga = $bobot[0];
$bobot_rating = $bobot[1];
$bobot_durasi = $bobot[2];
$bobot_kota = $bobot[3];

// Proses SAW
$destinasi = [];
$harga_max = $rating_min = $durasi_max = $kota = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi input dan validasi
    $harga_max = filter_input(INPUT_POST, 'harga', FILTER_SANITIZE_NUMBER_INT);
    $rating_min = filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT);
    $durasi_max = filter_input(INPUT_POST, 'durasi', FILTER_SANITIZE_NUMBER_INT);
    $kota = filter_input(INPUT_POST, 'kota', FILTER_SANITIZE_STRING);

    // Validasi input
    if ($harga_max <= 0 || $rating_min < 1 || $rating_min > 5 || $durasi_max <= 0) {
        echo "Input tidak valid.";
        exit;
    }

    // Membuat query untuk mencari data destinasi sesuai kriteria
    $query = "SELECT * FROM cagar_alam 
              WHERE Price <= :harga_max 
              AND Rating >= :rating_min 
              AND Time_Minutes <= :durasi_max";

    // Jika kota dipilih, tambahkan filter untuk kota
    if (!empty($kota)) {
        $query .= " AND City = :kota";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':harga_max', $harga_max, PDO::PARAM_INT);
    $stmt->bindParam(':rating_min', $rating_min, PDO::PARAM_INT);
    $stmt->bindParam(':durasi_max', $durasi_max, PDO::PARAM_INT);

    // Jika kota diisi, bind parameter untuk kota
    if (!empty($kota)) {
        $stmt->bindParam(':kota', $kota, PDO::PARAM_STR);
    }

    $stmt->execute();
    $destinasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($destinasi) > 0) {
        // Normalisasi data destinasi
        $max_rating = max(array_column($destinasi, 'Rating'));
        $max_durasi = max(array_column($destinasi, 'Time_Minutes'));
        $unique_cities = array_unique(array_column($destinasi, 'City'));
        $city_scores = array_flip($unique_cities);

        // Beri nilai (contoh: berdasarkan alfabetis, dengan asumsi kota diurutkan)
        foreach ($city_scores as $city => $index) {
            $city_scores[$city] = 1 / (1 + $index); // Nilai normalisasi
        }

        foreach ($destinasi as &$d) {
            $d['normalisasi_harga'] = $harga_max / $d['Price'];
            $d['normalisasi_rating'] = $d['Rating'] / $max_rating;
            $d['normalisasi_durasi'] = $durasi_max / $max_durasi;
            $d['normalisasi_kota'] = $city_scores[$d['City']];
        }

        // Hitung skor SAW dengan bobot AHP
        foreach ($destinasi as &$d) {
            $d['skor'] = (
                $d['normalisasi_harga'] * $bobot_harga +
                $d['normalisasi_rating'] * $bobot_rating +
                $d['normalisasi_durasi'] * $bobot_durasi +
                $d['normalisasi_kota'] * $bobot_kota
            );
        }

        // Urutkan destinasi berdasarkan skor tertinggi
        usort($destinasi, function ($a, $b) {
            return $b['skor'] <=> $a['skor'];
        });
    } else {
        $destinasi = [];
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Rekomendasi Wisata Alam</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h1>Rekomendasi Wisata Alam di Pulau Jawa <br>Menggunakan Metode AHP & SAW</h1>
    <form method="POST" action="">
        <label for="harga">Harga Maksimum (IDR):</label>
        <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($harga_max ?? ''); ?>" required>

        <label for="rating">Rating Minimum (1-5):</label>
        <input type="number" id="rating" name="rating" value="<?php echo htmlspecialchars($rating_min ?? ''); ?>" min="1" max="5" required>

        <label for="durasi">Durasi Kunjungan Maksimum (Menit):</label>
        <input type="number" id="durasi" name="durasi" value="<?php echo htmlspecialchars($durasi_max ?? ''); ?>" required>

        <label for="kota">Kota (Opsional):</label>
        <select id="kota" name="kota">
            <option value="">Pilih Kota (Opsional)</option>
            <option value="Jakarta" <?php echo ($kota == 'Jakarta' ? 'selected' : ''); ?>>Jakarta</option>
            <option value="Bandung" <?php echo ($kota == 'Bandung' ? 'selected' : ''); ?>>Bandung</option>
            <option value="Surabaya" <?php echo ($kota == 'Surabaya' ? 'selected' : ''); ?>>Surabaya</option>
            <option value="Yogyakarta" <?php echo ($kota == 'Yogyakarta' ? 'selected' : ''); ?>>Yogyakarta</option>
            <option value="Semarang" <?php echo ($kota == 'Semarang' ? 'selected' : ''); ?>>Semarang</option>
        </select>

        <input type="submit" value="Cari Rekomendasi">
    </form>

    <table>
        <thead>
            <tr>
                <th>Nama Tempat</th>
                <th>Harga (IDR)</th>
                <th>Rating</th>
                <th>Durasi Kunjungan (Menit)</th>
                <th>Kota</th>
                <th>Skor</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($destinasi)): ?>
                <tr>
                    <td colspan="7">Tidak ada destinasi yang ditemukan berdasarkan kriteria yang dimasukkan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($destinasi as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['Place_Name']); ?></td>
                        <td>IDR <?php echo number_format($d['Price'], 0, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($d['Rating']); ?></td>
                        <td><?php echo htmlspecialchars($d['Time_Minutes']); ?> menit</td>
                        <td><?php echo htmlspecialchars($d['City']); ?></td>
                        <td><?php echo number_format($d['skor'], 3); ?></td>
                        <td><a href="detail.php?id=<?php echo $d['Place_Id']; ?>">Lihat Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>

</html>