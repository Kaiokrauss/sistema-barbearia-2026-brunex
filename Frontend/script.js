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
        return alert('Por favor, preencha todos os campos obrigatórios.');
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
                <div style="margin-top:4px;">Código de cancelamento: <strong style="color:#d4af37; background:#000; padding:2px 6px; border-radius:4px;">${codigo}</strong></div>
                <div style="margin-top:8px;">${zapButtons}</div>
                <div style="margin-top:10px;">
                    <button onclick="showTab('tab-agendamentos')" style="padding:6px 12px; font-size:0.85rem; background:#444; color:#fff; border-radius:6px; border:none; cursor:pointer;">Ver na Tabela de Agendamentos &rarr;</button>
                </div>
            `;

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
            alert(res.error || 'Não foi possível confirmar o agendamento.');
        }
    } catch (e) {
        console.error('Sync API erro:', e);
        const codigo = Math.random().toString(36).substring(2, 8).toUpperCase();
        appState.agendamentos.push({ id: codigo, data, horario, nome, servico, telefone, status: 'ativo' });
        saveState();

        msgBox.innerHTML = `<strong>Tudo certo (modo local), ${nome}!</strong><br>Serviço: ${servico}<br>Horário: ${horario} no dia ${data}.<br>Código: <strong>${codigo}</strong>`;
        document.getElementById('cliente-nome').value = '';
        if (telInput) telInput.value = '';
        loadAvailableSlots();
    }
}

function cancelarAgendamento() {
    const codigo = document.getElementById('codigo-cancelamento').value.toUpperCase();
    const index = appState.agendamentos.findIndex(a => a.id === codigo && a.status === 'ativo');

    // Sincroniza cancelamento na API MySQL
    fetch('../api/agendamento.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: codigo })
    }).catch(e => console.log('Sync cancel erro:', e));

    if (index === -1) {
        alert('Código verificado e cancelamento solicitado.');
        return;
    }

    const agendamento = appState.agendamentos[index];
    appState.agendamentos[index].status = 'cancelado';
    saveState();

    alert('Cancelado com sucesso!');
    
    const text = encodeURIComponent(`AVISO: O cliente ${agendamento.nome} CANCELOU o horário das ${agendamento.horario} (Dia ${agendamento.data}) para ${agendamento.servico}.`);
    window.open(`https://wa.me/${appState.whatsappAdmin}?text=${text}`, '_blank');
}

// --- LÓGICA DO BARBEIRO (ADMIN) ---
function renderAdminHorarios() {
    const data = document.getElementById('admin-data').value;
    const container = document.getElementById('lista-horarios-admin');
    container.innerHTML = '';
    if (!data) return;

    if (!appState.bloqueios[data]) appState.bloqueios[data] = [];

    defaultHorarios.forEach(h => {
        const agendamento = appState.agendamentos.find(a => a.data === data && a.horario === h && a.status === 'ativo');
        const isBlocked = appState.bloqueios[data].includes(h);
        
        let statusHtml = '<span style="color:var(--success)">Livre</span>';
        let btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-danger">Bloquear</button>`;

        if (agendamento) {
            // Mostrando o serviço na agenda
            statusHtml = `<span style="color:var(--info)">Ocupado: ${agendamento.nome} <strong>(${agendamento.servico})</strong></span>`;
            btnHtml = `<button disabled style="opacity:0.5">Reservado</button>`;
        } else if (isBlocked) {
            statusHtml = '<span style="color:var(--danger)">Bloqueado</span>';
            btnHtml = `<button onclick="toggleBlock('${data}', '${h}')" class="btn-success">Liberar</button>`;
        }

        container.innerHTML += `
            <div class="list-item">
                <div><strong>${h}</strong> - ${statusHtml}</div>
                <div>${btnHtml}</div>
            </div>`;
    });
}

