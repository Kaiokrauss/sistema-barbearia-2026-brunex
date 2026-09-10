<?php
/**
 * API: Backup do Banco de Dados
 * Permite o download em 1 clique do dump SQL completo do banco de dados da Barbearia VIP.
 */
session_start();

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/BackupService.php';

try {
    $backupService = new BackupService();

    // Se requisitado apenas dados estatísticos em JSON
    if (isset($_GET['info'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'database' => 'barbearia_vip',
            'estatisticas' => $backupService->getEstatisticas(),
            'gerado_em' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Geração e download do arquivo .sql
    $sql = $backupService->gerarBackupSql();
    $nomeArquivo = 'backup_barbearia_vip_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $sql;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Falha ao gerar backup: ' . $e->getMessage()
    ]);
    exit;
}