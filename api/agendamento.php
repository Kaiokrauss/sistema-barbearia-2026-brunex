<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Agendamento.php';
require_once __DIR__ . '/../Models/WhatsAppService.php';

$conn = Database::getInstance()->getConnection();
$ag = new Agendamento($conn);

function getHorariosExpediente(): array {
    $file = __DIR__ . '/config_horarios.json';
    if (file_exists($file)) {
        $content = json_decode(file_get_contents($file), true);
        if (is_array($content) && !empty($content)) {
            return $content;
        }
    }
    return ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    // Retorna a grade completa de horários de uma data com seus status detalhados
    if (isset($_GET['grade_completa'])) {
        $data = $_GET['data'] ?? date('Y-m-d');
        $todosHorarios = getHorariosExpediente();
        $ocupados = $ag->getHorariosOcupados($data);
        $bloqueados = $ag->getHorariosBloqueados($data);

        $stAg = $conn->prepare("SELECT a.horario, a.cliente_nome, a.codigo, s.nome AS servico_nome 
                                FROM agendamentos a 
                                LEFT JOIN servicos s ON a.servico_id = s.id 
                                WHERE a.data_agendada = :data AND a.status = 'ativo'");
        $stAg->execute([':data' => $data]);
        $agendamentosDia = [];
        while ($row = $stAg->fetch(PDO::FETCH_ASSOC)) {
            $hFmt = substr($row['horario'], 0, 5);
            $agendamentosDia[$hFmt] = $row;
        }

        $grade = [];
        foreach ($todosHorarios as $h) {
            $hFmt = substr($h, 0, 5);
            if (isset($agendamentosDia[$hFmt])) {
                $grade[] = [
                    'horario' => $hFmt,
                    'status' => 'ocupado',
                    'cliente_nome' => $agendamentosDia[$hFmt]['cliente_nome'],
                    'servico_nome' => $agendamentosDia[$hFmt]['servico_nome'],
                    'codigo' => $agendamentosDia[$hFmt]['codigo']
                ];
            } elseif (in_array($hFmt, $bloqueados)) {
                $grade[] = [
                    'horario' => $hFmt,
                    'status' => 'bloqueado'
                ];
            } else {
                $grade[] = [
                    'horario' => $hFmt,
                    'status' => 'livre'
                ];
            }
        }

        echo json_encode([
            'data' => $data,
            'horarios_expediente' => $todosHorarios,
            'grade' => $grade
        ]);
        exit;
    }

    // Retorna horários disponíveis para uma data ou lista de agendamentos
    if (isset($_GET['disponiveis']) || (isset($_GET['acao']) && $_GET['acao'] === 'horarios_livres')) {
        $data = $_GET['data'] ?? null;
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Data é obrigatória.']);
            exit;
        }

        $defaultHorarios = getHorariosExpediente();
        $ocupados = $ag->getHorariosOcupados($data);
        $bloqueados = $ag->getHorariosBloqueados($data);
        $disponiveis = array_values(array_diff($defaultHorarios, array_merge($ocupados, $bloqueados)));

        echo json_encode(['horarios' => $disponiveis]);
        exit;
    }

    // Retorna apenas horários bloqueados de uma data
    if (isset($_GET['bloqueios'])) {
        $data = $_GET['data'] ?? date('Y-m-d');
        $bloqueados = $ag->getHorariosBloqueados($data);
        echo json_encode(['data' => $data, 'bloqueados' => $bloqueados]);
        exit;
    }

    // Retorna a lista padrão de horários de expediente
    if (isset($_GET['expediente'])) {
        echo json_encode(['horarios' => getHorariosExpediente()]);
        exit;
    }

    // Suporta filtros: ?data=YYYY-MM-DD ou ?cliente=nome
    $data = $_GET['data'] ?? null;
    $cliente = $_GET['cliente'] ?? null;

    $sql = "SELECT a.*, s.nome AS servico_nome, s.preco AS servico_preco 
            FROM agendamentos a 
            LEFT JOIN servicos s ON a.servico_id = s.id";
    $conds = [];
    $params = [];
    if ($data) {
        $conds[] = "a.data_agendada = :data";
        $params[':data'] = $data;
    }
    if ($cliente) {
        $conds[] = "a.cliente_nome LIKE :cliente";
        $params[':cliente'] = "%$cliente%";
    }
    if (count($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);
    $sql .= ' ORDER BY a.data_agendada, a.horario';

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
    // AÇÕES DE GERENCIAMENTO DE HORÁRIOS (BLOQUEAR / LIBERAR)
    $acao = $input['acao'] ?? null;

    if ($acao === 'toggle_bloqueio') {
        $data = $input['data'] ?? null;
        $horario = $input['horario'] ?? null;
        if (!$data || !$horario) {
            http_response_code(400);
            echo json_encode(['error' => 'Data e horário são obrigatórios.']);
            exit;
        }
        $res = $ag->toggleBloqueio($data, $horario);
        echo json_encode(array_merge(['success' => true], $res));
        exit;
    }

    if ($acao === 'bloquear_horario') {
        $data = $input['data'] ?? null;
        $horario = $input['horario'] ?? null;
        $motivo = $input['motivo'] ?? 'Bloqueado pelo barbeiro';
        if (!$data || !$horario) {
            http_response_code(400);
            echo json_encode(['error' => 'Data e horário são obrigatórios.']);
            exit;
        }
        $ok = $ag->bloquearHorario($data, $horario, $motivo);
        echo json_encode(['success' => $ok, 'mensagem' => "Horário {$horario} bloqueado com sucesso!"]);
        exit;
    }

    if ($acao === 'desbloquear_horario') {
        $data = $input['data'] ?? null;
        $horario = $input['horario'] ?? null;
        if (!$data || !$horario) {
            http_response_code(400);
            echo json_encode(['error' => 'Data e horário são obrigatórios.']);
            exit;
        }
        $ok = $ag->desbloquearHorario($data, $horario);
        echo json_encode(['success' => $ok, 'mensagem' => "Horário {$horario} liberado com sucesso!"]);
        exit;
    }

    if ($acao === 'bloquear_dia') {
        $data = $input['data'] ?? null;
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Data é obrigatória.']);
            exit;
        }
        $horarios = $input['horarios'] ?? getHorariosExpediente();
        $ok = $ag->bloquearDia($data, $horarios);
        echo json_encode(['success' => $ok, 'mensagem' => "Todos os horários do dia foram bloqueados com sucesso."]);
        exit;
    }

    if ($acao === 'desbloquear_dia') {
        $data = $input['data'] ?? null;
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Data é obrigatória.']);
            exit;
        }
        $ok = $ag->desbloquearDia($data);
        echo json_encode(['success' => $ok, 'mensagem' => "Todos os horários do dia foram liberados com sucesso."]);
        exit;
    }

    if ($acao === 'salvar_horarios_expediente') {
        $novos = $input['horarios'] ?? [];
        if (is_array($novos) && !empty($novos)) {
            sort($novos);
            $novos = array_values(array_unique($novos));
            file_put_contents(__DIR__ . '/config_horarios.json', json_encode($novos, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'horarios' => $novos, 'mensagem' => 'Grade de expediente atualizada!']);
            exit;
        }
        http_response_code(400);
        echo json_encode(['error' => 'Lista de horários inválida.']);
        exit;
    }
    try {
        // Criar agendamento com checagem de conflitos
        $ag->cliente_nome = trim($input['cliente_nome'] ?? '');
        $ag->cliente_telefone = trim($input['cliente_telefone'] ?? '');
        $rawServico = $input['servico_id'] ?? null;
        $ag->data_agendada = $input['data_agendada'] ?? null; // YYYY-MM-DD
        $ag->horario = substr(trim($input['horario'] ?? ''), 0, 5); // HH:MM

        // Se o serviço vier como texto/nome, busca o ID correspondente
        if (!is_numeric($rawServico)) {
            $stBusca = $conn->prepare("SELECT id FROM servicos WHERE nome LIKE :nome LIMIT 1");
            $stBusca->execute([':nome' => "%" . trim((string)$rawServico) . "%"]);
            $svcEncontrado = $stBusca->fetchColumn();
            $ag->servico_id = $svcEncontrado ? (int)$svcEncontrado : 1;
        } else {
            $ag->servico_id = (int)$rawServico;
        }

        if (!$ag->cliente_nome || !$ag->servico_id || !$ag->data_agendada || !$ag->horario) {
            http_response_code(400);
            echo json_encode(['error' => 'Por favor, preencha todos os campos obrigatórios (nome, serviço, data e horário).']);
            exit;
        }

        // Checa conflitos
        $ocupados = $ag->getHorariosOcupados($ag->data_agendada);
        $bloqueados = $ag->getHorariosBloqueados($ag->data_agendada);
        if (in_array($ag->horario, $ocupados) || in_array($ag->horario, $bloqueados)) {
            http_response_code(409);
            echo json_encode(['error' => "O horário {$ag->horario} já está ocupado nesta data. Escolha outro horário."]);
            exit;
        }

        // Código único de 6 caracteres
        $ag->codigo = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        if ($ag->criar()) {
            // Busca dados do serviço para compor a mensagem do WhatsApp
            $servicoNome = 'Atendimento Barbearia';
            try {
                $stSvc = $conn->prepare("SELECT nome, preco FROM servicos WHERE id = :id");
                $stSvc->execute([':id' => $ag->servico_id]);
                $svcRow = $stSvc->fetch(PDO::FETCH_ASSOC);
                if ($svcRow) {
                    $servicoNome = $svcRow['nome'] . " (R$ " . number_format($svcRow['preco'], 2, ',', '.') . ")";
                }
            } catch (Exception $e) {}

            $dadosAgendamento = [
                'cliente_nome' => $ag->cliente_nome,
                'cliente_telefone' => $ag->cliente_telefone,
                'servico_nome' => $servicoNome,
                'data_agendada' => date('d/m/Y', strtotime($ag->data_agendada)),
                'horario' => $ag->horario,
                'codigo' => $ag->codigo
            ];

            // Gera links da API do WhatsApp para o cliente e para o barbeiro
            $whatsappUrlCliente = WhatsAppService::gerarLinkConfirmacaoCliente($dadosAgendamento);
            $whatsappUrlBarbeiro = WhatsAppService::gerarLinkNovoAgendamentoBarbeiro($dadosAgendamento);

            echo json_encode([
                'success' => 'Agendamento confirmado com sucesso!',
                'codigo' => $ag->codigo,
                'dados' => $dadosAgendamento,
                'whatsapp_url_cliente' => $whatsappUrlCliente,
                'whatsapp_url_barbeiro' => $whatsappUrlBarbeiro
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao salvar o agendamento no banco de dados.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no processamento do agendamento: ' . $e->getMessage()]);
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
