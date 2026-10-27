<?php
/**
 * Painel & API do Motor Preditivo de Demanda, Simulador de Metas e Detector Anti-Furo (No-Show) em Python
 * Executa scripts/previsao_demanda.py com os dados reais do banco MySQL.
 */
require_once __DIR__ . '/../Models/Database.php';

$conn = Database::getInstance()->getConnection();
$metaMensal = isset($_GET['meta']) && is_numeric($_GET['meta']) ? max(100, (float)$_GET['meta']) : 5000.0;

// 1. Busca todos os agendamentos com serviço e barbeiro
$agendamentos = [];
try {
    $sql = "SELECT a.id, a.cliente_nome, a.cliente_telefone, a.data_agendada, a.horario, a.status, a.codigo,
                   s.nome AS servico_nome, s.preco AS servico_preco,
                   u.nome AS barbeiro_nome
            FROM agendamentos a
            LEFT JOIN servicos s ON a.servico_id = s.id
            LEFT JOIN usuarios u ON a.barbeiro_id = u.id
            ORDER BY a.data_agendada DESC, a.horario ASC";
    $st = $conn->query($sql);
    if ($st) {
        $agendamentos = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

// 2. Busca MRR de assinaturas VIP
$mrrAssinaturas = 0.0;
try {
    $stAss = $conn->query("SELECT COALESCE(SUM(p.preco_mensal), 0)
                           FROM assinaturas_vip a
                           INNER JOIN planos_assinatura p ON a.plano_id = p.id
                           WHERE a.status = 'ativo' AND a.data_renovacao >= CURDATE()");
    if ($stAss) {
        $mrrAssinaturas = (float)$stAss->fetchColumn();
    }
} catch (Throwable $e) {}

// 3. Horários de expediente
$horariosExp = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
$cfgFile = __DIR__ . '/config_horarios.json';
if (file_exists($cfgFile)) {
    $parsed = json_decode(file_get_contents($cfgFile), true);
    if (is_array($parsed) && !empty($parsed)) {
        $horariosExp = $parsed;
    }
}

$payload = [
    'meta_mensal' => $metaMensal,
    'mrr_assinaturas' => $mrrAssinaturas,
    'horarios_expediente' => $horariosExp,
    'agendamentos' => $agendamentos
];

// 4. Executa scripts/previsao_demanda.py
$scriptPath = realpath(__DIR__ . '/../scripts/previsao_demanda.py');
$resultado = null;
$executadoViaPython = false;

if ($scriptPath && file_exists($scriptPath)) {
    $bins = ['python', 'python3', 'C:\\Python312\\python.exe', 'C:\\Python311\\python.exe', 'py'];
    foreach ($bins as $bin) {
        $cmd = $bin . ' ' . escapeshellarg($scriptPath) . ' --json --stdin';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_UNICODE));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
            if ($code === 0 && !empty($stdout)) {
                $dec = json_decode($stdout, true);
                if (is_array($dec) && !empty($dec['success'])) {
                    $resultado = $dec;
                    $executadoViaPython = true;
                    break;
                }
            }
        }
    }
}

