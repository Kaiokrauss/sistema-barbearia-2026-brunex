<?php
require_once __DIR__ . '/DescontoStrategy.php';

/**
 * Contexto do Padrão Strategy: CalculadoraPreco
 * Mantém uma referência para um objeto Strategy e delega a ele o cálculo do desconto.
 */
class CalculadoraPreco {
    private DescontoStrategy $strategy;

    public function __construct(?DescontoStrategy $strategy = null) {
        $this->strategy = $strategy ?? new SemDescontoStrategy();
    }

    public function setStrategy(DescontoStrategy $strategy): void {
        $this->strategy = $strategy;
    }

    public function getStrategy(): DescontoStrategy {
        return $this->strategy;
    }

    /**
     * Executa o cálculo com a estratégia configurada.
     */
    public function calcular(float $valorOriginal, string $dataAgendada = '', ?string $cupom = null): array {
        $desconto = $this->strategy->calcularDesconto($valorOriginal, $dataAgendada, $cupom);
        $desconto = min($valorOriginal, max(0.0, $desconto));
        $valorFinal = round($valorOriginal - $desconto, 2);

        return [
            'valor_original'      => $valorOriginal,
            'desconto_aplicado'   => $desconto,
            'valor_final'         => $valorFinal,
            'estrategia_tipo'     => $this->strategy->getTipo(),
            'estrategia_desc'     => $this->strategy->getDescricao(),
            'teve_desconto'       => $desconto > 0.0,
            'percentual_efetivo'  => $valorOriginal > 0 ? round(($desconto / $valorOriginal) * 100, 1) : 0.0
        ];
    }

    /**
     * Factory Helper: Seleciona automaticamente a melhor estratégia baseando-se no cupom e na data.
     */
    public static function criarMelhorEstrategia(?string $cupom, string $dataAgendada, float $valorOriginal): CalculadoraPreco {
        // 1. Se informou cupom e é válido, prioriza o cupom
        if (!empty($cupom)) {
            $stratCupom = new CupomDescontoStrategy();
            $descontoCupom = $stratCupom->calcularDesconto($valorOriginal, $dataAgendada, $cupom);
            if ($descontoCupom > 0) {
                return new self($stratCupom);
            }
        }

        // 2. Se for Terça ou Quarta, aplica promoção do dia
        $stratDia = new DiaPromocionalStrategy();
        if ($stratDia->calcularDesconto($valorOriginal, $dataAgendada) > 0) {
            return new self($stratDia);
        }

        // 3. Tarifa padrão sem desconto
        return new self(new SemDescontoStrategy());
    }
}