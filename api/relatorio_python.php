<?php
/**
 * Interface Executiva & API do Gerador de Relatório Financeiro em Python (scripts/relatorio_financeiro.py)
 * Coleta os dados reais do MySQL, processa no motor Python e oferece exportação PDF/Impressão, Excel (CSV) e WhatsApp.
 */
require_once __DIR__ . '/../Models/Database.php';

$conn = Database::getInstance()->getConnection();

$periodo = trim($_GET['periodo'] ?? ''); // ex: 2026-10 ou vazio para todos
$taxaComissao = isset($_GET['comissao']) && is_numeric($_GET['comissao']) ? max(0, min(100, (float)$_GET['comissao'])) : 50.0;
$custoFixo = isset($_GET['custo_fixo']) && is_numeric($_GET['custo_fixo']) ? max(0, (float)$_GET['custo_fixo']) : 0.0;
$formato = strtolower(trim($_GET['formato'] ?? 'html'));

// 1. Busca agendamentos do banco de dados
$sql = "SELECT a.id, a.cliente_nome, a.cliente_telefone, a.data_agendada, a.horario, a.status, a.codigo,
               s.nome AS servico_nome, s.preco AS servico_preco,
               u.nome AS barbeiro_nome
        FROM agendamentos a
        LEFT JOIN servicos s ON a.servico_id = s.id
        LEFT JOIN usuarios u ON a.barbeiro_id = u.id";
$params = [];
if ($periodo !== '' && preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $sql .= " WHERE a.data_agendada LIKE :periodo";
    $params[':periodo'] = $periodo . '%';
}
$sql .= " ORDER BY a.data_agendada DESC, a.horario ASC";

$agendamentos = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $agendamentos = [];
}

// 2. Busca MRR de assinaturas VIP ativas
$mrrAssinaturas = 0.0;
$totalAssinantesAtivos = 0;
try {
    $stAss = $conn->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(p.preco_mensal), 0) AS mrr
                           FROM assinaturas_vip a
                           INNER JOIN planos_assinatura p ON a.plano_id = p.id
                           WHERE a.status = 'ativo' AND a.data_renovacao >= CURDATE()");
    if ($stAss && ($rowAss = $stAss->fetch(PDO::FETCH_ASSOC))) {
        $totalAssinantesAtivos = (int)$rowAss['qtd'];
        $mrrAssinaturas = (float)$rowAss['mrr'];
    }
} catch (Throwable $e) {}

// 3. Busca valor total em estoque de produtos
$valorEstoque = 0.0;
try {
    $stProd = $conn->query("SELECT COALESCE(SUM(preco * estoque), 0) FROM produtos WHERE ativo = 1");
    if ($stProd) {
        $valorEstoque = (float)$stProd->fetchColumn();
    }
} catch (Throwable $e) {}

$payload = [
    'periodo' => $periodo !== '' ? $periodo : 'Todos os Períodos',
    'taxa_comissao' => $taxaComissao,
    'custo_fixo' => $custoFixo,
    'mrr_assinaturas' => $mrrAssinaturas,
    'total_assinantes_ativos' => $totalAssinantesAtivos,
    'valor_estoque_produtos' => $valorEstoque,
    'agendamentos' => $agendamentos
];

// 4. Executa o motor Python (scripts/relatorio_financeiro.py) via proc_open
$scriptPath = realpath(__DIR__ . '/../scripts/relatorio_financeiro.py');
$relatorio = null;
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
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_UNICODE));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode === 0 && !empty($stdout)) {
                $decoded = json_decode($stdout, true);
                if (is_array($decoded) && !empty($decoded['success'])) {
                    $relatorio = $decoded;
                    $executadoViaPython = true;
                    break;
                }
            }
        }
    }
}

