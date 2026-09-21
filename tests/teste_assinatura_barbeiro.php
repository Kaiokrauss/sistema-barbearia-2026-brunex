<?php
/**
 * Testes Automatizados: Clube de Assinatura VIP, Seleção de Barbeiro e Link da Bio do Instagram
 */
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/DescontoStrategy.php';
require_once __DIR__ . '/../Models/CalculadoraPreco.php';

$totalTestes = 0;
$aprovados = 0;
$reprovados = 0;

function assertTeste($condicao, $titulo) {
    global $totalTestes, $aprovados, $reprovados;
    $totalTestes++;
    if ($condicao) {
        echo "  [PASSOU] {$titulo}\n";
        $aprovados++;
    } else {
        echo "  [FALHOU] {$titulo}\n";
        $reprovados++;
    }
}

echo "============================================================\n";
echo "   BATERIA DE TESTES: ASSINATURA VIP & BARBEIROS (BIO LINK) \n";
echo "============================================================\n\n";

$db = Database::getInstance()->getConnection();

// ------------------------------------------------------------
// [1] TESTANDO ESTRUTURA DO BANCO DE DADOS E SEEDERS
// ------------------------------------------------------------
echo "[1] TESTANDO ESTRUTURA DAS TABELAS E SEEDERS:\n";

$stmtPlanos = $db->query("SELECT * FROM planos_assinatura ORDER BY preco_mensal ASC");
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);
assertTeste(count($planos) >= 3, "Tabela planos_assinatura contém no mínimo 3 planos cadastrados.");

$slugsPlanos = array_column($planos, 'slug');
assertTeste(in_array('silver', $slugsPlanos), "Plano Silver Pass ('silver') está presente no banco.");
assertTeste(in_array('gold', $slugsPlanos), "Plano Gold Pass ('gold') está presente no banco.");
assertTeste(in_array('black', $slugsPlanos), "Plano Black Diamond ('black') está presente no banco.");

$stmtBarbeiros = $db->query("SELECT * FROM usuarios WHERE perfil = 'barbeiro' AND ativo = 1");
$barbeiros = $stmtBarbeiros->fetchAll(PDO::FETCH_ASSOC);
assertTeste(count($barbeiros) >= 3, "Tabela usuarios contém no mínimo 3 barbeiros ativos com especialidades.");

$primeiroBarbeiro = $barbeiros[0];
assertTeste(!empty($primeiroBarbeiro['especialidade']), "Barbeiro possui campo 'especialidade' preenchido.");
assertTeste(!empty($primeiroBarbeiro['slug']), "Barbeiro possui campo 'slug' preenchido para URL amigável.");
assertTeste(!empty($primeiroBarbeiro['avatar']), "Barbeiro possui campo 'avatar' configurado para exibição.");

// ------------------------------------------------------------
// [2] TESTANDO GOF STRATEGY PATTERN (AssinaturaVipStrategy)
// ------------------------------------------------------------
echo "\n[2] TESTANDO STRATEGY PATTERN (AssinaturaVipStrategy & CalculadoraPreco):\n";

$stratAssinatura = new AssinaturaVipStrategy('VIP Gold Pass');
assertTeste($stratAssinatura instanceof DescontoStrategy, "AssinaturaVipStrategy implementa a interface DescontoStrategy.");
assertTeste($stratAssinatura->getTipo() === 'ASSINATURA_VIP', "AssinaturaVipStrategy possui tipo 'ASSINATURA_VIP'.");

$descCorte = $stratAssinatura->calcularDesconto(50.0);
assertTeste($descCorte === 50.0, "AssinaturaVipStrategy concedeu 100% de desconto em corte de R$ 50,00.");

$descCombo = $stratAssinatura->calcularDesconto(130.0);
assertTeste($descCombo === 130.0, "AssinaturaVipStrategy concedeu 100% de desconto em combo de R$ 130,00.");

$descricao = $stratAssinatura->getDescricao();
assertTeste(str_contains($descricao, '100% OFF'), "getDescricao() contém menção expressa de '100% OFF'.");

