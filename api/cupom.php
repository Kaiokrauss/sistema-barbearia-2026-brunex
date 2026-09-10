<?php
/**
 * API: Validação e Cálculo de Cupons / Descontos (Padrão Strategy)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/DescontoStrategy.php';
require_once __DIR__ . '/../Models/CalculadoraPreco.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_REQUEST;
    }

    // Se solicitar listar cupons ativos para consulta
    if (isset($_GET['listar'])) {
        echo json_encode([
            'success' => true,
            'cupons' => CupomDescontoStrategy::getCuponsDisponiveis()
        ]);
        exit;
    }

    $cupom = trim($input['cupom'] ?? '');
    $data = trim($input['data'] ?? date('Y-m-d'));
    $preco = isset($input['preco']) ? (float)$input['preco'] : 0.0;
    $servicoId = isset($input['servico_id']) ? (int)$input['servico_id'] : 0;

    // Se informou ID do serviço, busca o preço real no banco de dados
    if ($servicoId > 0) {
        $db = Database::getInstance()->getConnection();
        $st = $db->prepare("SELECT preco, nome FROM servicos WHERE id = :id LIMIT 1");
        $st->execute([':id' => $servicoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $preco = (float)$row['preco'];
        }
    }

    if ($preco <= 0) {
        $preco = 45.0; // Preço padrão de referência
    }

    $calculadora = CalculadoraPreco::criarMelhorEstrategia($cupom, $data, $preco);
    $resultado = $calculadora->calcular($preco, $data, $cupom);

    $valido = $resultado['teve_desconto'];

    echo json_encode(array_merge([
        'success' => true,
        'cupom' => strtoupper($cupom),
        'valido' => $valido,
        'mensagem' => $valido ? "Desconto aplicado: " . $resultado['estrategia_desc'] : "Nenhum desconto aplicável para este cupom ou data."
    ], $resultado));
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar cupom: ' . $e->getMessage()
    ]);
    exit;
}