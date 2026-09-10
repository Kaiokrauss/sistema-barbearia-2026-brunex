<?php
/**
 * Testes Unitários e de Integração: Padrão GoF Strategy & Voucher VIP
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
echo "       BATERIA DE TESTES: PADRÃO GOF STRATEGY & VOUCHER     \n";
echo "============================================================\n\n";

// ------------------------------------------------------------
// [1] TESTANDO O PADRÃO DE PROJETO STRATEGY
// ------------------------------------------------------------
echo "[1] TESTANDO AS ESTRATÉGIAS CONCRETAS DE DESCONTO (GoF Strategy):\n";

// 1.1 SemDescontoStrategy
$semDesconto = new SemDescontoStrategy();
assertTeste($semDesconto instanceof DescontoStrategy, "SemDescontoStrategy implementa DescontoStrategy.");
assertTeste($semDesconto->calcularDesconto(50.0) === 0.0, "SemDescontoStrategy retorna 0.0 de desconto.");
assertTeste($semDesconto->getTipo() === 'SEM_DESCONTO', "Tipo retornado é 'SEM_DESCONTO'.");

// 1.2 CupomDescontoStrategy
$stratCupom = new CupomDescontoStrategy();
assertTeste($stratCupom instanceof DescontoStrategy, "CupomDescontoStrategy implementa DescontoStrategy.");

// Teste Cupom Fixo VIP10 (R$ 10,00 off)
$descVip10 = $stratCupom->calcularDesconto(45.0, '2026-09-10', 'VIP10');
assertTeste($descVip10 === 10.0, "Cupom VIP10 aplicou R$ 10,00 de desconto em corte de R$ 45,00.");

// Teste Cupom Percentual BEMVINDO15 (15% off)
$descBv15 = $stratCupom->calcularDesconto(100.0, '2026-09-10', 'BEMVINDO15');
assertTeste($descBv15 === 15.0, "Cupom BEMVINDO15 aplicou 15% de desconto em valor de R$ 100,00.");

// Teste Cupom Percentual PRIMEIRA_VEZ (20% off)
$descPv20 = $stratCupom->calcularDesconto(50.0, '2026-09-10', 'PRIMEIRA_VEZ');
assertTeste($descPv20 === 10.0, "Cupom PRIMEIRA_VEZ aplicou 20% (R$ 10,00) em corte de R$ 50,00.");

// Teste Cupom Inválido
$descInvalido = $stratCupom->calcularDesconto(50.0, '2026-09-10', 'CUPOM_FALSO_123');
assertTeste($descInvalido === 0.0, "Cupom inválido não aplica nenhum desconto (retorna 0.0).");

// Teste Case Insensitive (minúsculas)
$descCase = $stratCupom->calcularDesconto(50.0, '2026-09-10', 'vip10');
assertTeste($descCase === 10.0, "Cupom funciona com letras minúsculas (case-insensitive).");

// 1.3 DiaPromocionalStrategy
$stratDia = new DiaPromocionalStrategy(15.0);
assertTeste($stratDia instanceof DescontoStrategy, "DiaPromocionalStrategy implementa DescontoStrategy.");

// Terça-feira (ex: 2026-09-15 é uma terça-feira)
$descTerca = $stratDia->calcularDesconto(100.0, '2026-09-15');
assertTeste($descTerca === 15.0, "DiaPromocionalStrategy aplicou 15% de desconto automático na Terça-feira.");

// Segunda-feira (ex: 2026-09-14 é uma segunda-feira)
$descSegunda = $stratDia->calcularDesconto(100.0, '2026-09-14');
assertTeste($descSegunda === 0.0, "DiaPromocionalStrategy não aplica desconto na Segunda-feira.");

// ------------------------------------------------------------
// [2] TESTANDO O CONTEXTO CALCULADORAPRECO
// ------------------------------------------------------------
echo "\n[2] TESTANDO O CONTEXTO (CalculadoraPreco):\n";

$calc = new CalculadoraPreco($semDesconto);
$resPadrao = $calc->calcular(50.0);
assertTeste($resPadrao['valor_final'] === 50.0, "Calculadora com SemDesconto mantém valor final integral (R$ 50,00).");
assertTeste($resPadrao['teve_desconto'] === false, "teve_desconto é false na tarifa padrão.");

// Troca dinâmica de estratégia em tempo de execução (polimorfismo do padrão Strategy)
$calc->setStrategy($stratCupom);
$resComCupom = $calc->calcular(50.0, '2026-09-10', 'VIP10');
assertTeste($resComCupom['valor_final'] === 40.0, "setStrategy trocou algoritmo e valor final passou para R$ 40,00.");
assertTeste($resComCupom['desconto_aplicado'] === 10.0, "desconto_aplicado é exatamente R$ 10,00.");
assertTeste($resComCupom['teve_desconto'] === true, "teve_desconto é true.");

// Teste do helper criarMelhorEstrategia
$calcAutoCupom = CalculadoraPreco::criarMelhorEstrategia('BEMVINDO15', '2026-09-10', 100.0);
assertTeste($calcAutoCupom->getStrategy() instanceof CupomDescontoStrategy, "criarMelhorEstrategia selecionou CupomDescontoStrategy para cupom válido.");

$calcAutoDia = CalculadoraPreco::criarMelhorEstrategia(null, '2026-09-15', 100.0);
assertTeste($calcAutoDia->getStrategy() instanceof DiaPromocionalStrategy, "criarMelhorEstrategia selecionou DiaPromocionalStrategy para Terça-feira sem cupom.");

// ------------------------------------------------------------
// [3] TESTANDO O VOUCHER VIP COM QR CODE E ICALENDAR
// ------------------------------------------------------------
echo "\n[3] TESTANDO A EMISSÃO DO VOUCHER VIP (comprovante.php):\n";

$db = Database::getInstance()->getConnection();
$codigoTeste = 'ST' . rand(1000, 9999);

$db->exec("INSERT INTO agendamentos (cliente_nome, cliente_telefone, servico_id, data_agendada, horario, status, codigo) 
           VALUES ('Cliente Strategy VIP', '(11) 97777-6666', 1, '2026-09-15', '16:00:00', 'ativo', '{$codigoTeste}')");

try {
    $voucherHtml = file_get_contents("http://localhost/sistema-barbearia-2026-brunex/api/comprovante.php?codigo={$codigoTeste}");
    assertTeste(!empty($voucherHtml), "Endpoint api/comprovante.php retornou resposta não vazia.");
    assertTeste(str_contains($voucherHtml, 'Cliente Strategy VIP'), "Voucher contém nome correto do cliente.");
    assertTeste(str_contains($voucherHtml, 'BORCELLE EXCLUSIVE'), "Voucher contém identificação da barbearia.");
    assertTeste(str_contains($voucherHtml, 'api.qrserver.com'), "Voucher renderiza a imagem do QR Code dinâmico.");
    assertTeste(str_contains($voucherHtml, $codigoTeste), "Voucher exibe o código de segurança único.");

    $ics = file_get_contents("http://localhost/sistema-barbearia-2026-brunex/api/comprovante.php?codigo={$codigoTeste}&ics=1");
    assertTeste(str_contains($ics, 'BEGIN:VCALENDAR') && str_contains($ics, 'DTSTART:20260915T160000'), "Download de iCalendar (.ics) gerou evento com data e horário corretos.");

} finally {
    $db->exec("DELETE FROM agendamentos WHERE codigo = '{$codigoTeste}'");
}

echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: {$totalTestes}\n";
echo "  Testes aprovados: {$aprovados}\n";
echo "  Testes reprovados: {$reprovados}\n";

if ($reprovados === 0) {
    echo "\n  >>> SUCESSO ABSOLUTO: Padrão Strategy e Voucher 100% Validados! <<<\n";
} else {
    echo "\n  >>> ATENÇÃO: Verifique as falhas acima! <<<\n";
}
echo "============================================================\n\n";