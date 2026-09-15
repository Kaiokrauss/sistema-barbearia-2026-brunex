// --- ESTADO DA APLICAÇÃO ---
const defaultHorarios = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

let appState = JSON.parse(localStorage.getItem('barbeariaState')) || {
    senhaAdmin: '1234',
    agendamentos: [],
    bloqueios: {},
    whatsappAdmin: '5511999999999'
};

let isAdminLogged = false;
let currentAdminTab = '';
let ocupacaoChart = null;
let proporcaoChart = null;

// Filtros em tempo real para a Tabela de Agendamentos (Admin)
let filtroAdminStatus = 'hoje';
let filtroAdminBusca = '';

// --- SISTEMA DE TOASTS MODERNOS (NOTIFICAÇÕES FLUTUANTES) ---
function mostrarToast(mensagem, tipo = 'sucesso') {
    let container = document.getElementById('toast-container-vip');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container-vip';
        container.style.cssText = 'position:fixed; top:20px; right:20px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none; max-width:390px; width:calc(100% - 40px);';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.style.cssText = 'pointer-events:auto; display:flex; align-items:center; gap:12px; padding:12px 18px; border-radius:14px; background:rgba(18,18,24,0.96); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); color:#fff; font-size:13.5px; font-weight:500; box-shadow:0 12px 35px rgba(0,0,0,0.65); transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); opacity:0; transform:translateY(-12px) scale(0.96); border:1px solid rgba(255,255,255,0.1);';

    let icone = '✨';
    if (tipo === 'sucesso') {
        icone = '✅';
        toast.style.borderColor = 'rgba(34,197,94,0.45)';
        toast.style.boxShadow = '0 12px 35px rgba(0,0,0,0.65), 0 0 15px rgba(34,197,94,0.15)';
    } else if (tipo === 'erro') {
        icone = '❌';
        toast.style.borderColor = 'rgba(239,68,68,0.45)';
        toast.style.boxShadow = '0 12px 35px rgba(0,0,0,0.65), 0 0 15px rgba(239,68,68,0.15)';
    } else if (tipo === 'aviso') {
        icone = '⚠️';
        toast.style.borderColor = 'rgba(234,179,8,0.45)';
        toast.style.boxShadow = '0 12px 35px rgba(0,0,0,0.65), 0 0 15px rgba(234,179,8,0.15)';
    } else if (tipo === 'copiado') {
        icone = '📋';
        toast.style.borderColor = 'rgba(212,175,55,0.6)';
        toast.style.boxShadow = '0 12px 35px rgba(0,0,0,0.65), 0 0 18px rgba(212,175,55,0.25)';
    }

    toast.innerHTML = `
        <span style="font-size:18px; flex-shrink:0;">${icone}</span>
        <div style="flex:1; line-height:1.4;">${mensagem}</div>
        <button style="background:transparent; border:none; color:#888; cursor:pointer; font-size:18px; padding:0; line-height:1; margin-left:4px;" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0) scale(1)';
    });

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px) scale(0.96)';
        setTimeout(() => toast.remove(), 320);
    }, 3500);
}

// --- MÁSCARA AUTOMÁTICA DE TELEFONE / WHATSAPP ---
function aplicarMascaraTelefone(input) {
    if (!input) return;
    input.addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 11) v = v.substring(0, 11);
        
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4,5})(\d{0,4})$/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
        } else if (v.length > 0) {
            v = v.replace(/^(\d*)$/, '($1');
        }
        e.target.value = v;
    });
}

// --- COPIAR CÓDIGO COM 1 CLIQUE (CLIPBOARD API) ---
function copiarTexto(texto, elementoBotao = null) {
    if (!texto) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => {
            mostrarToast(`Código <strong>${texto}</strong> copiado para a área de transferência!`, 'copiado');
            if (elementoBotao) {
                const originalHtml = elementoBotao.innerHTML;
                elementoBotao.innerHTML = '✓ Copiado!';
                elementoBotao.style.filter = 'brightness(1.3)';
                setTimeout(() => {
                    elementoBotao.innerHTML = originalHtml;
                    elementoBotao.style.filter = 'none';
                }, 2000);
            }
        }).catch(() => fallbackCopiar(texto, elementoBotao));
    } else {
        fallbackCopiar(texto, elementoBotao);
    }
}

function fallbackCopiar(texto, elementoBotao) {
    const tempInput = document.createElement('input');
    tempInput.value = texto;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        document.execCommand('copy');
        mostrarToast(`Código <strong>${texto}</strong> copiado!`, 'copiado');
        if (elementoBotao) {
            const originalHtml = elementoBotao.innerHTML;
            elementoBotao.innerHTML = '✓ Copiado!';
            setTimeout(() => { elementoBotao.innerHTML = originalHtml; }, 2000);
        }
    } catch (err) {
        mostrarToast('Não foi possível copiar automaticamente.', 'aviso');
    }
    document.body.removeChild(tempInput);
}

function saveState() {
    localStorage.setItem('barbeariaState', JSON.stringify(appState));
    updateBadge();
}

function sincronizarAgendamentosApi() {
    fetch('../api/agendamento.php')
        .then(r => r.json())
        .then(res => {
            if (res.data && Array.isArray(res.data)) {
                const apiAgendamentos = res.data.map(item => ({
                    id: item.codigo || String(item.id),
                    dbId: item.id,
                    nome: item.cliente_nome,
                    telefone: item.cliente_telefone || '',
                    data: item.data_agendada,
                    horario: item.horario ? item.horario.substring(0, 5) : '',
                    servico: item.servico_nome || 'Atendimento',
                    status: item.status || 'ativo'
                }));
                appState.agendamentos = apiAgendamentos;
                saveState();
                updateBadge();
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab && activeTab.id === 'tab-agendamentos') renderAdminAgendamentos();
                if (activeTab && activeTab.id === 'tab-lembretes') renderAdminLembretes();
            }
        })
        .catch(e => console.log('Erro ao sincronizar com banco:', e));
}

// --- NAVEGAÇÃO E LOGIN ---
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    
    if (tabId === 'tab-agendar') loadAvailableSlots();
    if (tabId === 'tab-agendamentos') {
        sincronizarAgendamentosApi();
        renderAdminAgendamentos();
    }
    if (tabId === 'tab-lembretes') {
        sincronizarAgendamentosApi();
        renderAdminLembretes();
    }
    if (tabId === 'tab-historico') {
        sincronizarAgendamentosApi();
        renderAdminHistorico();
    }
    if (tabId === 'tab-horarios') renderAdminHorarios();
    if (tabId === 'tab-fidelidade-admin') renderAdminFidelidade();
    if (tabId === 'tab-avaliacoes-admin') renderAdminAvaliacoes();
}

function checkAdminAuth(targetTab) {
    currentAdminTab = targetTab;
    if (isAdminLogged) {
        showTab(targetTab);
    } else {
        showTab('tab-login');
    }
}

function loginAdmin() {
    const inputSenha = document.getElementById('admin-senha').value;
    if (inputSenha === appState.senhaAdmin) {
        isAdminLogged = true;
        document.getElementById('btn-sair').style.display = 'inline-block';
        document.getElementById('admin-senha').value = '';
        showTab(currentAdminTab || 'tab-agendamentos');
    } else {
        alert('Senha incorreta!');
    }
}

function logoutAdmin() {
    isAdminLogged = false;
    document.getElementById('btn-sair').style.display = 'none';
    showTab('tab-agendar');
}

// --- LÓGICA DO CLIENTE ---
function loadAvailableSlots() {
    const data = document.getElementById('cliente-data').value;
    const select = document.getElementById('cliente-horario');
    select.innerHTML = '<option value="">Buscando horários disponíveis...</option>';

    if (!data) {
        select.innerHTML = '<option value="">Selecione uma data primeiro</option>';
        return;
    }

    fetch(`../api/agendamento.php?disponiveis=1&data=${data}`)
        .then(r => r.json())
        .then(res => {
            select.innerHTML = '';
            if (res.horarios && res.horarios.length > 0) {
                res.horarios.forEach(h => {
                    let opt = document.createElement('option');
                    opt.value = h;
                    opt.textContent = `${h} - Livre`;
                    select.appendChild(opt);
                });
            } else {
                select.innerHTML = '<option value="">Lotado para este dia</option>';
            }
        })
        .catch(() => {
            const bloqueadosHoje = appState.bloqueios[data] || [];
            const ocupadosHoje = appState.agendamentos
                .filter(a => a.data === data && a.status === 'ativo')
                .map(a => a.horario);

            let disponiveis = defaultHorarios.filter(h => !bloqueadosHoje.includes(h) && !ocupadosHoje.includes(h));
            select.innerHTML = '';
            if (disponiveis.length === 0) {
                select.innerHTML = '<option value="">Lotado para este dia</option>';
            } else {
                disponiveis.forEach(h => {
                    let opt = document.createElement('option');
                    opt.value = h; opt.textContent = h;
                    select.appendChild(opt);
                });
            }
        });
}

function carregarServicosApi() {
    const select = document.getElementById('cliente-servico');
    if (!select) return;
    fetch('../api/servico.php')
        .then(r => r.json())
        .then(res => {
            if (res.data && res.data.length > 0) {
                select.innerHTML = '<option value="">Selecione o que deseja fazer...</option>';
                res.data.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = `${s.nome} - R$ ${parseFloat(s.preco).toFixed(2)}`;
                    opt.dataset.id = s.id;
                    opt.textContent = `${s.nome} - R$ ${parseFloat(s.preco).toFixed(2)} (${s.duracao_minutos} min)`;
                    select.appendChild(opt);
                });
            }
        })
        .catch(e => console.log('Servicos API:', e));
}

async function agendar() {
    const nome = document.getElementById('cliente-nome').value.trim();
    const telInput = document.getElementById('cliente-telefone');
    const telefone = telInput ? telInput.value.trim() : '';
    const servicoSelect = document.getElementById('cliente-servico');
    const servico = servicoSelect.value;
    const servicoId = servicoSelect.options[servicoSelect.selectedIndex]?.dataset?.id || 1;
    const data = document.getElementById('cliente-data').value;
    const horario = document.getElementById('cliente-horario').value;

    if (!nome || !servico || !data || !horario) {
        mostrarToast('Por favor, preencha todos os campos obrigatórios.', 'aviso');
        return;
    }

    const msgBox = document.getElementById('agendamento-sucesso');
    msgBox.style.display = 'block';
    msgBox.innerHTML = '<em>Processando agendamento e gerando link do WhatsApp...</em>';

    try {
        const resp = await fetch('../api/agendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cliente_nome: nome,
                cliente_telefone: telefone,
                servico_id: servicoId,
                data_agendada: data,
                horario: horario
            })
        });

        const res = await resp.json();

        if (resp.ok && res.success) {
            const codigo = res.codigo || Math.random().toString(36).substring(2, 8).toUpperCase();
            
            // Salvando no estado local
            appState.agendamentos.push({ 
                id: codigo, 
                data, 
                horario, 
                nome, 
                servico, 
                telefone,
                status: 'ativo' 
            });
            saveState();

            let zapButtons = '';
            if (res.whatsapp_url_cliente) {
                zapButtons += `
                    <a href="${res.whatsapp_url_cliente}" target="_blank" style="display:inline-block; margin-top:8px; margin-right:8px; padding:8px 14px; background:#25D366; color:#000; font-weight:bold; border-radius:8px; text-decoration:none;">
                        📲 Enviar Confirmação via WhatsApp
                    </a>
                `;
            }
            if (res.whatsapp_url_barbeiro) {
                zapButtons += `
                    <a href="${res.whatsapp_url_barbeiro}" target="_blank" style="display:inline-block; margin-top:8px; padding:8px 14px; background:#333; color:#fff; font-weight:600; border-radius:8px; text-decoration:none;">
                        💬 Notificar Barbeiro
                    </a>
                `;
            }

            msgBox.innerHTML = `
                <div style="font-size:1.05rem;"><strong>🎉 Agendamento confirmado, ${nome}!</strong></div>
                <div style="margin-top:4px;">Serviço: <strong>${servico}</strong></div>
                <div>Data & Horário: <strong>${data} às ${horario}</strong></div>
                <div style="margin-top:6px; display:flex; align-items:center; gap:8px;">
                    <span>Código:</span> 
                    <strong style="color:#d4af37; background:#000; padding:2px 8px; border-radius:4px; font-family:monospace; font-size:1.05rem;">${codigo}</strong>
                    <button type="button" onclick="copiarTexto('${codigo}', this)" style="background:#222; border:1px solid #d4af37; color:#d4af37; font-size:11px; font-weight:bold; padding:3px 8px; border-radius:6px; cursor:pointer;">📋 Copiar</button>
                </div>
                <div style="margin-top:8px;">${zapButtons}</div>
                <div style="margin-top:10px;">
                    <button onclick="showTab('tab-agendamentos')" style="padding:6px 12px; font-size:0.85rem; background:#444; color:#fff; border-radius:6px; border:none; cursor:pointer;">Ver na Tabela de Agendamentos &rarr;</button>
                </div>
            `;

            mostrarToast(`🎉 Agendamento confirmado para ${nome}!`, 'sucesso');

            // Abre o WhatsApp do cliente automaticamente se preenchido
            if (res.whatsapp_url_cliente && telefone) {
                window.open(res.whatsapp_url_cliente, '_blank');
            }

            document.getElementById('cliente-nome').value = '';
            if (telInput) telInput.value = '';
            loadAvailableSlots();
            sincronizarAgendamentosApi();
        } else {
            msgBox.style.display = 'block';
            msgBox.innerHTML = `<span style="color:#dc3545;">❌ ${res.error || 'Erro ao realizar agendamento.'}</span>`;
            mostrarToast(res.error || 'Não foi possível confirmar o agendamento.', 'erro');
        }
    } catch (e) {
        console.error('Sync API erro:', e);
        const codigo = Math.random().toString(36).substring(2, 8).toUpperCase();
        appState.agendamentos.push({ id: codigo, data, horario, nome, servico, telefone, status: 'ativo' });
        saveState();

        msgBox.innerHTML = `
            <strong>Tudo certo (modo local), ${nome}!</strong><br>
            Serviço: ${servico}<br>
            Horário: ${horario} no dia ${data}.<br>
            Código: <strong style="color:#d4af37; font-family:monospace;">${codigo}</strong>
            <button type="button" onclick="copiarTexto('${codigo}', this)" style="background:#222; border:1px solid #d4af37; color:#d4af37; font-size:11px; padding:2px 6px; border-radius:4px; cursor:pointer; margin-left:6px;">📋 Copiar</button>
        `;
        mostrarToast('Agendamento salvo no modo offline.', 'aviso');
        document.getElementById('cliente-nome').value = '';
        if (telInput) telInput.value = '';
        loadAvailableSlots();
    }
}

function cancelarAgendamento() {
    const codigo = document.getElementById('codigo-cancelamento').value.toUpperCase().trim();
    if (!codigo) {
        mostrarToast('Informe o código de cancelamento.', 'aviso');
        return;
    }

    const index = appState.agendamentos.findIndex(a => a.id === codigo && a.status === 'ativo');

    // Sincroniza cancelamento na API MySQL
    fetch('../api/agendamento.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: codigo })
    }).catch(e => console.log('Sync cancel erro:', e));

    if (index === -1) {
        mostrarToast('Código não encontrado nos agendamentos ativos locais.', 'aviso');
        return;
    }

    const agendamento = appState.agendamentos[index];
    appState.agendamentos[index].status = 'cancelado';
    saveState();

    mostrarToast(`Agendamento de ${agendamento.nome} cancelado com sucesso.`, 'sucesso');
    
    const text = encodeURIComponent(`AVISO: O cliente ${agendamento.nome} CANCELOU o horário das ${agendamento.horario} (Dia ${agendamento.data}) para ${agendamento.servico}.`);
    window.open(`https://wa.me/${appState.whatsappAdmin}?text=${text}`, '_blank');
}

