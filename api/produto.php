<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Produto.php';

$conn = Database::getInstance()->getConnection();
$produtoModel = new Produto($conn);

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// [GET] CONSULTAS DE PRODUTOS & ESTOQUE
// ==========================================
if ($method === 'GET') {
    // 1. Estatísticas de estoque para o painel admin
    if (isset($_GET['stats'])) {
        $stats = $produtoModel->getEstatisticasEstoque();
        echo json_encode(['success' => true, 'dados' => $stats]);
        exit;
    }

    // 2. Lista de categorias
    if (isset($_GET['categorias'])) {
        $cats = $produtoModel->getCategorias();
        echo json_encode(['success' => true, 'dados' => $cats]);
        exit;
    }

    // 3. Produto específico por ID
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $prod = $produtoModel->lerPorId($id);
        if ($prod) {
            echo json_encode(['success' => true, 'dados' => $prod]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Produto não encontrado.']);
        }
        exit;
    }

    // 4. Listagem geral com filtros
    $categoria = $_GET['categoria'] ?? null;
    $apenasAtivos = !isset($_GET['admin']); // Se for admin, lista inativos também
    $destaque = isset($_GET['destaque']) ? ((int)$_GET['destaque'] === 1) : null;

    $produtos = $produtoModel->lerTodos($categoria, $apenasAtivos, $destaque);
    echo json_encode(['success' => true, 'total' => count($produtos), 'dados' => $produtos]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

// ==========================================
// [POST] CADASTRO, COMPRA/RESERVA OU ESTOQUE
// ==========================================
if ($method === 'POST') {
    $acao = $input['acao'] ?? $_GET['acao'] ?? 'criar';

    // Ação 1: Reserva / Compra de Produto via WhatsApp
    if ($acao === 'comprar' || $acao === 'reservar') {
        $produtoId = (int)($input['produto_id'] ?? 0);
        $qtd = max(1, (int)($input['quantidade'] ?? 1));
        $clienteNome = trim($input['cliente_nome'] ?? 'Cliente VIP');
        $clienteTel = trim($input['cliente_telefone'] ?? '');

        $prod = $produtoModel->lerPorId($produtoId);
        if (!$prod || !$prod['ativo']) {
            http_response_code(404);
            echo json_encode(['error' => 'Produto indisponível ou esgotado.']);
            exit;
        }

        if ((int)$prod['estoque'] < $qtd) {
            http_response_code(400);
            echo json_encode(['error' => "Estoque insuficiente. Temos apenas {$prod['estoque']} unidade(s) disponíveis."]);
            exit;
        }

        $valorTotal = (float)$prod['preco'] * $qtd;
        $valorFmt = number_format($valorTotal, 2, ',', '.');
        $precoUnFmt = number_format((float)$prod['preco'], 2, ',', '.');

        // Monta mensagem personalizada e link direto para WhatsApp
        $msg = "💈 *NOVO PEDIDO - MINI-LOJA BARBEARIA VIP*\n\n";
        $msg .= "👤 *Cliente:* {$clienteNome}\n";
        if ($clienteTel) $msg .= "📱 *WhatsApp:* {$clienteTel}\n";
        $msg .= "🧴 *Produto:* {$prod['nome']}\n";
        $msg .= "📦 *Quantidade:* {$qtd} un. (R$ {$precoUnFmt} cada)\n";
        $msg .= "💰 *Total a pagar:* R$ {$valorFmt}\n\n";
        $msg .= "Olá! Gostaria de reservar este produto para retirar na barbearia no meu próximo horário! ✂️";

        $whatsappUrl = "https://api.whatsapp.com/send?phone=5511999999999&text=" . rawurlencode($msg);

        echo json_encode([
            'success' => true,
            'mensagem' => 'Pedido de reserva preparado com sucesso!',
            'whatsapp_url' => $whatsappUrl,
            'dados_pedido' => [
                'produto' => $prod['nome'],
                'quantidade' => $qtd,
                'total' => $valorTotal,
                'total_formatado' => "R$ {$valorFmt}"
            ]
        ]);
        exit;
    }

    // Ação 2: Ajuste rápido de estoque (+1, -1, ou delta)
    if ($acao === 'ajustar_estoque') {
        $produtoId = (int)($input['produto_id'] ?? 0);
        $delta = (int)($input['delta'] ?? 0);

        if (!$produtoId || $delta === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do produto e delta são obrigatórios.']);
            exit;
        }

        $ok = $produtoModel->ajustarEstoque($produtoId, $delta);
        if ($ok) {
            $atualizado = $produtoModel->lerPorId($produtoId);
            echo json_encode([
                'success' => true,
                'mensagem' => 'Estoque atualizado com sucesso!',
                'novo_estoque' => (int)$atualizado['estoque']
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Não foi possível alterar o estoque (quantidade insuficiente ou produto inexistente).']);
        }
        exit;
    }

    // Ação 3: Cadastrar novo produto
    $nome = trim($input['nome'] ?? '');
    $preco = (float)($input['preco'] ?? 0);

    if (empty($nome) || $preco <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome do produto e preço válido são obrigatórios.']);
        exit;
    }

    $novoId = $produtoModel->criar($input);
    if ($novoId > 0) {
        echo json_encode([
            'success' => true,
            'mensagem' => 'Produto cadastrado com sucesso!',
            'id' => $novoId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao cadastrar produto.']);
    }
    exit;
}

// ==========================================
// [PUT] ATUALIZAR PRODUTO
// ==========================================
if ($method === 'PUT') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do produto é obrigatório para atualização.']);
        exit;
    }

    $ok = $produtoModel->atualizar($id, $input);
    if ($ok) {
        echo json_encode(['success' => true, 'mensagem' => 'Produto atualizado com sucesso!']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar produto.']);
    }
    exit;
}

// ==========================================
// [DELETE] EXCLUIR / DESATIVAR PRODUTO
// ==========================================
if ($method === 'DELETE') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do produto é obrigatório.']);
        exit;
    }

    $hardDelete = isset($_GET['permanent']) && $_GET['permanent'] === '1';
    $ok = $produtoModel->deletar($id, !$hardDelete);

    if ($ok) {
        echo json_encode(['success' => true, 'mensagem' => 'Produto removido com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao remover produto.']);
    }
    exit;
}
