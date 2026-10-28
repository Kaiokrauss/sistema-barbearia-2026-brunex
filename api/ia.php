<?php
/**
 * API REST de Inteligência Artificial para a Barbearia VIP (2026)
 * Endpoints:
 * - POST /api/ia.php (acao: visagismo)
 * - GET  /api/ia.php?acao=clientes_inativos&dias=25
 * - POST /api/ia.php (acao: gerar_mensagem_recuperacao)
 * - POST /api/ia.php (acao: gerar_campanha)
 */

// Impede que avisos ou notices de PHP interfiram no payload JSON
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ob_start();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) ob_end_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Models/IaService.php';

function responderJson($dados, int $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $iaService = new IaService();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $acao = $_GET['acao'] ?? 'clientes_inativos';

        if ($acao === 'clientes_inativos') {
            $dias = isset($_GET['dias']) ? max(1, (int)$_GET['dias']) : 20;
            $res = $iaService->obterClientesInativos($dias);
            responderJson($res);
        }

        if ($acao === 'status' || $acao === 'status_motores') {
            $res = $iaService->obterStatusMotores();
            responderJson($res);
        }

        if ($acao === 'campanhas') {
            $objetivo = $_GET['objetivo'] ?? 'fim_de_semana';
            $tom = $_GET['tom'] ?? 'vip';
            $res = $iaService->gerarCampanhasMarketing($objetivo, $tom);
            responderJson($res);
        }

        responderJson(['success' => false, 'erro' => 'Ação GET não reconhecida.'], 400);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true) ?? [];
        $dados = !empty($json) ? $json : $_POST;

        $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

        if ($acao === 'status' || $acao === 'status_motores') {
            $res = $iaService->obterStatusMotores();
            responderJson($res);
        }

        if ($acao === 'visagismo') {
            $res = $iaService->analisarVisagismo($dados);
            responderJson($res);
        }

        if ($acao === 'gerar_mensagem_recuperacao') {
            $res = $iaService->gerarMensagemRecuperacao($dados);
            responderJson($res);
        }

        if ($acao === 'gerar_campanha' || $acao === 'gerar_campanhas') {
            $objetivo = $dados['objetivo'] ?? 'fim_de_semana';
            $tom = $dados['tom'] ?? 'vip';
            $res = $iaService->gerarCampanhasMarketing($objetivo, $tom);
            responderJson($res);
        }

        responderJson(['success' => false, 'erro' => 'Ação POST não especificada.'], 400);
    }

    responderJson(['success' => false, 'erro' => 'Método não suportado.'], 405);
} catch (Throwable $e) {
    responderJson([
        'success' => false,
        'erro' => 'Erro interno na Inteligência Artificial: ' . $e->getMessage()
    ], 500);
}