// --- LÓGICA DO BARBEIRO (ADMIN) ---
async function renderAdminHorarios() {
    const inputData = document.getElementById('admin-data');
    if (!inputData) return;
    if (!inputData.value) {
        inputData.value = new Date().toISOString().split('T')[0];
    }
    const data = inputData.value;
    const container = document.getElementById('lista-horarios-admin');
    if (!container) return;

    container.innerHTML = '<div style="text-align:center; padding:15px; color:#a1a1aa;">Carregando horários do banco...</div>';

    try {
        const resp = await fetch(`../api/agendamento.php?grade_completa=1&data=${data}`);
        const res = await resp.json();
        const grade = res.grade || [];

        container.innerHTML = '';
        if (grade.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:15px; color:#a1a1aa;">Nenhum horário configurado no expediente.</div>';
            return;
        }

        grade.forEach(item => {
            const h = item.horario;
            let statusHtml = '<span style="color:var(--success); font-weight:600;">Livre</span>';
            let btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-danger" style="font-size:12px; padding:6px 14px;">Bloquear</button>`;

            if (item.status === 'ocupado') {
                statusHtml = `<span style="color:var(--info); font-weight:600;">Ocupado: ${item.cliente_nome || 'Cliente'} <strong>(${item.servico_nome || 'Serviço'})</strong></span>`;
                btnHtml = `<button disabled style="opacity:0.5; font-size:12px; padding:6px 14px;">Reservado</button>`;
            } else if (item.status === 'bloqueado') {
                statusHtml = '<span style="color:var(--danger); font-weight:600;">Bloqueado</span>';
                btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-success" style="font-size:12px; padding:6px 14px;">Liberar</button>`;
            }

            container.innerHTML += `
                <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; margin-bottom:8px; background:rgba(255,255,255,0.03);">
                    <div><strong style="font-size:1.05rem; font-family:monospace; color:#D4AF37;">${h}</strong> — ${statusHtml}</div>
                    <div>${btnHtml}</div>
                </div>`;
        });
    } catch (e) {
        // Fallback local caso haja falha temporária de rede
        if (!appState.bloqueios[data]) appState.bloqueios[data] = [];
        container.innerHTML = '';
        defaultHorarios.forEach(h => {
            const agendamento = appState.agendamentos.find(a => a.data === data && a.horario === h && a.status === 'ativo');
            const isBlocked = appState.bloqueios[data].includes(h);

            let statusHtml = '<span style="color:var(--success)">Livre</span>';
            let btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-danger" style="font-size:12px; padding:6px 14px;">Bloquear</button>`;

            if (agendamento) {
                statusHtml = `<span style="color:var(--info)">Ocupado: ${agendamento.nome} <strong>(${agendamento.servico})</strong></span>`;
                btnHtml = `<button disabled style="opacity:0.5; font-size:12px; padding:6px 14px;">Reservado</button>`;
            } else if (isBlocked) {
                statusHtml = '<span style="color:var(--danger)">Bloqueado</span>';
                btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-success" style="font-size:12px; padding:6px 14px;">Liberar</button>`;
            }

            container.innerHTML += `
                <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; margin-bottom:8px; background:rgba(255,255,255,0.03);">
                    <div><strong style="font-size:1.05rem; font-family:monospace; color:#D4AF37;">${h}</strong> — ${statusHtml}</div>
                    <div>${btnHtml}</div>
                </div>`;
        });
    }
}

