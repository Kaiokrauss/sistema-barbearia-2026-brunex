<?php
/**
 * Suíte de Testes Automatizados: Cartão Fidelidade VIP & Sistema de Avaliações com Estrelas
 */

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/DescontoStrategy.php';
require_once __DIR__ . '/../Models/CalculadoraPreco.php';

$totalTestes = 0;
$aprovados = 0;
$reprovados = 0;

function testar(string $descricao, bool $condicao) {
    global $totalTestes, $aprovados, $reprovados;
    $totalTestes++;
    if ($condicao) {
        $aprovados++;
        echo "  [PASSOU] $descricao\n";
    } else {
        $reprovados++;
        echo "  [FALHOU] $descricao\n";
    }
}

function chamarApi(string $arquivo, string $metodo = 'GET', array $get = [], array $post = []): array {
    $phpBin = 'C:\xampp\php\php.exe';
    $tempScript = sys_get_temp_dir() . '/req_' . uniqid() . '.php';
    $code = '<?php ' .
        '$_SERVER["REQUEST_METHOD"] = ' . var_export($metodo, true) . '; ' .
        '$_GET = ' . var_export($get, true) . '; ' .
        '$_POST = ' . var_export($post, true) . '; ' .
        'require ' . var_export($arquivo, true) . ';';
    file_put_contents($tempScript, $code);
    $output = shell_exec("\"$phpBin\" \"$tempScript\"");
    @unlink($tempScript);
    return json_decode((string)$output, true) ?? ['sucesso' => false, 'raw' => $output];
}

echo "\n============================================================\n";
echo "   BATERIA DE TESTES: CARTÃO FIDELIDADE & AVALIAÇÕES VIP     \n";
echo "============================================================\n\n";

$db = Database::getInstance()->getConnection();
testar("Conexão PDO obtida com o banco MySQL via Singleton", $db instanceof PDO);

// -------------------------------------------------------------
// 1. ESTRUTURA DO BANCO DE DADOS
// -------------------------------------------------------------
echo "\n[1] TESTANDO ESTRUTURA DAS TABELAS:\n";
$stAv = $db->query("SHOW TABLES LIKE 'avaliacoes'");
testar("Tabela 'avaliacoes' existe no MySQL", $stAv->rowCount() > 0);

$stRes = $db->query("SHOW TABLES LIKE 'resgates_fidelidade'");
testar("Tabela 'resgates_fidelidade' existe no MySQL", $stRes->rowCount() > 0);

$stCountAv = $db->query("SELECT COUNT(*) FROM avaliacoes");
testar("Tabela 'avaliacoes' contém registros populados", (int)$stCountAv->fetchColumn() >= 1);

// -------------------------------------------------------------
// 2. TESTANDO API DE AVALIAÇÕES (api/avaliacao.php)
// -------------------------------------------------------------
echo "\n[2] TESTANDO API DE AVALIAÇÕES (api/avaliacao.php):\n";

$arquivoAv = realpath(__DIR__ . '/../api/avaliacao.php');

// GET de listagem e estatísticas
$jsonGetAv = chamarApi($arquivoAv, 'GET');

testar("api/avaliacao.php (GET) retornou status de sucesso", ($jsonGetAv['sucesso'] ?? false) === true);
testar("Estatísticas contêm 'media_geral' numérica", isset($jsonGetAv['estatisticas']['media_geral']) && is_numeric($jsonGetAv['estatisticas']['media_geral']));
testar("Média geral está entre 1.0 e 5.0", $jsonGetAv['estatisticas']['media_geral'] >= 1.0 && $jsonGetAv['estatisticas']['media_geral'] <= 5.0);
testar("Estatísticas contêm 'porcentagem_recomendacao'", isset($jsonGetAv['estatisticas']['porcentagem_recomendacao']));
testar("Lista de avaliações retornada não está vazia", !empty($jsonGetAv['avaliacoes']));

// POST: Inserir nova avaliação de teste
$dadosNovaAv = [
    'cliente_nome' => 'Cliente Teste Automatizado',
    'cliente_telefone' => '(11) 98888-7777',
    'servico_nome' => 'Corte Degradê Clássico',
    'nota' => 5,
    'comentario' => 'Depoimento de teste com nota máxima e excelência no atendimento VIP.'
];

$jsonPostAv = chamarApi($arquivoAv, 'POST', [], $dadosNovaAv);

testar("api/avaliacao.php (POST) cadastrou avaliação com sucesso", ($jsonPostAv['sucesso'] ?? false) === true);
$idAvCriada = (int)($jsonPostAv['avaliacao']['id'] ?? 0);
testar("ID da nova avaliação gerado corretamente", $idAvCriada > 0);

// POST: Rejeitar nota inválida (ex: nota 9)
$jsonInvalido = chamarApi($arquivoAv, 'POST', [], [
    'cliente_nome' => 'Teste Invalido',
    'nota' => 9,
    'comentario' => 'Nota impossível'
]);
testar("API rejeitou nota fora do intervalo [1..5]", ($jsonInvalido['sucesso'] ?? true) === false);

// DELETE: Excluir a avaliação de teste criada
$jsonDelAv = chamarApi($arquivoAv, 'POST', [], ['acao' => 'excluir', 'id' => $idAvCriada]);
testar("Exclusão de avaliação pelo ID realizada com sucesso", ($jsonDelAv['sucesso'] ?? false) === true);

