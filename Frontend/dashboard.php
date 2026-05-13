<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.html');
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel - Borcelle Barbearia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #121212; color: #f8f8f8; }
        .panel-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,0.12); }
    </style>
</head>
<body class="min-h-screen p-6 text-white">
    <div class="max-w-4xl mx-auto">
        <header class="mb-10 flex flex-col gap-4 rounded-[28px] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/30">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-[#D4AF37]">Painel do Usuário</p>
                    <h1 class="mt-2 text-3xl font-semibold">Bem-vindo, <?php echo htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                </div>
                <div class="flex gap-3 items-center">
                    <a href="login.html" class="rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white hover:bg-white/20">Voltar ao Login</a>
                    <a href="logout.php" class="rounded-2xl bg-[#D4AF37] px-5 py-3 text-sm font-semibold text-black hover:bg-[#E1B12C]">Sair</a>
                </div>
            </div>
            <p class="text-sm text-gray-300">E-mail: <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?> | Telefone: <?php echo htmlspecialchars($user['telefone'], ENT_QUOTES, 'UTF-8'); ?></p>
        </header>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel-card rounded-[28px] p-6">
                <h2 class="text-xl font-semibold text-white">Seu painel</h2>
                <p class="mt-3 text-gray-300">Aqui você pode ver os próximos passos para usar o sistema de agendamento.</p>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-gray-200">
                    <li>Acesse a barra de navegação para agendar.</li>
                    <li>Use a área administrativa para gerenciar horários.</li>
                    <li>Desconecte-se ao terminar.</li>
                </ul>
            </section>
            <section class="panel-card rounded-[28px] p-6">
                <h2 class="text-xl font-semibold text-white">Próximo passo</h2>
                <p class="mt-3 text-gray-300">Acesse a página principal de administração ou aguarde implementação de fluxos adicionais.</p>
                <div class="mt-5 space-y-3">
                    <a href="login.html" class="block rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white hover:bg-white/20">Voltar para login</a>
                    <a href="logout.php" class="block rounded-2xl bg-[#D4AF37] px-5 py-3 text-sm font-semibold text-black hover:bg-[#E1B12C]">Finalizar sessão</a>
                </div>
            </section>
        </div>
    </div>
</body>
</html>