<?php
/**
 * Serviço de Integração com a API do WhatsApp
 * Responsável por formatar telefones e gerar links diretos para a API do WhatsApp (wa.me / api.whatsapp.com)
 */
class WhatsAppService {

    /**
     * Normaliza e limpa o número de telefone, adicionando o DDI do Brasil (55) se ausente.
     */
    public static function limparTelefone(?string $telefone): string {
        if (!$telefone) return '';
        // Remove tudo que não for dígito
        $nums = preg_replace('/\D/', '', $telefone);
        
        // Se tiver 10 ou 11 dígitos (DDD + número), adiciona DDI 55
        if (strlen($nums) === 10 || strlen($nums) === 11) {
            $nums = '55' . $nums;
        }
        return $nums;
    }

    /**
     * Cria a URL da API do WhatsApp.
     */
    public static function criarLinkWhatsApp(string $telefone, string $mensagem): string {
        $telLimpo = self::limparTelefone($telefone);
        $textoUrl = urlencode($mensagem);
        
        if (!empty($telLimpo)) {
            return "https://api.whatsapp.com/send?phone={$telLimpo}&text={$textoUrl}";
        }
        return "https://api.whatsapp.com/send?text={$textoUrl}";
    }

    /**
     * Gera mensagem e link para notificar o BARBEIRO de um novo agendamento.
     */
    public static function gerarLinkNovoAgendamentoBarbeiro(array $dados, string $telefoneBarbeiro = '5511999999999'): string {
        $nome = $dados['cliente_nome'] ?? 'Cliente';
        $tel = $dados['cliente_telefone'] ?? 'Não informado';
        $servico = $dados['servico_nome'] ?? 'Serviço Barbearia';
        $data = $dados['data_agendada'] ?? date('d/m/Y');
        $horario = $dados['horario'] ?? '00:00';
        $codigo = $dados['codigo'] ?? '';

        $msg = "💈 *NOVO AGENDAMENTO RECEBIDO - BARBEARIA VIP*\n\n"
             . "👤 *Cliente:* {$nome}\n"
             . "📞 *Telefone:* {$tel}\n"
             . "✂️ *Serviço:* {$servico}\n"
             . "📅 *Data:* {$data}\n"
             . "⏰ *Horário:* {$horario}\n"
             . "🔑 *Código:* {$codigo}\n\n"
             . "_Agendamento cadastrado com sucesso no sistema._";

        return self::criarLinkWhatsApp($telefoneBarbeiro, $msg);
    }

    /**
     * Gera mensagem e link de confirmação para o CLIENTE.
     */
    public static function gerarLinkConfirmacaoCliente(array $dados): string {
        $tel = $dados['cliente_telefone'] ?? '';
        $nome = $dados['cliente_nome'] ?? 'Cliente';
        $servico = $dados['servico_nome'] ?? 'Serviço Barbearia';
        $data = $dados['data_agendada'] ?? date('d/m/Y');
        $horario = $dados['horario'] ?? '00:00';
        $codigo = $dados['codigo'] ?? '';

        $msg = "💈 *CONFIRMAÇÃO DE AGENDAMENTO - BARBEARIA VIP*\n\n"
             . "Olá, *{$nome}*! Seu horário foi agendado com sucesso:\n\n"
             . "✂️ *Serviço:* {$servico}\n"
             . "📅 *Data:* {$data}\n"
             . "⏰ *Horário:* {$horario}\n"
             . "🔑 *Código do Agendamento:* {$codigo}\n\n"
             . "Caso precise cancelar, você pode usar seu código no site.\n"
             . "Te aguardamos! Obrigado pela preferência! ✂️🔥";

        return self::criarLinkWhatsApp($tel, $msg);
    }

    /**
     * Gera link de lembrete diário para o cliente.
     */
    public static function gerarLinkLembrete(array $dados): string {
        $tel = $dados['cliente_telefone'] ?? '';
        $nome = $dados['cliente_nome'] ?? 'Cliente';
        $horario = $dados['horario'] ?? '00:00';
        $servico = $dados['servico_nome'] ?? 'atendimento';

        $msg = "Olá *{$nome}*! 👋\n"
             . "Passando para confirmar seu horário na *Barbearia VIP* hoje às *{$horario}* ({$servico}).\n"
             . "Até já!";

        return self::criarLinkWhatsApp($tel, $msg);
    }
}
?>

