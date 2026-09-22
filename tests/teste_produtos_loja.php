<?php
/**
 * Bateria de Testes Automatizados: Mini-Loja de Produtos VIP da Barbearia
 * Valida a criação da tabela, seeders, métodos do Model Produto,
 * movimentações de estoque, cálculos analíticos e a API REST com pedidos WhatsApp.
 */

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Produto.php';

$totalTestes = 0;
$testesPassados = 0;
$testesFalhados = 0;

function assertTeste($condicao, $mensagem) {
    global $totalTestes, $testesPassados, $testesFalhados;
    $totalTestes++;
    if ($condicao) {
        $testesPassados++;
        echo "  [PASSOU] $mensagem\n";
    } else {
        $testesFalhados++;
        echo "  [FALHOU] $mensagem\n";
    }
}

function chamarApi(string $arquivo, string $metodo = 'GET', array $get = [], array $post = []): array {
    $phpBin = 'C:\\xampp\\php\\php.exe';
    $tempScript = __DIR__ . '/temp_runner_' . uniqid() . '.php';
    $code = '<?php ' .
        '$_SERVER["REQUEST_METHOD"] = ' . var_export($metodo, true) . '; ' .
        '$_GET = ' . var_export($get, true) . '; ' .
        '$_POST = ' . var_export($post, true) . '; ' .
        'require ' . var_export($arquivo, true) . ';';
    file_put_contents($tempScript, $code);
    $output = shell_exec("\"$phpBin\" \"$tempScript\"");
    @unlink($tempScript);
    return json_decode((string)$output, true) ?? ['success' => false, 'raw' => $output];
}

echo "============================================================\n";
echo "   BATERIA DE TESTES: MINI-LOJA DE PRODUTOS VIP (GROOMING)  \n";
echo "============================================================\n\n";

$conn = Database::getInstance()->getConnection();
assertTeste($conn instanceof PDO, "Conexão PDO obtida com o banco MySQL via Singleton");

// [1] TESTANDO ESTRUTURA DA TABELA E SEEDERS
echo "\n[1] TESTANDO ESTRUTURA DA TABELA E SEEDERS:\n";
$stTab = $conn->query("SHOW TABLES LIKE 'produtos'");
assertTeste($stTab && $stTab->rowCount() > 0, "Tabela 'produtos' existe no MySQL");

$stCols = $conn->query("SHOW COLUMNS FROM `produtos`");
$colunas = $stCols->fetchAll(PDO::FETCH_COLUMN);
assertTeste(in_array('id', $colunas), "Coluna 'id' presente na tabela produtos");
assertTeste(in_array('nome', $colunas), "Coluna 'nome' presente na tabela produtos");
assertTeste(in_array('categoria', $colunas), "Coluna 'categoria' presente na tabela produtos");
assertTeste(in_array('preco', $colunas), "Coluna 'preco' presente na tabela produtos");
assertTeste(in_array('estoque', $colunas), "Coluna 'estoque' presente na tabela produtos");
assertTeste(in_array('destaque', $colunas), "Coluna 'destaque' presente na tabela produtos");

$stCont = $conn->query("SELECT COUNT(*) FROM `produtos` WHERE `ativo` = 1");
$qtdProdutos = (int)$stCont->fetchColumn();
assertTeste($qtdProdutos >= 6, "No mínimo 6 produtos padrão populados no banco ($qtdProdutos encontrados)");

// [2] TESTANDO MODEL PRODUTO
echo "\n[2] TESTANDO MODEL PRODUTO (CRUD & REGRAS):\n";
$produtoModel = new Produto($conn);

$todos = $produtoModel->lerTodos();
assertTeste(is_array($todos) && count($todos) >= 6, "lerTodos() retornou lista completa de produtos");

$categorias = $produtoModel->getCategorias();
assertTeste(in_array('Cabelo', $categorias), "Categoria 'Cabelo' localizada nas categorias disponíveis");
assertTeste(in_array('Barba', $categorias), "Categoria 'Barba' localizada nas categorias disponíveis");

$prodCabelo = $produtoModel->lerTodos('Cabelo');
assertTeste(count($prodCabelo) > 0, "Filtro por categoria 'Cabelo' retornou itens corretamente");

// Cadastro de produto teste
$novoId = $produtoModel->criar([
    'nome' => 'Pomada Teste Automatizado VIP',
    'categoria' => 'Cabelo',
    'preco' => 49.90,
    'estoque' => 10,
    'descricao' => 'Produto criado para bateria de testes unitários.',
    'destaque' => 1
]);
assertTeste($novoId > 0, "criar() cadastrou novo produto com ID gerado ($novoId)");

$prodCarregado = $produtoModel->lerPorId($novoId);
assertTeste($prodCarregado && $prodCarregado['nome'] === 'Pomada Teste Automatizado VIP', "lerPorId() recuperou produto recém-criado");
assertTeste((float)$prodCarregado['preco'] === 49.90, "Preço gravado corretamente (R$ 49,90)");
assertTeste((int)$prodCarregado['estoque'] === 10, "Estoque inicial gravado como 10 unidades");
assertTeste(!empty($prodCarregado['slug']), "Slug amigável gerado automaticamente ({$prodCarregado['slug']})");