// --- FILTROS E BUSCA INSTANTÂNEA DA TABELA (ADMIN) ---
function setFiltroAdminStatus(status) {
    filtroAdminStatus = status;
    const botoes = ['hoje', 'todos', 'ativo', 'cancelado'];
    botoes.forEach(s => {
        const btn = document.getElementById(`btn-filtro-${s}`);
        if (!btn) return;
        if (s === status) {
            btn.className = 'text-xs px-3 py-1.5 rounded-lg border border-amber-500/40 bg-amber-500/20 text-amber-300 font-semibold transition';
        } else {
            btn.className = 'text-xs px-3 py-1.5 rounded-lg border border-white/10 bg-white/5 text-zinc-400 hover:text-white transition';
        }
    });
    renderAdminAgendamentos();
}

function filtrarAgendamentosAdmin() {
    const input = document.getElementById('filtro-busca-admin');
    filtroAdminBusca = (input ? input.value : '').toLowerCase().trim();
    renderAdminAgendamentos();
}

function renderAdminAgendamentos() {
    const hoje = new Date().toISOString().split('T')[0];
    const todosAgendamentos = appState.agendamentos || [];
    const ativosHoje = todosAgendamentos.filter(a => a.data === hoje && a.status === 'ativo');
    const canceladosHoje = todosAgendamentos.filter(a => a.data === hoje && a.status === 'cancelado');
    const bloqueiosHoje = appState.bloqueios[hoje] || [];
    const disponiveisHoje = defaultHorarios.filter(h => !ativosHoje.some(a => a.horario === h) && !bloqueiosHoje.includes(h));

    const faturamentoBrutoGeral = todosAgendamentos.reduce((acc, a) => acc + (parseFloat(a.servico_preco || a.preco || 0)), 0);
    const faturamentoAtivosHoje = ativosHoje.reduce((acc, a) => acc + (parseFloat(a.servico_preco || a.preco || 0)), 0);

    const cardTotal = document.getElementById('card-total-agendamentos');
    if (cardTotal) cardTotal.textContent = todosAgendamentos.filter(a => a.status === 'ativo').length;
    const cardCanc = document.getElementById('card-cancelamentos');
    if (cardCanc) cardCanc.textContent = todosAgendamentos.filter(a => a.status === 'cancelado').length;
    const cardDisp = document.getElementById('card-horarios-disponiveis');
    if (cardDisp) cardDisp.textContent = disponiveisHoje.length;
    
    const elResumoFat = document.getElementById('resumo-faturamento');
    if (elResumoFat) {
        elResumoFat.innerHTML = `<span class="text-amber-400 font-bold font-mono">Faturamento Bruto: R$ ${faturamentoBrutoGeral.toFixed(2).replace('.', ',')}</span> <span class="text-xs text-zinc-400 font-normal">(Hoje: R$ ${faturamentoAtivosHoje.toFixed(2).replace('.', ',')} em ${ativosHoje.length} agendados)</span>`;
    }

    renderCharts();

    // Aplicação dos filtros rápidos e de pesquisa em tempo real (0ms)
    let filtrados = todosAgendamentos;

    if (filtroAdminStatus === 'hoje') {
        filtrados = filtrados.filter(a => a.data === hoje);
    } else if (filtroAdminStatus === 'ativo') {
        filtrados = filtrados.filter(a => a.status === 'ativo');
    } else if (filtroAdminStatus === 'cancelado') {
        filtrados = filtrados.filter(a => a.status === 'cancelado');
    }

    if (filtroAdminBusca) {
        filtrados = filtrados.filter(a => 
            (a.nome && a.nome.toLowerCase().includes(filtroAdminBusca)) ||
            (a.id && a.id.toLowerCase().includes(filtroAdminBusca)) ||
            (a.servico && a.servico.toLowerCase().includes(filtroAdminBusca)) ||
            (a.telefone && a.telefone.includes(filtroAdminBusca)) ||
            (a.data && a.data.includes(filtroAdminBusca)) ||
            (a.horario && a.horario.includes(filtroAdminBusca))
        );
    }

    renderAgendamentosTable(filtrados, disponiveisHoje);
}

