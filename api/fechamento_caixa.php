<?php
/**
 * API: Fechamento de Caixa Diário & Relatório de Comissões
 * Fornece os dados financeiros consolidados, rateio de comissões e detalhamento de atendimentos.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/CaixaService.php';

try {
    $caixaService = new CaixaService();

    // Se solicitar histórico comparativo dos últimos N dias
    if (isset($_GET['historico'])) {
        $dias = max(1, min(30, (int)$_GET['historico']));
        $taxaComissao = isset($_GET['comissao']) ? (float)$_GET['comissao'] : 50.0;
        $historico = $caixaService->getHistoricoResumido($dias, $taxaComissao);

        echo json_encode([
            'success' => true,
            'dias' => $dias,
            'comissao_padrao' => $taxaComissao,
            'historico' => $historico
        ]);
        exit;
    }

    // Fechamento da data solicitada (ou data atual)
    $data = $_GET['data'] ?? date('Y-m-d');
    $taxaComissao = isset($_GET['comissao']) ? (float)$_GET['comissao'] : 50.0;

    $fechamento = $caixaService->fecharCaixa($data, $taxaComissao);

    echo json_encode([
        'success' => true,
        'dados' => $fechamento
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Falha ao processar fechamento de caixa: ' . $e->getMessage()
    ]);
    exit;
}