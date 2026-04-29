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

function saveState() {
    localStorage.setItem('barbeariaState', JSON.stringify(appState));
    updateBadge();
}

// --- NAVEGAÇÃO E LOGIN ---
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    
    if(tabId === 'tab-agendar') loadAvailableSlots();
    if(tabId === 'tab-agendamentos') renderAdminAgendamentos();
    if(tabId === 'tab-lembretes') renderAdminLembretes();
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
    const container = document.getElementById('lista-agendamentos');
    const hoje = new Date().toISOString().split('T')[0];
    
    const ativosHoje = appState.agendamentos.filter(a => a.data === hoje && a.status === 'ativo');
    
    if (ativosHoje.length === 0) {
        container.innerHTML = '<p>Nenhum agendamento pendente para hoje.</p>';
        return;
    }

    ativosHoje.sort((a,b) => a.horario.localeCompare(b.horario));
    container.innerHTML = ativosHoje.map(a => `
        <div class="list-item">
            <div><strong>${a.horario}</strong> - ${a.nome}<br><small>Serviço: <strong>${a.servico}</strong> (Cód: ${a.id})</small></div>
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