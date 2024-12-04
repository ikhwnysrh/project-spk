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

// Ambil ID destinasi dari URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data destinasi berdasarkan ID
$stmt = $pdo->prepare("SELECT * FROM cagar_alam WHERE Place_Id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$destinasi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$destinasi) {
    echo "Destinasi tidak ditemukan.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Destinasi - <?php echo htmlspecialchars($destinasi['Place_Name']); ?></title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 70%;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 2.5em;
            color: #007bff;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        ul li {
            font-size: 1.1em;
            color: #34495e;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        ul li strong {
            color: #007bff;
            font-weight: 600;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 24px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.2em;
            border-radius: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .back-link:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .container ul {
            border-top: 2px solid #ecf0f1;
            padding-top: 20px;
        }

        .container ul li:last-child {
            margin-bottom: 0;
        }

        /* Tambahkan animasi ringan */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        .container {
            animation: fadeIn 1s ease-out;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Detail Destinasi: <?php echo htmlspecialchars($destinasi['Place_Name']); ?></h1>
        <ul>
            <li><strong>Harga:</strong> IDR <?php echo number_format($destinasi['Price'], 0, ',', '.'); ?></li>
            <li><strong>Rating:</strong> <?php echo htmlspecialchars($destinasi['Rating']); ?></li>
            <li><strong>Durasi:</strong> <?php echo htmlspecialchars($destinasi['Time_Minutes']); ?> menit</li>
            <li><strong>Kota:</strong> <?php echo htmlspecialchars($destinasi['City']); ?></li>
            <li><strong>Deskripsi:</strong> <?php echo nl2br(htmlspecialchars($destinasi['Description'])); ?></li>
            <li><strong>Koordinat:</strong> <?php echo htmlspecialchars($destinasi['Coordinate']); ?></li>
        </ul>
        <a href="javascript:history.back()" class="back-link">Kembali</a>
    </div>
</body>

</html>