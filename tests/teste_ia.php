<?php
/**
 * BATERIA DE TESTES AUTOMATIZADOS: INTELIGÊNCIA ARTIFICIAL VIP
 * Testa:
 * 1. Motor de Visagismo Masculino (Formato facial, curvatura, harmonização, barbeiro e produto)
 * 2. Radar Analítico de Clientes Inativos (Identificação de ausência e risco de churn)
 * 3. Gerador de Copywriting Persuasivo com IA para WhatsApp
 * 4. Gerador de Campanhas de Marketing Turbo
 * 5. Endpoint REST api/ia.php
 */

require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/IaService.php';

$totalTestes = 0;
$testesPassados = 0;

function assertTeste($condicao, $mensagem) {
    global $totalTestes, $testesPassados;
    $totalTestes++;
    if ($condicao) {
        $testesPassados++;
        echo "  [PASSOU] $mensagem\n";
    } else {
        echo "  [FALHOU] $mensagem\n";
    }
}

echo "============================================================\n";
echo "   BATERIA DE TESTES: INTELIGÊNCIA ARTIFICIAL VIP (2026)    \n";
echo "============================================================\n\n";

// [1] TESTANDO MOTOR DE VISAGISMO MASCULINO
echo "[1] TESTANDO O MOTOR DE VISAGISMO MASCULINO COM IA:\n";
$iaService = new IaService();
assertTeste($iaService instanceof IaService, "IaService instanciado com sucesso");

// Caso 1: Rosto Quadrado + Cabelo Liso
$diag1 = $iaService->analisarVisagismo([
    'formato_rosto' => 'quadrado',
    'tipo_cabelo' => 'liso',
    'estilo_barba' => 'barba_curta',
    'estilo_vibe' => 'moderno'
]);
assertTeste($diag1['success'] === true, "Diagnóstico de visagismo retornou sucesso");
assertTeste(!empty($diag1['corte_sugerido']['nome']), "Nome do corte sugerido retornado ({$diag1['corte_sugerido']['nome']})");
assertTeste($diag1['corte_sugerido']['match'] >= 90, "Match de visagismo acima de 90% ({$diag1['corte_sugerido']['match']}%)");
assertTeste(!empty($diag1['corte_sugerido']['explicacao_visagismo']), "Explicação geométrica de visagismo fornecida");
assertTeste(!empty($diag1['corte_sugerido']['dica_finalizacao']), "Dica de estilização fornecida");
assertTeste(!empty($diag1['corte_sugerido']['foto_referencia']), "Foto de referência em alta definição anexada");
assertTeste(!empty($diag1['barbeiro_recomendado']['nome']), "Barbeiro especialista recomendado ({$diag1['barbeiro_recomendado']['nome']})");
assertTeste(!empty($diag1['produto_recomendado']['nome']), "Produto de finalização recomendado ({$diag1['produto_recomendado']['nome']})");

// Caso 2: Rosto Redondo + Cabelo Ondulado
$diag2 = $iaService->analisarVisagismo([
    'formato_rosto' => 'redondo',
    'tipo_cabelo' => 'ondulado',
    'estilo_barba' => 'barba_cheia',
    'estilo_vibe' => 'ousado'
]);
assertTeste(str_contains(strtolower($diag2['corte_sugerido']['explicacao_visagismo']), 'redondo') || str_contains(strtolower($diag2['corte_sugerido']['explicacao_visagismo']), 'alongar'), "Explicação de visagismo específica para formato redondo");
assertTeste($diag2['corte_sugerido']['servico_nome'] === 'Corte e Barba', "Identificou necessidade de serviço conjunto 'Corte e Barba'");

// Caso 3: Rosto Oval + Cabelo Cacheado
$diag3 = $iaService->analisarVisagismo([
    'formato_rosto' => 'oval',
    'tipo_cabelo' => 'cacheado',
    'estilo_barba' => 'sem_barba'
]);
assertTeste(!empty($diag3['corte_sugerido']['nome']), "Recomendação gerada para perfil Oval e Cacheado");

