<?php
require_once __DIR__ . '/ObserverInterface.php';

/**
 * Concrete Observer: AuditoriaObserver
 * Grava em arquivo de log estruturado todas as ações e eventos do sistema.
 */
class AuditoriaObserver implements AgendamentoObserver {
    private string $caminhoLog;
    private string $ultimoLogGravado = '';

    public function __construct(?string $caminhoLog = null) {
        $this->caminhoLog = $caminhoLog ?? dirname(__DIR__) . '/logs/auditoria.log';
        $this->garantirDiretorio();
    }

    private function garantirDiretorio(): void {
        $dir = dirname($this->caminhoLog);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Proteção .htaccess para impedir acesso direto via browser
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }

    public function update(AgendamentoSubject $subject): void {
        $evento = $subject->getUltimoEvento();
        if (empty($evento)) return;

        $tipo = $evento['tipo'] ?? 'EVENTO';
        $timestamp = $evento['timestamp'] ?? date('Y-m-d H:i:s');
        $ip = $evento['ip'] ?? '127.0.0.1';
        $dados = $evento['dados'] ?? [];

        $codigo = $dados['codigo'] ?? '-';
        $cliente = $dados['cliente_nome'] ?? '-';
        $servico = $dados['servico_nome'] ?? '-';
        $horario = $dados['horario'] ?? '-';

        $linha = sprintf(
            "[%s] [%s] IP: %s | Código: %s | Cliente: %s | Serviço: %s | Horário: %s\n",
            $timestamp,
            $tipo,
            $ip,
            $codigo,
            $cliente,
            $servico,
            $horario
        );

        $this->ultimoLogGravado = $linha;
        file_put_contents($this->caminhoLog, $linha, FILE_APPEND | LOCK_EX);
    }

    public function getNome(): string {
        return "AuditoriaObserver";
    }

    public function getUltimoLog(): string {
        return $this->ultimoLogGravado;
    }

    public function getCaminhoLog(): string {
        return $this->caminhoLog;
    }
}