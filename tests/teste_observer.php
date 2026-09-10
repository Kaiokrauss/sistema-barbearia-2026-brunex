<?php
/**
 * Testes Unitários e de Integração: Padrão GoF Observer (Auditoria & Eventos)
 */
require_once __DIR__ . '/../Models/ObserverInterface.php';
require_once __DIR__ . '/../Models/AuditoriaObserver.php';
require_once __DIR__ . '/../Models/NotificacaoWhatsAppObserver.php';
require_once __DIR__ . '/../Models/EstatisticaObserver.php';

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
echo "       BATERIA DE TESTES: PADRÃO GOF OBSERVER (EVENTOS)     \n";
echo "============================================================\n\n";

// ------------------------------------------------------------
// [1] TESTANDO O SUBJECT (AgendamentoSubject)
// ------------------------------------------------------------
echo "[1] TESTANDO O SUBJECT (AgendamentoSubject):\n";

$subject = new AgendamentoSubject();
assertTeste($subject instanceof AgendamentoSubject, "Instância de AgendamentoSubject criada.");
assertTeste($subject->getTotalObservers() === 0, "Subject inicia com 0 observadores inscritos.");

$obsAuditoria = new AuditoriaObserver();
$obsWhatsApp = new NotificacaoWhatsAppObserver();
$obsStats = new EstatisticaObserver();

$subject->attach($obsAuditoria);
$subject->attach($obsWhatsApp);
$subject->attach($obsStats);
assertTeste($subject->getTotalObservers() === 3, "attach() inscreveu com sucesso 3 observadores.");

// Teste de detach()
$subject->detach($obsStats);
assertTeste($subject->getTotalObservers() === 2, "detach() removeu 1 observador, restando 2.");

// Reinscrever para os testes subsequentes
$subject->attach($obsStats);
assertTeste($subject->getTotalObservers() === 3, "Observador de estatísticas reinscrito com sucesso.");

// ------------------------------------------------------------
// [2] TESTANDO O DISPARO DE EVENTO & NOTIFICAÇÃO (Broadcast)
// ------------------------------------------------------------
echo "\n[2] TESTANDO O DISPARO DE EVENTO E ATUALIZAÇÃO DOS OBSERVERS:\n";

$dadosMock = [
    'cliente_nome' => 'Marcos Teste Observer',
    'cliente_telefone' => '(11) 98888-1111',
    'servico_nome' => 'Corte Degradê Clássico',
    'data_agendada' => '2026-09-20',
    'horario' => '15:00',
    'codigo' => 'OBS999'
];

$subject->dispararEvento('AGENDAMENTO_CRIADO', $dadosMock);
$ultimoEvento = $subject->getUltimoEvento();

assertTeste($ultimoEvento['tipo'] === 'AGENDAMENTO_CRIADO', "Evento 'AGENDAMENTO_CRIADO' gravado no estado do Subject.");
assertTeste(isset($ultimoEvento['timestamp']), "Evento possui timestamp de disparo.");
assertTeste($ultimoEvento['dados']['codigo'] === 'OBS999', "Dados do evento passados corretamente.");

// ------------------------------------------------------------
// [3] TESTANDO AUDITORIAOBSERVER (Gravação em Log Seguro)
// ------------------------------------------------------------
echo "\n[3] TESTANDO AUDITORIAOBSERVER (Logs Locais):\n";

assertTeste($obsAuditoria instanceof AgendamentoObserver, "AuditoriaObserver implementa AgendamentoObserver.");
assertTeste($obsAuditoria->getNome() === "AuditoriaObserver", "Nome do observador é 'AuditoriaObserver'.");

$ultimoLog = $obsAuditoria->getUltimoLog();
assertTeste(!empty($ultimoLog), "AuditoriaObserver gerou uma linha de log.");
assertTeste(str_contains($ultimoLog, 'AGENDAMENTO_CRIADO'), "Log contém o tipo do evento disparado.");
assertTeste(str_contains($ultimoLog, 'OBS999'), "Log contém o código do agendamento.");
assertTeste(str_contains($ultimoLog, 'Marcos Teste Observer'), "Log contém o nome do cliente.");

// Verifica se o arquivo de log físico existe e tem conteúdo
$caminhoArquivoLog = $obsAuditoria->getCaminhoLog();
assertTeste(file_exists($caminhoArquivoLog), "Arquivo físico 'logs/auditoria.log' existe no disco.");
assertTeste(filesize($caminhoArquivoLog) > 0, "Arquivo 'logs/auditoria.log' possui bytes gravados.");

// ------------------------------------------------------------
// [4] TESTANDO NOTIFICACAOWHATSAPPOBSERVER (Passivo sem Disparo Externo)
// ------------------------------------------------------------
echo "\n[4] TESTANDO NOTIFICACAOWHATSAPPOBSERVER (Formatação Passiva):\n";

assertTeste($obsWhatsApp instanceof AgendamentoObserver, "NotificacaoWhatsAppObserver implementa AgendamentoObserver.");
$msgFormatada = $obsWhatsApp->getUltimaMensagem();
$urlZap = $obsWhatsApp->getUltimaUrl();

assertTeste(!empty($msgFormatada), "Mensagem do WhatsApp foi montada com sucesso.");
assertTeste(str_contains($msgFormatada, 'Marcos Teste Observer'), "Mensagem formatada cita o nome do cliente.");
assertTeste(str_contains($msgFormatada, 'OBS999'), "Mensagem cita o código de segurança #OBS999.");
assertTeste(str_contains($urlZap, 'https://api.whatsapp.com/send'), "URL gerada é compatível com WhatsApp Web / Mobile sem chamadas de rede externas.");

// ------------------------------------------------------------
// [5] TESTANDO ESTATISTICAOBSERVER
// ------------------------------------------------------------
echo "\n[5] TESTANDO ESTATISTICAOBSERVER (Contadores de Eventos):\n";

assertTeste($obsStats instanceof AgendamentoObserver, "EstatisticaObserver implementa AgendamentoObserver.");
assertTeste($obsStats->getTotalEventos() === 1, "EstatisticaObserver registrou exatamente 1 evento até aqui.");

// Disparar segundo evento: cancelamento
$subject->dispararEvento('AGENDAMENTO_CANCELADO', $dadosMock);
assertTeste($obsStats->getTotalEventos() === 2, "EstatisticaObserver incrementou para 2 eventos.");

$contadores = $obsStats->getContadores();
assertTeste($contadores['AGENDAMENTO_CRIADO'] === 1, "Contador de AGENDAMENTO_CRIADO é 1.");
assertTeste($contadores['AGENDAMENTO_CANCELADO'] === 1, "Contador de AGENDAMENTO_CANCELADO é 1.");

echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: {$totalTestes}\n";
echo "  Testes aprovados: {$aprovados}\n";
echo "  Testes reprovados: {$reprovados}\n";

if ($reprovados === 0) {
    echo "\n  >>> SUCESSO ABSOLUTO: Padrão GoF Observer 100% Validado! <<<\n";
} else {
    echo "\n  >>> ATENÇÃO: Verifique as falhas acima! <<<\n";
}
echo "============================================================\n\n";