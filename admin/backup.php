<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/FiturBioskop.php';
$user = wajibAkses('operasional');
FiturBioskop::audit($user->getId(), 'unduh_backup', 'database');
$dump = Database::getInstance()->exportSql();
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="sineverse-backup-' . date('Ymd-His') . '.sql"');
header('Content-Length: ' . strlen($dump));
echo $dump;
exit();
