<?php
/**
 * Padrão de Projeto GoF: Strategy (Comportamental)
 * Define uma família de algoritmos de desconto, encapsula cada um deles
 * e os torna intercambiáveis, permitindo que a regra de preço varie
 * independentemente dos clientes que a utilizam.
 */

interface DescontoStrategy {
    /**
     * Calcula o valor absoluto do desconto a ser concedido.
     *
     * @param float $valorOriginal Preço base do serviço
     * @param string $dataAgendada Data do agendamento (YYYY-MM-DD)
     * @param string|null $cupom Código do cupom informado pelo cliente
     * @return float Valor do desconto em R$
     */
    public function calcularDesconto(float $valorOriginal, string $dataAgendada = '', ?string $cupom = null): float;

    /**
     * Descrição amigável da regra aplicada para exibição no comprovante.
     */
    public function getDescricao(): string;

    /**
     * Identificador do tipo de estratégia.
     */
    public function getTipo(): string;
}

/**
 * Estratégia 1: Sem Desconto (Tarifa Padrão)
 */
class SemDescontoStrategy implements DescontoStrategy {
    public function calcularDesconto(float $valorOriginal, string $dataAgendada = '', ?string $cupom = null): float {
        return 0.0;
    }

    public function getDescricao(): string {
        return "Tarifa Integral Padrão";
    }

    public function getTipo(): string {
        return "SEM_DESCONTO";
    }
}

/**
 * Estratégia 2: Desconto por Cupom Promocional
 */
class CupomDescontoStrategy implements DescontoStrategy {
    private string $cupomAplicado = '';
    private string $descricaoRegra = '';

    private const CUPONS = [
        'VIP10'        => ['tipo' => 'fixo',       'valor' => 10.0, 'desc' => 'Cupom VIP10 (R$ 10,00 OFF)'],
        'BEMVINDO15'   => ['tipo' => 'percentual', 'valor' => 15.0, 'desc' => 'Boas-vindas VIP (15% OFF)'],
        'PRIMEIRA_VEZ' => ['tipo' => 'percentual', 'valor' => 20.0, 'desc' => 'Primeira Experiência (20% OFF)'],
        'CLIENTEVIP'   => ['tipo' => 'percentual', 'valor' => 25.0, 'desc' => 'Membro Diamante VIP (25% OFF)'],
        'DIAMANTE'     => ['tipo' => 'fixo',       'valor' => 20.0, 'desc' => 'Corte Diamante (R$ 20,00 OFF)']
    ];

    public function calcularDesconto(float $valorOriginal, string $dataAgendada = '', ?string $cupom = null): float {
        if (!$cupom) {
            return 0.0;
        }

        $codigo = strtoupper(trim($cupom));
        if (!isset(self::CUPONS[$codigo])) {
            return 0.0;
        }

        $regra = self::CUPONS[$codigo];
        $this->cupomAplicado = $codigo;
        $this->descricaoRegra = $regra['desc'];

        if ($regra['tipo'] === 'percentual') {
            $desconto = round($valorOriginal * ($regra['valor'] / 100.0), 2);
        } else {
            $desconto = min($valorOriginal, (float)$regra['valor']);
        }

        return max(0.0, $desconto);
    }

    public function getDescricao(): string {
        return $this->descricaoRegra ?: "Cupom Promocional";
    }

    public function getTipo(): string {
        return "CUPOM_" . ($this->cupomAplicado ?: "INVALIDO");
    }

    public static function getCuponsDisponiveis(): array {
        return self::CUPONS;
    }
}

/**
 * Estratégia 3: Dia Promocional Automático (Terça e Quarta VIP)
 */
class DiaPromocionalStrategy implements DescontoStrategy {
    private float $percentualDesconto;

    public function __construct(float $percentualDesconto = 15.0) {
        $this->percentualDesconto = $percentualDesconto;
    }

    public function calcularDesconto(float $valorOriginal, string $dataAgendada = '', ?string $cupom = null): float {
        if (empty($dataAgendada)) {
            $dataAgendada = date('Y-m-d');
        }

        $diaSemana = (int)date('w', strtotime($dataAgendada)); // 0 = Domingo, 2 = Terça, 3 = Quarta

        // Terça-feira (2) ou Quarta-feira (3)
        if ($diaSemana === 2 || $diaSemana === 3) {
            return round($valorOriginal * ($this->percentualDesconto / 100.0), 2);
        }

        return 0.0;
    }

    public function getDescricao(): string {
        return "Terça & Quarta VIP ({$this->percentualDesconto}% OFF Automático)";
    }

    public function getTipo(): string {
        return "DIA_PROMOCIONAL";
    }
}