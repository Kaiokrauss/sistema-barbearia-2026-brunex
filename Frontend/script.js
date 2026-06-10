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

// --- NAVEGAÇÃO E LOGIN ---
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    
    if (tabId === 'tab-agendar') loadAvailableSlots();
    if (tabId === 'tab-agendamentos') renderAdminAgendamentos();
    if (tabId === 'tab-lembretes') renderAdminLembretes();
    if (tabId === 'tab-historico') renderAdminHistorico();
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
    select.innerHTML = '';

    if (!data) {
        select.innerHTML = '<option value="">Selecione uma data primeiro</option>';
        return;
    }

    const bloqueadosHoje = appState.bloqueios[data] || [];
    const ocupadosHoje = appState.agendamentos
        .filter(a => a.data === data && a.status === 'ativo')
        .map(a => a.horario);

    let disponiveis = defaultHorarios.filter(h => !bloqueadosHoje.includes(h) && !ocupadosHoje.includes(h));

    if (disponiveis.length === 0) {
        select.innerHTML = '<option value="">Lotado para este dia</option>';
    } else {
        disponiveis.forEach(h => {
            let opt = document.createElement('option');
            opt.value = h; opt.textContent = h;
            select.appendChild(opt);
        });
    }
}

function agendar() {
    const nome = document.getElementById('cliente-nome').value;
    const servico = document.getElementById('cliente-servico').value; // Pegando o serviço
    const data = document.getElementById('cliente-data').value;
    const horario = document.getElementById('cliente-horario').value;

    if (!nome || !servico || !data || !horario) return alert('Por favor, preencha todos os campos.');

    const codigo = Math.random().toString(36).substring(2, 6).toUpperCase();
    
    // Salvando o serviço no banco de dados do navegador
    appState.agendamentos.push({ id: codigo, data, horario, nome, servico, status: 'ativo' });
    saveState();

    const msgBox = document.getElementById('agendamento-sucesso');
    msgBox.innerHTML = `<strong>Tudo certo, ${nome}!</strong><br>Serviço: ${servico}<br>Horário: ${horario} no dia ${data}.<br>Código para cancelar: <strong>${codigo}</strong>`;
    msgBox.style.display = 'block';

    document.getElementById('cliente-nome').value = '';
    document.getElementById('cliente-servico').value = '';
    loadAvailableSlots();
    showTab('tab-agendamentos');
}

function cancelarAgendamento() {
    const codigo = document.getElementById('codigo-cancelamento').value.toUpperCase();
    const index = appState.agendamentos.findIndex(a => a.id === codigo && a.status === 'ativo');

    if (index === -1) return alert('Código não encontrado.');

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

        rows.push(`
            <tr class="border-t border-slate-200">
                <td class="py-3 px-3">${a.nome}</td>
                <td class="py-3 px-3">${a.horario}</td>
                <td class="py-3 px-3">${statusLabel}</td>
                <td class="py-3 px-3">${action}</td>
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

    const ocupacaoCtx = document.getElementById('ocupacaoChart').getContext('2d');
    const proporcaoCtx = document.getElementById('proporcaoChart').getContext('2d');

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
                    backgroundColor: '#D4AF37',
                    borderRadius: 12,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } }
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
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 18 } }
                }
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
        // Mensagem de zap agora inclui o serviço
        const msg = encodeURIComponent(`Olá ${a.nome}, confirmando seu horário hoje às ${a.horario} para ${a.servico}. Te aguardamos!`);

        return `
            <div class="list-item">
                <div><strong>${a.horario}</strong> - ${a.nome} (${a.servico})<br><small>${tempoTxt}</small></div>
                <button class="btn-success" onclick="window.open('https://wa.me/?text=${msg}')">Enviar Lembrete</button>
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