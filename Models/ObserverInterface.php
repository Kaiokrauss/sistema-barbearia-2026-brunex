<?php
/**
 * Padrão de Projeto GoF: Observer (Comportamental)
 * Define uma dependência um-para-muitos entre objetos de modo que quando um objeto
 * muda de estado, todos os seus dependentes são notificados e atualizados automaticamente.
 */

interface AgendamentoObserver {
    /**
     * Método chamado pelo Subject quando um evento ocorre.
     */
    public function update(AgendamentoSubject $subject): void;

    /**
     * Nome identificador do observador.
     */
    public function getNome(): string;
}

class AgendamentoSubject {
    /**
     * @var AgendamentoObserver[]
     */
    private array $observers = [];

    /**
     * Dados do último evento disparado.
     */
    private array $ultimoEvento = [];

    /**
     * Inscreve um observador.
     */
    public function attach(AgendamentoObserver $observer): void {
        $id = spl_object_hash($observer);
        $this->observers[$id] = $observer;
    }

    /**
     * Remove a inscrição de um observador.
     */
    public function detach(AgendamentoObserver $observer): void {
        $id = spl_object_hash($observer);
        unset($this->observers[$id]);
    }

    /**
     * Notifica todos os observadores inscritos.
     */
    public function notify(): void {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * Dispara um evento, armazenando seu estado e notificando os observers.
     *
     * @param string $tipo Ex: 'AGENDAMENTO_CRIADO', 'AGENDAMENTO_CANCELADO'
     * @param array $dados Dados contextuais do evento
     */
    public function dispararEvento(string $tipo, array $dados): void {
        $this->ultimoEvento = [
            'tipo'      => $tipo,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'dados'     => $dados
        ];

        $this->notify();
    }

    /**
     * Retorna os dados do último evento registrado.
     */
    public function getUltimoEvento(): array {
        return $this->ultimoEvento;
    }

    /**
     * Retorna a quantidade de observadores atualmente inscritos.
     */
    public function getTotalObservers(): int {
        return count($this->observers);
    }
}