function renderAdminAgendamentos() {
    const hoje = new Date().toISOString().split('T')[0];
    const agendamentosHoje = appState.agendamentos.filter(a => a.data === hoje);
    const ativosHoje = agendamentosHoje.filter(a => a.status === 'ativo');
    const canceladosHoje = agendamentosHoje.filter(a => a.status === 'cancelado');
    const bloqueiosHoje = appState.bloqueios[hoje] || [];
    const disponiveisHoje = defaultHorarios.filter(h => !ativosHoje.some(a => a.horario === h) && !bloqueiosHoje.includes(h));

    document.getElementById('card-total-agendamentos').textContent = appState.agendamentos.filter(a => a.status === 'ativo').length;
    document.getElementById('card-cancelamentos').textContent = appState.agendamentos.filter(a => a.status === 'cancelado').length;
    document.getElementById('card-horarios-disponiveis').textContent = disponiveisHoje.length;
    document.getElementById('resumo-faturamento').textContent = `Hoje: ${ativosHoje.length} agendados`;

    renderCharts();
    renderAgendamentosTable(agendamentosHoje, disponiveisHoje);
}

function renderAgendamentosTable(agendamentosHoje, disponiveisHoje) {
    const container = document.getElementById('tabela-agendamentos-body');
    const rows = [];

    agendamentosHoje.sort((a, b) => a.horario.localeCompare(b.horario)).forEach(a => {
        const statusLabel = a.status === 'ativo' ? '✅ Marcado' : '❌ Cancelado';
        const action = a.status === 'ativo'
            ? `<button onclick="cancelarAgendamentoAdmin('${a.id}')" class="text-red-600 hover:underline">Cancelar</button>`
            : `<button onclick="reativarAgendamento('${a.id}')" class="text-green-600 hover:underline">Reativar</button>`;

        const telLimpo = (a.telefone || a.cliente_telefone || '').replace(/\D/g, '');
        const telComDdi = telLimpo ? (telLimpo.length <= 11 && !telLimpo.startsWith('55') ? '55' + telLimpo : telLimpo) : '';
        const zapMsg = encodeURIComponent(`Olá ${a.nome}! Confirmando seu agendamento na Barbearia VIP para o dia ${a.data} às ${a.horario} (${a.servico || 'Atendimento'}).`);
        const zapUrl = telComDdi ? `https://api.whatsapp.com/send?phone=${telComDdi}&text=${zapMsg}` : `https://api.whatsapp.com/send?text=${zapMsg}`;
        const zapBtn = `<a href="${zapUrl}" target="_blank" style="background:#25D366; color:#000; font-weight:600; padding:3px 8px; border-radius:6px; text-decoration:none; margin-right:8px; font-size:12px; display:inline-block;">📲 WhatsApp</a>`;

        rows.push(`
            <tr class="border-t border-slate-200">
                <td class="py-3 px-3">${a.nome}</td>
                <td class="py-3 px-3">${a.horario}</td>
                <td class="py-3 px-3">${statusLabel}</td>
                <td class="py-3 px-3">${zapBtn}${action}</td>
            </tr>
        `);
    });

    disponiveisHoje.slice(0, 3).forEach(hora => {
        rows.push(`
            <tr class="border-t border-slate-200">
                <td class="py-3 px-3">--</td>
                <td class="py-3 px-3">${hora}</td>
                <td class="py-3 px-3">🔓 Disponível</td>
                <td class="py-3 px-3"><button onclick="reservarHorario('${hora}')" class="text-blue-600 hover:underline">Reservar</button></td>
            </tr>
        `);
    });

    if (rows.length === 0) {
        container.innerHTML = `<tr><td colspan="4" class="py-4 text-center text-slate-500">Nenhum registro encontrado.</td></tr>`;
    } else {
        container.innerHTML = rows.join('');
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

function toggleBlock(data, horario) {
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
        alert('Senha atualizada!');
    }
}

function salvarConfig() {
    appState.whatsappAdmin = document.getElementById('admin-whatsapp').value;
    saveState();
    alert('Configurações salvas!');
}

// Inicialização
document.getElementById('cliente-data').min = new Date().toISOString().split('T')[0];
document.getElementById('admin-data').value = new Date().toISOString().split('T')[0];
document.getElementById('admin-whatsapp').value = appState.whatsappAdmin;
updateBadge();
carregarServicosApi();
sincronizarAgendamentosApi();

setInterval(() => {
    updateBadge();
    if(isAdminLogged && document.getElementById('tab-lembretes').classList.contains('active')) {
        renderAdminLembretes();
    }
}, 30000);



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