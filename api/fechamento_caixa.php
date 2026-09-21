<?php
/**
 * API: Fechamento de Caixa Diário & Relatório de Comissões
 * Fornece os dados financeiros consolidados, rateio de comissões por barbeiro e detalhamento de atendimentos.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/CaixaService.php';

try {
    $caixaService = new CaixaService();

    // Rota para listar apenas os barbeiros cadastrados
    if (isset($_GET['barbeiros']) || (isset($_GET['acao']) && $_GET['acao'] === 'barbeiros')) {
        echo json_encode([
            'success' => true,
            'barbeiros' => $caixaService->getBarbeiros()
        ]);
        exit;
    }

    $barbeiroId = isset($_GET['barbeiro_id']) && is_numeric($_GET['barbeiro_id']) && (int)$_GET['barbeiro_id'] > 0
        ? (int)$_GET['barbeiro_id']
        : null;

    $taxaComissao = isset($_GET['comissao']) ? (float)$_GET['comissao'] : 50.0;

    // Se solicitar histórico comparativo dos últimos N dias
    if (isset($_GET['historico'])) {
        $dias = max(1, min(30, (int)$_GET['historico']));
        $historico = $caixaService->getHistoricoResumido($dias, $taxaComissao, $barbeiroId);

        echo json_encode([
            'success' => true,
            'dias' => $dias,
            'barbeiro_id' => $barbeiroId,
            'comissao_padrao' => $taxaComissao,
            'historico' => $historico
        ]);
        exit;
    }

    // Fechamento da data solicitada (ou data atual)
    $data = $_GET['data'] ?? date('Y-m-d');
    $fechamento = $caixaService->fecharCaixa($data, $taxaComissao, $barbeiroId);

    echo json_encode([
        'success' => true,
        'barbeiros' => $caixaService->getBarbeiros(),
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