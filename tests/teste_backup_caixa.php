<?php
/**
 * Testes Unitários e de Integração: BackupService & CaixaService
 */
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/BackupService.php';
require_once __DIR__ . '/../Models/CaixaService.php';

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
echo "    BATERIA DE TESTES: BACKUPSERVICE & CAIXASERVICE        \n";
echo "============================================================\n\n";

$db = Database::getInstance()->getConnection();

// ------------------------------------------------------------
// [1] TESTANDO BACKUPSERVICE
// ------------------------------------------------------------
echo "[1] TESTANDO O SERVIÇO DE BACKUP (BackupService):\n";

$backup = new BackupService($db);
assertTeste($backup instanceof BackupService, "Instância de BackupService criada com sucesso.");

$stats = $backup->getEstatisticas();
assertTeste(isset($stats['total_tabelas']) && $stats['total_tabelas'] >= 4, "getEstatisticas() detectou as 4 tabelas do sistema.");
assertTeste(isset($stats['total_registros']), "getEstatisticas() contabilizou total de registros.");

$sql = $backup->gerarBackupSql();
assertTeste(is_string($sql) && strlen($sql) > 500, "gerarBackupSql() retornou dump SQL consistente e não vazio.");
assertTeste(str_contains($sql, 'Barbearia VIP - Backup do Banco de Dados'), "Dump contém cabeçalho de metadados da Barbearia VIP.");
assertTeste(str_contains($sql, 'CREATE TABLE `servicos`'), "Dump contém instrução CREATE TABLE para tabela `servicos`.");
assertTeste(str_contains($sql, 'CREATE TABLE `agendamentos`'), "Dump contém instrução CREATE TABLE para tabela `agendamentos`.");
assertTeste(str_contains($sql, 'SET FOREIGN_KEY_CHECKS'), "Dump contém proteção de integridade relacional FOREIGN_KEY_CHECKS.");

// ------------------------------------------------------------
// [2] TESTANDO CAIXASERVICE COM SIMULAÇÃO MATEMÁTICA
// ------------------------------------------------------------
echo "\n[2] TESTANDO O SERVIÇO DE FECHAMENTO DE CAIXA (CaixaService):\n";

$caixa = new CaixaService($db);
assertTeste($caixa instanceof CaixaService, "Instância de CaixaService criada com sucesso.");

$hoje = date('Y-m-d');
$fechamentoHoje = $caixa->fecharCaixa($hoje, 50.0);
assertTeste(isset($fechamentoHoje['resumo_financeiro']), "fecharCaixa() gerou bloco 'resumo_financeiro'.");
assertTeste(isset($fechamentoHoje['resumo_operacional']), "fecharCaixa() gerou bloco 'resumo_operacional'.");
assertTeste(isset($fechamentoHoje['breakdown_servicos']), "fecharCaixa() gerou bloco 'breakdown_servicos'.");
assertTeste(isset($fechamentoHoje['atendimentos']), "fecharCaixa() gerou bloco 'atendimentos'.");

// Inserir agendamento temporário para teste de apuração matemática
$codigoTemp = 'TC' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
$db->exec("DELETE FROM agendamentos WHERE codigo = '{$codigoTemp}'");
$stServico = $db->query("SELECT id, preco FROM servicos LIMIT 1");
$servico = $stServico->fetch(PDO::FETCH_ASSOC);

