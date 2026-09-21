<?php
require_once __DIR__ . '/Database.php';

/**
 * Service: CaixaService
 * Responsável pelo fechamento de caixa diário, apuração financeira,
 * rateio de comissões profissionais por barbeiro e relatórios analíticos por serviço.
 */
class CaixaService {
    private PDO $conn;

    public function __construct(?PDO $conn = null) {
        $this->conn = $conn ?? Database::getInstance()->getConnection();
    }

    /**
     * Retorna a listagem de todos os barbeiros ativos cadastrados no sistema.
     */
    public function getBarbeiros(): array {
        $sql = "SELECT id, nome, email, telefone FROM usuarios WHERE perfil = 'barbeiro' AND ativo = 1 ORDER BY nome ASC";
        $stmt = $this->conn->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Realiza o fechamento financeiro de uma data específica,
     * opcionalmente filtrado por um barbeiro individual.
     *
     * @param string $data Data no formato YYYY-MM-DD
     * @param float $taxaComissao Percentual de comissão dos barbeiros (ex: 50.0 para 50%)
     * @param int|null $barbeiroId ID do barbeiro para apuração individual (null = todos)
     * @return array Dados consolidados do fechamento
     */
    public function fecharCaixa(string $data, float $taxaComissao = 50.0, ?int $barbeiroId = null): array {
        // Garantir formato de data válido
        $timestamp = strtotime($data);
        if (!$timestamp) {
            $data = date('Y-m-d');
            $timestamp = strtotime($data);
        }
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

        // Clamping da comissão entre 0% e 100%
        $taxaComissao = max(0.0, min(100.0, $taxaComissao));
        $fatorComissao = $taxaComissao / 100.0;
        $fatorCasa = 1.0 - $fatorComissao;

        // Metadados do barbeiro se selecionado
        $barbeiroSelecionado = null;
        if ($barbeiroId !== null && $barbeiroId > 0) {
            $stB = $this->conn->prepare("SELECT id, nome, email, telefone FROM usuarios WHERE id = :id AND perfil = 'barbeiro'");
            $stB->execute([':id' => $barbeiroId]);
            $barbeiroSelecionado = $stB->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $sql = "SELECT a.*, s.nome AS servico_nome, s.preco AS servico_preco, s.duracao_minutos,
                       u.nome AS barbeiro_nome, u.email AS barbeiro_email
                FROM agendamentos a 
                LEFT JOIN servicos s ON a.servico_id = s.id 
                LEFT JOIN usuarios u ON a.barbeiro_id = u.id 
                WHERE a.data_agendada = :data";

        if ($barbeiroId !== null && $barbeiroId > 0) {
            $sql .= " AND a.barbeiro_id = :barbeiro_id";
        }

        $sql .= " ORDER BY a.horario ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        if ($barbeiroId !== null && $barbeiroId > 0) {
            $stmt->bindParam(':barbeiro_id', $barbeiroId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $faturamentoBruto = 0.0;
        $totalAtivos = 0;
        $totalConcluidos = 0;
        $totalCancelados = 0;

        $servicosAgrupados = [];
        $barbeirosAgrupados = [];
        $atendimentosDetalhados = [];

        foreach ($agendamentos as $ag) {
            $status = strtolower($ag['status'] ?? 'ativo');
            $preco = (float)($ag['servico_preco'] ?? 0.0);
            $nomeServico = $ag['servico_nome'] ?? 'Serviço Personalizado';
            $nomeBarbeiro = $ag['barbeiro_nome'] ?? 'Barbeiro da Casa';
            $bId = !empty($ag['barbeiro_id']) ? (int)$ag['barbeiro_id'] : null;

            $comissaoBarbeiro = 0.0;
            $lucroCasa = 0.0;

            if ($status === 'cancelado') {
                $totalCancelados++;
            } else {
                if ($status === 'concluido') {
                    $totalConcluidos++;
                } else {
                    $totalAtivos++;
                }

                $faturamentoBruto += $preco;
                $comissaoBarbeiro = round($preco * $fatorComissao, 2);
                $lucroCasa = round($preco * $fatorCasa, 2);

                // Agrupamento por serviço
                if (!isset($servicosAgrupados[$nomeServico])) {
                    $servicosAgrupados[$nomeServico] = [
                        'servico' => $nomeServico,
                        'quantidade' => 0,
                        'total_faturado' => 0.0,
                        'total_comissao' => 0.0,
                        'total_casa' => 0.0
                    ];
                }
                $servicosAgrupados[$nomeServico]['quantidade']++;
                $servicosAgrupados[$nomeServico]['total_faturado'] += $preco;
                $servicosAgrupados[$nomeServico]['total_comissao'] += $comissaoBarbeiro;
                $servicosAgrupados[$nomeServico]['total_casa'] += $lucroCasa;

                // Agrupamento por barbeiro
                $chaveB = $bId ? (string)$bId : 'sem_barbeiro';
                if (!isset($barbeirosAgrupados[$chaveB])) {
                    $barbeirosAgrupados[$chaveB] = [
                        'barbeiro_id' => $bId,
                        'barbeiro_nome' => $nomeBarbeiro,
                        'quantidade' => 0,
                        'total_faturado' => 0.0,
                        'total_comissao' => 0.0,
                        'total_casa' => 0.0
                    ];
                }
                $barbeirosAgrupados[$chaveB]['quantidade']++;
                $barbeirosAgrupados[$chaveB]['total_faturado'] += $preco;
                $barbeirosAgrupados[$chaveB]['total_comissao'] += $comissaoBarbeiro;
                $barbeirosAgrupados[$chaveB]['total_casa'] += $lucroCasa;
            }

            $atendimentosDetalhados[] = [
                'id' => (int)$ag['id'],
                'codigo' => $ag['codigo'],
                'horario' => substr($ag['horario'], 0, 5),
                'cliente_nome' => $ag['cliente_nome'],
                'cliente_telefone' => $ag['cliente_telefone'],
                'servico' => $nomeServico,
                'barbeiro_id' => $bId,
                'barbeiro_nome' => $nomeBarbeiro,
                'preco' => $preco,
                'status' => $status,
                'comissao_barbeiro' => $comissaoBarbeiro,
                'lucro_casa' => $lucroCasa
            ];
        }

        $totalRealizados = $totalAtivos + $totalConcluidos;
        $totalComissaoBarbeiros = round($faturamentoBruto * $fatorComissao, 2);
        $totalLucroBarbearia = round($faturamentoBruto * $fatorCasa, 2);
        $ticketMedio = $totalRealizados > 0 ? round($faturamentoBruto / $totalRealizados, 2) : 0.0;

        return [
            'data' => $data,
            'data_formatada' => $dataFormatada,
            'dia_semana' => $diaSemana,
            'taxa_comissao_percentual' => $taxaComissao,
            'barbeiro_selecionado' => $barbeiroSelecionado,
            'barbeiros_disponiveis' => $this->getBarbeiros(),
            'resumo_financeiro' => [
                'faturamento_bruto' => $faturamentoBruto,
                'comissao_barbeiros' => $totalComissaoBarbeiros,
                'lucro_liquido_barbearia' => $totalLucroBarbearia,
                'ticket_medio' => $ticketMedio
            ],
            'resumo_operacional' => [
                'total_agendamentos' => count($agendamentos),
                'total_realizados' => $totalRealizados,
                'total_ativos' => $totalAtivos,
                'total_concluidos' => $totalConcluidos,
                'total_cancelados' => $totalCancelados
            ],
            'breakdown_barbeiros' => array_values($barbeirosAgrupados),
            'breakdown_servicos' => array_values($servicosAgrupados),
            'atendimentos' => $atendimentosDetalhados
        ];
    }

    /**
     * Retorna resumo comparativo dos últimos N dias, opcionalmente filtrado por barbeiro.
     */
    public function getHistoricoResumido(int $dias = 7, float $taxaComissao = 50.0, ?int $barbeiroId = null): array {
        $historico = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $data = date('Y-m-d', strtotime("-{$i} days"));
            $res = $this->fecharCaixa($data, $taxaComissao, $barbeiroId);
            $historico[] = [
                'data' => $data,
                'data_formatada' => $res['data_formatada'],
                'dia_semana' => $res['dia_semana'],
                'faturamento_bruto' => $res['resumo_financeiro']['faturamento_bruto'],
                'comissao' => $res['resumo_financeiro']['comissao_barbeiros'],
                'lucro_liquido' => $res['resumo_financeiro']['lucro_liquido_barbearia'],
                'total_atendimentos' => $res['resumo_operacional']['total_realizados']
            ];
        }
        return $historico;
    }
}