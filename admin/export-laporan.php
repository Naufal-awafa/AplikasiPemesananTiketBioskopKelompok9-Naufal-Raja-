<?php
require_once __DIR__ . '/../includes/helper.php';
$user = wajibAkses('laporan');
$pdo = Database::getInstance()->getKoneksi();
$rows = $pdo
    ->query(
        'SELECT t.kode_tiket,f.judul,j.tanggal,j.jam,t.subtotal_harga,t.potongan_jabatan,t.potongan_promo,t.potongan_poin,t.total_produk,t.total_harga,t.metode_bayar,t.status,t.dibuat_pada FROM tiket t JOIN jadwal j ON j.id=t.jadwal_id JOIN film f ON f.id=j.film_id ORDER BY t.id DESC',
    )
    ->fetchAll();
if (($_GET['format'] ?? 'csv') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan-sineverse-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Kode',
        'Film',
        'Tanggal',
        'Jam',
        'Subtotal',
        'Diskon Staff',
        'Diskon Promo',
        'Diskon Poin',
        'Produk',
        'Total',
        'Metode',
        'Status',
        'Dibuat',
    ]);
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    exit();
}
?><!doctype html><html><head><meta charset="utf-8"><title>Laporan Sineverse</title><style>body{font-family:Arial;padding:24px;color:#111}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #bbb;padding:6px;text-align:left}h1{margin-bottom:4px}@media print{button{display:none}}</style></head><body><button onclick="print()">Cetak / Simpan PDF</button><h1>Laporan Sineverse Cinema</h1><p><?= date(
    'd M Y H:i',
) ?></p><table><thead><tr><?php foreach (array_keys($rows[0] ?? ['data' => '']) as $h): ?><th><?= htmlspecialchars(
    $h,
) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $r): ?><tr><?php foreach (
    $r
    as $v
): ?><td><?= htmlspecialchars(
    (string) $v,
) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></body></html>
