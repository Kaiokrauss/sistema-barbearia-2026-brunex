<?php
/**
 * API & View: Voucher / Comprovante VIP de Atendimento
 * Renderiza o comprovante oficial com estética de Boarding Pass VIP, QR Code dinâmico
 * e suporte a exportação em PDF/impressão e iCalendar (.ics).
 */
require_once __DIR__ . '/../Models/Database.php';

$codigo = trim($_GET['codigo'] ?? '');
if (empty($codigo)) {
    http_response_code(400);
    die("Código de agendamento não informado.");
}

$db = Database::getInstance()->getConnection();
$sql = "SELECT a.*, s.nome AS servico_nome, s.preco AS servico_preco, s.duracao_minutos 
        FROM agendamentos a 
        LEFT JOIN servicos s ON a.servico_id = s.id 
        WHERE a.codigo = :codigo 
        LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute([':codigo' => $codigo]);
$ag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ag) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-BR" class="bg-[#0A0A0C] text-white"><body class="flex items-center justify-center min-h-screen font-sans text-center p-6"><div class="p-8 rounded-3xl bg-white/5 border border-white/10 max-w-md"><span class="text-4xl">⚠️</span><h1 class="text-xl font-bold mt-3">Agendamento Não Encontrado</h1><p class="text-zinc-400 text-sm mt-2">O código informado não existe ou expirou.</p><a href="../Frontend/index.html" class="mt-5 inline-block px-5 py-2.5 rounded-xl bg-amber-500 text-black font-bold text-xs">Voltar ao Início</a></div></body></html>';
    exit;
}

// Suporte a download de arquivo iCalendar (.ics) para adicionar na agenda do celular
if (isset($_GET['ics'])) {
    $dataHoraInicio = date('Ymd\THis', strtotime($ag['data_agendada'] . ' ' . $ag['horario']));
    $duracao = (int)($ag['duracao_minutos'] ?: 30);
    $dataHoraFim = date('Ymd\THis', strtotime($ag['data_agendada'] . ' ' . $ag['horario'] . " +{$duracao} minutes"));

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Barbearia VIP//Agendamento//PT-BR\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:agendamento-{$codigo}@barbeariavip.com\r\n";
    $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $ics .= "DTSTART:{$dataHoraInicio}\r\n";
    $ics .= "DTEND:{$dataHoraFim}\r\n";
    $ics .= "SUMMARY:Corte na Barbearia VIP - " . $ag['servico_nome'] . "\r\n";
    $ics .= "DESCRIPTION:Agendamento VIP confirmado. Código de cancelamento: {$codigo}\r\n";
    $ics .= "LOCATION:Barbearia VIP Borcelle Exclusive\r\n";
    $ics .= "STATUS:CONFIRMED\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="agendamento_barbearia_' . $codigo . '.ics"');
    echo $ics;
    exit;
}

$timestamp = strtotime($ag['data_agendada']);
$dataFormatada = date('d/m/Y', $timestamp);
$diaSemana = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda-feira',
    'Tuesday' => 'Terça-feira',
    'Wednesday' => 'Quarta-feira',
    'Thursday' => 'Quinta-feira',
    'Friday' => 'Sexta-feira',
    'Saturday' => 'Sábado'
][date('l', $timestamp)] ?? date('l', $timestamp);

$horarioFmt = substr($ag['horario'], 0, 5);
$precoFmt = number_format((float)($ag['servico_preco'] ?? 0), 2, ',', '.');
$status = strtolower($ag['status']);

// URL absoluta de verificação para o QR Code
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$urlValidacao = "{$scheme}://{$host}" . $_SERVER['REQUEST_URI'];
$urlValidacaoSemIcs = strtok($urlValidacao, '&');

