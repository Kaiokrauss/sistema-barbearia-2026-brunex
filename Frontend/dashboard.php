<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.html');
    exit;
}
$user = $_SESSION['user'];
$perfil = strtolower($user['perfil'] ?? 'cliente');
$isAdmin = in_array($perfil, ['admin', 'barbeiro']);
$isBarbeiro = $perfil === 'barbeiro';
$isMasterAdmin = $perfil === 'admin';

$tituloPainel = $isMasterAdmin ? '👑 Painel do Administrador' : ($isBarbeiro ? '✂️ Painel do Barbeiro' : '👤 Painel do Cliente');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tituloPainel; ?> - Barbearia VIP</title>
    <!-- Google Fonts: Cinzel & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #0A0A0C;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.18) 0%, rgba(15, 15, 20, 0.7) 45%, rgba(10, 10, 12, 1) 90%),
                radial-gradient(circle at 10% 40%, rgba(212, 175, 55, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 60%, rgba(212, 175, 55, 0.04) 0%, transparent 40%);
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #f4f4f5;
        }
        .font-display { font-family: 'Cinzel', serif; }
        .glass-card {
            background: rgba(18, 18, 24, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: 0 35px 80px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        }
        .gold-btn {
            background: linear-gradient(135deg, #FFF1D0 0%, #D4AF37 50%, #B38728 100%);
            color: #0A0A0C;
            box-shadow: 0 10px 25px -5px rgba(212, 175, 55, 0.4);
        }
        .gold-btn:hover {
            background: linear-gradient(135deg, #FFFFFF 0%, #E5C158 50%, #C9972E 100%);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-6 text-white">
    <div class="max-w-5xl mx-auto">
        <!-- BARRA DE NAVEGAÇÃO INTEGRADA -->
        <nav class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <span class="text-2xl">💈</span>
                <span class="font-display font-bold text-amber-400 text-lg">Barbearia VIP</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <a href="index.html" class="rounded-xl px-3 py-2 text-zinc-300 hover:bg-white/10 hover:text-white transition">🏠 Início</a>
                <a href="index.html#tab-agendar" class="rounded-xl px-3 py-2 text-zinc-300 hover:bg-white/10 hover:text-white transition">✂️ Agendar</a>
                <?php if ($isAdmin): ?>
                    <a href="index.html#tab-dashboard" class="rounded-xl px-3 py-2 text-amber-400 bg-amber-500/10 border border-amber-500/30 hover:bg-amber-500/20 font-semibold transition">📊 Resumo & Faturamento Bruto</a>
                    <a href="index.html#tab-horarios" class="rounded-xl px-3 py-2 text-zinc-300 hover:bg-white/10 hover:text-white transition">⏰ Horários</a>
                    <a href="../views/admin/servicos.html" class="rounded-xl px-3 py-2 text-zinc-300 hover:bg-white/10 hover:text-white transition">📋 Serviços</a>
                    <a href="../views/admin/agendamentos.html" class="rounded-xl px-3 py-2 text-zinc-300 hover:bg-white/10 hover:text-white transition">📥 Exportações</a>
                <?php endif; ?>
                <a href="logout.php" class="rounded-xl bg-red-500/15 border border-red-500/30 px-3 py-2 text-red-300 hover:bg-red-500/30 transition">🚪 Sair</a>
            </div>
        </nav>

        <!-- CABEÇALHO DO USUÁRIO -->
        <header class="mb-8 flex flex-col gap-4 rounded-3xl border border-amber-500/20 glass-card p-6 sm:p-8 shadow-2xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/15 border border-amber-500/30 text-amber-300">
                            <?php echo $perfil; ?>
                        </span>
                        <p class="text-xs uppercase tracking-[0.25em] text-zinc-400 font-bold"><?php echo $tituloPainel; ?></p>
                    </div>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-display font-bold text-white">Bem-vindo(a), <?php echo htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                </div>
                <div class="flex gap-2.5 items-center flex-wrap">
                    <a href="index.html" class="rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2.5 text-xs font-semibold text-white transition">Ir ao Início</a>
                    <?php if ($isAdmin): ?>
                        <a href="index.html#tab-dashboard" class="rounded-xl gold-btn px-4 py-2.5 text-xs font-bold transition">📊 Ver Resumo VIP</a>
                    <?php else: ?>
                        <a href="index.html#tab-agendar" class="rounded-xl gold-btn px-4 py-2.5 text-xs font-bold transition">✂️ Agendar Corte</a>
                    <?php endif; ?>
                    <a href="logout.php" class="rounded-xl bg-red-500/15 border border-red-500/30 hover:bg-red-500/30 px-3.5 py-2.5 text-xs font-semibold text-red-300 transition">Sair</a>
                </div>
            </div>
            <div class="pt-4 border-t border-white/5 flex flex-wrap gap-4 text-xs text-zinc-400">
                <p>📧 E-mail: <strong class="text-zinc-200"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <?php if (!empty($user['telefone'])): ?>
                    <p>📱 Telefone: <strong class="text-zinc-200"><?php echo htmlspecialchars($user['telefone'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- MÓDULO 1: FERRAMENTAS -->
            <section class="glass-card rounded-3xl p-6 border border-white/10">
                <h2 class="text-lg font-bold font-display text-white flex items-center gap-2">
                    <span class="text-amber-400"><?php echo $isAdmin ? '⚙️' : '📋'; ?></span>
                    <?php echo $isAdmin ? 'Módulos Administrativos' : 'Seu Histórico & Agendamentos'; ?>
                </h2>
                <p class="mt-2 text-xs text-zinc-400">
                    <?php echo $isAdmin ? 'Acesse o controle completo da barbearia, métricas e serviços.' : 'Gerencie seus horários na barbearia de forma rápida e segura.'; ?>
                </p>
                
                <div class="mt-5 space-y-3">
                    <?php if ($isAdmin): ?>
                        <a href="index.html#tab-dashboard" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-amber-500/20 hover:border-amber-500/40 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition">📊 Resumo com Faturamento Bruto & Gráficos</p>
                                <p class="text-xs text-zinc-400">Faturamento Bruto (R$), receita confirmada, métricas e gráficos Chart.js</p>
                            </div>
                            <span class="text-amber-400 text-sm">→</span>
                        </a>
                        <a href="index.html#tab-horarios" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/10 hover:border-white/20 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition">⏰ Gerenciar Horários de Atendimento</p>
                                <p class="text-xs text-zinc-400">Liberar ou bloquear horários e dias inteiros</p>
                            </div>
                            <span class="text-zinc-400 text-sm">→</span>
                        </a>
                        <a href="../views/admin/servicos.html" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/10 hover:border-white/20 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition">📋 Gerenciar Serviços da Barbearia</p>
                                <p class="text-xs text-zinc-400">Cadastrar novos cortes, alterar preços e duração</p>
                            </div>
                            <span class="text-zinc-400 text-sm">→</span>
                        </a>
                        <a href="../views/admin/agendamentos.html" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/10 hover:border-white/20 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition">📥 Exportações (CSV / XML / JSON)</p>
                                <p class="text-xs text-zinc-400">Exportar dados utilizando o padrão de projeto Adapter</p>
                            </div>
                            <span class="text-zinc-400 text-sm">→</span>
                        </a>
                    <?php else: ?>
                        <a href="index.html#tab-agendar" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-amber-500/20 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition">✂️ Agendar Novo Horário</p>
                                <p class="text-xs text-zinc-400">Escolha o barbeiro, o serviço e o horário ideal</p>
                            </div>
                            <span class="text-amber-400 text-sm">→</span>
                        </a>
                        <a href="index.html#tab-cancelar" class="flex items-center justify-between p-3.5 rounded-2xl bg-white/[0.03] hover:bg-white/[0.07] border border-white/10 transition group">
                            <div>
                                <p class="text-sm font-semibold text-white group-hover:text-red-400 transition">❌ Cancelar um Horário</p>
                                <p class="text-xs text-zinc-400">Insira o código do agendamento para cancelar</p>
                            </div>
                            <span class="text-zinc-400 text-sm">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- MÓDULO 2: ATALHOS RÁPIDOS -->
            <section class="glass-card rounded-3xl p-6 border border-white/10 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-bold font-display text-white flex items-center gap-2">
                        <span class="text-amber-400">⚡</span> Ações Rápidas
                    </h2>
                    <p class="mt-2 text-xs text-zinc-400">Navegue pelas áreas principais do sistema:</p>
                    
                    <div class="mt-5 space-y-2.5">
                        <a href="index.html" class="block w-full text-center rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 py-3 text-xs font-semibold text-white transition">
                            🏠 Ir para Página Inicial da Barbearia
                        </a>
                        <?php if ($isAdmin): ?>
                            <a href="admin.html" class="block w-full text-center rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 py-3 text-xs font-semibold text-white transition">
                                📈 Abrir Painel com Gráficos Dedicado (admin.html)
                            </a>
                            <a href="index.html#tab-dashboard" class="block w-full text-center rounded-xl gold-btn py-3 text-xs font-bold transition">
                                💰 Visualizar Faturamento e Clientes
                            </a>
                        <?php else: ?>
                            <a href="index.html#tab-agendar" class="block w-full text-center rounded-xl gold-btn py-3 text-xs font-bold transition">
                                ✂️ Fazer Agendamento Agora
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-white/5 text-center">
                    <a href="logout.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-400 hover:text-red-300 transition">
                        <span>🚪</span> Sair da Conta e Encerrar Sessão
                    </a>
                </div>
            </section>
        </div>
    </div>
</body>
</html>