function renderAgendamentosTable(agendamentosFiltrados, disponiveisHoje) {
    const container = document.getElementById('tabela-agendamentos-body');
    if (!container) return;
    const rows = [];

    const lista = [...agendamentosFiltrados];
    lista.sort((a, b) => (b.data || '').localeCompare(a.data || '') || (a.horario || '').localeCompare(b.horario || ''));

    lista.forEach(a => {
        const isAtivo = a.status === 'ativo';
        const statusBadge = isAtivo 
            ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 border border-emerald-500/30 text-emerald-400">✅ Marcado</span>` 
            : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/15 border border-red-500/30 text-red-400">❌ Cancelado</span>`;
        
        const action = isAtivo
            ? `<button onclick="cancelarAgendamentoAdmin('${a.id}')" class="text-xs text-red-400 hover:text-red-300 font-semibold underline transition">Cancelar</button>`
            : `<button onclick="reativarAgendamento('${a.id}')" class="text-xs text-emerald-400 hover:text-emerald-300 font-semibold underline transition">Reativar</button>`;

        const telLimpo = (a.telefone || a.cliente_telefone || '').replace(/\D/g, '');
        const telComDdi = telLimpo ? (telLimpo.length <= 11 && !telLimpo.startsWith('55') ? '55' + telLimpo : telLimpo) : '';
        const zapMsg = encodeURIComponent(`Olá ${a.nome}! Confirmando seu agendamento na Barbearia VIP para o dia ${a.data} às ${a.horario} (${a.servico || 'Atendimento'}).`);
        const zapUrl = telComDdi ? `https://api.whatsapp.com/send?phone=${telComDdi}&text=${zapMsg}` : `https://api.whatsapp.com/send?text=${zapMsg}`;
        const zapBtn = `<a href="${zapUrl}" target="_blank" class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg bg-[#25D366]/20 border border-[#25D366]/40 text-[#25D366] hover:bg-[#25D366]/30 font-semibold transition mr-2">📲 WhatsApp</a>`;

        const codigoBtn = `
            <div class="flex items-center gap-1.5">
                <span class="font-mono text-xs font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">${a.id}</span>
                <button type="button" onclick="copiarTexto('${a.id}', this)" title="Copiar código" class="text-[11px] px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-zinc-300 hover:text-white transition">📋</button>
            </div>
        `;

        rows.push(`
            <tr class="hover:bg-white/5 transition">
                <td class="py-3 px-3">
                    <div class="font-semibold text-white">${a.nome}</div>
                    <div class="text-xs text-zinc-400 font-mono">${a.telefone || '--'}</div>
                </td>
                <td class="py-3 px-3">
                    <div class="text-zinc-200 text-xs">${a.data || '--'}</div>
                    <div class="text-xs font-mono text-amber-400 font-bold">${a.horario || '--'}</div>
                </td>
                <td class="py-3 px-3 text-zinc-300 text-xs">${a.servico || 'Atendimento'}</td>
                <td class="py-3 px-3">${codigoBtn}</td>
                <td class="py-3 px-3">${statusBadge}</td>
                <td class="py-3 px-3 text-right whitespace-nowrap">${zapBtn}${action}</td>
            </tr>
        `);
    });

    if (filtroAdminStatus === 'hoje' && !filtroAdminBusca) {
        disponiveisHoje.slice(0, 3).forEach(hora => {
            rows.push(`
                <tr class="hover:bg-white/[0.02] border-t border-white/5 opacity-70">
                    <td class="py-2.5 px-3 text-zinc-500 italic text-xs">Horário Livre</td>
                    <td class="py-2.5 px-3">
                        <span class="text-xs font-mono text-emerald-400 font-bold">${hora}</span>
                    </td>
                    <td class="py-2.5 px-3 text-zinc-500 text-xs">--</td>
                    <td class="py-2.5 px-3 text-zinc-500 text-xs">--</td>
                    <td class="py-2.5 px-3"><span class="text-xs text-emerald-400/80 font-medium">🔓 Disponível</span></td>
                    <td class="py-2.5 px-3 text-right">
                        <button onclick="showTab('tab-agendar')" class="text-xs text-amber-400 hover:underline">Reservar</button>
                    </td>
                </tr>
            `);
        });
    }

    if (rows.length === 0) {
        container.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-zinc-500">Nenhum agendamento encontrado para o filtro aplicado.</td></tr>`;
    } else {
        container.innerHTML = rows.join('');
    }

    const contadorEl = document.getElementById('contador-agendamentos-admin');
    if (contadorEl) {
        contadorEl.innerHTML = `Exibindo <strong>${agendamentosFiltrados.length}</strong> de <strong>${appState.agendamentos.length}</strong> agendamentos cadastrados.`;
    }
}