// Fallback caso Python não esteja no PATH
if (!$resultado) {
    $ativos = array_filter($agendamentos, fn($a) => in_array(strtolower($a['status'] ?? 'ativo'), ['ativo', 'concluido']));
    $recCortes = round(array_reduce($ativos, fn($c, $a) => $c + (float)($a['servico_preco'] ?? 0), 0.0), 2);
    $recAtual = round($recCortes + $mrrAssinaturas, 2);
    $tkt = count($ativos) > 0 ? round($recCortes / count($ativos), 2) : 50.0;
    $falta = max(0.0, round($metaMensal - $recAtual, 2));
    $prog = min(100.0, round(($recAtual / $metaMensal) * 100.0, 1));
    $cortesRest = $falta > 0 ? (int)ceil($falta / max(1, $tkt)) : 0;

    $resultado = [
        'success' => true,
        'engine' => 'php_fallback',
        'versao_python' => 'N/A',
        'gerado_em' => date('d/m/Y H:i:s'),
        'simulacao_meta' => [
            'meta_mensal' => $metaMensal,
            'receita_atual' => $recAtual,
            'falta_para_meta' => $falta,
            'progresso_pct' => $prog,
            'ticket_medio_atual' => $tkt,
            'cortes_restantes' => $cortesRest,
            'combos_restantes' => (int)ceil($cortesRest / 1.6),
            'ritmo_diario' => round($recAtual / max(1, (int)date('d')), 2),
            'projecao_30_dias' => round(($recAtual / max(1, (int)date('d'))) * 30, 2),
            'meta_atingida' => $falta <= 0
        ],
        'analise_noshow' => [],
        'grade_demanda' => [],
        'horarios_ociosos' => [],
        'dica_estrategica_python' => "Faltam {$cortesRest} atendimento(s) para atingir a meta de R$ " . number_format($metaMensal, 2, ',', '.') . "."
    ];
}

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sim = $resultado['simulacao_meta'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IA Preditiva de Demanda, Metas & Anti-Furo (Python) — Barbearia VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0A0A0C] text-zinc-200 font-sans p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/90 border border-amber-500/30 rounded-3xl p-6 shadow-2xl">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/40 flex items-center justify-center text-3xl">
                    🎯
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] uppercase tracking-[0.2em] px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold">
                            <?php echo $executadoViaPython ? "Python Preditivo " . htmlspecialchars($resultado['versao_python']) : "Motor Preditivo"; ?>
                        </span>
                        <span class="text-xs text-zinc-400">Atualizado em <?php echo htmlspecialchars($resultado['gerado_em']); ?></span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">Simulador de Metas & Detector Anti-Furo (Python)</h1>
                    <p class="text-xs text-zinc-400">Análise preditiva em Python (<code class="text-amber-300">scripts/previsao_demanda.py</code>) para bater metas, evitar faltas (No-Show) e preencher horários vagos.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="relatorio_python.php" class="px-4 py-2.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 font-bold text-xs transition">
                    📈 Ver DRE & Relatório
                </a>
                <a href="../Frontend/index.html" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 transition">
                    ← Voltar ao Sistema
                </a>
            </div>
        </div>

        <!-- Simulador de Meta Mensal -->
        <div class="bg-zinc-900/90 border border-white/10 rounded-3xl p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-4">
                <div>
                    <span class="text-xs uppercase tracking-widest text-amber-400 font-bold">Simulador de Meta em Python</span>
                    <h2 class="text-xl font-bold text-white">Progresso da Meta Financeira da Barbearia</h2>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-xs text-zinc-400">Definir Meta (R$):</label>
                    <input type="number" step="100" min="100" name="meta" value="<?php echo htmlspecialchars((string)$metaMensal); ?>" class="w-32 rounded-xl bg-black/60 border border-white/15 px-3 py-2 text-sm text-white font-mono">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs transition">
                        Simular
                    </button>
                </form>
            </div>

            <!-- Barra de Progresso -->
            <div>
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="text-zinc-300">Receita Acumulada: <strong class="text-emerald-400 font-mono">R$ <?php echo number_format($sim['receita_atual'], 2, ',', '.'); ?></strong></span>
                    <span class="text-amber-300 font-bold font-mono"><?php echo number_format($sim['progresso_pct'], 1, ',', '.'); ?>% da Meta (R$ <?php echo number_format($sim['meta_mensal'], 2, ',', '.'); ?>)</span>
                </div>
                <div class="w-full h-4 bg-black/60 rounded-full overflow-hidden border border-white/10 p-0.5">
                    <div class="h-full bg-gradient-to-r from-amber-500 via-amber-400 to-emerald-400 rounded-full transition-all duration-500" style="width: <?php echo min(100, max(2, (float)$sim['progresso_pct'])); ?>%"></div>
                </div>
            </div>

            <!-- Cards do Simulador -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-black/40 border border-white/10">
                    <span class="text-[11px] uppercase text-zinc-400">Falta para Bater a Meta</span>
                    <div class="text-xl font-extrabold font-mono text-amber-400 mt-1">
                        R$ <?php echo number_format($sim['falta_para_meta'], 2, ',', '.'); ?>
                    </div>
                    <span class="text-[11px] text-zinc-500">Ticket médio: R$ <?php echo number_format($sim['ticket_medio_atual'], 2, ',', '.'); ?></span>
                </div>

                <div class="p-4 rounded-2xl bg-black/40 border border-white/10">
                    <span class="text-[11px] uppercase text-zinc-400">Cortes Restantes Necessários</span>
                    <div class="text-xl font-extrabold font-mono text-white mt-1">
                        ✂️ <?php echo (int)$sim['cortes_restantes']; ?> corte(s)
                    </div>
                    <span class="text-[11px] text-emerald-400">Ou <?php echo (int)$sim['combos_restantes']; ?> combos (Corte + Produto)</span>
                </div>

                <div class="p-4 rounded-2xl bg-black/40 border border-white/10">
                    <span class="text-[11px] uppercase text-zinc-400">Ritmo Diário Atual</span>
                    <div class="text-xl font-extrabold font-mono text-sky-400 mt-1">
                        R$ <?php echo number_format($sim['ritmo_diario'], 2, ',', '.'); ?>/dia
                    </div>
                    <span class="text-[11px] text-zinc-500">Média acumulada no mês</span>
                </div>

                <div class="p-4 rounded-2xl bg-black/40 border border-white/10">
                    <span class="text-[11px] uppercase text-zinc-400">Projeção de Fechamento (30d)</span>
                    <div class="text-xl font-extrabold font-mono text-emerald-400 mt-1">
                        R$ <?php echo number_format($sim['projecao_30_dias'], 2, ',', '.'); ?>
                    </div>
                    <span class="text-[11px] text-zinc-500">Projeção linear Python</span>
                </div>
            </div>

            <!-- Insight da IA Python -->
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-200 flex items-center gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <strong class="text-amber-400 uppercase tracking-wider block text-[10px]">Recomendação Estratégica do Motor Python:</strong>
                    <?php echo htmlspecialchars($resultado['dica_estrategica_python']); ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Detector Anti-Furo / Risco de No-Show (7 colunas) -->
            <div class="lg:col-span-7 bg-zinc-900/90 border border-white/10 rounded-3xl p-6">
                <h2 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                    <span>🛡️</span> Detector Anti-Furo (Risco de No-Show por Cliente)
                </h2>
                <p class="text-xs text-zinc-400 mb-4">O Python avalia histórico de cancelamentos, horário e telefone para prever quem tem chance de faltar.</p>

                <div class="space-y-3">
                    <?php if (!empty($resultado['analise_noshow'])): ?>
                        <?php foreach (array_slice($resultado['analise_noshow'], 0, 10) as $item): ?>
                            <?php
                                $corBadge = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                                if ($item['nivel_risco'] === 'ALTO') {
                                    $corBadge = 'bg-red-500/20 text-red-300 border-red-500/40';
                                } elseif ($item['nivel_risco'] === 'MEDIO') {
                                    $corBadge = 'bg-amber-500/20 text-amber-300 border-amber-500/40';
                                }
                                $zapConfUrl = !empty($item['telefone_limpo'])
                                    ? "https://api.whatsapp.com/send?phone=" . urlencode($item['telefone_limpo']) . "&text=" . urlencode($item['mensagem_preventiva'])
                                    : "https://api.whatsapp.com/send?text=" . urlencode($item['mensagem_preventiva']);
                            ?>
                            <div class="p-4 rounded-2xl bg-black/40 border border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <strong class="text-sm text-white"><?php echo htmlspecialchars($item['cliente_nome']); ?></strong>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $corBadge; ?>">
                                            Risco <?php echo htmlspecialchars($item['nivel_risco']); ?> (<?php echo (int)$item['score_risco_pct']; ?>%)
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-300 mt-1">
                                        ✂️ <?php echo htmlspecialchars($item['servico_nome']); ?> • 📅 <?php echo htmlspecialchars($item['data_agendada']); ?> às <strong class="text-amber-400"><?php echo htmlspecialchars($item['horario']); ?></strong>
                                    </p>
                                    <p class="text-[11px] text-zinc-500 mt-0.5"><?php echo htmlspecialchars($item['motivo_principal']); ?></p>
                                </div>
                                <a href="<?php echo htmlspecialchars($zapConfUrl); ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-[#25D366]/20 hover:bg-[#25D366] text-[#25D366] hover:text-black border border-[#25D366]/40 font-bold text-xs transition whitespace-nowrap text-center">
                                    📲 Confirmar Vaga
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-zinc-500 py-6 text-center">Nenhum agendamento ativo para análise de risco.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mapa de Calor de Horários & Happy Hour (5 colunas) -->
            <div class="lg:col-span-5 bg-zinc-900/90 border border-white/10 rounded-3xl p-6">
                <h2 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                    <span>🔥</span> Termômetro de Horários & Happy Hour
                </h2>
                <p class="text-xs text-zinc-400 mb-4">Identificação automática de horários ociosos para promoções.</p>

                <div class="space-y-3">
                    <?php foreach ($resultado['grade_demanda'] as $g): ?>
                        <div class="p-3 rounded-2xl bg-black/40 border border-white/5">
                            <div class="flex justify-between items-center text-xs mb-1.5">
                                <span class="font-mono font-bold text-amber-400 text-sm"><?php echo htmlspecialchars($g['horario']); ?></span>
                                <span class="text-zinc-300 font-semibold"><?php echo (int)$g['atendimentos']; ?> agendamento(s)</span>
                            </div>
                            <div class="w-full h-2 bg-black/60 rounded-full overflow-hidden mb-1.5">
                                <div class="h-full <?php echo $g['intensidade_pct'] <= 35 ? 'bg-sky-400' : ($g['intensidade_pct'] >= 75 ? 'bg-emerald-400' : 'bg-amber-400'); ?> rounded-full" style="width: <?php echo max(8, (int)$g['intensidade_pct']); ?>%"></div>
                            </div>
                            <span class="text-[10px] text-zinc-400"><?php echo htmlspecialchars($g['classificacao']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
