<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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


// -------------------------------------------------------------
// GET: Listar Avaliações & Estatísticas de Satisfação
// -------------------------------------------------------------
if ($metodo === 'GET') {
    try {
        // Estatísticas gerais
        $stTotal = $db->query("SELECT COUNT(*) FROM avaliacoes");
        $total = (int)$stTotal->fetchColumn();

        $media = 5.0;
        $recomendacao = 100;
        $distribuicao = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        if ($total > 0) {
            $stMedia = $db->query("SELECT AVG(nota) FROM avaliacoes");
            $media = round((float)$stMedia->fetchColumn(), 1);

            $stPositivas = $db->query("SELECT COUNT(*) FROM avaliacoes WHERE nota >= 4");
            $positivas = (int)$stPositivas->fetchColumn();
            $recomendacao = round(($positivas / $total) * 100);

            $stDist = $db->query("SELECT nota, COUNT(*) as qtd FROM avaliacoes GROUP BY nota");
            while ($row = $stDist->fetch(PDO::FETCH_ASSOC)) {
                $n = (int)$row['nota'];
                if (isset($distribuicao[$n])) {
                    $distribuicao[$n] = (int)$row['qtd'];
                }
            }
        }

        // Busca as avaliações mais recentes (limite 30)
        $limite = isset($_GET['todos']) ? 100 : 20;
        $stmt = $db->query("
            SELECT 
                id, 
                cliente_nome, 
                cliente_telefone, 
                COALESCE(servico_nome, 'Corte Clássico') as servico_nome, 
                nota, 
                comentario, 
                destaque, 
                DATE_FORMAT(criado_em, '%d/%m/%Y às %H:%i') as data_formatada,
                criado_em
            FROM avaliacoes
            ORDER BY criado_em DESC
            LIMIT {$limite}
        ");
        $avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'sucesso' => true,
            'estatisticas' => [
                'total_avaliacoes' => $total,
                'media_geral' => $media,
                'estrelas_texto' => str_repeat('★', (int)round($media)) . str_repeat('☆', 5 - (int)round($media)),
                'porcentagem_recomendacao' => $recomendacao,
                'distribuicao_estrelas' => $distribuicao
            ],
            'avaliacoes' => $avaliacoes
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar avaliações: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// POST: Cadastrar Nova Avaliação com Estrelas
// -------------------------------------------------------------
if ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    // Tratamento de ação para exclusão pelo Admin
    if (($input['acao'] ?? '') === 'excluir') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido.']);
            exit;
        }
        $stDel = $db->prepare("DELETE FROM avaliacoes WHERE id = :id");
        $stDel->execute([':id' => $id]);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Avaliação removida com sucesso.']);
        exit;
    }

    $nome = trim($input['cliente_nome'] ?? $input['nome'] ?? '');
    $telefone = trim($input['cliente_telefone'] ?? $input['telefone'] ?? '');
    $servico = trim($input['servico_nome'] ?? $input['servico'] ?? 'Corte de Cabelo');
    $nota = (int)($input['nota'] ?? 5);
    $comentario = trim($input['comentario'] ?? '');

    // Validações
    if (mb_strlen($nome, 'UTF-8') < 2) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Por favor, informe seu nome para a avaliação.']);
        exit;
    }

    if ($nota < 1 || $nota > 5) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'A nota deve ser entre 1 e 5 estrelas.']);
        exit;
    }

    if (mb_strlen($comentario, 'UTF-8') < 3) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Por favor, escreva um breve comentário sobre seu atendimento.']);
        exit;
    }

    // Sanitização de HTML para segurança
    $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $comentario = htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8');
    $servico = htmlspecialchars($servico, ENT_QUOTES, 'UTF-8');

    try {
        $stmt = $db->prepare("
            INSERT INTO avaliacoes (cliente_nome, cliente_telefone, servico_nome, nota, comentario, destaque)
            VALUES (:nome, :tel, :servico, :nota, :comentario, 1)
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':tel' => $telefone ?: null,
            ':servico' => $servico,
            ':nota' => $nota,
            ':comentario' => $comentario
        ]);

        $novoId = (int)$db->lastInsertId();

        echo json_encode([
            'sucesso' => true,
            'mensagem' => '⭐ Sua avaliação foi publicada com sucesso! Agradecemos pelo seu feedback VIP!',
            'avaliacao' => [
                'id' => $novoId,
                'cliente_nome' => $nome,
                'servico_nome' => $servico,
                'nota' => $nota,
                'comentario' => $comentario,
                'data_formatada' => date('d/m/Y às H:i')
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar avaliação: ' . $e->getMessage()]);
        exit;
    }
}