function renderCharts() {
    const labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const weekData = getWeekOccupancy();
    const activeCount = appState.agendamentos.filter(a => a.status === 'ativo').length;
    const canceledCount = appState.agendamentos.filter(a => a.status === 'cancelado').length;

    const ocupacaoCtx = document.getElementById('ocupacaoChart')?.getContext('2d');
    const proporcaoCtx = document.getElementById('proporcaoChart')?.getContext('2d');
    if (!ocupacaoCtx || !proporcaoCtx) return;

    if (ocupacaoChart) {
        ocupacaoChart.data.labels = labels;
        ocupacaoChart.data.datasets[0].data = weekData;
        ocupacaoChart.update();
    } else {
        ocupacaoChart = new Chart(ocupacaoCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Agendamentos ativos',
                    data: weekData,
                    backgroundColor: 'rgba(212, 175, 55, 0.7)',
                    borderColor: '#D4AF37',
                    borderWidth: 1.5,
                    borderRadius: 10,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(10, 10, 12, 0.95)',
                        borderColor: '#D4AF37',
                        borderWidth: 1,
                        titleColor: '#D4AF37',
                        bodyColor: '#f4f4f5'
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#a1a1aa', font: { family: 'sans-serif', size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.08)' },
                        ticks: { color: '#a1a1aa', stepSize: 1, font: { family: 'sans-serif', size: 12 } }
                    }
                }
            }
        });
    }

    if (proporcaoChart) {
        proporcaoChart.data.datasets[0].data = [activeCount, canceledCount];
        proporcaoChart.update();
    } else {
        proporcaoChart = new Chart(proporcaoCtx, {
            type: 'doughnut',
            data: {
                labels: ['Marcados', 'Cancelados'],
                datasets: [{
                    data: [activeCount, canceledCount],
                    backgroundColor: ['#D4AF37', '#dc3545'],
                    borderColor: '#18181F',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 18, color: '#e4e4e7', font: { size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(10, 10, 12, 0.95)',
                        borderColor: '#D4AF37',
                        borderWidth: 1,
                        titleColor: '#D4AF37',
                        bodyColor: '#f4f4f5'
                    }
                },
                cutout: '65%'
            }
        });
    }
}