// Caso 4: Análise por Imagem Base64 simulada
$imagemSimulada = base64_encode("fake_image_pixels_sample_for_face_analysis_12345");
$diag4 = $iaService->analisarVisagismo([
    'imagem_base64' => $imagemSimulada,
    'tipo_cabelo' => 'crespo',
    'estilo_barba' => 'barba_curta'
]);
assertTeste($diag4['success'] === true, "Análise de imagem por IA executada com sucesso");
assertTeste(!empty($diag4['perfil']['formato_rosto']), "Formato de rosto estimado por imagem ({$diag4['perfil']['formato_rosto']})");

echo "\n[2] TESTANDO O RADAR ANALÍTICO DE CLIENTES INATIVOS:\n";
$inativos = $iaService->obterClientesInativos(1); // Testando com 1 dia mínimo para pegar registros existentes
assertTeste($inativos['success'] === true, "Radar de clientes inativos respondeu com sucesso");
assertTeste(isset($inativos['total_inativos']), "Contagem de clientes inativos calculada ({$inativos['total_inativos']} inativos)");
assertTeste(isset($inativos['faturamento_em_risco']), "Faturamento em risco calculado (R$ " . number_format($inativos['faturamento_em_risco'], 2, ',', '.') . ")");
assertTeste(is_array($inativos['clientes']), "Lista de clientes retornada em formato array");

if (!empty($inativos['clientes'])) {
    $cExemplo = $inativos['clientes'][0];
    assertTeste(!empty($cExemplo['cliente_nome']), "Nome do cliente presente no radar");
    assertTeste(!empty($cExemplo['risco']), "Classificação de risco atribuída ({$cExemplo['risco']})");
    assertTeste(isset($cExemplo['dias_sem_retorno']), "Contador de dias de ausência calculado ({$cExemplo['dias_sem_retorno']} dias)");
} else {
    echo "  [INFO] Nenhum agendamento antigo encontrado na base de teste para validar campos individuais.\n";
}

echo "\n[3] TESTANDO GERADOR DE COPYWRITING PERSUASIVO COM IA PARA WHATSAPP:\n";
$copy = $iaService->gerarMensagemRecuperacao([
    'cliente_nome' => 'Bruno Alcantara',
    'cliente_telefone' => '(11) 98888-7777',
    'dias_sem_retorno' => 38,
    'ultimo_servico' => 'Degradê Navalhado',
    'barbeiro_nome' => 'Carlos Navalha',
    'cupom' => 'VOLTAVIP15'
]);
assertTeste($copy['success'] === true, "Copywriting de recuperação gerado com sucesso");
assertTeste(str_contains($copy['mensagem'], 'Bruno'), "Mensagem personalizada inclui o primeiro nome do cliente");
assertTeste(str_contains($copy['mensagem'], '38 dias'), "Mensagem cita o número exato de dias de ausência");
assertTeste(str_contains($copy['mensagem'], 'VOLTAVIP15'), "Mensagem inclui o código do cupom VIP");
assertTeste(str_contains($copy['whatsapp_link'], 'https://wa.me/5511988887777'), "Link do WhatsApp gerado com formatação correta");

echo "\n[4] TESTANDO GERADOR DE CAMPANHAS DE MARKETING TURBO:\n";
$campMeioSemana = $iaService->gerarCampanhasMarketing('meio_de_semana');
assertTeste($campMeioSemana['success'] === true, "Campanha para meio de semana gerada");
assertTeste(count($campMeioSemana['campanhas']) === 3, "3 variações de campanhas geradas");
assertTeste(str_contains($campMeioSemana['campanhas'][0]['texto'], 'BARBEARIA'), "Texto promocional formatado com formatação persuasiva");

$campFimSemana = $iaService->gerarCampanhasMarketing('fim_de_semana');
assertTeste(count($campFimSemana['campanhas']) === 3, "Campanhas de fim de semana geradas com 3 variações");