// -------------------------------------------------------------
// 3. TESTANDO CARTÃO FIDELIDADE & RESGATE (api/fidelidade.php)
// -------------------------------------------------------------
echo "\n[3] TESTANDO API DE CARTÃO FIDELIDADE (api/fidelidade.php):\n";

$arquivoFid = realpath(__DIR__ . '/../api/fidelidade.php');
$telTeste = '(11) 94444-3333';

// Limpa dados de testes prévios para este número
$db->exec("DELETE FROM agendamentos WHERE cliente_telefone LIKE '%944443333%' OR cliente_telefone = '$telTeste'");
$db->exec("DELETE FROM resgates_fidelidade WHERE cliente_telefone LIKE '%944443333%' OR cliente_telefone = '$telTeste'");

// Consulta sem agendamentos
$jsonFidVazio = chamarApi($arquivoFid, 'GET', ['telefone' => $telTeste]);

testar("Consulta de fidelidade inicial retorna 0 cortes", ($jsonFidVazio['cliente']['total_cortes'] ?? -1) === 0);
testar("Cliente sem cortes não pode resgatar", ($jsonFidVazio['cliente']['pode_resgatar'] ?? true) === false);
testar("Faltam exatamente 5 cortes para o benefício", ($jsonFidVazio['cliente']['cortes_restantes'] ?? -1) === 5);

// Insere 5 cortes no banco para o cliente teste
$stInsAg = $db->prepare("
    INSERT INTO agendamentos (cliente_nome, cliente_telefone, servico_id, data_agendada, horario, status, codigo)
    VALUES ('Carlos Fidelidade Teste', :tel, 1, DATE_SUB(CURDATE(), INTERVAL :dias DAY), '10:00', 'concluido', :cod)
");

for ($i = 1; $i <= 5; $i++) {
    $cod = 'FID' . $i . rand(10, 99);
    $stInsAg->execute([':tel' => $telTeste, ':dias' => $i, ':cod' => $cod]);
}

// Re-consulta com 5 agendamentos realizados
$jsonFid5 = chamarApi($arquivoFid, 'GET', ['telefone' => $telTeste]);

testar("Total de cortes agora contabiliza 5", ($jsonFid5['cliente']['total_cortes'] ?? 0) === 5);
testar("Ciclos concluídos é 1", ($jsonFid5['cliente']['ciclos_concluidos'] ?? 0) === 1);
testar("Cliente com 5 cortes PODE resgatar corte grátis", ($jsonFid5['cliente']['pode_resgatar'] ?? false) === true);
testar("Progresso percentual atingiu 100%", ($jsonFid5['cliente']['progresso_percentual'] ?? 0) === 100);
testar("Nível VIP foi promovido para Prata Premium", ($jsonFid5['cliente']['nivel_vip'] ?? '') === 'Prata Premium');

// Testando ranking de fidelidade
$jsonRank = chamarApi($arquivoFid, 'GET', ['ranking' => '1']);
testar("api/fidelidade.php?ranking=1 retorna lista de clientes fiéis", !empty($jsonRank['ranking']));

// POST: Realizar resgate do corte grátis
$jsonResgate = chamarApi($arquivoFid, 'POST', [], [
    'acao' => 'resgatar',
    'telefone' => $telTeste,
    'nome' => 'Carlos Fidelidade Teste'
]);

testar("Resgate do corte grátis efetuado com sucesso", ($jsonResgate['sucesso'] ?? false) === true);
$voucherCodigo = $jsonResgate['voucher']['codigo'] ?? '';
testar("Voucher gerado possui prefixo 'GRATIS-'", str_starts_with($voucherCodigo, 'GRATIS-'));

// -------------------------------------------------------------
// 4. INTEGRAÇÃO COM A CALCULADORA DE PREÇOS (100% OFF)
// -------------------------------------------------------------
echo "\n[4] TESTANDO APLICAÇÃO DO VOUCHER FIDELIDADE NO MOTOR DE PREÇOS:\n";
$calc = CalculadoraPreco::criarMelhorEstrategia($voucherCodigo, date('Y-m-d'), 50.0);
$resCalculo = $calc->calcular(50.0, date('Y-m-d'), $voucherCodigo);

testar("Voucher gerado pela fidelidade é aceito pela CalculadoraPreco", $resCalculo['teve_desconto'] === true);
testar("Desconto aplicado é de 100% (R$ 50,00 de desconto)", (float)$resCalculo['desconto_aplicado'] === 50.0);
testar("Valor final a pagar pelo corte sai R$ 0,00", (float)$resCalculo['valor_final'] === 0.0);

// Limpeza final de dados de teste
$db->exec("DELETE FROM agendamentos WHERE cliente_telefone LIKE '%944443333%' OR cliente_telefone = '$telTeste'");
$db->exec("DELETE FROM resgates_fidelidade WHERE cliente_telefone LIKE '%944443333%' OR cliente_telefone = '$telTeste'");

echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: $totalTestes\n";
echo "  Testes aprovados: $aprovados\n";
echo "  Testes reprovados: $reprovados\n\n";

if ($reprovados === 0) {
    echo "  >>> SUCESSO ABSOLUTO: Fidelidade e Avaliações 100% Validados! <<<\n";
} else {
    echo "  >>> ALERTA: Alguns testes reprovaram. <<<\n";
}
echo "============================================================\n\n";

exit($reprovados === 0 ? 0 : 1);