function getWeekOccupancy() {
    const today = new Date();
    const monday = getWeekStart(today);
    return Array.from({ length: 6 }, (_, index) => {
        const date = addDays(monday, index);
        const dateKey = formatDate(date);
        return appState.agendamentos.filter(a => a.data === dateKey && a.status === 'ativo').length;
    });
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function getWeekStart(date) {
    const current = new Date(date);
    const day = current.getDay();
    const diff = (day + 6) % 7;
    return addDays(current, -diff);
}

function addDays(date, days) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);
    return copy;
}

function cancelarAgendamentoAdmin(id) {
    const item = appState.agendamentos.find(a => a.id === id);
    if (!item) return;
    item.status = 'cancelado';
    saveState();
    renderAdminAgendamentos();
}

function reativarAgendamento(id) {
    const item = appState.agendamentos.find(a => a.id === id);
    if (!item) return;
    item.status = 'ativo';
    saveState();
    renderAdminAgendamentos();
}

function reservarHorario(hora) {
    alert(`Reservar horário ${hora} pode ser feito pela aba de agendamento.`);
}

async function toggleBlock(data, horario) {
    try {
        const resp = await fetch('../api/agendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'toggle_bloqueio', data: data, horario: horario })
        });
        const res = await resp.json();
        if (res.success) {
            mostrarToast(`Horário ${horario} (${data}) atualizado com sucesso!`, 'sucesso');
        }
    } catch (e) {
        console.log('Sync block erro:', e);
    }

    if (!appState.bloqueios[data]) {
        appState.bloqueios[data] = [];
    }

    const bloqueios = appState.bloqueios[data];
    const index = bloqueios.indexOf(horario);

    if (index === -1) {
        bloqueios.push(horario);
    } else {
        bloqueios.splice(index, 1);
    }

    saveState();
    renderAdminHorarios();
}

