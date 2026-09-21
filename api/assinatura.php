<?php
/**
 * API: Clube de Assinatura VIP / Barber Pass
 * Gerencia planos de mensalidade, consulta e validação de assinantes ativos em tempo real.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/DescontoStrategy.php';

try {
    $conn = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // 1. Consulta em tempo real por telefone (Usado no formulário de agendamento)
        if (isset($_GET['check_telefone'])) {
            $telBruto = trim($_GET['check_telefone']);
            $telDigitos = preg_replace('/\D/', '', $telBruto);

            if (strlen($telDigitos) < 8) {
                echo json_encode([
                    'success' => true,
                    'assinante' => false,
                    'mensagem' => 'Telefone insuficiente para verificação.'
                ]);
                exit;
            }

            $sql = "SELECT a.*, p.nome AS plano_nome, p.slug AS plano_slug, p.preco_mensal, p.beneficios, p.cor_badge 
                    FROM assinantes_vip a 
                    INNER JOIN planos_assinatura p ON a.plano_id = p.id 
                    WHERE (a.cliente_telefone = :tel_bruto OR REPLACE(REPLACE(REPLACE(REPLACE(a.cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') = :tel_digitos)
                    AND a.status = 'ativo' 
                    AND a.data_renovacao >= CURDATE()
                    ORDER BY a.data_renovacao DESC 
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':tel_bruto' => $telBruto,
                ':tel_digitos' => $telDigitos
            ]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sub) {
                $beneficiosArray = array_filter(array_map('trim', explode("\n", $sub['beneficios'] ?? '')));
                $dataRenovacao = strtotime($sub['data_renovacao']);
                $hoje = strtotime(date('Y-m-d'));
                $diasRestantes = max(0, (int)floor(($dataRenovacao - $hoje) / 86400));

                echo json_encode([
                    'success' => true,
                    'assinante' => true,
                    'dados' => [
                        'id' => (int)$sub['id'],
                        'cliente_nome' => $sub['cliente_nome'],
                        'cliente_telefone' => $sub['cliente_telefone'],
                        'plano_id' => (int)$sub['plano_id'],
                        'plano_nome' => $sub['plano_nome'],
                        'plano_slug' => $sub['plano_slug'],
                        'preco_mensal' => (float)$sub['preco_mensal'],
                        'cor_badge' => $sub['cor_badge'],
                        'beneficios' => array_values($beneficiosArray),
                        'data_renovacao' => date('d/m/Y', $dataRenovacao),
                        'dias_restantes' => $diasRestantes,
                        'voucher_assinante' => 'VIPCLUB-' . substr(md5($sub['cliente_telefone']), 0, 6)
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'assinante' => false,
                    'mensagem' => 'Cliente não possui assinatura ativa no momento.'
                ]);
            }
            exit;
        }

        // 2. Listagem de Planos de Assinatura
        if (isset($_GET['planos']) || !isset($_GET['assinantes'])) {
            $stmt = $conn->query("SELECT * FROM planos_assinatura WHERE ativo = 1 ORDER BY preco_mensal ASC");
            $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($planos as &$p) {
                $p['preco_mensal'] = (float)$p['preco_mensal'];
                $p['beneficios_lista'] = array_filter(array_map('trim', explode("\n", $p['beneficios'] ?? '')));
            }

            if (!isset($_GET['assinantes'])) {
                echo json_encode([
                    'success' => true,
                    'planos' => $planos
                ]);
                exit;
            }
        }

        // 3. Listagem Geral de Assinantes VIP (Painel Administrativo)
        if (isset($_GET['assinantes'])) {
            $sql = "SELECT a.*, p.nome AS plano_nome, p.slug AS plano_slug, p.preco_mensal, p.cor_badge 
                    FROM assinantes_vip a 
                    INNER JOIN planos_assinatura p ON a.plano_id = p.id 
                    ORDER BY a.status ASC, a.data_renovacao DESC";
            $stmt = $conn->query($sql);
            $assinantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($assinantes as &$ass) {
                $ass['preco_mensal'] = (float)$ass['preco_mensal'];
                $ass['data_inicio_formatada'] = date('d/m/Y', strtotime($ass['data_inicio']));
                $ass['data_renovacao_formatada'] = date('d/m/Y', strtotime($ass['data_renovacao']));
                $ass['em_dia'] = ($ass['status'] === 'ativo' && strtotime($ass['data_renovacao']) >= strtotime(date('Y-m-d')));
            }

            echo json_encode([
                'success' => true,
                'total' => count($assinantes),
                'assinantes' => $assinantes
            ]);
            exit;
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    // 4. Cadastro ou Renovação de Assinante VIP
    if ($method === 'POST') {
        $nome = trim($input['cliente_nome'] ?? '');
        $telefone = trim($input['cliente_telefone'] ?? '');
        $email = trim($input['cliente_email'] ?? '');
        $planoId = (int)($input['plano_id'] ?? 0);
        $meses = max(1, (int)($input['meses'] ?? 1));

        if (!$nome || !$telefone || !$planoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome, telefone e plano são obrigatórios para a assinatura.']);
            exit;
        }

        $dataInicio = date('Y-m-d');
        $dataRenovacao = date('Y-m-d', strtotime("+{$meses} month"));

        // Se já existir assinatura anterior para esse telefone, atualiza / renova
        $check = $conn->prepare("SELECT id FROM assinantes_vip WHERE cliente_telefone = :tel LIMIT 1");
        $check->execute([':tel' => $telefone]);
        $existente = $check->fetchColumn();

        if ($existente) {
            $upd = $conn->prepare("UPDATE assinantes_vip 
                                  SET cliente_nome = :nome, cliente_email = :email, plano_id = :plano, 
                                      status = 'ativo', data_renovacao = :renovacao 
                                  WHERE id = :id");
            $upd->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':plano' => $planoId,
                ':renovacao' => $dataRenovacao,
                ':id' => $existente
            ]);
            $assId = (int)$existente;
            $msg = 'Assinatura VIP renovada com sucesso!';
        } else {
            $ins = $conn->prepare("INSERT INTO assinantes_vip (cliente_nome, cliente_telefone, cliente_email, plano_id, status, data_inicio, data_renovacao) 
                                  VALUES (:nome, :tel, :email, :plano, 'ativo', :inicio, :renovacao)");
            $ins->execute([
                ':nome' => $nome,
                ':tel' => $telefone,
                ':email' => $email,
                ':plano' => $planoId,
                ':inicio' => $dataInicio,
                ':renovacao' => $dataRenovacao
            ]);
            $assId = (int)$conn->lastInsertId();
            $msg = 'Novo membro VIP cadastrado com sucesso!';
        }

        echo json_encode([
            'success' => true,
            'id' => $assId,
            'mensagem' => $msg,
            'data_renovacao' => date('d/m/Y', strtotime($dataRenovacao))
        ]);
        exit;
    }

    // 5. Atualização de status da assinatura (ativo / suspenso / cancelado)
    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? null;
        $novaData = $input['data_renovacao'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da assinatura é obrigatório.']);
            exit;
        }

        $fields = [];
        $params = [':id' => $id];

        if ($status && in_array($status, ['ativo', 'suspenso', 'cancelado'])) {
            $fields[] = "status = :status";
            $params[':status'] = $status;
        }
        if ($novaData) {
            $fields[] = "data_renovacao = :data_renovacao";
            $params[':data_renovacao'] = $novaData;
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum campo válido para atualização.']);
            exit;
        }

        $sql = "UPDATE assinantes_vip SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'mensagem' => 'Assinatura atualizada com sucesso!']);
        exit;
    }

    // 6. Exclusão de assinatura
    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? ($_GET['id'] ?? 0));
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da assinatura é obrigatório.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM assinantes_vip WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'mensagem' => 'Registro de assinatura excluído com sucesso.']);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro na API de Assinatura VIP: ' . $e->getMessage()
    ]);
    exit;
}