// Fallback em PHP caso o ambiente não tenha Python instalado no PATH
if (!$relatorio) {
    $ativos = array_filter($agendamentos, fn($a) => in_array(strtolower($a['status'] ?? 'ativo'), ['ativo', 'concluido']));
    $cancelados = array_filter($agendamentos, fn($a) => strtolower($a['status'] ?? '') === 'cancelado');
    $recServicos = round(array_reduce($ativos, fn($c, $a) => $c + (float)($a['servico_preco'] ?? 0), 0.0), 2);
    $recPerdida = round(array_reduce($cancelados, fn($c, $a) => $c + (float)($a['servico_preco'] ?? 0), 0.0), 2);
    $recTotal = round($recServicos + $mrrAssinaturas, 2);
    $comissoes = round($recServicos * ($taxaComissao / 100.0), 2);
    $lucro = round($recTotal - $comissoes - $custoFixo, 2);
    $margem = $recTotal > 0 ? round(($lucro / $recTotal) * 100.0, 1) : 0.0;
    $tkt = count($ativos) > 0 ? round($recServicos / count($ativos), 2) : 0.0;

    $relatorio = [
        'success' => true,
        'engine' => 'php_fallback',
        'versao_python' => 'N/A',
        'gerado_em' => date('d/m/Y H:i:s'),
        'periodo' => $payload['periodo'],
        'metricas' => [
            'total_agendamentos' => count($agendamentos),
            'atendimentos_ativos' => count($ativos),
            'atendimentos_cancelados' => count($cancelados),
            'taxa_conversao_pct' => count($agendamentos) > 0 ? round((count($ativos) / count($agendamentos)) * 100, 1) : 100,
            'taxa_cancelamento_pct' => count($agendamentos) > 0 ? round((count($cancelados) / count($agendamentos)) * 100, 1) : 0,
            'faturamento_bruto_agendado' => $recServicos + $recPerdida,
            'receita_servicos_confirmada' => $recServicos,
            'receita_perdida_cancelados' => $recPerdida,
            'mrr_assinaturas' => $mrrAssinaturas,
            'total_assinantes_ativos' => $totalAssinantesAtivos,
            'receita_operacional_total' => $recTotal,
            'taxa_comissao_pct' => $taxaComissao,
            'total_comissoes_barbeiros' => $comissoes,
            'custo_fixo' => $custoFixo,
            'lucro_liquido_barbearia' => $lucro,
            'margem_lucro_pct' => $margem,
            'ticket_medio' => $tkt,
            'valor_estoque_produtos' => $valorEstoque
        ],
        'ranking_barbeiros' => [],
        'ranking_servicos' => [],
        'horarios_pico' => [],
        'resumo_whatsapp' => "📊 *RELATÓRIO FINANCEIRO - BARBEARIA VIP*\nReceita Total: R$ " . number_format($recTotal, 2, ',', '.') . "\nLucro Líquido: R$ " . number_format($lucro, 2, ',', '.'),
        'csv_conteudo' => "ID;Data;Horario;Cliente;Servico;Valor\n"
    ];
}

// Exportação em Planilha CSV / Excel
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_financeiro_barbearia_vip_' . date('Ymd_His') . '.csv"');
    // UTF-8 BOM para o Excel abrir acentos perfeitamente
    echo "\xEF\xBB\xBF" . ($relatorio['csv_conteudo'] ?? '');
    exit;
}