function renderAdminHistorico() {
    const mes = document.getElementById('admin-mes').value;
    const resumo = document.getElementById('resumo-historico');
    const lista = document.getElementById('lista-historico');

    if (!mes) {
        resumo.textContent = 'Selecione um mês para ver o histórico.';
        lista.innerHTML = '';
        return;
    }

    const [ano, mesNum] = mes.split('-');
    const historico = appState.agendamentos
        .filter(a => a.data.startsWith(`${ano}-${mesNum}`))
        .sort((a, b) => a.data.localeCompare(b.data) || a.horario.localeCompare(b.horario));

    const ativos = historico.filter(a => a.status === 'ativo').length;
    const cancelados = historico.filter(a => a.status === 'cancelado').length;

    resumo.textContent = `Mês selecionado: ${mes} — ${ativos} ativos, ${cancelados} cancelados (${historico.length} registros).`;

    if (historico.length === 0) {
        lista.innerHTML = '<p>Nenhum agendamento encontrado para este mês.</p>';
        return;
    }

    lista.innerHTML = historico.map(a => `
        <div class="list-item">
            <strong>${a.data} ${a.horario}</strong><br>
            ${a.nome} — ${a.servico} — <span style="font-weight:700;">${a.status}</span>
        </div>
    `).join('');
}

function renderAdminLembretes() {
    const container = document.getElementById('lista-lembretes');
    const hoje = new Date().toISOString().split('T')[0];
    const agora = new Date();
    
    const ativosHoje = appState.agendamentos.filter(a => a.data === hoje && a.status === 'ativo');
    if (ativosHoje.length === 0) {
        container.innerHTML = '<p>Ninguém agendado para hoje.</p>';
        return;
    }

    ativosHoje.sort((a,b) => a.horario.localeCompare(b.horario));

    container.innerHTML = ativosHoje.map(a => {
        const [hora, min] = a.horario.split(':');
        const hAgend = new Date(); hAgend.setHours(hora, min, 0);
        const diffMins = Math.floor((hAgend - agora) / 60000);
        
        let tempoTxt = diffMins < 0 ? "Já passou" : `Faltam ${diffMins} min`;
        const msg = encodeURIComponent(`Olá ${a.nome}, confirmando seu horário hoje às ${a.horario} para ${a.servico || 'atendimento'}. Te aguardamos na Barbearia VIP!`);
        const telLimpo = (a.telefone || a.cliente_telefone || '').replace(/\D/g, '');
        const phoneWithCountry = telLimpo ? (telLimpo.length <= 11 && !telLimpo.startsWith('55') ? '55' + telLimpo : telLimpo) : '';
        const zapUrl = phoneWithCountry
            ? `https://api.whatsapp.com/send?phone=${phoneWithCountry}&text=${msg}`
            : `https://api.whatsapp.com/send?text=${msg}`;

        return `
            <div class="list-item">
                <div><strong>${a.horario}</strong> - ${a.nome} (${a.servico || 'Serviço'})<br><small>${tempoTxt}</small></div>
                <button class="btn-success" onclick="window.open('${zapUrl}', '_blank')">📲 Enviar Lembrete WhatsApp</button>
            </div>`;
    }).join('');
}

function updateBadge() {
    const hoje = new Date().toISOString().split('T')[0];
    const qtd = appState.agendamentos.filter(a => a.data === hoje && a.status === 'ativo').length;
    const badge = document.getElementById('badge-count');
    badge.textContent = qtd;
    badge.style.display = qtd > 0 ? 'inline-block' : 'none';
}

function mudarSenha() {
    const nova = document.getElementById('nova-senha').value;
    if (nova.length >= 4) {
        appState.senhaAdmin = nova;
        saveState();
        document.getElementById('nova-senha').value = '';
        mostrarToast('Senha administrativa atualizada com sucesso!', 'sucesso');
    } else {
        mostrarToast('A nova senha deve possuir pelo menos 4 caracteres.', 'aviso');
    }
}

function salvarConfig() {
    const num = document.getElementById('admin-whatsapp').value.replace(/\D/g, '');
    if (num.length >= 10) {
        appState.whatsappAdmin = num;
        saveState();
        mostrarToast('Número do WhatsApp salvo com sucesso!', 'sucesso');
    } else {
        mostrarToast('Informe um telefone válido com DDD.', 'aviso');
    }
}

// Inicialização
document.getElementById('cliente-data').min = new Date().toISOString().split('T')[0];
document.getElementById('admin-data').value = new Date().toISOString().split('T')[0];
document.getElementById('admin-whatsapp').value = appState.whatsappAdmin;

// Ativação da Máscara Automática de Telefone nos inputs
document.querySelectorAll('input[type="tel"], #cliente-telefone, #cliente-tel, #admin-whatsapp, #telefone').forEach(aplicarMascaraTelefone);

