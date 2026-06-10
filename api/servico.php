<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Servico.php';

$dbObj = new Database();
$conn = $dbObj->getConnection();
$servico = new Servico($conn);

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    // Retorna todos os serviços
    $stmt = $servico->lerTodos();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if ($method === 'POST') {
    // Criar
    $servico->nome = trim($input['nome'] ?? '');
    $servico->preco = $input['preco'] ?? 0;
    $servico->duracao_minutos = $input['duracao_minutos'] ?? 30;

    if (!$servico->nome) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome do serviço é obrigatório.']);
        exit;
    }

    if ($servico->criar()) {
        echo json_encode(['success' => 'Serviço criado com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao criar serviço.']);
    }
    exit;
}

if ($method === 'PUT') {
    // Atualizar
    $servico->id = $input['id'] ?? null;
    $servico->nome = trim($input['nome'] ?? '');
    $servico->preco = $input['preco'] ?? 0;
    $servico->duracao_minutos = $input['duracao_minutos'] ?? 30;

    if (!$servico->id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do serviço é obrigatório.']);
        exit;
    }

    if ($servico->atualizar()) {
        echo json_encode(['success' => 'Serviço atualizado com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar serviço.']);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $input['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do serviço é obrigatório para exclusão.']);
        exit;
    }
    $servico->id = $id;
    if ($servico->deletar()) {
        echo json_encode(['success' => 'Serviço deletado.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao deletar serviço.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não permitido.']);

?>
