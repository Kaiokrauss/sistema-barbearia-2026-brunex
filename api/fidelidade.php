<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Models/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados.");
    }
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}


// Helper para extrair apenas dígitos do telefone
function limparTelefone(?string $tel): string {
    return preg_replace('/[^0-9]/', '', (string)$tel);
}

// -------------------------------------------------------------
// GET: Consultar Cartão Fidelidade do Cliente ou Ranking Geral
// -------------------------------------------------------------
if ($metodo === 'GET') {
    $telefoneRaw = $_GET['telefone'] ?? '';
    $telefoneLimpo = limparTelefone($telefoneRaw);

    // Se solicitado ranking para painel Admin
    if (isset($_GET['ranking'])) {
        try {
            $sql = "
                SELECT 
                    cliente_nome, 
                    cliente_telefone, 
                    COUNT(*) as total_cortes,
                    MAX(data_agendada) as ultimo_corte
                FROM agendamentos
                WHERE status != 'cancelado' AND cliente_telefone IS NOT NULL AND cliente_telefone != ''
                GROUP BY cliente_nome, cliente_telefone
                ORDER BY total_cortes DESC, ultimo_corte DESC
                LIMIT 10
            ";
            $stmt = $db->query($sql);
            $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'sucesso' => true,
                'ranking' => $ranking
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar ranking: ' . $e->getMessage()]);
            exit;
        }
    }

    if (empty($telefoneLimpo)) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Informe o número de telefone para consultar seu Cartão Fidelidade VIP.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Busca agendamentos do cliente pelo telefone (limpo ou formatado)
        $sql = "
            SELECT 
                a.id, 
                a.cliente_nome, 
                a.cliente_telefone, 
                a.servico_id, 
                a.data_agendada, 
                a.horario, 
                a.status, 
                a.codigo,
                COALESCE(s.nome, 'Corte Clássico') as servico_nome,
                COALESCE(s.preco, 40.00) as preco
            FROM agendamentos a
            LEFT JOIN servicos s ON a.servico_id = s.id
            WHERE a.status != 'cancelado'
              AND (
                  REPLACE(REPLACE(REPLACE(REPLACE(a.cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel_fim
                  OR a.cliente_telefone LIKE :tel_raw
              )
            ORDER BY a.data_agendada DESC, a.horario DESC
        ";

        // Compara com os últimos 8 dígitos para cobrir variações com/sem DDD ou nono dígito
        $ultimosDigitos = strlen($telefoneLimpo) >= 8 ? substr($telefoneLimpo, -8) : $telefoneLimpo;
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':tel_fim' => "%{$ultimosDigitos}%",
            ':tel_raw' => "%{$telefoneRaw}%"
        ]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCortes = count($agendamentos);
        $clienteNome = $totalCortes > 0 ? $agendamentos[0]['cliente_nome'] : 'Cliente VIP';

        // Busca resgates de voucher já realizados por este telefone
        $stResgates = $db->prepare("
            SELECT COUNT(*) FROM resgates_fidelidade 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel_fim
        ");
        $stResgates->execute([':tel_fim' => "%{$ultimosDigitos}%"]);
        $totalResgatados = (int)$stResgates->fetchColumn();

        // Regra de Negócio: A cada 5 cortes = 1 recompensa (corte grátis)
        $metaCiclo = 5;
        $ciclosConcluidos = (int)floor($totalCortes / $metaCiclo);
        $recompensasDisponiveis = max(0, $ciclosConcluidos - $totalResgatados);

        // Selos no ciclo corrente (de 0 a 5)
        $selosCicloAtual = $totalCortes % $metaCiclo;
        if ($selosCicloAtual === 0 && $totalCortes > 0 && $recompensasDisponiveis > 0) {
            $selosCicloAtual = 5; // Ciclo recém-completado pronto para resgate
        }

        $cortesRestantes = ($selosCicloAtual === 5) ? 0 : ($metaCiclo - $selosCicloAtual);
        $progressoPercentual = min(100, round(($selosCicloAtual / $metaCiclo) * 100));

        // Nível VIP baseado no histórico total
        $nivelVip = 'Bronze';
        $corVip = '#cd7f32';
        if ($totalCortes >= 15) {
            $nivelVip = 'Diamante VIP';
            $corVip = '#00f2fe';
        } elseif ($totalCortes >= 10) {
            $nivelVip = 'Ouro Black';
            $corVip = '#D4AF37';
        } elseif ($totalCortes >= 5) {
            $nivelVip = 'Prata Premium';
            $corVip = '#e0e0e0';
        }

        echo json_encode([
            'sucesso' => true,
            'cliente' => [
                'nome' => $clienteNome,
                'telefone' => $telefoneRaw,
                'nivel_vip' => $nivelVip,
                'cor_vip' => $corVip,
                'total_cortes' => $totalCortes,
                'ciclos_concluidos' => $ciclosConcluidos,
                'recompensas_disponiveis' => $recompensasDisponiveis,
                'selos_atuais' => $selosCicloAtual,
                'meta_ciclo' => $metaCiclo,
                'cortes_restantes' => $cortesRestantes,
                'progresso_percentual' => $progressoPercentual,
                'pode_resgatar' => ($recompensasDisponiveis > 0)
            ],
            'historico' => array_slice($agendamentos, 0, 5)
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao consultar fidelidade: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// POST: Resgatar Corte Grátis / Gerar Voucher Fidelidade
// -------------------------------------------------------------
if ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $acao = $input['acao'] ?? '';
    $telefoneRaw = $input['telefone'] ?? '';
    $clienteNome = trim($input['nome'] ?? 'Cliente VIP');
    $telefoneLimpo = limparTelefone($telefoneRaw);

    if ($acao === 'resgatar') {
        if (empty($telefoneLimpo)) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Telefone não informado.']);
            exit;
        }

        $ultimosDigitos = strlen($telefoneLimpo) >= 8 ? substr($telefoneLimpo, -8) : $telefoneLimpo;

        try {
            // Verifica elegibilidade
            $stCortes = $db->prepare("
                SELECT COUNT(*) FROM agendamentos
                WHERE status != 'cancelado'
                  AND REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel_fim
            ");
            $stCortes->execute([':tel_fim' => "%{$ultimosDigitos}%"]);
            $totalCortes = (int)$stCortes->fetchColumn();

            $stResgates = $db->prepare("
                SELECT COUNT(*) FROM resgates_fidelidade 
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel_fim
            ");
            $stResgates->execute([':tel_fim' => "%{$ultimosDigitos}%"]);
            $totalResgatados = (int)$stResgates->fetchColumn();

            $disponiveis = (int)floor($totalCortes / 5) - $totalResgatados;

            if ($disponiveis <= 0) {
                echo json_encode([
                    'sucesso' => false,
                    'mensagem' => "Você ainda não possui recompensas disponíveis para resgate. Complete 5 cortes para liberar seu corte grátis!"
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Gera código único de voucher fidelidade
            $codigoVoucher = 'GRATIS-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

            $stIns = $db->prepare("
                INSERT INTO resgates_fidelidade (cliente_telefone, cliente_nome, codigo_voucher)
                VALUES (:tel, :nome, :cod)
            ");
            $stIns->execute([
                ':tel' => $telefoneRaw ?: $telefoneLimpo,
                ':nome' => $clienteNome,
                ':cod' => $codigoVoucher
            ]);

            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Parabéns! Seu corte grátis foi resgatado com sucesso!',
                'voucher' => [
                    'codigo' => $codigoVoucher,
                    'desconto' => '100% OFF (Corte Grátis)',
                    'validade_dias' => 30,
                    'instrucoes' => 'Apresente este código no balcão da Barbearia VIP ou insira no campo Cupom ao agendar seu próximo horário!'
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao processar resgate: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    exit;
}
