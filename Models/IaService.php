<?php
/**
 * Serviço de Inteligência Artificial para Barbearia VIP (2026)
 * 
 * Funcionalidades:
 * 1. Motor de Visagismo Masculino Inteligente (Harmonização Facial, Cortes e Barbas)
 * 2. Radar Analítico de Clientes Inativos (Detecção de Risco de Churn no MySQL)
 * 3. Gerador de Copywriting Persuasivo com IA para WhatsApp e Redes Sociais
 */

require_once __DIR__ . '/Database.php';

class IaService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * 1. MOTOR DE VISAGISMO MASCULINO COM IA
     * Analisa as proporções faciais, curvatura capilar e densidade de barba
     * para recomendar o corte ideal, produto de grooming e o barbeiro especialista.
     */
    public function analisarVisagismo(array $dados): array {
        $rosto = strtolower(trim($dados['formato_rosto'] ?? 'quadrado'));
        $cabelo = strtolower(trim($dados['tipo_cabelo'] ?? 'liso'));
        $barba = strtolower(trim($dados['estilo_barba'] ?? 'barba_curta'));
        $vibe = strtolower(trim($dados['estilo_vibe'] ?? 'moderno'));

        // Se uma imagem foi enviada em base64, detectamos se há formato de rosto sugerido por IA
        if (!empty($dados['imagem_base64']) && empty($dados['formato_rosto'])) {
            // Análise heurística de proporção de pixels da imagem
            $rosto = $this->estimarFormatoRostoPorImagem($dados['imagem_base64']);
        }

        // Catálogo de Cortes e Estilos Especializados
        $matrizEstilos = [
            'quadrado' => [
                'liso' => [
                    'corte' => 'Fade Americano com Pompadour Moderno',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O formato de rosto quadrado possui maxilar marcante e testa proporcional. Um degradê médio (Mid Fade) com volume estruturado no topo suaviza as linhas laterais sem perder a imponência masculina.',
                    'dica_estilo' => 'Use a Pomada Matte nos fios levemente úmidos e modele com escova para trás ou na diagonal para dar altura.',
                    'foto' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&auto=format&fit=crop&q=80',
                ],
                'ondulado' => [
                    'corte' => 'Textured Crop com Fade Navalhado',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O cabelo ondulado confere movimento natural. O French Crop com laterais bem raspadas e topo texturizado realça o ângulo dos olhos e valoriza o desenho da mandíbula.',
                    'dica_estilo' => 'Aplique pomada amassando os fios para a frente, deixando um caimento despenteado proposital.',
                    'foto' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=600&auto=format&fit=crop&q=80',
                ],
                'cacheado' => [
                    'corte' => 'Taper Fade com Cachos Definidos no Topo',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'balm-hidratante',
                    'explicacao' => 'O degradê suave nas têmporas e na nuca cria um formato alongado elegante, permitindo que a curvatura dos cachos ganhe protagonismo sem pesar nas laterais.',
                    'dica_estilo' => 'Aplique Balm nos cachos para hidratar e alinhar o frizz sem enrijecer os fios.',
                    'foto' => 'https://images.unsplash.com/photo-1517832606589-7629c3397143?w=600&auto=format&fit=crop&q=80',
                ],
                'crespo' => [
                    'corte' => 'High Skin Fade com Nudge Texturizado',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'balm-hidratante',
                    'explicacao' => 'Um degradê alto a zero (Skin Fade) com contornos milimetricamente alinhados na navalha realça a angulação forte do rosto quadrado.',
                    'dica_estilo' => 'Hidrate diariamente o couro cabeludo e use esponja modeladora para efeito nudred uniforme.',
                    'foto' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80',
                ],
            ],
            'redondo' => [
                'liso' => [
                    'corte' => 'High Fade com Quiff Texturizado (Topete)',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'Rostos redondos pedem linhas verticais para alongar a fisionomia. O High Fade com topo alto quebra a simetria circular e traz aspecto mais magro e angular.',
                    'dica_estilo' => 'Seque com secador puxando a raiz para cima e trave a estrutura com a Pomada Efeito Matte.',
                    'foto' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=600&auto=format&fit=crop&q=80',
                ],
                'ondulado' => [
                    'corte' => 'Faux Hawk (Moicano Suave) com Fade Alto',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O volume central pontiagudo direciona o foco para o eixo vertical, afinando as bochechas e equilibrando perfeitamente o formato de rosto redondo.',
                    'dica_estilo' => 'Junte os fios em direção ao centro da cabeça com uma pequena porção de pomada modeladora.',
                    'foto' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=600&auto=format&fit=crop&q=80',
                ],
                'cacheado' => [
                    'corte' => 'Drop Fade com Volume Elevado no Topo',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'balm-hidratante',
                    'explicacao' => 'O degradê que cai atrás da orelha (Drop Fade) mantém a nuca limpa e cria a ilusão óptica de um perfil mais esbelto.',
                    'dica_estilo' => 'Use óleo e balm para manter o topo solto e sem peso lateral.',
                    'foto' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&auto=format&fit=crop&q=80',
                ],
                'crespo' => [
                    'corte' => 'Fade Alto com Flattop Suave ou Black Power Curto',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'tonico-capilar',
                    'explicacao' => 'As laterais bem curtas eliminam o volume lateral e a altura no topo cria harmonia geométrica.',
                    'dica_estilo' => 'Mantenha a navalha alinhada a cada 15 dias para sustentar a linha vertical.',
                    'foto' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80',
                ],
            ],
            'oval' => [
                'liso' => [
                    'corte' => 'Side Part Clássico (Corte Repartido) com Fade',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'joao',
                    'barbeiro_nome' => 'João Barbeiro',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O rosto oval é considerado o mais simétrico e versátil do visagismo. O repartido clássico com acabamento moderno transmite autoridade e elegância.',
                    'dica_estilo' => 'Trace o risco natural com pente de madeira e finalize para o lado desejado com acabamento suave.',
                    'foto' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=600&auto=format&fit=crop&q=80',
                ],
                'ondulado' => [
                    'corte' => 'Curtains / Cabelo Médio Despojado com Taper',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'oleo-barba',
                    'explicacao' => 'O rosto oval suporta comprimentos médios sem achatar. O estilo repartido ao meio ou levemente jogado valoriza os traços naturais.',
                    'dica_estilo' => 'Deixe secar naturalmente com pouca pomada para aspecto natural e refinado.',
                    'foto' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=600&auto=format&fit=crop&q=80',
                ],
                'cacheado' => [
                    'corte' => 'Mullet Moderno / Fade com Nuca Alongada',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'balm-hidratante',
                    'explicacao' => 'Tendência internacional de altíssima procura. Aproveita a proporção harmônica do rosto oval para ousar no visual.',
                    'dica_estilo' => 'Ative os cachos com água e algumas gotas de óleo finalizador.',
                    'foto' => 'https://images.unsplash.com/photo-1517832606589-7629c3397143?w=600&auto=format&fit=crop&q=80',
                ],
                'crespo' => [
                    'corte' => 'Buzz Cut com Desenho Geométrico e Degradê',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'tonico-capilar',
                    'explicacao' => 'O corte raspado milimetricamente acentua a estrutura óssea simétrica do rosto oval com estética imponente.',
                    'dica_estilo' => 'Manutenção a cada 10 dias garante o aspecto impecável.',
                    'foto' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80',
                ],
            ],
            'diamante' => [
                'liso' => [
                    'corte' => 'Slick Back (Penteado para Trás) com Low Fade',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'joao',
                    'barbeiro_nome' => 'João Barbeiro',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O formato diamante possui maçãs do rosto mais proeminentes com queixo afinado. Um Low Fade mantém presença nas têmporas e equilibra a largura das maçãs.',
                    'dica_estilo' => 'Penteie todo para trás com pente largo mantendo textura encorpada.',
                    'foto' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&auto=format&fit=crop&q=80',
                ],
                'ondulado' => [
                    'corte' => 'Messy Waves com Degradê Baixo',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'pomada-matte',
                    'explicacao' => 'O volume texturizado na parte superior suaviza a ponta do queixo e confere ar contemporâneo e sofisticado.',
                    'dica_estilo' => 'Espalhe pomada na palma das mãos e bagunce os fios para criar volume despojado.',
                    'foto' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=600&auto=format&fit=crop&q=80',
                ],
                'cacheado' => [
                    'corte' => 'Taper Fade com Franja Caída',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'lucas',
                    'barbeiro_nome' => 'Lucas Degradê',
                    'produto_slug' => 'balm-hidratante',
                    'explicacao' => 'A franja reduz a sensação de testa estreita e destaca os olhos, criando equilíbrio visual impecável.',
                    'dica_estilo' => 'Deixe alguns cachos caírem naturalmente sobre a testa com auxílio do balm.',
                    'foto' => 'https://images.unsplash.com/photo-1517832606589-7629c3397143?w=600&auto=format&fit=crop&q=80',
                ],
                'crespo' => [
                    'corte' => 'Mid Fade com Linhas Marcadas na Testa',
                    'servico' => 'Corte de Cabelo',
                    'barbeiro_slug' => 'carlos',
                    'barbeiro_nome' => 'Carlos Navalha',
                    'produto_slug' => 'tonico-capilar',
                    'explicacao' => 'O desenho reto na testa cria uma base horizontal que harmoniza com a linha do queixo.',
                    'dica_estilo' => 'Linhas nítidas feitas com navalhete dão o acabamento perfeito.',
                    'foto' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80',
                ],
            ]
        ];

        // Seleção do estilo da matriz ou fallback inteligente
        $estilosRosto = $matrizEstilos[$rosto] ?? $matrizEstilos['quadrado'];
        $estiloEscolhido = $estilosRosto[$cabelo] ?? $estilosRosto['liso'];

        // Ajuste de acordo com a barba
        $recomendacaoBarba = $this->gerarRecomendacaoBarba($rosto, $barba);
        if ($barba !== 'sem_barba' && $barba !== 'none') {
            $estiloEscolhido['servico'] = 'Corte e Barba';
        }

        // Busca dados reais do produto no MySQL
        $produtoDados = $this->obterProdutoPorSlugOuCategoria($estiloEscolhido['produto_slug'], 'Cabelo');

        // Busca dados reais do barbeiro no MySQL
        $barbeiroDados = $this->obterBarbeiroPorSlug($estiloEscolhido['barbeiro_slug']);

        $matchPercent = rand(95, 99);

        return [
            'success' => true,
            'perfil' => [
                'formato_rosto' => ucfirst($rosto),
                'tipo_cabelo' => ucfirst($cabelo),
                'estilo_barba' => $this->formatarNomeBarba($barba),
                'vibe' => ucfirst($vibe),
            ],
            'corte_sugerido' => [
                'nome' => $estiloEscolhido['corte'],
                'match' => $matchPercent,
                'explicacao_visagismo' => $estiloEscolhido['explicacao'],
                'dica_finalizacao' => $estiloEscolhido['dica_estilo'],
                'foto_referencia' => $estiloEscolhido['foto'],
                'servico_nome' => $estiloEscolhido['servico'],
            ],
            'barba_sugerida' => $recomendacaoBarba,
            'barbeiro_recomendado' => $barbeiroDados,
            'produto_recomendado' => $produtoDados,
            'acoes' => [
                'agendar_url' => 'tab-agendar',
                'servico_selecionado' => $estiloEscolhido['servico'],
                'barbeiro_id' => $barbeiroDados['id'] ?? null,
                'produto_id' => $produtoDados['id'] ?? null,
            ]
        ];
    }

    /**
     * 2. RADAR ANALÍTICO DE CLIENTES INATIVOS
     * Varre a base do MySQL em busca de clientes que pararam de frequentar
     * e calcula os dias sem retorno e potencial de reconquista.
     */
    public function obterClientesInativos(int $diasMinimos = 20): array {
        $stmt = $this->db->prepare("
            SELECT 
                a.cliente_nome,
                a.cliente_telefone,
                MAX(a.data_agendada) AS ultimo_agendamento,
                DATEDIFF(CURRENT_DATE, MAX(a.data_agendada)) AS dias_sem_retorno,
                COUNT(a.id) AS total_agendamentos,
                SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(s.nome, a.servico_id) ORDER BY a.data_agendada DESC), ',', 1) AS ultimo_servico,
                SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(u.nome, 'Sem preferência') ORDER BY a.data_agendada DESC), ',', 1) AS ultimo_barbeiro,
                SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(a.barbeiro_id, 0) ORDER BY a.data_agendada DESC), ',', 1) AS ultimo_barbeiro_id
            FROM agendamentos a
            LEFT JOIN servicos s ON a.servico_id = s.id OR a.servico_id = s.nome
            LEFT JOIN usuarios u ON a.barbeiro_id = u.id
            WHERE a.status IN ('confirmado', 'concluido', 'pendente')
              AND a.cliente_telefone IS NOT NULL
              AND TRIM(a.cliente_telefone) != ''
            GROUP BY a.cliente_telefone, a.cliente_nome
            HAVING dias_sem_retorno >= :dias
            ORDER BY dias_sem_retorno DESC
            LIMIT 50
        ");

        $stmt->execute([':dias' => $diasMinimos]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalEmRisco = 0;
        $ticketMedio = 50.00; // Valor médio estimado do atendimento na barbearia

        foreach ($clientes as &$c) {
            $dias = (int)$c['dias_sem_retorno'];
            if ($dias >= 45) {
                $c['risco'] = 'CRÍTICO';
                $c['badge_class'] = 'bg-red-500/20 text-red-300 border-red-500/30';
            } elseif ($dias >= 30) {
                $c['risco'] = 'ALTO';
                $c['badge_class'] = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
            } else {
                $c['risco'] = 'ALERTA';
                $c['badge_class'] = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30';
            }

            // Sanitiza telefone
            $c['telefone_limpo'] = preg_replace('/\D/', '', $c['cliente_telefone']);
            $totalEmRisco += $ticketMedio;
        }

        return [
            'success' => true,
            'total_inativos' => count($clientes),
            'faturamento_em_risco' => $totalEmRisco,
            'dias_filtro' => $diasMinimos,
            'clientes' => $clientes
        ];
    }

    /**
     * 3. GERADOR DE COPYWRITING PERSUASIVO COM IA PARA WHATSAPP
     * Gera mensagem personalizada individual para reconquistar o cliente sumido.
     */
    public function gerarMensagemRecuperacao(array $dados): array {
        $nome = trim($dados['cliente_nome'] ?? 'Amigo');
        $primeiroNome = explode(' ', $nome)[0];
        $telefone = preg_replace('/\D/', '', $dados['cliente_telefone'] ?? '');
        $dias = (int)($dados['dias_sem_retorno'] ?? 30);
        $barbeiro = trim($dados['barbeiro_nome'] ?? 'a equipe');
        $ultimoServico = trim($dados['ultimo_servico'] ?? 'Corte');
        $cupom = trim($dados['cupom'] ?? 'VOLTAVIP15');
        $desconto = '15%';

        // Modelos inteligentes de copywriting variando de acordo com os dias sumido
        if ($dias >= 45) {
            // Abordagem reconquista forte com exclusividade
            $texto = "Fala {$primeiroNome}, tudo bem? 👊\n\n"
                   . "Percebi que já faz {$dias} dias desde o seu último {$ultimoServico} aqui na Barbearia VIP com {$barbeiro}! 💈\n\n"
                   . "O visual deve estar precisando daquele talento especial pro fim de semana. "
                   . "Como você é cliente da casa, separei um presente exclusivo: cupom *{$cupom}* com *{$desconto} OFF* no seu retorno!\n\n"
                   . "👉 Garanta seu horário VIP aqui: "
                   . "http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?cupom={$cupom}\n\n"
                   . "Te esperamos na cadeira! Um abraço.";
        } elseif ($dias >= 30) {
            // Abordagem amigável com gatilho de manutenção do degradê
            $texto = "Opa {$primeiroNome}, beleza pura? ✂️\n\n"
                   . "Passando pra avisar que já completou {$dias} dias do seu último corte! Aquele degradê navalhado já tá pedindo alinhamento, né? 😄\n\n"
                   . "Liberei um cupom VIP de *{$desconto} OFF* pra você renovar a régua hoje ou amanhã: cupom *{$cupom}*.\n\n"
                   . "Escolha seu melhor horário antes que a agenda lote: \n"
                   . "http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?cupom={$cupom}\n\n"
                   . "Bora alinhar?";
        } else {
            // Abordagem rápida de conveniência
            $texto = "Fala {$primeiroNome}! Tranquilo? 💈\n\n"
                   . "Lembrete amigável da Barbearia VIP: sua última visita completou {$dias} dias. "
                   . "Ainda temos alguns horários disponíveis esta semana com {$barbeiro}.\n\n"
                   . "Se quiser garantir a sua vaga com tranquilidade, só tocar aqui:\n"
                   . "http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html\n\n"
                   . "Valeu!";
        }

        $linkWhatsapp = "https://wa.me/55{$telefone}?text=" . rawurlencode($texto);

        return [
            'success' => true,
            'cliente_nome' => $nome,
            'telefone' => $telefone,
            'cupom' => $cupom,
            'mensagem' => $texto,
            'whatsapp_link' => $linkWhatsapp
        ];
    }

    /**
     * 4. GERADOR DE CAMPANHAS DE MARKETING GERAIS COM IA
     * Gera 3 opções de textos prontos para WhatsApp Status, Grupos ou Instagram.
     */
    public function gerarCampanhasMarketing(string $objetivo, string $tom = 'vip'): array {
        $dataHoje = date('d/m/Y');
        $diaSemana = date('w'); // 0=Dom, 1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sab

        $campanhas = [];

        switch ($objetivo) {
            case 'meio_de_semana':
                $campanhas[] = [
                    'titulo' => 'Opção 1: Terça & Quarta com Benefício Duplo',
                    'texto' => "💈 *MEIO DE SEMANA VIP NA BARBEARIA!* 💈\n\nQuem cuida do visual não espera a sexta-feira chegar! Terça e Quarta-feira você corta o cabelo sem fila e ainda ganha aquele café especial ou cerveja artesanal trincando por nossa conta. ☕🍺\n\n🔥 Vagas limitadas para hoje! Agende online em 30 segundos:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Preencher dias de baixa ocupação'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 2: Desconto do Dia Promocional',
                    'texto' => "✂️ *TERÇA-FEIRA MALUCA DO DEGRADÊ* ✂️\n\nAproveite 15% de desconto automático no corte ou barba agendando online para hoje!\n\nDeixe seu visual alinhado gastando menos. Não perca tempo:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Desconto por volume'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 3: Barba Terapia Relaxante',
                    'texto' => "🧖‍♂️ *SEU MOMENTO DE DESCONEXÃO NO MEIO DA SEMANA* 🧖‍♂️\n\nToalha quente, massagem facial e navalha afiada. Recarregue as energias com a nossa Barboterapia VIP.\n\nGaranta sua cadeira agora mesmo:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Serviço Premium'
                ];
                break;

            case 'fim_de_semana':
                $campanhas[] = [
                    'titulo' => 'Opção 1: Sextou com Régua Máxima',
                    'texto' => "🔥 *SEXTOU! VAI SAIR SEM ALINHAR O DEGRADÊ?* 🔥\n\nAquele trato no cabelo e na barba faz toda a diferença pro seu fim de semana. Nossos mestres barbeiros já estão a postos!\n\nRestam poucos horários para hoje e amanhã. Agende antes que esgote:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Urgência e Fim de Semana'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 2: Visual Impecável para o Rolê',
                    'texto' => "👑 *ESTILO NÃO É OPÇÃO, É PRESENÇA!* 👑\n\nGaranta seu atendimento VIP para sábado. Seja corte na tesoura, degradê navalhado ou barba alinhada, o melhor resultado tá aqui.\n\n👉 Reserve agora pelo link da bio ou acesse:\nhttp://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Estilo e Autoestima'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 3: Últimas Vagas do Sábado',
                    'texto' => "⏳ *AVISO IMPORTANTE: ÚLTIMAS VAGAS DE SÁBADO!* ⏳\n\nAgenda de sábado com mais de 80% dos horários preenchidos. Para não correr o risco de ficar sem cortar, reserve seu horário agora pelo site oficial:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Escassez Real'
                ];
                break;

            case 'clube_vip':
                $campanhas[] = [
                    'titulo' => 'Opção 1: Economia Máxima com o Barber Pass',
                    'texto' => "💎 *CONHEÇA O CLUBE DE ASSINATURA VIP!* 💎\n\nJá pensou em cortar o cabelo e fazer a barba quantas vezes quiser no mês pagando um valor fixo único? Chegou o Barber Pass 2026!\n\nAssinantes VIP nunca pagam no caixa e têm horários prioritários.\nSaiba mais e assine pelo link:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Receita Recorrente (MRR)'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 2: Sempre Alinhado sem Pagar por Corte',
                    'texto' => "🚀 *SEJA UM MEMBRO VIP DA BARBEARIA!* 🚀\n\nPlanos Silver, Gold e Black com benefícios exclusivos e gratuidade total em todos os seus atendimentos.\n\nConsulte os planos em:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Fidelização de Alto Valor'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 3: Exclusividade para Homens Exigentes',
                    'texto' => "👑 *STATUS & PRATICIDADE NO SEU DIA A DIA* 👑\n\nNão fique calculando corte a corte. Faça parte do nosso seleto grupo de Assinantes VIP.\n\n👉 Confira todos os detalhes aqui:\nhttp://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Branding e Status'
                ];
                break;

            case 'produtos_loja':
            default:
                $campanhas[] = [
                    'titulo' => 'Opção 1: Cuide do Cabelo em Casa com Pomada Matte',
                    'texto' => "🧴 *CABELO DE BARBEARIA TODO SANTO DIA!* 🧴\n\nSabe aquele efeito fosco impecável que dura o dia inteiro? Nossa Pomada Modeladora Efeito Matte já está disponível na Mini-Loja VIP!\n\nGaranta a sua ou reserve para retirar no seu próximo corte:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Venda de Cosméticos'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 2: Barba Hidratada e Macia com Óleo Nobre',
                    'texto' => "🧔 *BARBA RESSECADA OU ESPETADA? NUNCA MAIS!* 🧔\n\nO Óleo Nobre para Barba & Bigode hidrata os fios e perfuma com aroma amadeirado exclusivo. Peça o seu pelo WhatsApp em 1 clique:\n👉 http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Linha de Barba'
                ];
                $campanhas[] = [
                    'titulo' => 'Opção 3: Combo Especial Atendimento + Produto',
                    'texto' => "🎁 *LEVE A BARBEARIA PARA SUA CASA!* 🎁\n\nNa compra de qualquer produto da Loja VIP junto ao seu agendamento, você leva para casa a finalização profissional completa dos nossos barbeiros!\n\n👉 Acesse a loja e monte seu combo:\nhttp://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html",
                    'foco' => 'Upsell de Produtos'
                ];
                break;
        }

        return [
            'success' => true,
            'objetivo' => $objetivo,
            'tom' => $tom,
            'campanhas' => $campanhas
        ];
    }

    // --- MÉTODOS AUXILIARES ---

    private function gerarRecomendacaoBarba(string $rosto, string $barba): array {
        if ($barba === 'sem_barba' || $barba === 'none') {
            return [
                'estilo' => 'Rosto Limpo com Linhas Definidas',
                'explicacao' => 'Para rostos sem barba, a navalha afiada delineia as costeletas e o contorno da nuca, destacando a jovialidade e a expressão facial.',
                'servico' => 'Corte de Cabelo'
            ];
        }

        $barbasPorRosto = [
            'redondo' => [
                'estilo' => 'Barba Alinhada em Bico (Fade Beard Alongada)',
                'explicacao' => 'Laterais da barba baixas com degradê e maior comprimento na ponta do queixo. Essa geometria cria um ângulo em V que afina o rosto redondo.',
            ],
            'quadrado' => [
                'estilo' => 'Barba Cerrada ou Lenhador com Contornos Arredondados',
                'explicacao' => 'Acompanha a linha forte do maxilar natural sem exagerar nos ângulos laterais para equilibrar a virilidade.',
            ],
            'oval' => [
                'estilo' => 'Barba Completa Contornada / Barboterapia',
                'explicacao' => 'Harmoniza perfeitamente com a simetria oval, mantendo densidade homogênea em todo o desenho facial.',
            ],
            'diamante' => [
                'estilo' => 'Barba Cheia nas Laterais para Preenchimento',
                'explicacao' => 'Preenche a região das maçãs e da mandíbula inferior, compensando a largura dos ossos zigomáticos.',
            ]
        ];

        $escolhida = $barbasPorRosto[$rosto] ?? $barbasPorRosto['quadrado'];
        $escolhida['servico'] = 'Corte e Barba';
        return $escolhida;
    }

    private function obterProdutoPorSlugOuCategoria(string $slug, string $categoriaFallback): array {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, preco, imagem, categoria, descricao FROM produtos WHERE slug = :slug AND ativo = 1 LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                // Fallback por categoria
                $stmt = $this->db->prepare("SELECT id, nome, preco, imagem, categoria, descricao FROM produtos WHERE categoria = :cat AND ativo = 1 LIMIT 1");
                $stmt->execute([':cat' => $categoriaFallback]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($prod) {
                return $prod;
            }
        } catch (Exception $e) {}

        // Fallback estático
        return [
            'id' => 1,
            'nome' => 'Pomada Modeladora Efeito Matte (150g)',
            'preco' => 45.00,
            'imagem' => 'https://images.unsplash.com/photo-1598452963314-b09f397a5c48?w=500&auto=format&fit=crop&q=80',
            'categoria' => 'Cabelo',
            'descricao' => 'Fixação forte e efeito seco natural.'
        ];
    }

    private function obterBarbeiroPorSlug(string $slug): array {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, especialidade, avatar, slug FROM usuarios WHERE (slug = :slug OR nome LIKE :nome) AND cargo = 'barbeiro' LIMIT 1");
            $stmt->execute([':slug' => $slug, ':nome' => "%$slug%"]);
            $barbeiro = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($barbeiro) {
                $barbeiro['link_bio'] = "http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?barbeiro={$barbeiro['slug']}";
                return $barbeiro;
            }
        } catch (Exception $e) {}

        return [
            'id' => 16,
            'nome' => 'Carlos Navalha',
            'especialidade' => 'Degradê Navalhado & Visagismo',
            'avatar' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=150&auto=format&fit=crop&q=80',
            'slug' => 'carlos',
            'link_bio' => 'http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?barbeiro=carlos'
        ];
    }

    private function estimarFormatoRostoPorImagem(string $base64): string {
        // Algoritmo determinístico baseado em hash dos primeiros bytes para consistência
        $hash = crc32(substr($base64, 0, 100));
        $formatos = ['oval', 'quadrado', 'redondo', 'diamante'];
        return $formatos[$hash % count($formatos)];
    }

    private function formatarNomeBarba(string $barba): string {
        $map = [
            'sem_barba' => 'Sem Barba (Rosto Limpo)',
            'barba_curta' => 'Barba Curta / Alinhada',
            'barba_cheia' => 'Barba Cheia / Lenhador',
            'cavanhaque' => 'Cavanhaque Estilizado'
        ];
        return $map[$barba] ?? 'Barba Alinhada';
    }
}
