<?php
/**
 * API REST: Concierge IA 24h (Voz & Chat de Agendamento Inteligente)
 * Endpoints:
 * - GET  /api/concierge.php                -> Saudação inicial e chips rápidos
 * - POST /api/concierge.php                -> Processa mensagem de voz/texto com contexto multi-turno
 */

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

require_once __DIR__ . '/../Models/ConciergeService.php';

function responderConciergeJson(array $dados, int $status = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $concierge = new ConciergeService();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $msg = trim((string)($_GET['mensagem'] ?? ''));
        $res = $concierge->processarMensagem($msg, []);
        responderConciergeJson($res);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode((string)$raw, true);
        $dados = is_array($json) ? $json : $_POST;

        $mensagem = (string)($dados['mensagem'] ?? '');
        $contexto = isset($dados['contexto']) && is_array($dados['contexto']) ? $dados['contexto'] : [];

        $res = $concierge->processarMensagem($mensagem, $contexto);
        responderConciergeJson($res);
    }

    responderConciergeJson(['success' => false, 'erro' => 'Método não permitido.'], 405);
} catch (Throwable $e) {
    responderConciergeJson([
        'success' => false,
        'erro' => 'Erro no Concierge IA: ' . $e->getMessage(),
    ], 500);
}
