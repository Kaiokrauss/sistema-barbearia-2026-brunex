<?php
require_once __DIR__ . '/ObserverInterface.php';

/**
 * Concrete Observer: EstatisticaObserver
 * Contabiliza os eventos em memória para auditoria e métricas de execução.
 */
class EstatisticaObserver implements AgendamentoObserver {
    private array $contadores = [
        'AGENDAMENTO_CRIADO'    => 0,
        'AGENDAMENTO_CANCELADO' => 0,
        'TOTAL_EVENTOS'         => 0
    ];

    public function update(AgendamentoSubject $subject): void {
        $evento = $subject->getUltimoEvento();
        if (empty($evento)) return;

        $tipo = $evento['tipo'] ?? 'OUTRO';

        if (!isset($this->contadores[$tipo])) {
            $this->contadores[$tipo] = 0;
        }

        $this->contadores[$tipo]++;
        $this->contadores['TOTAL_EVENTOS']++;
    }

    public function getNome(): string {
        return "EstatisticaObserver";
    }

    public function getContadores(): array {
        return $this->contadores;
    }

    public function getTotalEventos(): int {
        return $this->contadores['TOTAL_EVENTOS'];
    }
}