updateBadge();
carregarServicosApi();
sincronizarAgendamentosApi();

setInterval(() => {
    updateBadge();
    if(isAdminLogged && document.getElementById('tab-lembretes').classList.contains('active')) {
        renderAdminLembretes();
    }
}, 30000);



// --- ADMIN: RANKING DO CARTÃO FIDELIDADE ---
async function renderAdminFidelidade() {
    const tbody = document.getElementById('tabela-fidelidade-admin-body');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-zinc-500">Buscando ranking de fidelidade...</td></tr>';

    try {
        const resp = await fetch('../api/fidelidade.php?ranking=1');
        const res = await resp.json();

        if (res.sucesso && res.ranking && res.ranking.length > 0) {
            tbody.innerHTML = res.ranking.map((item, idx) => {
                const medalhas = ['🥇', '🥈', '🥉'];
                const pos = medalhas[idx] || `#${idx + 1}`;
                const ciclos = Math.floor(Number(item.total_cortes) / 5);
                const dataFormatada = item.ultimo_corte ? item.ultimo_corte.split('-').reverse().join('/') : '-';

                return `
                    <tr class="hover:bg-white/[0.02] transition">
                        <td class="py-3 px-3 font-bold text-amber-400">${pos}</td>
                        <td class="py-3 px-3 font-semibold text-white">${item.cliente_nome}</td>
                        <td class="py-3 px-3 font-mono text-zinc-400">${item.cliente_telefone || 'Não informado'}</td>
                        <td class="py-3 px-3 font-mono font-bold text-white">${item.total_cortes} corte(s)</td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold ${ciclos > 0 ? 'bg-amber-400/20 text-amber-300 border border-amber-400/40' : 'bg-white/5 text-zinc-500'}">
                                ${ciclos} ciclo(s) completo(s)
                            </span>
                        </td>
                        <td class="py-3 px-3 text-zinc-400 text-xs">${dataFormatada}</td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-zinc-500">Nenhum histórico de fidelidade registrado ainda.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-red-400">Falha ao buscar ranking de fidelidade.</td></tr>';
    }
}

// --- ADMIN: AVALIAÇÕES E SATISFAÇÃO DOS CLIENTES ---
async function renderAdminAvaliacoes() {
    const tbody = document.getElementById('tabela-avaliacoes-admin-body');
    const resumo = document.getElementById('admin-placar-resumo');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-zinc-500">Buscando avaliações...</td></tr>';

    try {
        const resp = await fetch('../api/avaliacao.php?todos=1');
        const res = await resp.json();

        if (res.sucesso) {
            const est = res.estatisticas;
            if (resumo) {
                resumo.innerHTML = `⭐ Média Geral: <strong class="text-white">${est.media_geral.toFixed(1)}</strong> (${est.total_avaliacoes} avaliações • ${est.porcentagem_recomendacao}% recomendam)`;
            }

            if (res.avaliacoes && res.avaliacoes.length > 0) {
                tbody.innerHTML = res.avaliacoes.map(av => {
                    const estrelas = '★'.repeat(Number(av.nota)) + '☆'.repeat(5 - Number(av.nota));

                    return `
                        <tr class="hover:bg-white/[0.02] transition">
                            <td class="py-3 px-3 text-xs text-zinc-400">${av.data_formatada || '-'}</td>
                            <td class="py-3 px-3 font-semibold text-white">${av.cliente_nome}</td>
                            <td class="py-3 px-3 text-xs text-zinc-300">${av.servico_nome}</td>
                            <td class="py-3 px-3 text-amber-400 font-bold text-xs tracking-wider">${estrelas} (${av.nota}/5)</td>
                            <td class="py-3 px-3 text-xs text-zinc-300 max-w-xs italic">"${av.comentario}"</td>
                            <td class="py-3 px-3 text-right">
                                <button type="button" onclick="excluirAvaliacaoAdmin(${av.id})" class="text-xs px-2.5 py-1 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30 border border-red-500/30 transition">
                                    🗑️ Excluir
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-zinc-500">Nenhuma avaliação encontrada.</td></tr>';
            }
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-red-400">Falha ao buscar avaliações.</td></tr>';
    }
}

async function excluirAvaliacaoAdmin(id) {
    if (!confirm('Deseja realmente remover esta avaliação do sistema?')) return;
    try {
        const resp = await fetch('../api/avaliacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'excluir', id })
        });
        const res = await resp.json();
        if (res.sucesso) {
            mostrarToast('Avaliação excluída com sucesso.', 'sucesso');
            renderAdminAvaliacoes();
        } else {
            mostrarToast(res.mensagem || 'Erro ao excluir avaliação.', 'erro');
        }
    } catch (e) {
        mostrarToast('Erro de comunicação ao excluir.', 'erro');
    }
}

// --- CONTROLE DO MENU HAMBÚRGUER ---
function toggleMenu() {
    // Adiciona ou tira a classe 'mostrar' (que revela a coluna de botões)
    document.getElementById('navLinks').classList.toggle('mostrar');
}

function closeMenu() {
    // Se estiver no celular, fecha o menu automaticamente após clicar em uma opção
    if (window.innerWidth <= 768) {
        document.getElementById('navLinks').classList.remove('mostrar');
    }
}