// Testando Contexto CalculadoraPreco com AssinaturaVipStrategy
$calcVip = new CalculadoraPreco($stratAssinatura);
$resultadoCalc = $calcVip->calcular(90.0);
assertTeste($resultadoCalc['valor_final'] === 0.0, "CalculadoraPreco com AssinaturaVipStrategy gerou valor final zerado (R$ 0,00).");
assertTeste($resultadoCalc['desconto_aplicado'] === 90.0, "CalculadoraPreco registrou desconto integral de R$ 90,00.");
assertTeste($resultadoCalc['teve_desconto'] === true, "CalculadoraPreco marcou flag teve_desconto = true.");

// Testando Reconhecimento de Vouchers VIP pelo CupomDescontoStrategy
$stratCupom = new CupomDescontoStrategy();
$descVipClub = $stratCupom->calcularDesconto(75.0, '2026-09-21', 'VIPCLUB-a1b2c3');
assertTeste($descVipClub === 75.0, "CupomDescontoStrategy reconheceu voucher 'VIPCLUB-a1b2c3' como assinante e concedeu 100% de desconto.");

$descAssinante = $stratCupom->calcularDesconto(60.0, '2026-09-21', 'ASSINANTE-9876');
assertTeste($descAssinante === 60.0, "CupomDescontoStrategy reconheceu voucher 'ASSINANTE-9876' com 100% de desconto.");

$descAssinanteVip = $stratCupom->calcularDesconto(110.0, '2026-09-21', 'ASSINANTEVIP');
assertTeste($descAssinanteVip === 110.0, "CupomDescontoStrategy reconheceu código direto 'ASSINANTEVIP' com 100% de desconto.");

// Testando Factory Helper de Seleção Automática
$calcAuto = CalculadoraPreco::criarMelhorEstrategia('VIPCLUB-445566', '2026-09-21', 80.0);
$resAuto = $calcAuto->calcular(80.0, '2026-09-21', 'VIPCLUB-445566');
assertTeste($resAuto['valor_final'] === 0.0, "CalculadoraPreco::criarMelhorEstrategia identificou assinante VIP e gerou agendamento 100% gratuito.");

// ------------------------------------------------------------
// [3] TESTANDO PERSISTÊNCIA E API DE ASSINATURA VIP (api/assinatura.php)
// ------------------------------------------------------------
echo "\n[3] TESTANDO PERSISTÊNCIA E CONSULTA DE ASSINATURAS VIP:\n";

// Inserir assinante temporário para validação
$telTeste = '(11) 98888-7777';
$telLimpo = '11988887777';
$db->exec("DELETE FROM assinantes_vip WHERE cliente_telefone = '{$telTeste}' OR cliente_telefone = '{$telLimpo}'");

$planoTesteId = (int)$planos[0]['id'];
$hoje = date('Y-m-d');
$renovacao = date('Y-m-d', strtotime('+30 days'));

