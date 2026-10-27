<?php
require_once __DIR__ . '/ObserverInterface.php';

/**
 * Concrete Observer: NotificacaoWhatsAppObserver
 * Prepara e formata mensagens de notificação para WhatsApp de forma estritamente passiva
 * (sem chamadas de rede ou disparos automáticos para servidores externos).
 */
class NotificacaoWhatsAppObserver implements AgendamentoObserver {
    private string $ultimaMensagemFormatada = '';
    private string $ultimaUrlWhatsApp = '';

    public function update(AgendamentoSubject $subject): void {
        $evento = $subject->getUltimoEvento();
        if (empty($evento)) return;

        $tipo = $evento['tipo'] ?? '';
        $dados = $evento['dados'] ?? [];

        $cliente = $dados['cliente_nome'] ?? 'Cliente';
        $telefone = preg_replace('/[^\d]/', '', $dados['cliente_telefone'] ?? '');
        $data = $dados['data_agendada'] ?? date('Y-m-d');
        $horario = $dados['horario'] ?? '--:--';
        $servico = $dados['servico_nome'] ?? 'Serviço Barbearia VIP';
        $barbeiroNome = $dados['barbeiro_nome'] ?? '';
        $servicoComBarbeiro = $barbeiroNome ? "{$servico} com {$barbeiroNome}" : $servico;
        $codigo = $dados['codigo'] ?? '';

        $servicoPreco = isset($dados['servico_preco']) ? (float)$dados['servico_preco'] : 0.0;
        $produtoNome = $dados['produto_nome'] ?? '';
        $produtoPreco = isset($dados['produto_preco']) ? (float)$dados['produto_preco'] : 0.0;
        $valorTotal = isset($dados['valor_total']) ? (float)$dados['valor_total'] : ($servicoPreco + $produtoPreco);

        if ($tipo === 'AGENDAMENTO_CRIADO') {
            $msg = "💈 *Barbearia VIP Borcelle*\n";
            $msg .= "Olá, *{$cliente}*! Seu agendamento foi confirmado:\n";
            $msg .= "✂️ Serviço: {$servicoComBarbeiro}\n";
            if ($barbeiroNome) {
                $msg .= "💈 Barbeiro: {$barbeiroNome}\n";
            }
            if ($produtoNome) {
                $prodPrecoTxt = $produtoPreco > 0 ? " (R$ " . number_format($produtoPreco, 2, ',', '.') . ")" : "";
                $msg .= "🧴 Produto: {$produtoNome}{$prodPrecoTxt}\n";
            }
            $msg .= "📅 Data: {$data} às {$horario}\n";
            if ($produtoNome && $valorTotal > 0) {
                $corteFmt = number_format($servicoPreco, 2, ',', '.');
                $prodFmt = number_format($produtoPreco, 2, ',', '.');
                $totFmt = number_format($valorTotal, 2, ',', '.');
                $msg .= "💰 Total (Corte + Produto): R$ {$totFmt} (Corte: R$ {$corteFmt} + Produto: R$ {$prodFmt})\n";
            } elseif ($valorTotal > 0) {
                $totFmt = number_format($valorTotal, 2, ',', '.');
                $msg .= "💰 Total: R$ {$totFmt}\n";
            }
            $msg .= "🔑 Código de Segurança: #{$codigo}\n";
            $msg .= "Tolerância de 10 minutos. Apresente seu código na recepção!";
        } elseif ($tipo === 'AGENDAMENTO_CANCELADO') {
            $msg = "💈 *Barbearia VIP Borcelle*\n";
            $msg .= "Olá, *{$cliente}*. Seu agendamento (#{$codigo}) foi cancelado com sucesso e o horário liberado na agenda.";
        } else {
            $msg = "Barbearia VIP: Evento {$tipo} registrado para #{$codigo}.";
        }

        $this->ultimaMensagemFormatada = $msg;

        if (!empty($telefone)) {
            $this->ultimaUrlWhatsApp = "https://api.whatsapp.com/send?phone=55{$telefone}&text=" . urlencode($msg);
        } else {
            $this->ultimaUrlWhatsApp = "https://api.whatsapp.com/send?text=" . urlencode($msg);
        }
    }

    public function getNome(): string {
        return "NotificacaoWhatsAppObserver";
    }

    public function getUltimaMensagem(): string {
        return $this->ultimaMensagemFormatada;
    }

    public function getUltimaUrl(): string {
        return $this->ultimaUrlWhatsApp;
    }
}
