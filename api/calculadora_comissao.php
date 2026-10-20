<?php
/**
 * Interface Visual & API que executa a Calculadora de Comissão em Python (scripts/calculadora_comissao.py)
 */

$barbeiro = trim($_GET['barbeiro'] ?? 'Carlos Navalha');
$valor = isset($_GET['valor']) && is_numeric($_GET['valor']) ? (float)$_GET['valor'] : 180.00;
$comissao = isset($_GET['comissao']) && is_numeric($_GET['comissao']) ? (float)$_GET['comissao'] : 50.0;
$gorjeta = isset($_GET['gorjeta']) && is_numeric($_GET['gorjeta']) ? (float)$_GET['gorjeta'] : 15.00;
$cortes = isset($_GET['cortes']) && is_numeric($_GET['cortes']) ? max(1, (int)$_GET['cortes']) : 4;

$scriptPath = realpath(__DIR__ . '/../scripts/calculadora_comissao.py');
$resultado = null;
$executadoViaPython = false;

if ($scriptPath && file_exists($scriptPath)) {
    $cmd = sprintf(
        'python %s --json --barbeiro %s --valor %s --comissao %s --gorjeta %s --cortes %d 2>&1',
        escapeshellarg($scriptPath),
        escapeshellarg($barbeiro),
        escapeshellarg((string)$valor),
        escapeshellarg((string)$comissao),
        escapeshellarg((string)$gorjeta),
        $cortes
    );
    $output = shell_exec($cmd);
    if ($output) {
        $decoded = json_decode($output, true);
        if (is_array($decoded) && !empty($decoded['success'])) {
            $resultado = $decoded;
            $executadoViaPython = true;
        }
    }
}

// Fallback caso o binário python não esteja no PATH do Apache
if (!$resultado) {
    $valorComissao = round($valor * ($comissao / 100.0), 2);
    $resultado = [
        'success' => true,
        'data_calculo' => date('d/m/Y H:i'),
        'barbeiro' => $barbeiro,
        'qtd_cortes' => $cortes,
        'valor_bruto_servicos' => $valor,
        'percentual_comissao' => $comissao,
        'valor_comissao' => $valorComissao,
        'gorjeta' => $gorjeta,
        'total_receber_barbeiro' => round($valorComissao + $gorjeta, 2),
        'lucro_liquido_barbearia' => round($valor - $valorComissao, 2),
        'ticket_medio' => round($valor / max(1, $cortes), 2)
    ];
}

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Comissão (Python) — Barbearia VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[#0A0A0C] text-zinc-200 font-sans">
    <div class="w-full max-w-lg rounded-3xl bg-zinc-900/90 border border-amber-500/30 p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-2xl">
                    🐍
                </div>
                <div>
                    <span class="text-[10px] uppercase tracking-[0.2em] text-amber-400 font-bold">
                        <?php echo $executadoViaPython ? 'Motor Python Ativo (calculadora_comissao.py)' : 'Calculadora de Comissão'; ?>
                    </span>
                    <h1 class="text-xl font-bold text-white">Calculadora de Lucro & Comissão</h1>
                </div>
            </div>
            <a href="../Frontend/index.html" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 transition">
                ← Voltar
            </a>
        </div>

        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            <div class="sm:col-span-2">
                <label class="text-xs text-zinc-400 block mb-1">Profissional / Barbeiro</label>
                <select name="barbeiro" class="w-full rounded-xl bg-black/50 border border-white/15 px-3 py-2.5 text-sm text-white">
                    <?php foreach (['Carlos Navalha', 'Lucas Degradê', 'João Barbeiro'] as $nomeBarb): ?>
                        <option value="<?php echo htmlspecialchars($nomeBarb); ?>" <?php echo $barbeiro === $nomeBarb ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nomeBarb); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Total em Serviços (R$)</label>
                <input type="number" step="0.01" name="valor" value="<?php echo htmlspecialchars((string)$valor); ?>" class="w-full rounded-xl bg-black/50 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Comissão do Barbeiro (%)</label>
                <input type="number" step="1" min="0" max="100" name="comissao" value="<?php echo htmlspecialchars((string)$comissao); ?>" class="w-full rounded-xl bg-black/50 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Gorjetas / Caixinha (R$)</label>
                <input type="number" step="0.01" name="gorjeta" value="<?php echo htmlspecialchars((string)$gorjeta); ?>" class="w-full rounded-xl bg-black/50 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="text-xs text-zinc-400 block mb-1">Qtd. de Cortes</label>
                <input type="number" step="1" min="1" name="cortes" value="<?php echo htmlspecialchars((string)$cortes); ?>" class="w-full rounded-xl bg-black/50 border border-white/15 px-3 py-2 text-sm text-white">
            </div>
            <div class="sm:col-span-2 pt-1">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs transition shadow-lg">
                    🧮 Calcular Comissão no Python
                </button>
            </div>
        </form>

        <!-- Resultado -->
        <div class="rounded-2xl bg-black/50 border border-white/10 p-4 space-y-2.5 text-sm">
            <div class="flex justify-between text-xs text-zinc-400">
                <span>Profissional: <strong class="text-white"><?php echo htmlspecialchars($resultado['barbeiro']); ?></strong></span>
                <span>Ticket Médio: <strong class="text-amber-300">R$ <?php echo number_format($resultado['ticket_medio'], 2, ',', '.'); ?></strong></span>
            </div>
            <div class="border-t border-white/10 pt-2 flex justify-between">
                <span class="text-zinc-400">Comissão (<?php echo (int)$resultado['percentual_comissao']; ?>%):</span>
                <span class="font-mono text-white">R$ <?php echo number_format($resultado['valor_comissao'], 2, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-400">Gorjetas (100% Barbeiro):</span>
                <span class="font-mono text-white">R$ <?php echo number_format($resultado['gorjeta'], 2, ',', '.'); ?></span>
            </div>
            <div class="border-t border-white/10 pt-2.5 flex justify-between items-center">
                <span class="font-bold text-emerald-400">✅ Total a Receber (Barbeiro):</span>
                <span class="font-mono text-lg font-extrabold text-emerald-400">R$ <?php echo number_format($resultado['total_receber_barbeiro'], 2, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="font-bold text-amber-400">🏦 Lucro Líquido (Barbearia):</span>
                <span class="font-mono text-base font-bold text-amber-400">R$ <?php echo number_format($resultado['lucro_liquido_barbearia'], 2, ',', '.'); ?></span>
            </div>
        </div>
    </div>
</body>
</html>