// Gerador de QR Code URL (API padrão confiável e rápida)
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($urlValidacaoSemIcs) . "&bgcolor=0A-0A-0C&color=D4-AF-37&margin=5";
?>
<!DOCTYPE html>
<html lang="pt-BR" class="bg-[#0A0A0C]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0A0A0C">
    <title>Voucher VIP #<?php echo htmlspecialchars($codigo); ?> — Barbearia VIP</title>
    <!-- Google Fonts: Cinzel & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['Cinzel', 'serif'],
                        barcode: ['"Libre Barcode 39 Text"', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0A0A0C;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.16) 0%, rgba(15, 15, 20, 0.6) 40%, rgba(10, 10, 12, 1) 90%),
                radial-gradient(circle at 10% 40%, rgba(212, 175, 55, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 60%, rgba(212, 175, 55, 0.04) 0%, transparent 40%);
            background-attachment: fixed;
            color: #d4d4d8;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .font-display { font-family: 'Cinzel', serif; }

        .glass-panel {
            background: rgba(18, 18, 24, 0.85);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(212, 175, 55, 0.28);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        }

        .gold-gradient-text {
            background: linear-gradient(135deg, #FFF1D0 0%, #D4AF37 50%, #B38728 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gold-btn {
            background: linear-gradient(135deg, #EAD086 0%, #D4AF37 50%, #B38728 100%);
            color: #0A0A0C;
            font-weight: 700;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.35);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .gold-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.5);
            filter: brightness(1.08);
        }

        /* Linha perfurada de ticket */
        .perforated-border {
            position: relative;
            border-left: 2px dashed rgba(212, 175, 55, 0.25);
        }

        @media (max-width: 768px) {
            .perforated-border {
                border-left: none;
                border-top: 2px dashed rgba(212, 175, 55, 0.25);
            }
        }

        /* Modo de impressão limpo */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                background-image: none !important;
            }
            .no-print {
                display: none !important;
            }
            .glass-panel {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                box-shadow: none !important;
                color: #000000 !important;
            }
            .gold-gradient-text, h1, h2, h3, p, span, strong {
                color: #000000 !important;
                -webkit-text-fill-color: #000000 !important;
            }
            .ticket-container {
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8 antialiased">

    <!-- AÇÕES SUPERIORES (NO-PRINT) -->
    <div class="no-print w-full max-w-4xl flex items-center justify-between mb-6 gap-3">
        <a href="../Frontend/index.html" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-zinc-300 transition flex items-center gap-2">
            <span>←</span> Voltar à Barbearia
        </a>
        <div class="flex items-center gap-2 flex-wrap">
            <button onclick="window.print()" class="gold-btn px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2">
                <span>🖨️</span> Salvar em PDF / Imprimir
            </button>
            <a href="?codigo=<?php echo urlencode($codigo); ?>&ics=1" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/20 text-xs font-semibold text-white transition flex items-center gap-2" title="Adicionar ao Google Calendar ou Apple Agenda">
                <span>📅</span> Salvar na Agenda (.ics)
            </a>
        </div>
    </div>

    <!-- CARTÃO / VOUCHER VIP (ESTILO BOARDING PASS) -->
    <div class="w-full max-w-4xl glass-panel rounded-3xl overflow-hidden shadow-2xl relative ticket-container">
        
        <!-- FAIXA SUPERIOR DOURADA -->
        <div class="bg-gradient-to-r from-[#B38728] via-[#D4AF37] to-[#EAD086] p-2 text-center text-[11px] uppercase tracking-[0.3em] font-extrabold text-black">
            ★ BORCELLE EXCLUSIVE — PASSE OFICIAL DE ATENDIMENTO VIP ★
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12">
            
            <!-- LADO ESQUERDO: DADOS DO CLIENTE & AGENDAMENTO (8 COLUNAS) -->
            <div class="md:col-span-8 p-6 sm:p-8 flex flex-col justify-between">
                
                <!-- CABEÇALHO DO TICKET -->
                <div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-2xl shadow-md flex-shrink-0">
                                💈
                            </div>
                            <div>
                                <span class="text-[10px] uppercase tracking-[0.25em] text-amber-400 font-bold">Barbearia VIP</span>
                                <h1 class="text-2xl font-display font-bold text-white tracking-wide">Comprovante de Atendimento</h1>
                            </div>
                        </div>

                        <!-- BADGE DE STATUS -->
                        <div>
                            <?php if ($status === 'ativo'): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    ✓ Confirmado
                                </span>
                            <?php elseif ($status === 'concluido'): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-500/40">
                                    ✓ Concluído
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-red-500/20 text-red-300 border border-red-500/40">
                                    ✕ Cancelado
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- GRID DE INFORMAÇÕES -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 my-6">
                        <div>
                            <p class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Cliente VIP</p>
                            <p class="text-base font-semibold text-white mt-1"><?php echo htmlspecialchars($ag['cliente_nome']); ?></p>
                            <?php if (!empty($ag['cliente_telefone'])): ?>
                                <p class="text-xs text-zinc-400 font-mono mt-0.5"><?php echo htmlspecialchars($ag['cliente_telefone']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <p class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Data Agendada</p>
                            <p class="text-base font-semibold text-white mt-1"><?php echo $dataFormatada; ?></p>
                            <p class="text-xs text-amber-400/90 font-medium"><?php echo $diaSemana; ?></p>
                        </div>

                        <div>
                            <p class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Horário Marcado</p>
                            <p class="text-2xl font-display font-extrabold text-amber-400 mt-0.5"><?php echo $horarioFmt; ?></p>
                            <p class="text-[10px] text-zinc-400">Duração: ~<?php echo (int)($ag['duracao_minutos'] ?: 30); ?> min</p>
                        </div>

                        <div class="col-span-2">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Serviço Solicitado</p>
                            <p class="text-base font-semibold text-white mt-1 flex items-center gap-2">
                                <span>✂️</span> <?php echo htmlspecialchars($ag['servico_nome'] ?: 'Corte Masculino VIP'); ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-[10px] uppercase font-bold tracking-wider text-zinc-400">Valor Previsto</p>
                            <p class="text-xl font-mono font-bold text-emerald-300 mt-1">R$ <?php echo $precoFmt; ?></p>
                        </div>
                    </div>
                </div>

                <!-- POLÍTICAS VIP & SEGURANÇA -->
                <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs text-zinc-400">
                    <div>
                        <p class="flex items-center gap-1.5 text-zinc-300">
                            <span>⏱️</span> <strong>Tolerância de 10 minutos.</strong> Chegue 5 min antes.
                        </p>
                        <p class="text-[11px] text-zinc-500 mt-0.5">Apresente este voucher na recepção ou informe seu código de atendimento.</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="text-[10px] text-zinc-400 uppercase tracking-widest block">Código Único</span>
                        <span class="font-mono font-extrabold text-lg text-amber-400 bg-amber-500/10 border border-amber-500/30 px-3 py-1 rounded-xl inline-block tracking-wider">
                            #<?php echo htmlspecialchars($codigo); ?>
                        </span>
                    </div>
                </div>

            </div>

            <!-- LADO DIREITO: QR CODE & CANHOTO DESTACÁVEL (4 COLUNAS) -->
            <div class="md:col-span-4 perforated-border p-6 sm:p-8 flex flex-col items-center justify-between text-center bg-black/20">
                
                <div>
                    <span class="text-[10px] uppercase tracking-widest font-bold text-amber-400">Validação Digital</span>
                    <p class="text-xs text-zinc-400 mt-1">Aponte a câmera do celular para conferir autenticidade</p>
                </div>

                <!-- IMAGEM DO QR CODE -->
                <div class="my-5 p-3 rounded-2xl bg-black/60 border border-amber-500/30 shadow-xl">
                    <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code Agendamento <?php echo htmlspecialchars($codigo); ?>" class="w-40 h-40 object-contain rounded-xl" loading="lazy">
                </div>

                <!-- CÓDIGO DE BARRAS ESTILIZADO -->
                <div>
                    <p class="font-mono text-sm tracking-[0.25em] text-zinc-300 font-bold">VIP-<?php echo htmlspecialchars($codigo); ?>-2026</p>
                    <p class="text-[10px] text-zinc-500 mt-1">Emissão Segura • Sistema Barbearia 2026</p>
                </div>

            </div>

        </div>

        <!-- RODAPÉ DO VOUCHER -->
        <div class="bg-white/[0.02] border-t border-white/5 p-4 text-center text-xs text-zinc-500 flex flex-col sm:flex-row justify-between items-center gap-2">
            <span>Barbearia VIP Borcelle • Rua dos Barbeiros, 100 • Agendamento Online</span>
            <span class="font-mono text-[11px] text-zinc-400">Data de Emissão: <?php echo date('d/m/Y H:i'); ?></span>
        </div>

    </div>

    <!-- BOTÕES COMPARTILHAR (NO-PRINT) -->
    <div class="no-print mt-6 flex items-center gap-3 flex-wrap">
        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode("💈 Olá! Meu agendamento na Barbearia VIP está confirmado para {$dataFormatada} às {$horarioFmt}. Código: #{$codigo}\nVeja meu voucher: {$urlValidacaoSemIcs}"); ?>" target="_blank" class="px-5 py-2.5 rounded-xl bg-[#25D366] hover:bg-[#1EBE5D] text-black font-bold text-xs flex items-center gap-2 transition shadow-lg">
            <span>📲</span> Enviar no WhatsApp
        </a>
        <button onclick="navigator.clipboard.writeText('<?php echo $urlValidacaoSemIcs; ?>'); alert('Link do Voucher copiado para a área de transferência!');" class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-zinc-300 transition flex items-center gap-2">
            <span>🔗</span> Copiar Link do Voucher
        </button>
    </div>

</body>
</html>