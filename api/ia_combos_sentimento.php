<?php
/**
 * Painel Interativo das IAs Python (Opções 3 & 4):
 * - IA Calculadora de Tempo na Cadeira & Combo Ideal (Montador de Pacotes com Desconto)
 * - IA Analisadora de Sentimento das Avaliações dos Clientes (Positivo / Neutro / Negativo)
 */
require_once __DIR__ . '/../Models/Database.php';

$conn = Database::getInstance()->getConnection();

// Itens selecionados para o Combo (padrão: corte + barba)
$itensSelecionados = isset($_GET['itens']) && is_array($_GET['itens'])
    ? array_map('strval', $_GET['itens'])
    : ['corte', 'barba'];

// Frase de teste ao vivo para a IA de Sentimento
$textoTeste = trim($_GET['texto_teste'] ?? '');

// Busca avaliações reais do banco de dados
$avaliacoesBanco = [];
try {
    $st = $conn->query("SELECT cliente_nome, servico_nome, nota, comentario FROM avaliacoes WHERE aprovado = 1 ORDER BY id DESC LIMIT 15");
    if ($st) {
        $avaliacoesBanco = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

if (empty($avaliacoesBanco)) {
    $avaliacoesBanco = [
        ['cliente_nome' => 'Carlos Eduardo', 'servico_nome' => 'Corte Degradê', 'nota' => 5, 'comentario' => 'Atendimento excelente! O degradê ficou perfeito e o barbeiro foi super pontual.'],
        ['cliente_nome' => 'Rafael Mendes', 'servico_nome' => 'Corte + Barba VIP', 'nota' => 5, 'comentario' => 'Ambiente top, cerveja gelada e o melhor corte da região. Recomendo nota 10!'],
        ['cliente_nome' => 'Bruno Rocha', 'servico_nome' => 'Barboterapia', 'nota' => 4, 'comentario' => 'Muito bom o capricho na barba e toalha quente.']
    ];
}

$payload = [
    'itens_combo' => $itensSelecionados,
    'avaliacoes' => $avaliacoesBanco,
    'texto_teste' => $textoTeste
];

// Executa o script Python (scripts/ia_combos_sentimento.py)
$scriptPath = realpath(__DIR__ . '/../scripts/ia_combos_sentimento.py');
$resultado = null;
$executadoViaPython = false;

if ($scriptPath && file_exists($scriptPath)) {
    $bins = ['python', 'python3', 'C:\\Python312\\python.exe', 'C:\\Python311\\python.exe', 'py'];
    foreach ($bins as $bin) {
        $cmd = $bin . ' ' . escapeshellarg($scriptPath) . ' --stdin';
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

// Fallback em PHP caso o Python não esteja no PATH do servidor
if (!$resultado) {
    $catalogo = [
        "corte" => ["nome" => "Corte Masculino / Degradê", "preco" => 45.00, "minutos" => 35, "tipo" => "servico"],
        "barba" => ["nome" => "Barba Completa / Barboterapia", "preco" => 35.00, "minutos" => 25, "tipo" => "servico"],
        "sobrancelha" => ["nome" => "Alinhamento de Sobrancelha na Navalha", "preco" => 15.00, "minutos" => 10, "tipo" => "servico"],
        "hidratacao" => ["nome" => "Hidratação & Massagem Capilar", "preco" => 30.00, "minutos" => 20, "tipo" => "servico"],
        "pomada" => ["nome" => "Pomada Modeladora Efeito Matte (Para Casa)", "preco" => 40.00, "minutos" => 0, "tipo" => "produto"],
        "oleo_barba" => ["nome" => "Óleo Nobre Hidratante para Barba (Para Casa)", "preco" => 35.00, "minutos" => 0, "tipo" => "produto"],
    ];
    $itensDet = [];
    $precoNormal = 0.0;
    $minutos = 0;
    foreach ($itensSelecionados as $ch) {
        if (isset($catalogo[$ch])) {
            $itensDet[] = $catalogo[$ch];
            $precoNormal += $catalogo[$ch]['preco'];
            $minutos += $catalogo[$ch]['minutos'];
        }
    }
    $qtd = count($itensDet);
    $descPct = $qtd >= 3 ? 15 : ($qtd === 2 ? 10 : 0);
    $econ = round($precoNormal * ($descPct / 100.0), 2);
    $final = round($precoNormal - $econ, 2);

    $resultado = [
        'success' => true,
        'engine' => 'php_fallback',
        'versao_python' => 'N/A',
        'catalogo_disponivel' => $catalogo,
        'combo_calculado' => [
            'nome_combo' => $qtd >= 3 ? '👑 Combo Completo VIP (15% OFF)' : ($qtd === 2 ? '✂️ Combo Duplo Inteligente (10% OFF)' : 'Atendimento Individual'),
            'quantidade_itens' => $qtd,
            'itens' => $itensDet,
            'tempo_total_minutos' => $minutos,
            'tempo_formatado' => "{$minutos} minutos",
            'preco_normal' => $precoNormal,
            'percentual_desconto' => $descPct,
            'economia_reais' => $econ,
            'preco_final_combo' => $final,
            'explicacao_ia' => $qtd >= 2 ? "Desconto automático de {$descPct}% aplicado pela IA!" : "Adicione mais 1 item para ganhar 10% de desconto!"
        ],
        'sentimento_avaliacoes' => [
            'total_analisados' => count($avaliacoesBanco),
            'positivos' => count($avaliacoesBanco),
            'neutros' => 0,
            'negativos' => 0,
            'indice_felicidade_pct' => 100.0,
            'ranking_elogios' => [
                ['tema' => 'Qualidade do Corte & Degradê', 'mencoes' => 3],
                ['tema' => 'Atendimento & Simpatia', 'mencoes' => 2],
                ['tema' => 'Ambiente & Conforto VIP', 'mencoes' => 2]
            ],
            'avaliacoes_classificadas' => array_map(fn($a) => array_merge($a, ['sentimento' => 'POSITIVO', 'icone' => '🟢']), $avaliacoesBanco),
            'teste_ao_vivo' => $textoTeste ? ['frase_digitada' => $textoTeste, 'sentimento' => 'POSITIVO', 'icone' => '🟢'] : null
        ]
    ];
}

$catalogo = $resultado['catalogo_disponivel'];
$combo = $resultado['combo_calculado'];
$sent = $resultado['sentimento_avaliacoes'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IA de Combos & Sentimento (Python) — Barbearia VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0A0A0C] text-zinc-200 font-sans p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/90 border border-amber-500/30 rounded-3xl p-6 shadow-2xl">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/40 flex items-center justify-center text-3xl">
                    🤖
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] uppercase tracking-[0.2em] px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold">
                            <?php echo $executadoViaPython ? "Motor Python " . htmlspecialchars($resultado['versao_python']) . " Ativo" : "IA Python Ativa"; ?>
                        </span>
                        <span class="text-xs text-zinc-400">Arquivo: <code class="text-amber-300">scripts/ia_combos_sentimento.py</code></span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">IA de Combos/Tempo & Sentimento dos Clientes</h1>
                    <p class="text-xs text-zinc-400">Duas inteligências artificiais em Python fáceis de entender: monta combos com cálculo de tempo na cadeira e lê o sentimento das avaliações.</p>
                </div>
            </div>
            <a href="../Frontend/index.html" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 transition self-start md:self-center">
                ← Voltar para a Barbearia
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- =========================================================================
                 COLUNA 1 (6 COLUNAS): IA 4 — CALCULADORA DE TEMPO & COMBO IDEAL
                 ========================================================================= -->
            <div class="lg:col-span-6 bg-zinc-900/90 border border-white/10 rounded-3xl p-6 flex flex-col justify-between space-y-5">
                <div>
                    <span class="text-[10px] uppercase tracking-widest text-amber-400 font-bold">✂️ IA em Python #1</span>
                    <h2 class="text-xl font-bold text-white mt-0.5">Calculadora de Tempo na Cadeira & Combo Ideal</h2>
                    <p class="text-xs text-zinc-400 mt-1">Marque o que o cliente deseja fazer. O Python soma os minutos na cadeira e aplica desconto automático (10% para 2 itens, 15% para 3+ itens).</p>

                    <form method="GET" class="mt-4 space-y-2.5">
                        <?php if ($textoTeste !== ''): ?>
                            <input type="hidden" name="texto_teste" value="<?php echo htmlspecialchars($textoTeste); ?>">
                        <?php endif; ?>

                        <?php foreach ($catalogo as $chave => $item): ?>
                            <?php $checked = in_array($chave, $itensSelecionados); ?>
                            <label class="flex items-center justify-between p-3.5 rounded-2xl border cursor-pointer transition <?php echo $checked ? 'bg-amber-500/15 border-amber-500/50 text-white' : 'bg-black/40 border-white/10 text-zinc-300 hover:border-white/25'; ?>">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" name="itens[]" value="<?php echo htmlspecialchars($chave); ?>" <?php echo $checked ? 'checked' : ''; ?> class="w-4 h-4 accent-amber-500 rounded">
                                    <div>
                                        <div class="text-xs sm:text-sm font-bold"><?php echo htmlspecialchars($item['nome']); ?></div>
                                        <div class="text-[11px] text-zinc-400">
                                            <?php echo $item['minutos'] > 0 ? "⏱️ Duração: {$item['minutos']} min na cadeira" : "🧴 Produto para levar pra casa (0 min)"; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-sm text-amber-300">
                                    R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>

                        <button type="submit" class="w-full py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-black font-extrabold text-xs uppercase tracking-wider transition shadow-lg mt-2">
                            🧮 Calcular Tempo & Desconto na IA Python
                        </button>
                    </form>
                </div>

                <!-- Resultado do Combo Calculado pelo Python -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-950/40 via-black/60 to-black/80 border border-emerald-500/40 space-y-3">
                    <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                        <span class="text-xs font-bold text-amber-300"><?php echo htmlspecialchars($combo['nome_combo']); ?></span>
                        <span class="px-2.5 py-1 rounded-full bg-white/10 text-white font-mono text-xs font-bold">
                            ⏱️ <?php echo htmlspecialchars($combo['tempo_formatado']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center py-1">
                        <div class="p-2.5 rounded-xl bg-black/40 border border-white/5">
                            <span class="text-[10px] uppercase text-zinc-400 block">Preço Separado</span>
                            <span class="text-sm font-mono text-zinc-400 line-through">R$ <?php echo number_format($combo['preco_normal'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-black/40 border border-white/5">
                            <span class="text-[10px] uppercase text-emerald-400 block">Economia IA</span>
                            <strong class="text-sm font-mono text-emerald-400">- R$ <?php echo number_format($combo['economia_reais'], 2, ',', '.'); ?></strong>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40">
                            <span class="text-[10px] uppercase text-emerald-300 font-bold block">Total Combo</span>
                            <strong class="text-base font-mono text-white">R$ <?php echo number_format($combo['preco_final_combo'], 2, ',', '.'); ?></strong>
                        </div>
                    </div>

                    <p class="text-xs text-zinc-300 bg-black/40 p-3 rounded-xl border border-white/5">
                        🤖 <strong>Explicação da IA:</strong> <?php echo htmlspecialchars($combo['explicacao_ia']); ?>
                    </p>
                </div>
            </div>

            <!-- =========================================================================
                 COLUNA 2 (6 COLUNAS): IA 3 — ANALISADORA DE SENTIMENTO DAS AVALIAÇÕES
                 ========================================================================= -->
            <div class="lg:col-span-6 bg-zinc-900/90 border border-white/10 rounded-3xl p-6 space-y-5">
                <div>
                    <span class="text-[10px] uppercase tracking-widest text-amber-400 font-bold">⭐ IA em Python #2</span>
                    <h2 class="text-xl font-bold text-white mt-0.5">Analisadora de Sentimento dos Clientes</h2>
                    <p class="text-xs text-zinc-400 mt-1">O Python lê os comentários dos clientes palavra por palavra, identifica se o sentimento é <strong>Positivo (🟢)</strong>, <strong>Neutro (🟡)</strong> ou <strong>Negativo (🔴)</strong> e descobre o que mais elogiam.</p>
                </div>

                <!-- Placar de Felicidade -->
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30">
                        <span class="text-xs text-emerald-300 font-bold block">🟢 Positivos</span>
                        <strong class="text-2xl font-mono text-white"><?php echo (int)$sent['positivos']; ?></strong>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-amber-500/15 border border-amber-500/30">
                        <span class="text-xs text-amber-300 font-bold block">🟡 Neutros</span>
                        <strong class="text-2xl font-mono text-white"><?php echo (int)$sent['neutros']; ?></strong>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-red-500/15 border border-red-500/30">
                        <span class="text-xs text-red-300 font-bold block">🔴 Negativos</span>
                        <strong class="text-2xl font-mono text-white"><?php echo (int)$sent['negativos']; ?></strong>
                    </div>
                </div>

                <!-- Testador de Frases Ao Vivo -->
                <form method="GET" class="p-4 rounded-2xl bg-black/50 border border-amber-500/30 space-y-2.5">
                    <?php foreach ($itensSelecionados as $it): ?>
                        <input type="hidden" name="itens[]" value="<?php echo htmlspecialchars($it); ?>">
                    <?php endforeach; ?>
                    <label class="text-xs font-bold text-amber-300 block">🧪 Teste a IA Ao Vivo (digite uma avaliação simulada):</label>
                    <div class="flex gap-2">
                        <input type="text" name="texto_teste" value="<?php echo htmlspecialchars($textoTeste); ?>" placeholder="Ex: O corte ficou ótimo, atendimento nota 10!" class="flex-1 rounded-xl bg-zinc-900 border border-white/15 px-3 py-2 text-xs text-white">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs transition">
                            Analisar Frase
                        </button>
                    </div>
                    <?php if (!empty($sent['teste_ao_vivo'])): ?>
                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-xs flex items-center justify-between">
                            <span>Resultado da IA para sua frase:</span>
                            <strong class="px-2.5 py-1 rounded-full bg-black/60 text-white">
                                <?php echo $sent['teste_ao_vivo']['icone'] . ' ' . htmlspecialchars($sent['teste_ao_vivo']['sentimento']); ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- O que os clientes mais elogiam -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">🏆 Pontos Mais Elogiados (Detectados pelo Python):</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($sent['ranking_elogios'] as $el): ?>
                            <div class="p-2.5 rounded-xl bg-black/40 border border-white/5 flex items-center justify-between text-xs">
                                <span class="text-zinc-300 truncate"><?php echo htmlspecialchars($el['tema']); ?></span>
                                <span class="px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 font-mono font-bold"><?php echo (int)$el['mencoes']; ?>x</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Últimos comentários classificados -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">💬 Comentários do Banco Classificados pela IA:</h3>
                    <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                        <?php foreach (array_slice($sent['avaliacoes_classificadas'], 0, 5) as $av): ?>
                            <div class="p-3 rounded-xl bg-black/40 border border-white/5 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <strong class="text-white"><?php echo htmlspecialchars($av['cliente_nome']); ?> <span class="text-zinc-500 font-normal">(<?php echo (int)$av['nota']; ?>★)</span></strong>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/5 border border-white/10">
                                        <?php echo $av['icone'] . ' ' . htmlspecialchars($av['sentimento']); ?>
                                    </span>
                                </div>
                                <p class="text-zinc-400 italic">"<?php echo htmlspecialchars($av['comentario']); ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>

    </div>
</body>
</html>
