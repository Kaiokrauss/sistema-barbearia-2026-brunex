<?php
/**
 * BATERIA DE TESTES AUTOMATIZADOS: CONCIERGE VIRTUAL IA 24H (VOZ & TEXTO)
 * Testa:
 * 1. Saudação inicial e geração de chips interativos
 * 2. Consulta de tabela de preços, Clube VIP, Loja e Visagismo
 * 3. Extração NLU em Português (serviço, dia relativo/dia da semana, horário falado, nome e WhatsApp)
 * 4. Fluxo multi-turno (slot-filling) e gravação real de agendamento no MySQL + Strategy/Clube VIP
 * 5. Consulta de selos do Cartão Fidelidade pelo chat
 * 6. Cancelamento de agendamento por código de 6 dígitos acionando Observers
 */

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/ConciergeService.php';

$totalTestes = 0;
$testesPassados = 0;

function assertConcierge($condicao, string $mensagem): void {
    global $totalTestes, $testesPassados;
    $totalTestes++;
    if ($condicao) {
        $testesPassados++;
        echo "  [PASSOU] {$mensagem}\n";
    } else {
        echo "  [FALHOU] {$mensagem}\n";
    }
}

echo "============================================================\n";
echo "   BATERIA DE TESTES: CONCIERGE IA 24H (VOZ & TEXTO)        \n";
echo "============================================================\n\n";

$db = Database::getInstance()->getConnection();
$concierge = new ConciergeService($db);

// [1] Saudação Inicial
echo "[1] TESTANDO SAUDAÇÃO INICIAL E CHIPS:\n";
$r1 = $concierge->processarMensagem('', []);
assertConcierge($r1['success'] === true && $r1['intencao'] === 'saudacao', 'Saudação inicial retornada com sucesso');
assertConcierge(!empty($r1['chips']) && count($r1['chips']) >= 4, 'Chips rápidos do menu principal gerados');
assertConcierge(!empty($r1['resposta_voz']), 'Texto limpo para síntese de voz (TTS) gerado');

// [2] Consultas Informativas (Preços, Clube VIP, Fidelidade)
echo "\n[2] TESTANDO CONSULTAS DE PREÇOS, CLUBE VIP E VISAGISMO:\n";
$rPrecos = $concierge->processarMensagem('Quais são os preços dos serviços?', []);
assertConcierge($rPrecos['intencao'] === 'servicos' && str_contains($rPrecos['resposta'], 'R$'), 'Listou tabela de serviços e preços do MySQL');

$rVip = $concierge->processarMensagem('Como funciona a assinatura do Clube VIP?', []);
assertConcierge($rVip['intencao'] === 'clube_vip', 'Respondeu sobre planos do Clube VIP / Barber Pass');

$rVis = $concierge->processarMensagem('Qual corte combina com rosto redondo no visagismo?', []);
assertConcierge($rVis['intencao'] === 'visagismo' && str_contains(strtolower($rVis['resposta']), 'redondo'), 'Deu consultoria rápida para rosto redondo');

// [3] Fluxo Multi-Turno de Agendamento por Linguagem Natural + Gravação Real no MySQL
echo "\n[3] TESTANDO FLUXO CONVERSACIONAL MULTI-TURNO E AGENDAMENTO REAL:\n";

// Escolhe uma data futura limpa para teste
$dataTeste = date('Y-m-d', strtotime('+5 days'));
if ((int)date('N', strtotime($dataTeste)) === 7) {
    $dataTeste = date('Y-m-d', strtotime('+6 days'));
}
$dataTesteBr = date('d/m/Y', strtotime($dataTeste));

// Limpa eventual agendamento residual de teste nessa data às 16:00
$db->prepare("DELETE FROM agendamentos WHERE data_agendada = :d AND TIME_FORMAT(horario, '%H:%i') = '16:00'")
   ->execute([':d' => $dataTeste]);

// Turno 1: Fala serviço + data
$t1 = $concierge->processarMensagem("Quero cortar degradê no dia {$dataTesteBr}", []);
assertConcierge(!empty($t1['contexto']['servico_id']), "NLU extraiu o serviço ({$t1['contexto']['servico_nome']})");
assertConcierge($t1['contexto']['data_agendada'] === $dataTeste, "NLU extraiu a data ({$dataTeste})");
assertConcierge($t1['contexto']['etapa'] === 'escolher_horario', 'Avançou para etapa de escolher horário');

// Turno 2: Fala horário por extenso (como vem do microfone Web Speech API)
$t2 = $concierge->processarMensagem('quatro da tarde', $t1['contexto']);
assertConcierge($t2['contexto']['horario'] === '16:00', "NLU converteu 'quatro da tarde' para '16:00'");
assertConcierge($t2['contexto']['etapa'] === 'informar_dados', 'Avançou para solicitação de Nome e WhatsApp');

// Turno 3: Informa nome + telefone numa única frase natural e efetiva no banco
$t3 = $concierge->processarMensagem('Meu nome é Teste Concierge VIP e meu telefone (11) 98877-6655', $t2['contexto']);
assertConcierge($t3['intencao'] === 'confirmado', 'Agendamento confirmado no MySQL ao concluir dados');
assertConcierge(!empty($t3['card_confirmacao']['codigo']), "Código de 6 dígitos gerado ({$t3['card_confirmacao']['codigo']})");

$codigoCriado = $t3['card_confirmacao']['codigo'] ?? '';

// [4] Consulta de Fidelidade
echo "\n[4] TESTANDO CONSULTA DE CARTÃO FIDELIDADE PELO CHAT:\n";
$rFid = $concierge->processarMensagem('Quantos selos eu tenho no (11) 98877-6655?', []);
assertConcierge($rFid['intencao'] === 'fidelidade' && str_contains($rFid['resposta'], 'selos'), 'Consultou progresso real de selos no MySQL');

// [5] Cancelamento pelo Chat via Código
echo "\n[5] TESTANDO CANCELAMENTO PELO CHAT E LIMPEZA:\n";
$rCanc = $concierge->processarMensagem("Cancelar horário {$codigoCriado}", []);
assertConcierge($rCanc['intencao'] === 'cancelado_sucesso', "Cancelou agendamento {$codigoCriado} pelo chat com sucesso");

// Limpa registro de teste
$db->prepare("DELETE FROM agendamentos WHERE codigo = :c")->execute([':c' => $codigoCriado]);

echo "\n============================================================\n";
echo "RESULTADO FINAL: {$testesPassados}/{$totalTestes} TESTES PASSARAM!\n";
echo "============================================================\n";
exit($testesPassados === $totalTestes ? 0 : 1);
