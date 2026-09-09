<?php
/**
 * View: agendar.php
 * Tab de agendamento. Variáveis esperadas do Controller:
 *   $servicos      → array de serviços do banco ['id', 'nome', 'preco', 'duracao_minutos']
 *   $mensagem      → string de feedback (opcional)
 *   $tipoMensagem  → 'success' | 'erro'
 */
if (!isset($servicos)) {
    require_once __DIR__ . '/../../Models/Database.php';
    require_once __DIR__ . '/../../Models/Servico.php';

    $conn = Database::getInstance()->getConnection();
    $servicoModel = new Servico($conn);
    $stmt = $servicoModel->lerTodos();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$mensagem = $mensagem ?? '';
$tipoMensagem = $tipoMensagem ?? 'success';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Atendimento - Barbearia VIP</title>
    <link rel="stylesheet" href="../../Frontend/style.css">
    <link rel="stylesheet" href="agendar.css">
    <style>
        body {
            background-color: var(--bg);
            color: var(--text);
            padding: 20px;
        }
        .container-agendar {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container-agendar">
        <!-- BARRA DE NAVEGAÇÃO INTEGRADA -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; padding: 12px 18px; background: rgba(255,255,255,0.05); border-radius: 14px; border: 1px solid rgba(255,255,255,0.1); flex-wrap: wrap; gap: 10px;">
            <div style="display:flex; align-items:center; gap: 10px;">
                <span style="font-size: 1.4rem;">💈</span>
                <strong style="color: var(--secondary); font-size: 1.1rem;">Barbearia VIP</strong>
            </div>
            <div style="display:flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <a href="../../Frontend/index.html" style="color:var(--text); text-decoration:none;">🏠 Início</a>
                <a href="../../Frontend/index.html#tab-horarios" style="color:var(--text); text-decoration:none;">⏰ Gerenciar Horários</a>
                <a href="../../Frontend/login.html" style="color:var(--secondary); text-decoration:none;">🔐 Entrar</a>
                <a href="../../Frontend/logout.php" style="color:var(--danger); text-decoration:none;">🚪 Sair</a>
            </div>
        </div>

        <div id="tab-agendar" class="tab-content active">

    <!-- ===== CABEÇALHO DA SEÇÃO ===== -->
    <div class="agendar-header">
        <h2>Fazer Agendamento</h2>
        <p class="agendar-subtitulo">Escolha seu serviço, data e horário disponível.</p>
    </div>

    <div id="message" class="flash-msg" style="display:none;"></div>

    <!-- ===== MENSAGEM DE FEEDBACK (flash) ===== -->
    <?php if (!empty($mensagem)): ?>
        <div class="flash-msg flash-<?= htmlspecialchars($tipoMensagem, ENT_QUOTES) ?>">
            <?= $mensagem /* HTML já sanitizado no controller — contém apenas <strong> */ ?>
        </div>
    <?php endif; ?>

    <!-- ===== FORMULÁRIO PRINCIPAL ===== -->
    <form
        id="form-agendamento"
        action=""
        method="POST"
        novalidate
    >
        <!-- CSRF simples (token na sessão) -->
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

        <!-- NOME DO CLIENTE -->
        <div class="form-group">
            <label for="cliente-nome">Seu Nome <span class="obrigatorio">*</span></label>
            <input
                type="text"
                id="cliente-nome"
                name="cliente_nome"
                placeholder="Ex: João da Silva"
                maxlength="120"
                autocomplete="name"
                required
                value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '', ENT_QUOTES) ?>"
            >
        </div>

        <!-- TELEFONE (opcional, para lembretes) -->
        <div class="form-group">
            <label for="cliente-tel">
                Telefone / WhatsApp
                <span class="label-hint">(opcional — para lembrete)</span>
            </label>
            <input
                type="tel"
                id="cliente-tel"
                name="cliente_telefone"
                placeholder="Ex: (11) 9 9999-9999"
                maxlength="20"
                autocomplete="tel"
                value="<?= htmlspecialchars($_POST['cliente_telefone'] ?? '', ENT_QUOTES) ?>"
            >
        </div>

        <!-- SERVIÇO -->
        <div class="form-group">
            <label for="cliente-servico">Serviço <span class="obrigatorio">*</span></label>
            <select id="cliente-servico" name="servico_id" required>
                <option value="">Selecione o que deseja fazer…</option>

                <?php if (!empty($servicos)): ?>
                    <?php foreach ($servicos as $svc): ?>
                        <?php
                            $precoFmt = number_format((float)$svc['preco'], 2, ',', '.');
                            $durFmt   = isset($svc['duracao_minutos'])
                                ? " · {$svc['duracao_minutos']} min"
                                : '';
                            $selected = (isset($_POST['servico_id']) && (int)$_POST['servico_id'] === (int)$svc['id'])
                                ? 'selected' : '';
                        ?>
                        <option value="<?= (int)$svc['id'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($svc['nome'], ENT_QUOTES) ?>
                            — R$ <?= $precoFmt ?><?= $durFmt ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>Nenhum serviço cadastrado no momento.</option>
                <?php endif; ?>

            </select>
        </div>

        <!-- DATA -->
        <div class="form-group">
            <label for="cliente-data">Data <span class="obrigatorio">*</span></label>
            <input
                type="date"
                id="cliente-data"
                name="data_agendada"
                min="<?= date('Y-m-d') ?>"
                required
                value="<?= htmlspecialchars($_POST['data_agendada'] ?? '', ENT_QUOTES) ?>"
            >
        </div>

        <!-- HORÁRIO (populado via AJAX) -->
        <div class="form-group">
            <label for="cliente-horario">
                Horário Disponível <span class="obrigatorio">*</span>
            </label>

            <div class="horario-wrapper">
                <select id="cliente-horario" name="horario" required disabled>
                    <option value="">Selecione uma data primeiro</option>
                </select>
                <span id="horario-loading" class="horario-spinner" style="display:none">
                    ⏳ Buscando horários…
                </span>
            </div>

            <!-- Grade visual de horários (acessibilidade extra) -->
            <div id="grade-horarios" class="grade-horarios" style="display:none" aria-label="Horários disponíveis"></div>
        </div>

        <!-- RESUMO ANTES DE CONFIRMAR -->
        <div id="resumo-agendamento" class="resumo-card" style="display:none">
            <p class="resumo-titulo">📋 Resumo do seu agendamento</p>
            <ul class="resumo-lista">
                <li><strong>Nome:</strong>    <span id="res-nome">—</span></li>
                <li><strong>Serviço:</strong> <span id="res-servico">—</span></li>
                <li><strong>Data:</strong>    <span id="res-data">—</span></li>
                <li><strong>Horário:</strong> <span id="res-horario">—</span></li>
            </ul>
        </div>

        <button type="submit" class="btn-success btn-confirmar" id="btn-confirmar" disabled>
            Confirmar Agendamento
        </button>

    </form><!-- /form-agendamento -->

</div><!-- /tab-agendar -->


<!-- ==========================================================================
     JAVASCRIPT DA TAB DE AGENDAMENTO
     Responsável por:
       1. Buscar horários livres via AJAX ao selecionar data
       2. Renderizar grade visual de horários
       3. Atualizar o resumo antes de confirmar
       4. Feedback em tempo real no formulário
========================================================================== -->
<script>
(function () {
    'use strict';

    /* ---- Referências DOM ---- */
    const inputData       = document.getElementById('cliente-data');
    const selectHorario   = document.getElementById('cliente-horario');
    const gradeHorarios   = document.getElementById('grade-horarios');
    const spinner         = document.getElementById('horario-loading');
    const btnConfirmar    = document.getElementById('btn-confirmar');
    const resumoCard      = document.getElementById('resumo-agendamento');
    const inputNome       = document.getElementById('cliente-nome');
    const selectServico   = document.getElementById('cliente-servico');

    /* ---- Endpoint JSON ---- */
    const basePath = window.location.pathname.includes('/views/')
        ? window.location.pathname.split('/views/')[0]
        : window.location.pathname.replace(/\/[^/]*$/, '');
    const API_BASE = `${window.location.origin}${basePath}`;
    const ENDPOINT_HORARIOS = `${API_BASE}/api/agendamento.php?disponiveis=1&data=`;

    /* ---- Formata data YYYY-MM-DD → DD/MM/YYYY ---- */
    function formatarData(iso) {
        if (!iso) return '—';
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    }

    /* ---- Busca horários livres ao trocar a data ---- */
    async function buscarHorariosLivres() {
        const data = inputData.value;
        if (!data) return;

        // Reset UI
        selectHorario.innerHTML = '<option value="">Buscando…</option>';
        selectHorario.disabled  = true;
        gradeHorarios.innerHTML = '';
        gradeHorarios.style.display = 'none';
        spinner.style.display   = 'inline';
        btnConfirmar.disabled   = true;
        resumoCard.style.display = 'none';

        try {
            const resp = await fetch(ENDPOINT_HORARIOS + encodeURIComponent(data));
            if (!resp.ok) throw new Error('Erro HTTP ' + resp.status);
            const json = await resp.json();

            spinner.style.display = 'none';

            if (json.erro) {
                selectHorario.innerHTML = `<option value="">${json.erro}</option>`;
                return;
            }

            const horarios = json.horarios ?? [];

            if (horarios.length === 0) {
                selectHorario.innerHTML = '<option value="">😔 Sem horários disponíveis nesta data</option>';
                return;
            }

            // Popula <select>
            selectHorario.innerHTML = '<option value="">Escolha um horário</option>';
            horarios.forEach(h => {
                const opt = document.createElement('option');
                opt.value       = h;
                opt.textContent = h;
                selectHorario.appendChild(opt);
            });
            selectHorario.disabled = false;

            // Popula grade visual
            gradeHorarios.innerHTML = '';
            horarios.forEach(h => {
                const btn = document.createElement('button');
                btn.type        = 'button';
                btn.className   = 'horario-btn';
                btn.textContent = h;
                btn.dataset.horario = h;
                btn.addEventListener('click', () => selecionarHorario(h));
                gradeHorarios.appendChild(btn);
            });
            gradeHorarios.style.display = 'flex';

        } catch (err) {
            spinner.style.display = 'none';
            selectHorario.innerHTML = '<option value="">Falha ao buscar horários. Tente novamente.</option>';
            console.error('buscarHorariosLivres:', err);
        }
    }

    /* ---- Seleciona horário pela grade visual ---- */
    function selecionarHorario(horario) {
        selectHorario.value = horario;

        // Destaca botão selecionado
        gradeHorarios.querySelectorAll('.horario-btn').forEach(btn => {
            btn.classList.toggle('horario-btn--ativo', btn.dataset.horario === horario);
        });

        atualizarResumo();
    }

    /* ---- Atualiza o card de resumo ---- */
    function atualizarResumo() {
        const nome     = inputNome.value.trim();
        const servOpt  = selectServico.options[selectServico.selectedIndex];
        const horario  = selectHorario.value;
        const data     = inputData.value;

        const tudo = nome && selectServico.value && horario && data;

        if (tudo) {
            document.getElementById('res-nome').textContent    = nome;
            document.getElementById('res-servico').textContent = servOpt.textContent;
            document.getElementById('res-data').textContent    = formatarData(data);
            document.getElementById('res-horario').textContent = horario;
            resumoCard.style.display = 'block';
            btnConfirmar.disabled    = false;
        } else {
            resumoCard.style.display = 'none';
            btnConfirmar.disabled    = true;
        }
    }

    /* ---- Sync: quando usuário troca via <select> de horários ---- */
    selectHorario.addEventListener('change', function () {
        // Sincroniza grade
        gradeHorarios.querySelectorAll('.horario-btn').forEach(btn => {
            btn.classList.toggle('horario-btn--ativo', btn.dataset.horario === this.value);
        });
        atualizarResumo();
    });

    const formAgendamento = document.getElementById('form-agendamento');
    const messageBox = document.getElementById('message');

    function mostrarMensagem(html, type = 'success') {
        if (!messageBox) return;
        messageBox.className = `flash-msg flash-${type}`;
        messageBox.innerHTML = html;
        messageBox.style.display = 'block';
    }

    formAgendamento.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (btnConfirmar.disabled) return;

        const formData = new FormData(this);

        try {
            const response = await fetch(`${API_BASE}/api/agendamento.php`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (response.ok && data.success) {
                const codigoTexto = data.codigo ? ` | Código do agendamento: <span class="codigo-destaque">${data.codigo}</span>` : '';
                let zapHtml = '';
                if (data.whatsapp_url_cliente) {
                    zapHtml += `<div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="${data.whatsapp_url_cliente}" target="_blank" style="background:#25D366; color:#000; font-weight:bold; padding:10px 16px; border-radius:10px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                            <span>📲 Enviar Confirmação via WhatsApp</span>
                        </a>`;
                }
                if (data.whatsapp_url_barbeiro) {
                    zapHtml += `<a href="${data.whatsapp_url_barbeiro}" target="_blank" style="background:rgba(255,255,255,0.12); color:#fff; font-weight:600; padding:10px 16px; border-radius:10px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                            <span>💬 Notificar Barbeiro no WhatsApp</span>
                        </a></div>`;
                } else if (zapHtml) {
                    zapHtml += `</div>`;
                }

                mostrarMensagem(`<div><strong>🎉 ${data.success}</strong> ${codigoTexto}</div>${zapHtml}`, 'success');

                if (data.whatsapp_url_cliente && formData.get('cliente_telefone')) {
                    window.open(data.whatsapp_url_cliente, '_blank');
                }

                this.reset();
                selectHorario.innerHTML = '<option value="">Selecione uma data primeiro</option>';
                selectHorario.disabled = true;
                gradeHorarios.innerHTML = '';
                gradeHorarios.style.display = 'none';
                resumoCard.style.display = 'none';
                btnConfirmar.disabled = true;
            } else {
                mostrarMensagem(data.error || 'Erro ao criar agendamento.', 'erro');
            }
        } catch (err) {
            console.error('Erro ao enviar agendamento:', err);
            mostrarMensagem('Falha ao cadastrar agendamento. Tente novamente.', 'erro');
        }
    });

    /* ---- Eventos gerais ---- */
    inputData.addEventListener('change', buscarHorariosLivres);
    inputNome.addEventListener('input', atualizarResumo);
    selectServico.addEventListener('change', atualizarResumo);

    /* ---- Se data já vem preenchida (ex: retorno de erro de validação) ---- */
    if (inputData.value) buscarHorariosLivres();

})();
</script>
    </div>
</body>
</html>
