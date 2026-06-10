<?php
// Exporta agendamentos em CSV por data ou intervalo
require_once __DIR__ . '/../Models/Database.php';

$dbObj = new Database();
$conn = $dbObj->getConnection();

$from = $_GET['from'] ?? null; // YYYY-MM-DD
$to = $_GET['to'] ?? null;
$date = $_GET['date'] ?? null; // alternativa

if ($date) { $from = $date; $to = $date; }

$sql = "SELECT * FROM agendamentos";
$conds = [];
$params = [];
if ($from && $to) {
    $conds[] = "data_agendada BETWEEN :from AND :to";
    $params[':from'] = $from;
    $params[':to'] = $to;
} elseif ($from) {
    $conds[] = "data_agendada = :from";
    $params[':from'] = $from;
}
if (count($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agendamentos_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, array_keys($rows[0] ?? ['id','cliente_nome','cliente_telefone','servico_id','data_agendada','horario','status','codigo','criado_em']));
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;

?>
