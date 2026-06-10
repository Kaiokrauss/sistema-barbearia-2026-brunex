<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Agendamento.php';

$dbObj = new Database();
$conn = $dbObj->getConnection();
$ag = new Agendamento($conn);

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    // Suporta filtros: ?data=YYYY-MM-DD ou ?cliente=nome
    $data = $_GET['data'] ?? null;
    $cliente = $_GET['cliente'] ?? null;

    $sql = "SELECT * FROM agendamentos";
    $conds = [];
    $params = [];
    if ($data) {
        $conds[] = "data_agendada = :data";
        $params[':data'] = $data;
    }
    if ($cliente) {
        $conds[] = "cliente_nome LIKE :cliente";
        $params[':cliente'] = "%$cliente%";
    }
    if (count($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);
    $sql .= ' ORDER BY data_agendada, horario';

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if ($method === 'POST') {
    // Criar agendamento com checagem de conflitos
    $ag->cliente_nome = trim($input['cliente_nome'] ?? '');
    $ag->cliente_telefone = trim($input['cliente_telefone'] ?? '');
    $ag->servico_id = $input['servico_id'] ?? null;
    $ag->data_agendada = $input['data_agendada'] ?? null; // YYYY-MM-DD
    $ag->horario = $input['horario'] ?? null; // HH:MM

    if (!$ag->cliente_nome || !$ag->servico_id || !$ag->data_agendada || !$ag->horario) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios faltando.']);
        exit;
    }

    // Checa conflitos
    $ocupados = $ag->getHorariosOcupados($ag->data_agendada);
    $bloqueados = $ag->getHorariosBloqueados($ag->data_agendada);
    if (in_array($ag->horario, $ocupados) || in_array($ag->horario, $bloqueados)) {
        http_response_code(409);
        echo json_encode(['error' => 'Horário indisponível. Escolha outro horário.']);
        exit;
    }

    // Código único
    $ag->codigo = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

    if ($ag->criar()) {
        echo json_encode(['success' => 'Agendamento criado.', 'codigo' => $ag->codigo]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao criar agendamento.']);
    }
    exit;
}

if ($method === 'PUT') {
    // Atualiza status ou dados básicos
    $id = $input['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID necessário para atualização.']);
        exit;
    }

    $fields = [];
    $params = [':id' => $id];
    if (isset($input['status'])) { $fields[] = "status = :status"; $params[':status'] = $input['status']; }
    if (isset($input['horario'])) { $fields[] = "horario = :horario"; $params[':horario'] = $input['horario']; }
    if (isset($input['data_agendada'])) { $fields[] = "data_agendada = :data"; $params[':data'] = $input['data_agendada']; }

    if (!count($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum campo para atualizar.']);
        exit;
    }

    $sql = "UPDATE agendamentos SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    if ($stmt->execute()) echo json_encode(['success' => 'Agendamento atualizado.']);
    else { http_response_code(500); echo json_encode(['error' => 'Erro ao atualizar.']); }
    exit;
}

if ($method === 'DELETE') {
    // Suporta cancelamento por código
    $codigo = $input['codigo'] ?? ($_GET['codigo'] ?? null);
    if (!$codigo) {
        http_response_code(400);
        echo json_encode(['error' => 'Código do agendamento necessário para cancelamento.']);
        exit;
    }
    $res = $ag->cancelarPorCodigo($codigo);
    if ($res) echo json_encode(['success' => 'Agendamento cancelado.']);
    else { http_response_code(404); echo json_encode(['error' => 'Agendamento não encontrado ou já cancelado.']); }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não permitido.']);

?>