// Atualização
$okUpdate = $produtoModel->atualizar($novoId, [
    'preco' => 54.90,
    'destaque' => 0
]);
assertTeste($okUpdate, "atualizar() executou com sucesso");
$prodAtualizado = $produtoModel->lerPorId($novoId);
assertTeste((float)$prodAtualizado['preco'] === 54.90, "Preço atualizado para R$ 54,90");

// [3] TESTANDO GESTÃO DE ESTOQUE (ENTRADAS, SAÍDAS E TRAVAS)
echo "\n[3] TESTANDO GESTÃO DE ESTOQUE (ENTRADAS, SAÍDAS E TRAVAS):\n";
// Entrada de estoque (+5)
$okEntrada = $produtoModel->ajustarEstoque($novoId, 5);
assertTeste($okEntrada, "Entrada de +5 unidades executada");
$pEstoque = $produtoModel->lerPorId($novoId);
assertTeste((int)$pEstoque['estoque'] === 15, "Estoque agora contabiliza 15 unidades");

// Saída de estoque (-3)
$okSaida = $produtoModel->ajustarEstoque($novoId, -3);
assertTeste($okSaida, "Saída / Venda de -3 unidades executada");
$pEstoque2 = $produtoModel->lerPorId($novoId);
assertTeste((int)$pEstoque2['estoque'] === 12, "Estoque agora contabiliza 12 unidades");

// Trava contra estoque negativo (tentativa de tirar 20 de quem tem 12)
$okNegativo = $produtoModel->ajustarEstoque($novoId, -20);
assertTeste(!$okNegativo, "Trava impediu com sucesso ajuste que deixaria estoque negativo");
$pEstoque3 = $produtoModel->lerPorId($novoId);
assertTeste((int)$pEstoque3['estoque'] === 12, "Estoque permaneceu em 12 unidades intacto");

// Estatísticas analíticas de estoque
$stats = $produtoModel->getEstatisticasEstoque();
assertTeste($stats['total_produtos'] >= 6, "Total de produtos ativos contabilizado corretamente");
assertTeste($stats['total_unidades'] > 0, "Total de unidades físicas em estoque apurado (> 0)");
assertTeste($stats['valor_total_estoque'] > 0.0, "Valor financeiro total do estoque calculado em R$");

// [4] TESTANDO API DE PRODUTOS (api/produto.php)
echo "\n[4] TESTANDO API DE PRODUTOS (api/produto.php):\n";
$arquivoApi = realpath(__DIR__ . '/../api/produto.php');

// Simula chamada GET de produtos
$jsonGet = chamarApi($arquivoApi, 'GET');
assertTeste($jsonGet && ($jsonGet['success'] ?? false) === true, "api/produto.php (GET) respondeu com status de sucesso");
assertTeste(isset($jsonGet['dados']) && count($jsonGet['dados']) >= 6, "API retornou catálogo completo de produtos");

// Simula chamada de estatísticas
$jsonStats = chamarApi($arquivoApi, 'GET', ['stats' => '1']);
assertTeste(isset($jsonStats['dados']['total_produtos']), "api/produto.php?stats=1 retornou métricas de estoque");

// Simula chamada de reserva via WhatsApp
$jsonReserva = chamarApi($arquivoApi, 'POST', [], [
    'acao' => 'reservar',
    'produto_id' => $novoId,
    'quantidade' => 2,
    'cliente_nome' => 'Pedro Teste VIP',
    'cliente_telefone' => '(11) 97777-8888'
]);

assertTeste($jsonReserva && ($jsonReserva['success'] ?? false) === true, "api/produto.php (POST reservar) gerou pedido com sucesso");
assertTeste(!empty($jsonReserva['whatsapp_url']), "URL formatada do WhatsApp gerada pela API");
assertTeste(str_contains($jsonReserva['whatsapp_url'], 'Pomada'), "Mensagem do WhatsApp cita o nome do produto");
assertTeste(str_contains($jsonReserva['whatsapp_url'], 'Pedro') || str_contains($jsonReserva['whatsapp_url'], 'Pedro%20Teste%20VIP'), "Mensagem do WhatsApp cita o nome do cliente");

// Tentativa de reserva com quantidade superior ao estoque (rejeição)
$jsonErroQtd = chamarApi($arquivoApi, 'POST', [], [
    'acao' => 'reservar',
    'produto_id' => $novoId,
    'quantidade' => 999
]);
assertTeste(isset($jsonErroQtd['error']) && str_contains($jsonErroQtd['error'], 'Estoque insuficiente'), "API rejeitou reserva quando quantidade excede o estoque");

// Limpeza: remove o produto de teste
$okDelete = $produtoModel->deletar($novoId, false); // Hard delete no teste
assertTeste($okDelete, "Produto temporário de teste removido do banco");

echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: $totalTestes\n";
echo "  Testes aprovados: $testesPassados\n";
echo "  Testes reprovados: $testesFalhados\n\n";

if ($testesFalhados === 0) {
    echo "  >>> SUCESSO ABSOLUTO: Mini-Loja de Produtos 100% Validada! <<<\n";
} else {
    echo "  >>> ATENÇÃO: Houve $testesFalhados falhas nos testes. <<<\n";
}
echo "============================================================\n";