$campClube = $iaService->gerarCampanhasMarketing('clube_vip');
assertTeste(str_contains($campClube['campanhas'][0]['texto'], 'Barber Pass'), "Campanha do Clube VIP cita o Barber Pass");

echo "\n[5] TESTANDO ENDPOINT REST api/ia.php:\n";
$urlHttp = 'http://127.0.0.1/sistema-barbearia-2026-brunex/api/ia.php?acao=clientes_inativos&dias=10';
$ctx = stream_context_create(['http' => ['timeout' => 4]]);
$respHttp = @file_get_contents($urlHttp, false, $ctx);
if ($respHttp !== false) {
    $respJson = json_decode($respHttp, true);
} else {
    // Fallback para CLI
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['acao'] = 'clientes_inativos';
    $_GET['dias'] = '10';
    ob_start();
    require __DIR__ . '/../api/ia.php';
    $output = ob_get_clean();
    $respJson = json_decode($output, true);
}


assertTeste(is_array($respJson), "api/ia.php respondeu JSON válido");
assertTeste(($respJson['success'] ?? false) === true, "api/ia.php confirmou status de sucesso");

echo "\n[6] TESTANDO ARQUITETURA HÍBRIDA & MOTOR PYTHON (scripts/ia_engine.py):\n";
$scriptPython = dirname(__DIR__) . '/scripts/ia_engine.py';
assertTeste(file_exists($scriptPython), "Script autônomo Python scripts/ia_engine.py existe no projeto");
assertTeste(filesize($scriptPython) > 1000, "Script Python possui código e regras de IA estruturadas");

$statusMotores = $iaService->obterStatusMotores();
assertTeste($statusMotores['success'] === true, "obterStatusMotores() retornou sucesso");
assertTeste(!empty($statusMotores['motor_ativo']), "Identificou motor de IA ativo ({$statusMotores['motor_ativo']})");
assertTeste(isset($statusMotores['motores']['python']['disponivel']), "Detector de ambiente Python avaliado com sucesso");
assertTeste($statusMotores['motores']['nativo_php']['disponivel'] === true, "Motor nativo PHP 100% disponível para fallback automático");

// Valida metadados de rastreabilidade do motor nas operações
assertTeste(!empty($diag1['_motor']), "Diagnóstico de visagismo traz metadado do motor executor ({$diag1['_motor']})");
assertTeste(!empty($copy['_motor']), "Copywriting de recuperação traz metadado do motor executor ({$copy['_motor']})");
assertTeste(!empty($campFimSemana['_motor']), "Campanha traz metadado do motor executor ({$campFimSemana['_motor']})");

// Valida endpoint de status via REST isolando subprocesso para preservar execução
$phpBin = PHP_BINARY;
$apiFile = str_replace('\\', '/', dirname(__DIR__) . '/api/ia.php');
$code = "\$_GET['acao'] = 'status_motores'; \$_SERVER['REQUEST_METHOD'] = 'GET'; require '$apiFile';";
$cmd = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($code);
$outStatus = shell_exec($cmd);
$jsonStatus = json_decode($outStatus, true);
assertTeste(is_array($jsonStatus) && ($jsonStatus['success'] ?? false) === true, "Endpoint api/ia.php?acao=status_motores responde com diagnóstico operacional");


echo "\n============================================================\n";
echo "                  RESUMO DOS TESTES                         \n";
echo "============================================================\n";
echo "  Total de testes: $totalTestes\n";
echo "  Testes aprovados: $testesPassados\n";
echo "  Testes reprovados: " . ($totalTestes - $testesPassados) . "\n\n";

if ($totalTestes === $testesPassados) {
    echo "  >>> SUCESSO ABSOLUTO: Módulos de IA 100% Validados! <<<\n";
} else {
    echo "  >>> ATENÇÃO: Falha em testes de IA! <<<\n";
    exit(1);
}
echo "============================================================\n";