$stmtIns = $db->prepare("INSERT INTO assinantes_vip (cliente_nome, cliente_telefone, cliente_email, plano_id, status, data_inicio, data_renovacao) 
                        VALUES ('Cliente Teste VIP', :tel, 'vip@teste.com', :plano, 'ativo', :inicio, :renovacao)");
$stmtIns->execute([
    ':tel' => $telTeste,
    ':plano' => $planoTesteId,
    ':inicio' => $hoje,
    ':renovacao' => $renovacao
]);
$novoAssId = (int)$db->lastInsertId();
assertTeste($novoAssId > 0, "Assinante de teste inserido com sucesso na tabela assinantes_vip.");

// Consulta por telefone simulando rota da API
$checkStmt = $db->prepare("SELECT a.*, p.nome AS plano_nome, p.slug AS plano_slug, p.preco_mensal, p.beneficios, p.cor_badge 
                           FROM assinantes_vip a 
                           INNER JOIN planos_assinatura p ON a.plano_id = p.id 
                           WHERE (a.cliente_telefone = :tel OR REPLACE(REPLACE(REPLACE(REPLACE(a.cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') = :tel_digitos)
                           AND a.status = 'ativo' 
                           AND a.data_renovacao >= CURDATE()");
$checkStmt->execute([':tel' => $telTeste, ':tel_digitos' => $telLimpo]);
$resAss = $checkStmt->fetch(PDO::FETCH_ASSOC);

assertTeste(!empty($resAss), "Consulta em tempo real por telefone localizou assinante ativo.");
assertTeste($resAss['cliente_nome'] === 'Cliente Teste VIP', "Nome do assinante recuperado corretamente.");
assertTeste(!empty($resAss['plano_nome']), "Nome do plano associado recuperado com sucesso.");
assertTeste(strtotime($resAss['data_renovacao']) >= strtotime($hoje), "Data de renovação está no futuro (vigência válida).");

// Teste de telefone não assinante
$checkInexistente = $db->prepare("SELECT id FROM assinantes_vip WHERE cliente_telefone = '99999999999' AND status = 'ativo'");
$checkInexistente->execute();
assertTeste($checkInexistente->fetch() === false, "Telefone não assinante não retorna registro ativo.");

// Teste de alteração de status
$db->exec("UPDATE assinantes_vip SET status = 'suspenso' WHERE id = {$novoAssId}");
$stmtStatus = $db->query("SELECT status FROM assinantes_vip WHERE id = {$novoAssId}");
assertTeste($stmtStatus->fetchColumn() === 'suspenso', "Status do assinante atualizado para 'suspenso' com sucesso.");

$db->exec("UPDATE assinantes_vip SET status = 'ativo' WHERE id = {$novoAssId}");
$stmtStatusAtivo = $db->query("SELECT status FROM assinantes_vip WHERE id = {$novoAssId}");
assertTeste($stmtStatusAtivo->fetchColumn() === 'ativo', "Status do assinante reativado para 'ativo' com sucesso.");

// Limpar assinante de teste
$db->exec("DELETE FROM assinantes_vip WHERE id = {$novoAssId}");

// ------------------------------------------------------------
// [4] TESTANDO API DE BARBEIROS E LINKS PARA BIO DO INSTAGRAM
// ------------------------------------------------------------
echo "\n[4] TESTANDO API DE BARBEIROS E LINKS PARA BIO DO INSTAGRAM:\n";

$carlos = null;
foreach ($barbeiros as $b) {
    if (str_contains(strtolower($b['nome']), 'carlos')) {
        $carlos = $b;
        break;
    }
}
if (!$carlos) $carlos = $barbeiros[0];

assertTeste(!empty($carlos), "Barbeiro específico localizado para teste de link da bio.");

$busca = $carlos['slug'] ?: $carlos['id'];
$stmtBio = $db->prepare("SELECT id, nome, email, telefone, especialidade, slug, avatar FROM usuarios WHERE perfil = 'barbeiro' AND (slug = :busca OR id = :busca_id) LIMIT 1");
$stmtBio->execute([':busca' => $busca, ':busca_id' => is_numeric($busca) ? (int)$busca : 0]);
$barbeiroBio = $stmtBio->fetch(PDO::FETCH_ASSOC);

assertTeste(!empty($barbeiroBio), "Barbeiro localizado via slug/ID na rota da API.");
$linkGerado = "http://localhost/sistema-barbearia-2026-brunex/Frontend/index.html?barbeiro=" . ($barbeiroBio['slug'] ?: $barbeiroBio['id']);
assertTeste(str_contains($linkGerado, '?barbeiro='), "Link gerado contém o parâmetro '?barbeiro=' para pré-seleção no frontend.");
assertTeste(str_contains($linkGerado, $busca), "Link gerado contém a referência correta do barbeiro ({$busca}).");

echo "\n============================================================\n";
echo "                   RESUMO DOS TESTES                        \n";
echo "============================================================\n";
echo "Total de Testes Executados: {$totalTestes}\n";
echo "Aprovados: {$aprovados}\n";
echo "Reprovados: {$reprovados}\n";

if ($reprovados === 0) {
    echo "\n>>> SUCESSO TOTAL! Todos os testes passaram com 100% de integridade. <<<\n";
    exit(0);
} else {
    echo "\n>>> FALHA! Alguns testes reprovaram. Verifique os erros acima. <<<\n";
    exit(1);
}