if ($servico) {
    $servicoId = $servico['id'];
    $precoServico = (float)$servico['preco'];

    $sqlInsert = "INSERT INTO agendamentos (cliente_nome, cliente_telefone, servico_id, data_agendada, horario, status, codigo) 
                  VALUES ('Cliente Teste Caixa', '(11) 99999-8888', :servico_id, :data, '12:00:00', 'ativo', :codigo)";
    $stIns = $db->prepare($sqlInsert);
    $stIns->execute([':servico_id' => $servicoId, ':data' => $hoje, ':codigo' => $codigoTemp]);

    try {
        // Testar com 50% de comissão
        $fechamentoComAgend = $caixa->fecharCaixa($hoje, 50.0);
        $bruto = $fechamentoComAgend['resumo_financeiro']['faturamento_bruto'];
        $comissao50 = $fechamentoComAgend['resumo_financeiro']['comissao_barbeiros'];
        $lucro50 = $fechamentoComAgend['resumo_financeiro']['lucro_liquido_barbearia'];

        assertTeste($bruto >= $precoServico, "Faturamento bruto incorporou o atendimento teste ({$precoServico}).");
        assertTeste(abs(($comissao50 + $lucro50) - $bruto) < 0.01, "Equilíbrio financeiro exato: Comissão + Lucro == Faturamento Bruto.");
        assertTeste(abs($comissao50 - $lucro50) < 0.01, "Comissão de 50% dividiu receita igualmente entre barbeiro e casa.");

        // Testar com 60% de comissão
        $fechamento60 = $caixa->fecharCaixa($hoje, 60.0);
        $comissao60 = $fechamento60['resumo_financeiro']['comissao_barbeiros'];
        $esperado60 = round($bruto * 0.6, 2);
        assertTeste(abs($comissao60 - $esperado60) < 0.01, "Comissão dinâmica de 60% calculada com exatidão.");

        // Testar histórico de 7 dias
        $historico = $caixa->getHistoricoResumido(7);
        assertTeste(count($historico) === 7, "getHistoricoResumido(7) retornou exatamente 7 dias de comparativo.");

        // ------------------------------------------------------------
        // [3] TESTANDO RATEIO E FILTRO DE COMISSÕES POR BARBEIRO
        // ------------------------------------------------------------
        echo "\n[3] TESTANDO RATEIO E FILTRO DE COMISSÕES POR BARBEIRO:\n";

        $barbeiros = $caixa->getBarbeiros();
        assertTeste(is_array($barbeiros) && count($barbeiros) >= 1, "getBarbeiros() retornou lista ativa de profissionais cadastrados.");

        $b1 = $barbeiros[0];
        $b1Id = (int)$b1['id'];
        $b1Nome = $b1['nome'];

        assertTeste(!empty($b1Nome), "Primeiro barbeiro possui nome válido ({$b1Nome}).");

        // Inserir agendamento atribuído especificamente ao barbeiro 1
        $codigoB1 = 'TB' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stInsB = $db->prepare("INSERT INTO agendamentos (cliente_nome, cliente_telefone, servico_id, barbeiro_id, data_agendada, horario, status, codigo) 
                               VALUES ('Cliente Barbeiro Teste', '(11) 98888-0000', :servico_id, :barbeiro_id, :data, '15:00:00', 'ativo', :codigo)");
        $stInsB->execute([
            ':servico_id' => $servicoId,
            ':barbeiro_id' => $b1Id,
            ':data' => $hoje,
            ':codigo' => $codigoB1
        ]);

        try {
            // 1. Fechamento filtrado pelo barbeiro 1
            $fechamentoB1 = $caixa->fecharCaixa($hoje, 50.0, $b1Id);
            assertTeste(isset($fechamentoB1['barbeiro_selecionado']), "fecharCaixa() incluiu metadados 'barbeiro_selecionado'.");
            assertTeste($fechamentoB1['barbeiro_selecionado']['id'] == $b1Id, "barbeiro_selecionado corresponde ao ID filtrado.");
            assertTeste(count($fechamentoB1['atendimentos']) >= 1, "Atendimento do barbeiro foi filtrado corretamente.");

            // 2. Fechamento geral (sem filtro de barbeiro)
            $fechamentoGeral = $caixa->fecharCaixa($hoje, 50.0, null);
            assertTeste($fechamentoGeral['barbeiro_selecionado'] === null, "fecharCaixa() geral possui 'barbeiro_selecionado' como null.");
            assertTeste(isset($fechamentoGeral['breakdown_barbeiros']), "fecharCaixa() geral gerou bloco 'breakdown_barbeiros'.");

            // Verificar se o barbeiro 1 aparece no rateio de comissões consolidado
            $encontrouB1NoRateio = false;
            foreach ($fechamentoGeral['breakdown_barbeiros'] as $bb) {
                if ($bb['barbeiro_id'] == $b1Id) {
                    $encontrouB1NoRateio = true;
                    assertTeste($bb['quantidade'] >= 1, "Rateio consolidado registrou quantidade de atendimentos do barbeiro.");
                    assertTeste($bb['total_comissao'] > 0, "Rateio consolidado calculou comissão a pagar para o barbeiro.");
                    break;
                }
            }
            assertTeste($encontrouB1NoRateio, "Barbeiro avaliado está presente no breakdown_barbeiros.");

        } finally {
            $db->exec("DELETE FROM agendamentos WHERE codigo = '{$codigoB1}'");
        }

    } finally {
        // Limpar o agendamento temporário
        $db->exec("DELETE FROM agendamentos WHERE codigo = '{$codigoTemp}'");
    }
} else {
    echo "  [AVISO] Nenhum serviço cadastrado para teste de inserção.\n";
}

echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: {$totalTestes}\n";
echo "  Testes aprovados: {$aprovados}\n";
echo "  Testes reprovados: {$reprovados}\n";

if ($reprovados === 0) {
    echo "\n  >>> SUCESSO ABSOLUTO: Todas as validações passaram! <<<\n";
} else {
    echo "\n  >>> ATENÇÃO: Verifique os erros acima! <<<\n";
}
echo "============================================================\n\n";