// Exportação em JSON
if ($formato === 'json' || isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($relatorio, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$m = $relatorio['metricas'];
$zapUrl = "https://api.whatsapp.com/send?text=" . urlencode($relatorio['resumo_whatsapp']);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro Executivo (Python) — Barbearia VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: #fff !important; color: #000 !important; }
            .no-print { display: none !important; }
            .print-card { background: #fff !important; border: 1px solid #ccc !important; color: #000 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#0A0A0C] text-zinc-200 font-sans p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Topo & Ações -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/90 border border-amber-500/30 rounded-3xl p-6 shadow-2xl print-card">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/40 flex items-center justify-center text-3xl">
                    🐍
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] uppercase tracking-[0.2em] px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold">
                            <?php echo $executadoViaPython ? "Motor Python " . htmlspecialchars($relatorio['versao_python']) . " Ativo" : "Modo Compatibilidade"; ?>
                        </span>
                        <span class="text-xs text-zinc-400">Gerado em <?php echo htmlspecialchars($relatorio['gerado_em']); ?></span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">Relatório Financeiro Executivo</h1>
                    <p class="text-xs text-zinc-400">Fechamento automatizado via Python (<code class="text-amber-300">scripts/relatorio_financeiro.py</code>) com DRE, Comissões e Rankings.</p>
                </div>
            </div>

            <div class="no-print flex items-center gap-2 flex-wrap">
                <a href="?periodo=<?php echo urlencode($periodo); ?>&comissao=<?php echo urlencode((string)$taxaComissao); ?>&custo_fixo=<?php echo urlencode((string)$custoFixo); ?>&formato=csv" class="px-4 py-2.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 font-bold text-xs transition flex items-center gap-1.5">
                    <span>📊</span> Baixar Excel (.CSV)
                </a>
                <button onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs transition flex items-center gap-1.5 shadow-lg">
                    <span>🖨️</span> Salvar PDF / Imprimir
                </button>
                <a href="<?php echo htmlspecialchars($zapUrl); ?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-[#25D366] hover:bg-[#1EBE5D] text-black font-bold text-xs transition flex items-center gap-1.5 shadow-lg">
                    <span>📲</span> Enviar no WhatsApp
                </a>
                <a href="../Frontend/index.html" class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 transition">
                    ← Voltar
                </a>
            </div>
        </div>

        <!-- Filtros de Simulação Financeira (No-Print) -->
        <form method="GET" class="no-print bg-zinc-900/70 border border-white/10 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Mês / Período (Opcional)</label>
                <input type="month" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>" class="w-full rounded-xl bg-black/60 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Taxa de Comissão Equipe (%)</label>
                <input type="number" step="1" min="0" max="100" name="comissao" value="<?php echo htmlspecialchars((string)$taxaComissao); ?>" class="w-full rounded-xl bg-black/60 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Custos Fixos (Aluguel/Luz R$)</label>
                <input type="number" step="10" min="0" name="custo_fixo" value="<?php echo htmlspecialchars((string)$custoFixo); ?>" class="w-full rounded-xl bg-black/60 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 font-bold text-xs transition">
                    🔄 Recalcular no Python
                </button>
                <?php if ($periodo !== ''): ?>
                    <a href="relatorio_python.php" class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs text-zinc-400">Limpar</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Cards Principais (KPIs Executivos) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-zinc-900/90 border border-white/10 rounded-2xl p-5 print-card">
                <span class="text-xs uppercase tracking-wider text-zinc-400">Receita Operacional Total</span>
                <div class="text-2xl font-extrabold font-mono text-white mt-1">
                    R$ <?php echo number_format($m['receita_operacional_total'], 2, ',', '.'); ?>
                </div>
                <p class="text-[11px] text-zinc-400 mt-1">
                    Cortes: R$ <?php echo number_format($m['receita_servicos_confirmada'], 2, ',', '.'); ?> + VIP: R$ <?php echo number_format($m['mrr_assinaturas'], 2, ',', '.'); ?>
                </p>
            </div>

            <div class="bg-zinc-900/90 border border-white/10 rounded-2xl p-5 print-card">
                <span class="text-xs uppercase tracking-wider text-zinc-400">Comissões dos Barbeiros (<?php echo (int)$m['taxa_comissao_pct']; ?>%)</span>
                <div class="text-2xl font-extrabold font-mono text-amber-400 mt-1">
                    R$ <?php echo number_format($m['total_comissoes_barbeiros'], 2, ',', '.'); ?>
                </div>
                <p class="text-[11px] text-zinc-400 mt-1">
                    <?php echo (int)$m['atendimentos_ativos']; ?> cortes confirmados • Ticket: R$ <?php echo number_format($m['ticket_medio'], 2, ',', '.'); ?>
                </p>
            </div>

            <div class="bg-zinc-900/90 border border-emerald-500/30 rounded-2xl p-5 print-card">
                <span class="text-xs uppercase tracking-wider text-emerald-400 font-bold">🏦 Lucro Líquido da Barbearia</span>
                <div class="text-2xl font-extrabold font-mono text-emerald-400 mt-1">
                    R$ <?php echo number_format($m['lucro_liquido_barbearia'], 2, ',', '.'); ?>
                </div>
                <p class="text-[11px] text-emerald-300/80 mt-1">
                    Margem Líquida: <strong><?php echo number_format($m['margem_lucro_pct'], 1, ',', '.'); ?>%</strong>
                </p>
            </div>

            <div class="bg-zinc-900/90 border border-white/10 rounded-2xl p-5 print-card">
                <span class="text-xs uppercase tracking-wider text-zinc-400">Patrimônio em Estoque & Taxa Conversão</span>
                <div class="text-2xl font-extrabold font-mono text-purple-300 mt-1">
                    R$ <?php echo number_format($m['valor_estoque_produtos'], 2, ',', '.'); ?>
                </div>
                <p class="text-[11px] text-zinc-400 mt-1">
                    Conversão da Agenda: <strong class="text-white"><?php echo $m['taxa_conversao_pct']; ?>%</strong> (<?php echo $m['atendimentos_cancelados']; ?> canc.)
                </p>
            </div>
        </div>

        <!-- Tabelas de Ranking: Barbeiros & Serviços -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Ranking de Barbeiros (7 colunas) -->
            <div class="lg:col-span-7 bg-zinc-900/90 border border-white/10 rounded-3xl p-6 print-card">
                <h2 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                    <span>🏆</span> Ranking de Produtividade & Comissões por Barbeiro
                </h2>
                <p class="text-xs text-zinc-400 mb-4">Cálculo individual processado pelo Python com rateio de comissão e participação.</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-white/10 text-zinc-400 uppercase">
                                <th class="py-2.5 px-2">#</th>
                                <th class="py-2.5 px-2">Barbeiro</th>
                                <th class="py-2.5 px-2 text-center">Cortes</th>
                                <th class="py-2.5 px-2 text-right">Faturado</th>
                                <th class="py-2.5 px-2 text-right">Comissão</th>
                                <th class="py-2.5 px-2 text-right">Lucro Casa</th>
                                <th class="py-2.5 px-2 text-right">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (!empty($relatorio['ranking_barbeiros'])): ?>
                                <?php foreach ($relatorio['ranking_barbeiros'] as $idx => $b): ?>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-3 px-2 font-bold text-amber-400">#<?php echo $idx + 1; ?></td>
                                        <td class="py-3 px-2 font-semibold text-white"><?php echo htmlspecialchars($b['barbeiro']); ?></td>
                                        <td class="py-3 px-2 text-center font-mono"><?php echo (int)$b['atendimentos']; ?></td>
                                        <td class="py-3 px-2 text-right font-mono text-zinc-200">R$ <?php echo number_format($b['receita_gerada'], 2, ',', '.'); ?></td>
                                        <td class="py-3 px-2 text-right font-mono text-amber-300 font-bold">R$ <?php echo number_format($b['comissao_receber'], 2, ',', '.'); ?></td>
                                        <td class="py-3 px-2 text-right font-mono text-emerald-400">R$ <?php echo number_format($b['lucro_casa'], 2, ',', '.'); ?></td>
                                        <td class="py-3 px-2 text-right font-mono text-zinc-400"><?php echo number_format($b['participacao_pct'], 1, ',', '.'); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="py-6 text-center text-zinc-500">Nenhum atendimento registrado no período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ranking de Serviços & Horários de Pico (5 colunas) -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-zinc-900/90 border border-white/10 rounded-3xl p-6 print-card">
                    <h2 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                        <span>✂️</span> Serviços Mais Lucrativos
                    </h2>
                    <p class="text-xs text-zinc-400 mb-4">Curva ABC de receita por serviço.</p>
                    <div class="space-y-3">
                        <?php if (!empty($relatorio['ranking_servicos'])): ?>
                            <?php foreach ($relatorio['ranking_servicos'] as $s): ?>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="font-semibold text-white"><?php echo htmlspecialchars($s['servico']); ?> <span class="text-zinc-400 font-normal">(<?php echo (int)$s['quantidade']; ?>x)</span></span>
                                        <span class="font-mono text-amber-300 font-bold">R$ <?php echo number_format($s['receita'], 2, ',', '.'); ?> (<?php echo $s['participacao_pct']; ?>%)</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-black/60 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-amber-500 to-amber-300 rounded-full" style="width: <?php echo min(100, max(5, (float)$s['participacao_pct'])); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-zinc-500">Sem serviços ativos para exibir.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-zinc-900/90 border border-white/10 rounded-3xl p-6 print-card">
                    <h2 class="text-base font-bold text-white mb-2 flex items-center gap-2">
                        <span>⏰</span> Horários de Maior Demanda (Pico)
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <?php if (!empty($relatorio['horarios_pico'])): ?>
                            <?php foreach ($relatorio['horarios_pico'] as $hp): ?>
                                <span class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-xs font-mono text-amber-300">
                                    <strong><?php echo htmlspecialchars($hp['horario']); ?></strong> • <?php echo (int)$hp['atendimentos']; ?> corte(s)
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-xs text-zinc-500">Sem dados de horário.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>

