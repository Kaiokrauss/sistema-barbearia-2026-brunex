<?php
/**
 * API & View: Gerador de QR Code PIX e Cartão de Visita Digital
 * Permite gerar cobrança rápida via PIX na recepção ou retornar JSON via ?format=json
 */

$chavePix = $_GET['chave'] ?? 'pix@barbeariavip.com.br';
$beneficiario = 'BARBEARIA VIP BORCELLE';
$cidade = 'SAO PAULO';
$valor = isset($_GET['valor']) && is_numeric($_GET['valor']) ? (float)$_GET['valor'] : 45.00;
$descricao = trim($_GET['descricao'] ?? 'Corte Masculino VIP');
$formatoJson = isset($_GET['json']) || (($_GET['format'] ?? '') === 'json');

/**
 * Monta um payload PIX Copia e Cola simplificado (padrão EMV BR Code)
 */
function gerarPayloadPixSimples(string $chave, string $nome, string $cidade, float $valor, string $txid = 'BARBEARIA2026'): string {
    $valorFormatado = number_format($valor, 2, '.', '');
    $payload = "00020126" . sprintf("%02d", strlen("0014BR.GOV.BCB.PIX01" . sprintf("%02d", strlen($chave)) . $chave))
        . "0014BR.GOV.BCB.PIX01" . sprintf("%02d", strlen($chave)) . $chave
        . "520400005303986"
        . "54" . sprintf("%02d", strlen($valorFormatado)) . $valorFormatado
        . "5802BR"
        . "59" . sprintf("%02d", strlen($nome)) . $nome
        . "60" . sprintf("%02d", strlen($cidade)) . $cidade
        . "62" . sprintf("%02d", strlen("05" . sprintf("%02d", strlen($txid)) . $txid)) . "05" . sprintf("%02d", strlen($txid)) . $txid
        . "6304ABCD";
    return $payload;
}

$pixCopiaECola = gerarPayloadPixSimples($chavePix, $beneficiario, $cidade, $valor);
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($pixCopiaECola) . "&bgcolor=0A-0A-0C&color=D4-AF-37&margin=6";

if ($formatoJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'beneficiario' => $beneficiario,
        'chave_pix' => $chavePix,
        'valor' => $valor,
        'valor_formatado' => 'R$ ' . number_format($valor, 2, ',', '.'),
        'descricao' => $descricao,
        'pix_copia_e_cola' => $pixCopiaECola,
        'qrcode_url' => $qrCodeUrl
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C] text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX & Cartão Digital — Barbearia VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-[#0A0A0C] text-zinc-200 font-sans">
    <div class="w-full max-w-md rounded-3xl bg-zinc-900/90 border border-amber-500/30 p-6 shadow-2xl text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-2xl mb-3">
            💈
        </div>
        <p class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-bold">Barbearia VIP Borcelle</p>
        <h1 class="text-xl font-bold text-white mt-1">QR Code PIX & Cartão Digital</h1>
        <p class="text-xs text-zinc-400 mt-1"><?php echo htmlspecialchars($descricao); ?></p>

        <!-- Valor -->
        <div class="my-4 py-3 px-4 rounded-2xl bg-black/40 border border-white/10">
            <span class="text-xs text-zinc-400 block">Valor a Pagar</span>
            <strong class="text-2xl text-emerald-400 font-mono">R$ <?php echo number_format($valor, 2, ',', '.'); ?></strong>
        </div>

        <!-- Atalhos rápidos de valor -->
        <div class="flex justify-center gap-2 mb-4 text-xs">
            <a href="?valor=35&descricao=Barba+VIP" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-amber-500/20 border border-white/10 transition">Barba (R$ 35)</a>
            <a href="?valor=45&descricao=Corte+VIP" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-amber-500/20 border border-white/10 transition">Corte (R$ 45)</a>
            <a href="?valor=70&descricao=Combo+Corte+e+Barba" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-amber-500/20 border border-white/10 transition">Combo (R$ 70)</a>
        </div>

        <!-- QR Code -->
        <div class="inline-block p-3 rounded-2xl bg-black border border-amber-500/40 shadow-lg">
            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code PIX" class="w-48 h-48 mx-auto rounded-xl">
        </div>

        <p class="text-xs text-zinc-400 mt-3">Chave PIX: <strong class="text-amber-300"><?php echo htmlspecialchars($chavePix); ?></strong></p>

        <!-- Botão Copia e Cola -->
        <div class="mt-4">
            <input id="pixCode" type="text" readonly value="<?php echo htmlspecialchars($pixCopiaECola); ?>" class="w-full text-[11px] font-mono bg-black/50 border border-white/10 rounded-xl px-3 py-2 text-zinc-400 text-center mb-2">
            <button onclick="copiarPix()" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs transition">
                📋 Copiar Código PIX Copia e Cola
            </button>
        </div>

        <!-- Cartão de Visita Digital -->
        <div class="mt-6 pt-4 border-t border-white/10 text-xs text-zinc-400 flex justify-between items-center">
            <span>📍 Rua dos Barbeiros, 100</span>
            <a href="../Frontend/index.html" class="text-amber-400 hover:underline font-semibold">← Voltar ao Início</a>
        </div>
    </div>

    <script>
        function copiarPix() {
            const input = document.getElementById('pixCode');
            navigator.clipboard.writeText(input.value);
            alert('Código PIX copiado com sucesso!');
        }
    </script>
</body>
</html>
