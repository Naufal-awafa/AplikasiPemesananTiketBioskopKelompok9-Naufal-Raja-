<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/TransaksiProduk.php';
$user = wajibLogin(['kasir', 'admin']);
$transaksi = TransaksiProduk::cari((int) ($_GET['id'] ?? 0));
if (!$transaksi || ($user->getRole() === 'kasir' && (int)$transaksi['kasir_id'] !== $user->getId())) {
    header('Location: walkin.php?mode=snack'); exit();
}
$baris=''; foreach($transaksi['items'] as $item){$baris.='<tr><td>'.htmlspecialchars($item['nama'].' ('.ucfirst($item['ukuran']).') × '.$item['jumlah']).'</td><td>'.htmlspecialchars(formatRupiah((int)$item['harga_satuan']*(int)$item['jumlah'])).'</td></tr>';}
$html='<!doctype html><html lang="id"><meta charset="utf-8"><title>Struk '.htmlspecialchars($transaksi['kode_transaksi']).'</title><style>body{font-family:Arial;background:#eee;padding:30px;color:#111}.r{max-width:440px;margin:auto;background:#fff;padding:26px;border-radius:14px}.h{text-align:center;border-bottom:2px solid #111;padding-bottom:16px}table{width:100%;border-collapse:collapse;margin-top:18px}td{padding:8px 0;border-bottom:1px dashed #ccc}td:last-child{text-align:right;font-weight:bold}.total{font-size:18px;font-weight:bold;text-align:right;margin-top:20px}</style><div class="r"><div class="h"><h2>Sineverse Cinema</h2><p>Struk Snack &amp; Minuman</p><b>'.htmlspecialchars($transaksi['kode_transaksi']).'</b></div><table>'.$baris.'</table><p>Metode: '.htmlspecialchars($transaksi['metode_bayar']).'</p>'.(!empty($transaksi['kode_tiket'])?'<p>Tiket terkait: '.htmlspecialchars($transaksi['kode_tiket']).'</p>':'').'<div class="total">Total '.htmlspecialchars(formatRupiah((int)$transaksi['total_harga'])).'</div><p>'.htmlspecialchars(date('d M Y H:i',strtotime($transaksi['dibuat_pada']))).'</p></div></html>';
header('Content-Type:text/html;charset=UTF-8'); header('Content-Disposition:attachment;filename="struk-'.$transaksi['kode_transaksi'].'.html"'); echo $html; exit();
