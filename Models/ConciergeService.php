<?php
/**
 * Serviço Concierge Virtual 24h (Voz & Texto com NLU e Integração MySQL Real)
 *
 * Responsável por interpretar linguagem natural falada (Web Speech API) ou digitada,
 * manter o contexto multi-turno da conversa, consultar horários, barbeiros, serviços,
 * fidelidade e Clube VIP em tempo real no MySQL e efetivar/cancelar agendamentos
 * acionando os padrões GoF Strategy e Observer existentes no sistema.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Agendamento.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/ObserverInterface.php';
require_once __DIR__ . '/AuditoriaObserver.php';
require_once __DIR__ . '/NotificacaoWhatsAppObserver.php';
require_once __DIR__ . '/DescontoStrategy.php';
require_once __DIR__ . '/CalculadoraPreco.php';

class ConciergeService {
    private PDO $db;
    private Agendamento $agendamentoModel;

    public function __construct(?PDO $db = null) {
        date_default_timezone_set('America/Sao_Paulo');
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->agendamentoModel = new Agendamento($this->db);
    }

    /**
     * Processa uma mensagem do usuário com contexto conversacional multi-turno.
     */
    public function processarMensagem(string $mensagemBruta, array $contexto = []): array {
        $mensagem = trim($mensagemBruta);
        $norm = $this->normalizar($mensagem);

        $ctx = array_merge([
            'etapa' => null,
            'servico_id' => null,
            'servico_nome' => null,
            'servico_preco' => null,
            'barbeiro_id' => null,
            'barbeiro_nome' => null,
            'data_agendada' => null,
            'horario' => null,
            'periodo' => null,
            'cliente_nome' => null,
            'cliente_telefone' => null,
            'cupom' => null,
            'dados_preenchidos_auto' => false,
        ], $contexto);

        if ($mensagem === '') {
            return $this->montarSaudacaoInicial($ctx);
        }

        // 1. Comandos de reinício / limpar conversa / trocar horário
        if (preg_match('/\b(ver outros horarios|trocar horario|mudar horario|outro horario)\b/', $norm) && !empty($ctx['data_agendada'])) {
            $ctx['horario'] = null;
            $ctx['etapa'] = 'escolher_horario';
            return $this->conduzirFluxoAgendamento($ctx, $this->listarServicos(), $this->listarBarbeiros(), false);
        }

        if (preg_match('/\b(recomecar|limpar conversa|reiniciar|comecar de novo|esquecer|menu inicial)\b/', $norm)) {
            $novoCtx = [
                'etapa' => null,
                'cliente_nome' => $ctx['cliente_nome'] ?? null,
                'cliente_telefone' => $ctx['cliente_telefone'] ?? null,
                'dados_preenchidos_auto' => !empty($ctx['cliente_nome']),
            ];
            return [
                'success' => true,
                'intencao' => 'saudacao',
                'resposta' => "Conversa reiniciada! ✨ Como posso ajudar você agora na **Barbearia VIP**?",
                'resposta_voz' => "Conversa reiniciada! Como posso ajudar você agora na Barbearia VIP?",
                'contexto' => $novoCtx,
                'chips' => $this->chipsMenuPrincipal(),
                'card_confirmacao' => null,
            ];
        }

        $servicos = $this->listarServicos();
        $barbeiros = $this->listarBarbeiros();

        // 2. Intenção: Cancelar Agendamento por código (ou pedir para cancelar)
        if ($ctx['etapa'] === 'aguardando_codigo_cancelar' || preg_match('/\b(cancelar|desmarcar)\b/', $norm)) {
            // Evita capturar "cancelar fluxo" se o usuário disser "não quero mais agendar"
            if (preg_match('/\b(nao quero mais|desistir|deixa pra la)\b/', $norm)) {
                $ctx['etapa'] = null;
                return [
                    'success' => true,
                    'intencao' => 'cancelar_fluxo',
                    'resposta' => "Sem problemas! Cancelei o preenchimento atual. Se precisar de algo mais, estou à disposição. 💈",
                    'resposta_voz' => "Sem problemas! Cancelei o preenchimento atual. Se precisar de algo mais, estou à disposição.",
                    'contexto' => ['etapa' => null],
                    'chips' => $this->chipsMenuPrincipal(),
                    'card_confirmacao' => null,
                ];
            }
            return $this->tratarIntencaoCancelamento($mensagem, $norm, $ctx);
        }

        // 3. Intenção informativa: Cartão Fidelidade / Selos
        if ($ctx['etapa'] === 'aguardando_tel_fidelidade' || (preg_match('/\b(fidelidade|selo|selos|pontos|corte gratis|cortes acumulados)\b/', $norm) && !preg_match('/\b(agendar|marcar)\b/', $norm))) {
            return $this->tratarConsultaFidelidade($mensagem, $ctx);
        }

        // 4. Intenção informativa: Clube VIP / Assinatura / Barber Pass
        if (preg_match('/\b(clube vip|assinatura|assinante|barber pass|planos mensais|plano vip|mensalidade)\b/', $norm) && !preg_match('/\b(agendar|marcar)\b/', $norm)) {
            return $this->tratarConsultaClubeVip($ctx);
        }

        // 5. Intenção informativa: Loja / Produtos / Pomadas
        if (preg_match('/\b(loja|produto|produtos|pomada|oleo|balm|shampoo|tonico|pente|comprar)\b/', $norm) && !preg_match('/\b(agendar|marcar)\b/', $norm)) {
            return $this->tratarConsultaLoja($ctx);
        }

        // 6. Intenção informativa: Visagismo / Qual corte combina
        if (preg_match('/\b(visagismo|visagista|formato de rosto|rosto redondo|rosto quadrado|rosto oval|rosto diamante|qual corte combina|simulador|camera)\b/', $norm)) {
            return $this->tratarConsultaVisagismo($norm, $ctx);
        }

        // 7. Intenção informativa pura: Tabela de Preços / Serviços / Equipe de Barbeiros
        if ($ctx['etapa'] === null && preg_match('/\b(preco|precos|valores|quanto custa|tabela|quais servicos|cardapio de cortes)\b/', $norm) && !preg_match('/\b(agendar|marcar|quero cortar|hoje|amanha|segunda|terca|quarta|quinta|sexta|sabado)\b/', $norm)) {
            return $this->tratarConsultaServicos($servicos, $ctx);
        }

        if ($ctx['etapa'] === null && preg_match('/\b(quais barbeiros|quem sao os barbeiros|equipe|profissionais)\b/', $norm) && !preg_match('/\b(agendar|marcar)\b/', $norm)) {
            return $this->tratarConsultaBarbeiros($barbeiros, $ctx);
        }

        // 8. Extração NLU de entidades para Agendamento / Consulta de Horários
        $servicoDetectado = $this->extrairServico($norm, $servicos);
        $barbeiroDetectado = $this->extrairBarbeiro($norm, $barbeiros);
        $dataDetectada = $this->extrairData($mensagem, $norm);
        $tempoDetectado = $this->extrairHorarioEPeriodo($mensagem, $norm);
        $telefoneDetectado = $this->extrairTelefone($mensagem);
        $cupomDetectado = $this->extrairCupom($mensagem, $norm);

        $tinhaSlotNovo = false;

        if ($servicoDetectado) {
            $ctx['servico_id'] = (int)$servicoDetectado['id'];
            $ctx['servico_nome'] = $servicoDetectado['nome'];
            $ctx['servico_preco'] = (float)$servicoDetectado['preco'];
            $tinhaSlotNovo = true;
        }

        if ($barbeiroDetectado) {
            if ($barbeiroDetectado['id'] === 0) {
                $ctx['barbeiro_id'] = null;
                $ctx['barbeiro_nome'] = 'Qualquer Mestre Barbeiro';
            } else {
                $ctx['barbeiro_id'] = (int)$barbeiroDetectado['id'];
                $ctx['barbeiro_nome'] = $barbeiroDetectado['nome'];
            }
            $tinhaSlotNovo = true;
        }

        if ($dataDetectada) {
            // Se mudou a data, limpa o horário anterior a menos que também tenha falado horário agora
            if ($ctx['data_agendada'] !== $dataDetectada && empty($tempoDetectado['horario'])) {
                $ctx['horario'] = null;
            }
            $ctx['data_agendada'] = $dataDetectada;
            $tinhaSlotNovo = true;
        }

        if (!empty($tempoDetectado['periodo'])) {
            $ctx['periodo'] = $tempoDetectado['periodo'];
            $tinhaSlotNovo = true;
        }

        if (!empty($tempoDetectado['horario'])) {
            $ctx['horario'] = $tempoDetectado['horario'];
            $tinhaSlotNovo = true;
        }

        if ($cupomDetectado) {
            $ctx['cupom'] = $cupomDetectado;
            $tinhaSlotNovo = true;
        }

        if ($telefoneDetectado) {
            $ctx['cliente_telefone'] = $telefoneDetectado;
            $ctx['dados_preenchidos_auto'] = false;
            $tinhaSlotNovo = true;

            // Se ainda não temos o nome do cliente, tenta puxar automaticamente do histórico MySQL ou Clube VIP!
            if (empty($ctx['cliente_nome'])) {
                $nomeBanco = $this->buscarNomePorTelefone($telefoneDetectado);
                if ($nomeBanco) {
                    $ctx['cliente_nome'] = $nomeBanco;
                }
            }
        }

        $nomeDetectado = $this->extrairNomeCliente($mensagem, $norm, $ctx['etapa'], $servicos, $barbeiros);
        if ($nomeDetectado) {
            $ctx['cliente_nome'] = $nomeDetectado;
            $ctx['dados_preenchidos_auto'] = false;
            $tinhaSlotNovo = true;
        }

        // Verifica se é apenas consulta rápida de horários livres sem serviço definido ainda
        $querHorarios = (bool)preg_match('/\b(horario|horarios|vaga|vagas|disponivel|disponiveis|agenda livre|tem hora)\b/', $norm);
        $querAgendar = (bool)preg_match('/\b(agendar|marcar|quero cortar|reservar|confirmar|quero fazer|fazer o corte|fazer a barba|cortar)\b/', $norm);
        $confirmouExplicito = (bool)preg_match('/^(sim|confirmar|confirmo|confirmar agendamento|pode confirmar|pode marcar|fechado|ok confirmar|isso mesmo|agendar agora)\b/', $norm)
            || str_contains($norm, 'confirmar agendamento agora');

        // Saudação simples quando não há fluxo ativo nem entidades extraídas
        if (!$tinhaSlotNovo && !$querHorarios && !$querAgendar && !$confirmouExplicito && $ctx['etapa'] === null) {
            if (preg_match('/\b(oi|ola|bom dia|boa tarde|boa noite|e ai|opa|tudo bem|ajuda)\b/', $norm)) {
                return $this->montarSaudacaoInicial($ctx);
            }

            return [
                'success' => true,
                'intencao' => 'orientacao',
                'resposta' => "Posso agendar seu corte por texto ou voz em segundos! 💈\n\nExperimente dizer algo como:\n• *\"Quero cortar degradê amanhã à tarde\"*\n• *\"Quais horários livres para hoje?\"*\n• *\"Consultar meus selos de fidelidade\"*",
                'resposta_voz' => "Posso agendar seu corte por texto ou voz em segundos! Diga por exemplo: quero cortar degradê amanhã à tarde, ou pergunte quais horários estão livres hoje.",
                'contexto' => $ctx,
                'chips' => $this->chipsMenuPrincipal(),
                'card_confirmacao' => null,
            ];
        }

        // Se o usuário pediu apenas para ver horários de um dia e não iniciou escolha de serviço
        if ($querHorarios && !$querAgendar && empty($ctx['servico_id']) && $ctx['etapa'] === null) {
            $dataConsulta = $ctx['data_agendada'] ?: date('Y-m-d');
            $ctx['data_agendada'] = $dataConsulta;
            $livres = $this->obterHorariosLivres($dataConsulta, $ctx['periodo']);
            $dataBonita = $this->formatarDataHumana($dataConsulta);

            if (empty($livres)) {
                $prox = $this->sugerirProximaDataComVaga($dataConsulta);
                $chips = [];
                if ($prox) {
                    $chips[] = ['label' => '📅 Ver ' . $this->formatarDataHumana($prox['data']), 'valor' => 'Horários em ' . date('d/m/Y', strtotime($prox['data']))];
                }
                foreach (array_slice($servicos, 0, 2) as $s) {
                    $chips[] = ['label' => "✂️ Agendar {$s['nome']}", 'valor' => "Quero agendar {$s['nome']}"];
                }
                return [
                    'success' => true,
                    'intencao' => 'horarios',
                    'resposta' => "Para **{$dataBonita}**" . ($ctx['periodo'] ? " ({$this->nomePeriodo($ctx['periodo'])})" : "") . " não restam horários livres. ⚠️" .
                        ($prox ? "\n\nMas tenho vagas em **{$this->formatarDataHumana($prox['data'])}** a partir das **{$prox['primeiro_horario']}**! Quer aproveitar?" : ""),
                    'resposta_voz' => "Para {$dataBonita} não restam horários livres." . ($prox ? " Mas tenho vagas em {$this->formatarDataHumana($prox['data'])} a partir das {$prox['primeiro_horario']}." : ""),
                    'contexto' => $ctx,
                    'chips' => $chips,
                    'card_confirmacao' => null,
                ];
            }

            $chips = [];
            foreach (array_slice($livres, 0, 6) as $h) {
                $chips[] = ['label' => "⏰ Agendar às {$h}", 'valor' => "Quero agendar em " . date('d/m/Y', strtotime($dataConsulta)) . " às {$h}"];
            }

            return [
                'success' => true,
                'intencao' => 'horarios',
                'resposta' => "Em **{$dataBonita}**" . ($ctx['periodo'] ? " no período da **{$this->nomePeriodo($ctx['periodo'])}**" : "") . ", temos estes horários livres:\n\n**" . implode(' • ', $livres) . "**\n\nToque em um horário abaixo ou diga qual serviço deseja agendar! ✂️",
                'resposta_voz' => "Em {$dataBonita}, temos horários livres às " . implode(', ', array_slice($livres, 0, 5)) . ". Qual horário e serviço você prefere?",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        // 9. Fluxo Guiado Inteligente de Agendamento (Slot-Filling Multi-Turno)
        return $this->conduzirFluxoAgendamento($ctx, $servicos, $barbeiros, $confirmouExplicito);
    }

    /**
     * Conduz o preenchimento inteligente dos slots (Serviço -> Data -> Horário -> Nome/WhatsApp -> Confirmação MySQL).
     */
    private function conduzirFluxoAgendamento(array $ctx, array $servicos, array $barbeiros, bool $confirmouExplicito): array {
        // Passo A: Falta escolher o Serviço
        if (empty($ctx['servico_id'])) {
            $ctx['etapa'] = 'escolher_servico';
            $chips = [];
            foreach ($servicos as $s) {
                $precoFmt = number_format((float)$s['preco'], 2, ',', '.');
                $chips[] = [
                    'label' => "✂️ {$s['nome']} (R$ {$precoFmt})",
                    'valor' => "Serviço {$s['nome']}"
                ];
            }

            $detalheJaTem = [];
            if (!empty($ctx['barbeiro_nome'])) $detalheJaTem[] = "com **{$ctx['barbeiro_nome']}**";
            if (!empty($ctx['data_agendada'])) $detalheJaTem[] = "para **{$this->formatarDataHumana($ctx['data_agendada'])}**";
            if (!empty($ctx['horario'])) $detalheJaTem[] = "às **{$ctx['horario']}**";
            $complemento = !empty($detalheJaTem) ? " (" . implode(' ', $detalheJaTem) . ")" : "";

            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => "Perfeito! Vamos garantir seu horário{$complemento}. 💈\n\nQual **serviço** você deseja realizar?",
                'resposta_voz' => "Perfeito! Qual serviço você deseja realizar?",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        // Passo B: Falta escolher a Data
        if (empty($ctx['data_agendada'])) {
            $ctx['etapa'] = 'escolher_data';
            $hoje = date('Y-m-d');
            $amanha = date('Y-m-d', strtotime('+1 day'));
            $depois = date('Y-m-d', strtotime('+2 days'));

            $chips = [
                ['label' => '📅 Hoje (' . date('d/m') . ')', 'valor' => 'Para hoje'],
                ['label' => '📅 Amanhã (' . date('d/m', strtotime($amanha)) . ')', 'valor' => 'Para amanhã'],
                ['label' => '📅 ' . $this->nomeDiaSemana($depois) . ' (' . date('d/m', strtotime($depois)) . ')', 'valor' => 'Para ' . date('d/m/Y', strtotime($depois))],
            ];

            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => "Ótima escolha: **{$ctx['servico_nome']}**" . (!empty($ctx['barbeiro_nome']) ? " com **{$ctx['barbeiro_nome']}**" : "") . "! ✨\n\nPara **qual dia** você quer agendar? Pode tocar abaixo ou falar o dia (ex.: *\"amanhã à tarde\"* ou *\"sexta-feira\"*).",
                'resposta_voz' => "Ótima escolha, {$ctx['servico_nome']}! Para qual dia você quer agendar?",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        // Valida se o horário já escolhido está realmente livre na data escolhida
        $todosLivresDia = $this->obterHorariosLivres($ctx['data_agendada'], null);
        $livresFiltrados = $this->obterHorariosLivres($ctx['data_agendada'], $ctx['periodo']);

        if (!empty($ctx['horario']) && !in_array($ctx['horario'], $todosLivresDia, true)) {
            $horarioIndisponivel = $ctx['horario'];
            $ctx['horario'] = null;
            $ctx['etapa'] = 'escolher_horario';

            if (empty($todosLivresDia)) {
                $prox = $this->sugerirProximaDataComVaga($ctx['data_agendada']);
                $chips = [
                    ['label' => '📅 Amanhã', 'valor' => 'Para amanhã'],
                ];
                if ($prox) {
                    $chips[] = ['label' => '⚡ ' . $this->formatarDataHumana($prox['data']), 'valor' => 'Para ' . date('d/m/Y', strtotime($prox['data']))];
                }
                return [
                    'success' => true,
                    'intencao' => 'agendar',
                    'resposta' => "Puxa, o horário **{$horarioIndisponivel}** já está ocupado e a agenda de **{$this->formatarDataHumana($ctx['data_agendada'])}** lotou! ⚠️" .
                        ($prox ? "\n\nTenho vaga livre em **{$this->formatarDataHumana($prox['data'])}** (a partir das **{$prox['primeiro_horario']}**). Quer mudar para esse dia?" : "\n\nEscolha outra data para verificarmos:"),
                    'resposta_voz' => "O horário das {$horarioIndisponivel} já está ocupado e esse dia lotou. Quer escolher outra data?",
                    'contexto' => $ctx,
                    'chips' => $chips,
                    'card_confirmacao' => null,
                ];
            }

            $chips = array_map(fn($h) => ['label' => "⏰ {$h}", 'valor' => "Às {$h}"], array_slice($todosLivresDia, 0, 6));
            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => "O horário **{$horarioIndisponivel}** em **{$this->formatarDataHumana($ctx['data_agendada'])}** já foi preenchido! ⚠️\n\nMas ainda tenho estes horários livres no mesmo dia — qual prefere?",
                'resposta_voz' => "O horário das {$horarioIndisponivel} já foi preenchido, mas ainda tenho vagas às " . implode(', ', array_slice($todosLivresDia, 0, 4)) . ". Qual prefere?",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        // Passo C: Falta escolher o Horário
        if (empty($ctx['horario'])) {
            $ctx['etapa'] = 'escolher_horario';
            $listaExibir = !empty($livresFiltrados) ? $livresFiltrados : $todosLivresDia;

            if (empty($listaExibir)) {
                $prox = $this->sugerirProximaDataComVaga($ctx['data_agendada']);
                $ctx['data_agendada'] = null;
                $ctx['etapa'] = 'escolher_data';
                $chips = [];
                if ($prox) {
                    $chips[] = [
                        'label' => '📅 ' . $this->formatarDataHumana($prox['data']) . " ({$prox['primeiro_horario']})",
                        'valor' => 'Para ' . date('d/m/Y', strtotime($prox['data']))
                    ];
                }
                $chips[] = ['label' => '📅 Escolher Amanhã', 'valor' => 'Para amanhã'];

                return [
                    'success' => true,
                    'intencao' => 'agendar',
                    'resposta' => "Nossa agenda para essa data já está 100% preenchida! 🔥" .
                        ($prox ? "\n\nO próximo dia com horários disponíveis é **{$this->formatarDataHumana($prox['data'])}** ({$prox['qtd']} vagas livres). Vamos agendar nele?" : "\n\nQual outro dia fica bom para você?"),
                    'resposta_voz' => "Nossa agenda para essa data já está completa." . ($prox ? " O próximo dia com vagas é {$this->formatarDataHumana($prox['data'])}. Vamos agendar nele?" : " Qual outro dia fica bom para você?"),
                    'contexto' => $ctx,
                    'chips' => $chips,
                    'card_confirmacao' => null,
                ];
            }

            $avisoPeriodo = '';
            if (!empty($ctx['periodo']) && empty($livresFiltrados)) {
                $avisoPeriodo = "No período da **{$this->nomePeriodo($ctx['periodo'])}** não há mais vagas, mas tenho estes horários no mesmo dia:\n\n";
            }

            $chips = array_map(fn($h) => ['label' => "⏰ {$h}", 'valor' => "Às {$h}"], array_slice($listaExibir, 0, 8));

            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => $avisoPeriodo . "Para **{$ctx['servico_nome']}** em **{$this->formatarDataHumana($ctx['data_agendada'])}**, escolha seu horário disponível abaixo:",
                'resposta_voz' => "Para {$ctx['servico_nome']} em {$this->formatarDataHumana($ctx['data_agendada'])}, temos vagas às " . implode(', ', array_slice($listaExibir, 0, 4)) . ". Qual horário você escolhe?",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        // Passo D: Falta Nome ou WhatsApp do Cliente
        $etapaAnterior = $ctx['etapa'];
        if (empty($ctx['cliente_nome']) || empty($ctx['cliente_telefone'])) {
            $ctx['etapa'] = 'informar_dados';

            if (empty($ctx['cliente_nome']) && empty($ctx['cliente_telefone'])) {
                return [
                    'success' => true,
                    'intencao' => 'agendar',
                    'resposta' => "Horário **{$ctx['horario']}** separado em **{$this->formatarDataHumana($ctx['data_agendada'])}** para **{$ctx['servico_nome']}**! 🎯\n\nPara emitir seu voucher VIP, me diga seu **Nome Completo e WhatsApp** (ex.: *Carlos Silva, 11 98888-7777*):",
                    'resposta_voz' => "Horário das {$ctx['horario']} separado! Para finalizar, me diga seu nome e número de WhatsApp.",
                    'contexto' => $ctx,
                    'chips' => [
                        ['label' => '🔄 Trocar Horário', 'valor' => 'Ver outros horários'],
                    ],
                    'card_confirmacao' => null,
                ];
            }

            if (empty($ctx['cliente_telefone'])) {
                return [
                    'success' => true,
                    'intencao' => 'agendar',
                    'resposta' => "Prazer, **{$ctx['cliente_nome']}**! 👋 Agora só falta o seu número de **WhatsApp com DDD** para eu confirmar e gerar seu comprovante:",
                    'resposta_voz' => "Prazer, {$ctx['cliente_nome']}! Qual é o seu WhatsApp com DDD para eu confirmar o agendamento?",
                    'contexto' => $ctx,
                    'chips' => [],
                    'card_confirmacao' => null,
                ];
            }

            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => "Anotei seu WhatsApp (**{$ctx['cliente_telefone']}**)! 📱 Como posso chamar você (**seu nome**)?",
                'resposta_voz' => "Anotei seu WhatsApp! Qual é o seu nome para finalizar?",
                'contexto' => $ctx,
                'chips' => [],
                'card_confirmacao' => null,
            ];
        }

        // Passo E: Temos todos os dados!
        // Se os dados vieram pré-preenchidos automaticamente do navegador e o usuário ainda não clicou em Confirmar, pede confirmação de 1 clique.
        // Se o usuário acabou de informar os dados manualmente na etapa 'informar_dados' OU confirmou explicitamente, grava direto no MySQL!
        if (!empty($ctx['dados_preenchidos_auto']) && !$confirmouExplicito && $etapaAnterior !== 'pronto_confirmar' && $etapaAnterior !== 'informar_dados') {
            $ctx['etapa'] = 'pronto_confirmar';
            $assinante = $this->verificarAssinanteVip($ctx['cliente_telefone']);
            $precoExibir = $assinante ? 'R$ 0,00 (Coberto pelo Clube VIP 👑)' : 'R$ ' . number_format((float)$ctx['servico_preco'], 2, ',', '.');

            return [
                'success' => true,
                'intencao' => 'confirmar_resumo',
                'resposta' => "Confira o resumo do seu agendamento VIP:\n\n" .
                    "• **Cliente:** {$ctx['cliente_nome']} ({$ctx['cliente_telefone']})\n" .
                    "• **Serviço:** {$ctx['servico_nome']}\n" .
                    (!empty($ctx['barbeiro_nome']) ? "• **Profissional:** {$ctx['barbeiro_nome']}\n" : "") .
                    "• **Data & Hora:** {$this->formatarDataHumana($ctx['data_agendada'])} às **{$ctx['horario']}**\n" .
                    "• **Valor:** {$precoExibir}\n\n" .
                    "Posso confirmar agora?",
                'resposta_voz' => "Posso confirmar {$ctx['servico_nome']} em {$this->formatarDataHumana($ctx['data_agendada'])} às {$ctx['horario']} para {$ctx['cliente_nome']}?",
                'contexto' => $ctx,
                'chips' => [
                    ['label' => '✅ Confirmar Agendamento Agora', 'valor' => 'Confirmar agendamento agora'],
                    ['label' => '🔄 Trocar Horário', 'valor' => 'recomecar'],
                ],
                'card_confirmacao' => null,
            ];
        }

        // Efetiva o agendamento no banco de dados MySQL!
        return $this->efetivarAgendamentoNoBanco($ctx);
    }

    /**
     * Salva o agendamento no MySQL, aplica Strategy de desconto/Clube VIP e dispara Observers GoF.
     */
    private function efetivarAgendamentoNoBanco(array $ctx): array {
        $ag = new Agendamento($this->db);
        $ag->cliente_nome = $ctx['cliente_nome'];
        $ag->cliente_telefone = $ctx['cliente_telefone'];
        $ag->servico_id = (int)$ctx['servico_id'];
        $ag->barbeiro_id = !empty($ctx['barbeiro_id']) ? (int)$ctx['barbeiro_id'] : null;
        $ag->data_agendada = $ctx['data_agendada'];
        $ag->horario = substr($ctx['horario'], 0, 5);

        // Checagem final de concorrência
        $ocupados = $ag->getHorariosOcupados($ag->data_agendada);
        $bloqueados = $ag->getHorariosBloqueados($ag->data_agendada);
        if (in_array($ag->horario, $ocupados, true) || in_array($ag->horario, $bloqueados, true)) {
            $ctx['horario'] = null;
            $ctx['etapa'] = 'escolher_horario';
            $livres = $this->obterHorariosLivres($ag->data_agendada, null);
            $chips = array_map(fn($h) => ['label' => "⏰ {$h}", 'valor' => "Às {$h}"], array_slice($livres, 0, 6));
            return [
                'success' => true,
                'intencao' => 'agendar',
                'resposta' => "Alguém acabou de reservar as **{$ctx['horario']}** um segundo antes! ⚡ Escolha outro horário livre abaixo:",
                'resposta_voz' => "Esse horário acabou de ser reservado. Escolha outro horário disponível.",
                'contexto' => $ctx,
                'chips' => $chips,
                'card_confirmacao' => null,
            ];
        }

        $ag->codigo = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        if (!$ag->criar()) {
            return [
                'success' => false,
                'intencao' => 'erro',
                'resposta' => "Tive um contratempo ao gravar no banco de dados. Por favor, tente confirmar novamente.",
                'resposta_voz' => "Não foi possível gravar o agendamento agora. Tente novamente.",
                'contexto' => $ctx,
                'chips' => [['label' => '🔄 Tentar Novamente', 'valor' => 'Confirmar agendamento agora']],
                'card_confirmacao' => null,
            ];
        }

        // Preço, Clube VIP e Padrão Strategy de Cupom
        $servicoNomePuro = $ctx['servico_nome'] ?? 'Corte VIP';
        $servicoPreco = (float)($ctx['servico_preco'] ?? 0);
        try {
            $stSvc = $this->db->prepare("SELECT nome, preco FROM servicos WHERE id = :id LIMIT 1");
            $stSvc->execute([':id' => $ag->servico_id]);
            if ($rowS = $stSvc->fetch(PDO::FETCH_ASSOC)) {
                $servicoNomePuro = $rowS['nome'];
                $servicoPreco = (float)$rowS['preco'];
            }
        } catch (Throwable $e) {}

        $assinante = $this->verificarAssinanteVip($ag->cliente_telefone);
        $cupomUsado = trim((string)($ctx['cupom'] ?? ''));
        $beneficioTexto = null;

        if ($assinante) {
            $servicoPreco = 0.0;
            $beneficioTexto = "Assinante {$assinante['plano_nome']} (100% Coberto)";
        } elseif ($servicoPreco > 0) {
            try {
                $calc = CalculadoraPreco::criarMelhorEstrategia($cupomUsado, $ag->data_agendada, $servicoPreco);
                $resCalc = $calc->calcular($servicoPreco, $ag->data_agendada, $cupomUsado);
                if (!empty($resCalc['teve_desconto'])) {
                    $servicoPreco = (float)$resCalc['valor_final'];
                    $beneficioTexto = $resCalc['estrategia_desc'] ?? 'Desconto VIP Aplicado';
                }
            } catch (Throwable $e) {}
        }

        $barbeiroNome = $ctx['barbeiro_nome'] ?? null;
        $barbeiroTelefone = null;
        if (!empty($ag->barbeiro_id)) {
            try {
                $stB = $this->db->prepare("SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1");
                $stB->execute([':id' => $ag->barbeiro_id]);
                if ($rowB = $stB->fetch(PDO::FETCH_ASSOC)) {
                    $barbeiroNome = $rowB['nome'];
                    $barbeiroTelefone = $rowB['telefone'];
                }
            } catch (Throwable $e) {}
        }

        $valorTotalFmt = 'R$ ' . number_format($servicoPreco, 2, ',', '.');
        $dataBr = date('d/m/Y', strtotime($ag->data_agendada));

        $dadosEvento = [
            'cliente_nome' => $ag->cliente_nome,
            'cliente_telefone' => $ag->cliente_telefone,
            'servico_nome' => "{$servicoNomePuro} ({$valorTotalFmt})",
            'servico_preco' => $servicoPreco,
            'valor_total' => $servicoPreco,
            'valor_total_formatado' => $valorTotalFmt,
            'barbeiro_id' => $ag->barbeiro_id,
            'barbeiro_nome' => $barbeiroNome,
            'data_agendada' => $dataBr,
            'horario' => $ag->horario,
            'codigo' => $ag->codigo,
        ];

        $whatsCliente = WhatsAppService::gerarLinkConfirmacaoCliente($dadosEvento);

        // Dispara Padrão GoF Observer (Auditoria + WhatsApp Log)
        try {
            $subject = new AgendamentoSubject();
            $subject->attach(new AuditoriaObserver());
            $subject->attach(new NotificacaoWhatsAppObserver());
            $subject->dispararEvento('AGENDAMENTO_CRIADO', $dadosEvento);
        } catch (Throwable $e) {}

        // Limpa os slots do agendamento concluído mantendo nome/telefone para conveniência futura
        $novoCtx = [
            'etapa' => null,
            'cliente_nome' => $ag->cliente_nome,
            'cliente_telefone' => $ag->cliente_telefone,
            'dados_preenchidos_auto' => true,
        ];

        return [
            'success' => true,
            'intencao' => 'confirmado',
            'resposta' => "🎉 **Agendamento Confirmado com Sucesso!**\n\nTudo pronto, **{$ag->cliente_nome}**! Seu horário para **{$servicoNomePuro}**" . ($barbeiroNome ? " com **{$barbeiroNome}**" : "") . " está reservado para **{$dataBr} às {$ag->horario}**.\n\nSeu código exclusivo é **`{$ag->codigo}`**.",
            'resposta_voz' => "Agendamento confirmado com sucesso, {$ag->cliente_nome}! Seu horário para {$servicoNomePuro} está reservado para {$dataBr} às {$ag->horario}. Seu código é " . implode(' ', str_split($ag->codigo)) . ".",
            'contexto' => $novoCtx,
            'chips' => [
                ['label' => '📅 Novo Agendamento', 'valor' => 'Quero fazer outro agendamento'],
                ['label' => '🎁 Ver Meus Selos Fidelidade', 'valor' => "Consultar fidelidade {$ag->cliente_telefone}"],
            ],
            'card_confirmacao' => [
                'codigo' => $ag->codigo,
                'cliente_nome' => $ag->cliente_nome,
                'servico_nome' => $servicoNomePuro,
                'barbeiro_nome' => $barbeiroNome ?: 'Equipe VIP Borcelle',
                'data_formatada' => $dataBr,
                'horario' => $ag->horario,
                'valor_total_formatado' => $valorTotalFmt,
                'beneficio' => $beneficioTexto,
                'assinante_vip' => (bool)$assinante,
                'whatsapp_url_cliente' => $whatsCliente,
                'voucher_url' => "../api/comprovante.php?codigo={$ag->codigo}",
            ],
        ];
    }

    /**
     * Cancelamento de agendamento direto pelo Concierge via código de 6 caracteres.
     */
    private function tratarIntencaoCancelamento(string $mensagem, string $norm, array $ctx): array {
        // Procura código alfanumérico de 6 caracteres em destaque
        $codigo = null;
        if (preg_match('/\b([A-Za-z0-9]{6})\b/', $mensagem, $matches)) {
            $candidato = strtoupper($matches[1]);
            $palavrasComuns = ['QUERO', 'CANCEL', 'HORARI', 'AGENDA', 'MARCAR', 'CODIGO', 'CORTAR', 'AMANHA', 'SABADO', 'QUINTA', 'QUARTA'];
            if (!in_array($candidato, $palavrasComuns, true) && preg_match('/[0-9]/', $candidato)) {
                $codigo = $candidato;
            } elseif ($ctx['etapa'] === 'aguardando_codigo_cancelar' && !in_array($candidato, $palavrasComuns, true)) {
                $codigo = $candidato;
            }
        }

        if (!$codigo) {
            $ctx['etapa'] = 'aguardando_codigo_cancelar';
            return [
                'success' => true,
                'intencao' => 'cancelar',
                'resposta' => "Claro! Para eu cancelar e liberar a vaga na agenda, me informe o **código de 6 caracteres** do seu agendamento (ex.: `A7B9K2`):",
                'resposta_voz' => "Claro! Me informe o código de seis caracteres do seu agendamento para cancelar.",
                'contexto' => $ctx,
                'chips' => [
                    ['label' => '↩️ Voltar ao Menu', 'valor' => 'recomecar'],
                ],
                'card_confirmacao' => null,
            ];
        }

        $res = $this->agendamentoModel->cancelarPorCodigo($codigo);
        $ctx['etapa'] = null;

        if ($res) {
            try {
                $subject = new AgendamentoSubject();
                $subject->attach(new AuditoriaObserver());
                $subject->attach(new NotificacaoWhatsAppObserver());
                $subject->dispararEvento('AGENDAMENTO_CANCELADO', is_array($res) ? $res : ['codigo' => $codigo]);
            } catch (Throwable $e) {}

            return [
                'success' => true,
                'intencao' => 'cancelado_sucesso',
                'resposta' => "✅ O agendamento de código **`{$codigo}`** foi **cancelado com sucesso** e o horário já foi liberado! Quando quiser remarcar, é só chamar.",
                'resposta_voz' => "Pronto! O agendamento foi cancelado com sucesso e o horário foi liberado.",
                'contexto' => $ctx,
                'chips' => [
                    ['label' => '📅 Remarcar Novo Horário', 'valor' => 'Quero agendar um horário'],
                ],
                'card_confirmacao' => null,
            ];
        }

        return [
            'success' => true,
            'intencao' => 'cancelar',
            'resposta' => "⚠️ Não encontrei nenhum agendamento ativo com o código **`{$codigo}`** (ele pode já estar cancelado ou digitado incorretamente).",
            'resposta_voz' => "Não encontrei nenhum agendamento ativo com esse código.",
            'contexto' => $ctx,
            'chips' => [
                ['label' => '🔑 Tentar Outro Código', 'valor' => 'Quero cancelar um horário'],
                ['label' => '📅 Agendar Horário', 'valor' => 'Quero agendar'],
            ],
            'card_confirmacao' => null,
        ];
    }

    /**
     * Consulta em tempo real dos selos de Fidelidade pelo WhatsApp do cliente.
     */
    private function tratarConsultaFidelidade(string $mensagem, array $ctx): array {
        $tel = $this->extrairTelefone($mensagem) ?: ($ctx['cliente_telefone'] ?? null);
        if (!$tel) {
            $ctx['etapa'] = 'aguardando_tel_fidelidade';
            return [
                'success' => true,
                'intencao' => 'fidelidade',
                'resposta' => "🎁 No **Cartão Fidelidade VIP**, a cada **5 atendimentos** você ganha **1 corte 100% grátis**!\n\nQual é o seu **WhatsApp com DDD** para eu consultar seus selos acumulados?",
                'resposta_voz' => "A cada cinco atendimentos você ganha um corte grátis! Qual é o seu WhatsApp para eu consultar seus selos?",
                'contexto' => $ctx,
                'chips' => [['label' => '↩️ Voltar', 'valor' => 'recomecar']],
                'card_confirmacao' => null,
            ];
        }

        $ctx['etapa'] = null;
        $ctx['cliente_telefone'] = $tel;
        $digitos = preg_replace('/\D/', '', $tel);
        $ultimos8 = substr($digitos, -8);

        $totalCortes = 0;
        $totalResgates = 0;
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM agendamentos WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel AND status != 'cancelado'");
            $st->execute([':tel' => "%{$ultimos8}"]);
            $totalCortes = (int)$st->fetchColumn();

            $stR = $this->db->prepare("SELECT COUNT(*) FROM resgates_fidelidade WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :tel");
            $stR->execute([':tel' => "%{$ultimos8}"]);
            $totalResgates = (int)$stR->fetchColumn();
        } catch (Throwable $e) {}

        $saldoCortes = max(0, $totalCortes - ($totalResgates * 5));
        $selosAtuais = $saldoCortes % 5;
        $premiosDisponiveis = intdiv($saldoCortes, 5);
        $faltam = 5 - $selosAtuais;
        $barra = str_repeat('🟡', $selosAtuais) . str_repeat('⚪', 5 - $selosAtuais);

        if ($premiosDisponiveis > 0) {
            $resp = "🎉 **Parabéns!** Pelo número **{$tel}** você tem **{$premiosDisponiveis} corte(s) GRÁTIS disponível(is)** para resgatar na aba Fidelidade VIP!\n\nCartão atual: {$barra} ({$totalCortes} atendimentos no histórico)";
            $voz = "Parabéns! Você já tem {$premiosDisponiveis} corte grátis disponível para resgatar no seu Cartão Fidelidade!";
        } else {
            $resp = "🎁 **Cartão Fidelidade ({$tel}):**\n\nProgresso: {$barra} (**{$selosAtuais}/5 selos**)\nFaltam apenas **{$faltam} atendimento(s)** para liberar seu próximo **Corte 100% Grátis**!";
            $voz = "Você tem {$selosAtuais} de 5 selos no Cartão Fidelidade. Faltam {$faltam} atendimentos para ganhar seu corte grátis!";
        }

        return [
            'success' => true,
            'intencao' => 'fidelidade',
            'resposta' => $resp,
            'resposta_voz' => $voz,
            'contexto' => $ctx,
            'chips' => [
                ['label' => '🎁 Abrir Cartão Fidelidade', 'valor' => '', 'acao_ui' => 'abrir_aba:tab-fidelidade'],
                ['label' => '✂️ Agendar Próximo Corte', 'valor' => 'Quero agendar um horário'],
            ],
            'card_confirmacao' => null,
        ];
    }

    private function tratarConsultaServicos(array $servicos, array $ctx): array {
        $linhas = [];
        $chips = [];
        foreach ($servicos as $s) {
            $preco = number_format((float)$s['preco'], 2, ',', '.');
            $dur = (int)($s['duracao_minutos'] ?? 30);
            $linhas[] = "• **{$s['nome']}** — **R$ {$preco}** *({$dur} min)*";
            $chips[] = ['label' => "✂️ Agendar {$s['nome']}", 'valor' => "Quero agendar {$s['nome']}"];
        }

        return [
            'success' => true,
            'intencao' => 'servicos',
            'resposta' => "✂️ **Menu de Serviços da Barbearia VIP:**\n\n" . implode("\n", $linhas) . "\n\n💡 *Dica: use o cupom `BEMVINDO15` ou assine o Clube VIP para benefícios exclusivos!* Qual deseja agendar?",
            'resposta_voz' => "Nossos serviços incluem " . implode(', ', array_map(fn($s) => "{$s['nome']} por " . (int)$s['preco'] . " reais", array_slice($servicos, 0, 4))) . ". Qual deles você gostaria de agendar?",
            'contexto' => $ctx,
            'chips' => array_slice($chips, 0, 5),
            'card_confirmacao' => null,
        ];
    }

    private function tratarConsultaBarbeiros(array $barbeiros, array $ctx): array {
        $linhas = [];
        $chips = [];
        foreach ($barbeiros as $b) {
            $esp = $b['especialidade'] ?: 'Mestre Barbeiro';
            $linhas[] = "• **{$b['nome']}** — *{$esp}*";
            $chips[] = ['label' => "💈 Agendar com {$b['nome']}", 'valor' => "Quero agendar com {$b['nome']}"];
        }

        return [
            'success' => true,
            'intencao' => 'barbeiros',
            'resposta' => "💈 **Nossos Mestres Barbeiros:**\n\n" . implode("\n", $linhas) . "\n\nCom qual profissional você prefere agendar?",
            'resposta_voz' => "Nossa equipe conta com " . implode(', ', array_column($barbeiros, 'nome')) . ". Com quem você prefere agendar?",
            'contexto' => $ctx,
            'chips' => $chips,
            'card_confirmacao' => null,
        ];
    }

    private function tratarConsultaClubeVip(array $ctx): array {
        $planos = [];
        try {
            $st = $this->db->query("SELECT nome, preco_mensal, descricao FROM planos_assinatura WHERE ativo = 1 ORDER BY preco_mensal ASC");
            $planos = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {}

        $linhas = [];
        foreach ($planos as $p) {
            $pr = number_format((float)$p['preco_mensal'], 2, ',', '.');
            $linhas[] = "• **{$p['nome']}** — **R$ {$pr}/mês**: {$p['descricao']}";
        }
        $listaTxt = !empty($linhas) ? implode("\n", $linhas) : "• **VIP Silver (R$ 89,90)** • **VIP Gold (R$ 149,90)** • **VIP Black Diamond (R$ 199,90)**";

        return [
            'success' => true,
            'intencao' => 'clube_vip',
            'resposta' => "👑 **Clube de Assinatura Barber Pass:**\n\n{$listaTxt}\n\nAssinantes têm **R$ 0,00 reconhecido automaticamente** pelo telefone na hora de agendar!",
            'resposta_voz' => "No Clube de Assinatura Barber Pass você corta cabelo e faz a barba quantas vezes quiser no mês a partir de 89 e 90, com gratuidade automática nos agendamentos!",
            'contexto' => $ctx,
            'chips' => [
                ['label' => '👑 Ver Planos Clube VIP', 'valor' => '', 'acao_ui' => 'abrir_aba:tab-clube-vip'],
                ['label' => '📅 Agendar Meu Corte', 'valor' => 'Quero agendar um horário'],
            ],
            'card_confirmacao' => null,
        ];
    }

    private function tratarConsultaLoja(array $ctx): array {
        $prods = [];
        try {
            $st = $this->db->query("SELECT nome, preco, categoria FROM produtos WHERE ativo = 1 ORDER BY id ASC LIMIT 5");
            $prods = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {}

        $linhas = [];
        foreach ($prods as $p) {
            $pr = number_format((float)$p['preco'], 2, ',', '.');
            $linhas[] = "• **{$p['nome']}** ({$p['categoria']}) — **R$ {$pr}**";
        }
        $txt = !empty($linhas) ? implode("\n", $linhas) : "Pomada Matte, Óleo para Barba, Balm Hidratante e Shampoo 3 em 1.";

        return [
            'success' => true,
            'intencao' => 'loja',
            'resposta' => "🧴 **Linha Premium Grooming (Loja VIP):**\n\n{$txt}\n\nVocê pode reservar direto na Loja VIP ou adicionar junto com seu agendamento!",
            'resposta_voz' => "Temos linha completa de pomadas, óleos de barba, balms e tônicos disponíveis para reserva na nossa Loja VIP!",
            'contexto' => $ctx,
            'chips' => [
                ['label' => '🧴 Abrir Loja VIP', 'valor' => '', 'acao_ui' => 'abrir_aba:tab-loja'],
                ['label' => '✂️ Agendar Corte', 'valor' => 'Quero agendar'],
            ],
            'card_confirmacao' => null,
        ];
    }

    private function tratarConsultaVisagismo(string $norm, array $ctx): array {
        $dica = "Nosso **Visagista IA com Scanner 468 Pontos e Realidade Aumentada** analisa o formato do seu rosto ao vivo pela câmera!";
        if (str_contains($norm, 'redondo')) {
            $dica = "Para **rosto redondo**, o ideal é **High Fade + Quiff ou Pompadour**, criando linhas verticais que alongam e afinam a silhueta facial!";
        } elseif (str_contains($norm, 'quadrado')) {
            $dica = "Para **rosto quadrado**, cortes como **Fade Americano + Topo Texturizado** valorizam a mandíbula forte com equilíbrio clássico!";
        } elseif (str_contains($norm, 'oval')) {
            $dica = "O **rosto oval** possui proporção versátil e harmoniza super bem com **French Crop, Side Part Navalhado ou Mid Fade**!";
        } elseif (str_contains($norm, 'diamante')) {
            $dica = "Para **rosto diamante**, o **Textured Crop ou Taper Fade** equilibra as maçãs marcadas com textura no topo!";
        }

        return [
            'success' => true,
            'intencao' => 'visagismo',
            'resposta' => "🧠 **Consultoria Rápida de Visagismo:**\n\n{$dica}\n\nQuer abrir a câmera AR 3D para testar os cortes no seu próprio rosto ou já agendar?",
            'resposta_voz' => strip_tags(str_replace('**', '', $dica)) . " Quer testar na câmera com realidade aumentada ou já agendar seu corte?",
            'contexto' => $ctx,
            'chips' => [
                ['label' => '📷 Abrir Visagista AR 3D', 'valor' => '', 'acao_ui' => 'abrir_aba:tab-visagista'],
                ['label' => '✂️ Agendar Corte Agora', 'valor' => 'Quero agendar Corte Degradê'],
            ],
            'card_confirmacao' => null,
        ];
    }

    private function montarSaudacaoInicial(array $ctx): array {
        $saudacaoHora = (int)date('H') < 12 ? 'Bom dia' : ((int)date('H') < 18 ? 'Boa tarde' : 'Boa noite');
        $nome = !empty($ctx['cliente_nome']) ? ', **' . explode(' ', $ctx['cliente_nome'])[0] . '**' : '';
        $nomeVoz = !empty($ctx['cliente_nome']) ? ', ' . explode(' ', $ctx['cliente_nome'])[0] : '';

        return [
            'success' => true,
            'intencao' => 'saudacao',
            'resposta' => "💈 {$saudacaoHora}{$nome}! Sou o **Concierge IA 24h** da Barbearia VIP.\n\nVocê pode **digitar ou falar no microfone** 🎙️ frases como:\n• *\"Quero cortar cabelo e barba amanhã à tarde\"*\n• *\"Quais horários livres hoje?\"*\n\nComo posso te atender agora?",
            'resposta_voz' => "{$saudacaoHora}{$nomeVoz}! Sou o Concierge Virtual da Barbearia VIP. Como posso atender você hoje?",
            'contexto' => $ctx,
            'chips' => $this->chipsMenuPrincipal(),
            'card_confirmacao' => null,
        ];
    }

    private function chipsMenuPrincipal(): array {
        return [
            ['label' => '✂️ Agendar Horário', 'valor' => 'Quero agendar um horário'],
            ['label' => '⏰ Vagas de Hoje', 'valor' => 'Quais horários livres hoje?'],
            ['label' => '💰 Tabela de Preços', 'valor' => 'Quais são os preços dos serviços?'],
            ['label' => '🎁 Consultar Fidelidade', 'valor' => 'Consultar meus selos de fidelidade'],
            ['label' => '🧠 Dica de Visagismo', 'valor' => 'Qual corte combina comigo no visagismo?'],
        ];
    }

    // =========================================================================
    // MOTORES DE EXTRAÇÃO DE LINGUAGEM NATURAL (NLU EM PORTUGUÊS)
    // =========================================================================

    private function normalizar(string $texto): string {
        $texto = mb_strtolower($texto, 'UTF-8');
        $mapa = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n'
        ];
        return strtr($texto, $mapa);
    }

    private function extrairServico(string $norm, array $servicos): ?array {
        // 1. Verifica combinação conjunta "corte e barba" / "barba e cabelo" primeiro
        if ((str_contains($norm, 'barba') && (str_contains($norm, 'corte') || str_contains($norm, 'cabelo'))) || str_contains($norm, 'completo')) {
            foreach ($servicos as $s) {
                $sn = $this->normalizar($s['nome']);
                if (str_contains($sn, 'barba') && (str_contains($sn, 'cabelo') || str_contains($sn, 'corte'))) {
                    return $s;
                }
            }
        }

        // 2. Match exato do nome completo do serviço (ordenado do maior nome para o menor)
        $ordenados = $servicos;
        usort($ordenados, fn($a, $b) => strlen($b['nome']) <=> strlen($a['nome']));
        foreach ($ordenados as $s) {
            $sn = $this->normalizar($s['nome']);
            if ($sn !== '' && str_contains($norm, $sn)) {
                return $s;
            }
        }

        // 3. Sinônimos naturais falados pelo cliente
        if (preg_match('/\b(degrade|navalhado|fade|americano|disfarce)\b/', $norm)) {
            foreach ($servicos as $s) {
                if (str_contains($this->normalizar($s['nome']), 'degrade')) return $s;
            }
        }
        if (preg_match('/\b(social|tesoura|classico|executivo)\b/', $norm)) {
            foreach ($servicos as $s) {
                if (str_contains($this->normalizar($s['nome']), 'social')) return $s;
            }
        }
        if (preg_match('/\b(so barba|fazer a barba|barboterapia|alinhar barba)\b/', $norm)) {
            foreach ($servicos as $s) {
                if (str_contains($this->normalizar($s['nome']), 'barba')) return $s;
            }
        }

        return null;
    }

    private function extrairBarbeiro(string $norm, array $barbeiros): ?array {
        if (preg_match('/\b(qualquer barbeiro|qualquer profissional|tanto faz|primeiro disponivel)\b/', $norm)) {
            return ['id' => 0, 'nome' => 'Qualquer Mestre Barbeiro'];
        }

        foreach ($barbeiros as $b) {
            $nomeNorm = $this->normalizar($b['nome']);
            $partes = array_values(array_filter(explode(' ', $nomeNorm), fn($p) => strlen($p) >= 3 && !in_array($p, ['barbeiro', 'mestre', 'vip'], true)));
            if ($nomeNorm !== '' && str_contains($norm, $nomeNorm)) {
                return $b;
            }
            if (!empty($partes[0]) && preg_match('/\b' . preg_quote($partes[0], '/') . '\b/', $norm)) {
                return $b;
            }
        }
        return null;
    }

    private function extrairData(string $original, string $norm): ?string {
        // 1. YYYY-MM-DD explícito
        if (preg_match('/\b(20\d{2})-(\d{2})-(\d{2})\b/', $original, $m)) {
            if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                return "{$m[1]}-{$m[2]}-{$m[3]}";
            }
        }

        // 2. DD/MM/YYYY ou DD/MM
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $original, $m)) {
            $dia = (int)$m[1];
            $mes = (int)$m[2];
            $ano = !empty($m[3]) ? ((strlen($m[3]) === 2) ? (int)('20' . $m[3]) : (int)$m[3]) : (int)date('Y');
            if (checkdate($mes, $dia, $ano)) {
                $dt = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                if (empty($m[3]) && $dt < date('Y-m-d')) {
                    $dt = sprintf('%04d-%02d-%02d', $ano + 1, $mes, $dia);
                }
                return $dt;
            }
        }

        // 3. Expressões relativas faladas
        if (str_contains($norm, 'depois de amanha')) {
            return date('Y-m-d', strtotime('+2 days'));
        }
        if (preg_match('/\bamanha\b/', $norm)) {
            return date('Y-m-d', strtotime('+1 day'));
        }
        if (preg_match('/\bhoje\b/', $norm)) {
            return date('Y-m-d');
        }

        // 4. "dia 18"
        if (preg_match('/\bdia\s+(\d{1,2})\b/', $norm, $m)) {
            $dia = (int)$m[1];
            $mes = (int)date('m');
            $ano = (int)date('Y');
            if (checkdate($mes, $dia, $ano)) {
                $candidata = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                if ($candidata < date('Y-m-d')) {
                    $proxMes = strtotime('first day of next month');
                    $m2 = (int)date('m', $proxMes);
                    $a2 = (int)date('Y', $proxMes);
                    if (checkdate($m2, $dia, $a2)) {
                        $candidata = sprintf('%04d-%02d-%02d', $a2, $m2, $dia);
                    }
                }
                return $candidata;
            }
        }

        // 5. Dias da semana ("segunda", "sexta", "sábado"...)
        $mapaSemana = [
            'segunda' => 1,
            'terca'   => 2,
            'quarta'  => 3,
            'quinta'  => 4,
            'sexta'   => 5,
            'sabado'  => 6,
            'domingo' => 7,
        ];
        foreach ($mapaSemana as $nomeDia => $alvoN) {
            if (preg_match('/\b' . $nomeDia . '(?:-feira|\s+feira)?\b/', $norm)) {
                $hojeN = (int)date('N');
                $diff = ($alvoN - $hojeN + 7) % 7;
                if ($diff === 0 && preg_match('/\b(proxima|proximo|que vem)\b/', $norm)) {
                    $diff = 7;
                }
                return date('Y-m-d', strtotime("+{$diff} days"));
            }
        }

        return null;
    }

    private function extrairHorarioEPeriodo(string $original, string $norm): array {
        $res = ['horario' => null, 'periodo' => null];

        if (preg_match('/\b(manha|cedo)\b/', $norm)) {
            $res['periodo'] = 'manha';
        } elseif (preg_match('/\b(tarde)\b/', $norm)) {
            $res['periodo'] = 'tarde';
        } elseif (preg_match('/\b(noite|fim do dia)\b/', $norm)) {
            $res['periodo'] = 'noite';
        }

        // Remove telefones e datas para evitar falso positivo de números de hora
        $limpo = preg_replace('/\b20\d{2}-\d{2}-\d{2}\b/', ' ', $norm);
        $limpo = preg_replace('/\b\d{1,2}\/\d{1,2}(?:\/\d{2,4})?\b/', ' ', $limpo);
        $limpo = preg_replace('/\(?\d{2}\)?\s*9?\d{4}[-\s]?\d{4}/', ' ', $limpo);
        $limpo = preg_replace('/\bdia\s+\d{1,2}\b/', ' ', $limpo);

        // Horas por extenso comuns na fala (Web Speech API)
        $extenso = [
            'meio dia' => '12:00', 'meio-dia' => '12:00',
            'oito da manha' => '08:00', 'oito horas' => '08:00',
            'nove da manha' => '09:00', 'nove horas' => '09:00',
            'dez da manha' => '10:00', 'dez horas' => '10:00',
            'onze da manha' => '11:00', 'onze horas' => '11:00',
            'uma da tarde' => '13:00', 'uma hora' => '13:00', 'treze horas' => '13:00',
            'duas da tarde' => '14:00', 'duas horas' => '14:00', 'quatorze horas' => '14:00', 'catorze horas' => '14:00',
            'tres da tarde' => '15:00', 'tres horas' => '15:00', 'quinze horas' => '15:00',
            'quatro da tarde' => '16:00', 'quatro horas' => '16:00', 'dezesseis horas' => '16:00',
            'cinco da tarde' => '17:00', 'cinco horas' => '17:00', 'dezessete horas' => '17:00',
            'seis da tarde' => '18:00', 'seis da noite' => '18:00', 'dezoito horas' => '18:00',
            'sete da noite' => '19:00', 'dezenove horas' => '19:00',
        ];
        foreach ($extenso as $frase => $horaFmt) {
            if (str_contains($limpo, $frase)) {
                $res['horario'] = $horaFmt;
                return $res;
            }
        }

        // Padrões numéricos: "14:00", "14h30", "15h", "às 14", "15 horas"
        if (preg_match('/\b([01]?\d|2[0-3])\s*:\s*([0-5]\d)\b/', $limpo, $m)) {
            $h = (int)$m[1];
            if ($h >= 1 && $h <= 7) $h += 12;
            $res['horario'] = sprintf('%02d:%02d', $h, (int)$m[2]);
            return $res;
        }

        if (preg_match('/\b([01]?\d|2[0-3])\s*h(?:oras?)?\s*([0-5]\d)?\b/', $limpo, $m)) {
            $h = (int)$m[1];
            $min = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
            if ($h >= 1 && $h <= 7) $h += 12;
            $res['horario'] = sprintf('%02d:%02d', $h, $min);
            return $res;
        }

        if (preg_match('/\b(?:as|para as)\s+(\d{1,2})(?:\s*horas?)?\b/', $limpo, $m) || preg_match('/\b(\d{1,2})\s*horas?\b/', $limpo, $m)) {
            $h = (int)$m[1];
            if ($h >= 1 && $h <= 7) $h += 12;
            if ($h >= 8 && $h <= 22) {
                $res['horario'] = sprintf('%02d:00', $h);
            }
        }

        return $res;
    }

    private function extrairTelefone(string $texto): ?string {
        if (preg_match('/(?:\+?55\s*)?\(?([1-9]{2})\)?\s*(9?\d{4})[-\s]?(\d{4})\b/', $texto, $m)) {
            return sprintf('(%s) %s-%s', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    private function extrairCupom(string $original, string $norm): ?string {
        $cuponsConhecidos = ['VIP10', 'BEMVINDO15', 'VOLTAVIP15', 'BARBER20', 'PIX5', 'ASSINANTEVIP'];
        $upper = strtoupper($original);
        foreach ($cuponsConhecidos as $c) {
            if (preg_match('/\b' . $c . '\b/', $upper)) {
                return $c;
            }
        }
        if (preg_match('/\bcupom\s+([A-Za-z0-9]{4,15})\b/', $original, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    private function extrairNomeCliente(string $original, string $norm, ?string $etapa, array $servicos, array $barbeiros): ?string {
        // 1. Frases explícitas: "meu nome é Carlos Silva", "me chamo Bruno", "sou o Rafael"
        if (preg_match('/(?:meu nome [eé]|me chamo|aqui [eé] [oa]|sou [oa]|nome\s*:)\s+([A-Za-zÀ-ÿ]+(?:\s+[A-Za-zÀ-ÿ]+){0,3})/iu', $original, $m)) {
            $candidato = trim($m[1]);
            // Corta palavras que iniciam outra cláusula ("quero", "e meu telefone", "para", "amanha")
            $candidato = preg_replace('/\s+\b(e|quero|gostaria|preciso|para|amanha|amanhã|hoje|sexta|sabado|sábado|segunda|terca|terça|quarta|quinta|meu|telefone|whatsapp|zap|as|às|com)\b.*$/iu', '', $candidato);
            $candidato = trim($candidato, " ,.-");
            if (mb_strlen($candidato) >= 2) {
                return mb_convert_case($candidato, MB_CASE_TITLE, 'UTF-8');
            }
        }

        // 2. Se estamos especificamente na etapa 'informar_dados', extrai o nome removendo o telefone e pontuações
        if ($etapa === 'informar_dados') {
            $semTel = preg_replace('/(?:\+?55\s*)?\(?[1-9]{2}\)?\s*9?\d{4}[-\s]?\d{4}/', '', $original);
            $semTel = preg_replace('/\b(meu whatsapp|whatsapp|zap|telefone|celular|nome)\b/iu', '', $semTel);
            $limpo = trim($semTel, " ,;:-/|\t\n\r");
            // Aceita se tiver apenas letras e espaços (2 a 50 caracteres) e não for comando
            if ($limpo !== '' && preg_match('/^[A-Za-zÀ-ÿ]{2,}(?:\s+[A-Za-zÀ-ÿ]{2,}){0,4}$/u', $limpo)) {
                $normLimpo = $this->normalizar($limpo);
                $bloqueadas = ['sim', 'nao', 'trocar', 'horario', 'voltar', 'outro', 'amanha', 'hoje', 'confirmar'];
                if (!in_array($normLimpo, $bloqueadas, true)) {
                    return mb_convert_case($limpo, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }

        return null;
    }

    // =========================================================================
    // CONSULTAS AUXILIARES AO BANCO DE DADOS MYSQL
    // =========================================================================

    private function listarServicos(): array {
        try {
            $st = $this->db->query("SELECT id, nome, preco, duracao_minutos FROM servicos ORDER BY id ASC");
            return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function listarBarbeiros(): array {
        try {
            $st = $this->db->query("SELECT id, nome, especialidade, slug FROM usuarios WHERE perfil = 'barbeiro' AND ativo = 1 ORDER BY id ASC");
            return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function obterHorariosExpediente(): array {
        $file = __DIR__ . '/../api/config_horarios.json';
        if (file_exists($file)) {
            $arr = json_decode((string)file_get_contents($file), true);
            if (is_array($arr) && !empty($arr)) {
                return array_map(fn($h) => substr($h, 0, 5), $arr);
            }
        }
        return ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
    }

    public function obterHorariosLivres(string $data, ?string $periodo = null): array {
        $expediente = $this->obterHorariosExpediente();
        $ocupados = $this->agendamentoModel->getHorariosOcupados($data);
        $bloqueados = $this->agendamentoModel->getHorariosBloqueados($data);
        $livres = array_values(array_diff($expediente, array_merge($ocupados, $bloqueados)));

        if ($periodo) {
            $livres = array_values(array_filter($livres, function ($h) use ($periodo) {
                $horaInt = (int)substr($h, 0, 2);
                if ($periodo === 'manha') return $horaInt < 12;
                if ($periodo === 'tarde') return $horaInt >= 12 && $horaInt < 18;
                if ($periodo === 'noite') return $horaInt >= 18;
                return true;
            }));
        }

        return $livres;
    }

    private function sugerirProximaDataComVaga(string $dataBase): ?array {
        for ($i = 1; $i <= 7; $i++) {
            $dt = date('Y-m-d', strtotime("{$dataBase} +{$i} days"));
            if ((int)date('N', strtotime($dt)) === 7) continue; // Pula domingo por padrão
            $livres = $this->obterHorariosLivres($dt, null);
            if (!empty($livres)) {
                return [
                    'data' => $dt,
                    'primeiro_horario' => $livres[0],
                    'qtd' => count($livres),
                ];
            }
        }
        return null;
    }

    private function buscarNomePorTelefone(string $telefone): ?string {
        $digitos = preg_replace('/\D/', '', $telefone);
        if (strlen($digitos) < 8) return null;
        $ultimos8 = substr($digitos, -8);

        try {
            $stV = $this->db->prepare("SELECT cliente_nome FROM assinantes_vip WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :t LIMIT 1");
            $stV->execute([':t' => "%{$ultimos8}"]);
            if ($nomeVip = $stV->fetchColumn()) {
                return (string)$nomeVip;
            }

            $stA = $this->db->prepare("SELECT cliente_nome FROM agendamentos WHERE REPLACE(REPLACE(REPLACE(REPLACE(cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :t ORDER BY id DESC LIMIT 1");
            $stA->execute([':t' => "%{$ultimos8}"]);
            if ($nomeAg = $stA->fetchColumn()) {
                return (string)$nomeAg;
            }
        } catch (Throwable $e) {}

        return null;
    }

    private function verificarAssinanteVip(?string $telefone): ?array {
        if (!$telefone) return null;
        $digitos = preg_replace('/\D/', '', $telefone);
        if (strlen($digitos) < 8) return null;
        $ultimos8 = substr($digitos, -8);

        try {
            $sql = "SELECT a.cliente_nome, p.nome AS plano_nome
                    FROM assinantes_vip a
                    JOIN planos_assinatura p ON a.plano_id = p.id
                    WHERE a.status = 'ativo'
                      AND a.data_renovacao >= CURDATE()
                      AND REPLACE(REPLACE(REPLACE(REPLACE(a.cliente_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :t
                    LIMIT 1";
            $st = $this->db->prepare($sql);
            $st->execute([':t' => "%{$ultimos8}"]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function nomeDiaSemana(string $dataYmd): string {
        $nomes = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
        return $nomes[(int)date('N', strtotime($dataYmd))] ?? '';
    }

    private function nomePeriodo(string $periodo): string {
        return match ($periodo) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => $periodo,
        };
    }

    private function formatarDataHumana(string $dataYmd): string {
        $br = date('d/m', strtotime($dataYmd));
        if ($dataYmd === date('Y-m-d')) return "Hoje ({$br})";
        if ($dataYmd === date('Y-m-d', strtotime('+1 day'))) return "Amanhã ({$br})";
        return $this->nomeDiaSemana($dataYmd) . " ({$br})";
    }
}
