<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ExportadorInterface.php';

/**
 * Padrão de Projeto Adapter: Objeto a ser Adaptado (Adaptee)
 * Responsável por consultar dados do banco de dados (ex: agendamentos)
 * e estruturá-los nativamente em formato XML.
 */
class ExportadorXml implements ExportadorInterface {
    private ?PDO $conn;
    private ?string $from;
    private ?string $to;

    /**
     * @param string|null $from Data inicial (YYYY-MM-DD)
     * @param string|null $to Data final (YYYY-MM-DD)
     * @param PDO|null $conn Opcional: conexão PDO (se null, obtém via Singleton Database)
     */
    public function __construct(?string $from = null, ?string $to = null, ?PDO $conn = null) {
        $this->from = $from;
        $this->to = $to;
        // Obtém a conexão utilizando o padrão Singleton
        $this->conn = $conn ?? Database::getInstance()->getConnection();
    }

    /**
     * Consulta o banco de dados e retorna os registros de agendamentos.
     */
    public function buscarDados(): array {
        $sql = "SELECT a.id, a.cliente_nome, a.cliente_telefone, a.servico_id, 
                       s.nome AS servico_nome, s.preco AS servico_preco,
                       a.data_agendada, a.horario, a.status, a.codigo, a.criado_em 
                FROM agendamentos a 
                LEFT JOIN servicos s ON a.servico_id = s.id";
        $conds = [];
        $params = [];

        if ($this->from && $this->to) {
            $conds[] = "a.data_agendada BETWEEN :from AND :to";
            $params[':from'] = $this->from;
            $params[':to'] = $this->to;
        } elseif ($this->from) {
            $conds[] = "a.data_agendada = :from";
            $params[':from'] = $this->from;
        }

        if (count($conds) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conds);
        }

        $sql .= " ORDER BY a.data_agendada ASC, a.horario ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Método específico do Adaptee: gera e retorna o XML nativo formatado.
     */
    public function exportarXml(): string {
        $dados = $this->buscarDados();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><barbearia_agendamentos></barbearia_agendamentos>');
        $xml->addAttribute('gerado_em', date('c'));
        $xml->addAttribute('total_registros', (string)count($dados));

        foreach ($dados as $linha) {
            $agendamentoNode = $xml->addChild('agendamento');
            foreach ($linha as $campo => $valor) {
                $agendamentoNode->addChild($campo, htmlspecialchars((string)($valor ?? '')));
            }
        }

        // Formata o XML com identação usando DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Implementação da interface ExportadorInterface.
     */
    public function exportar(): string {
        return $this->exportarXml();
    }

    /**
     * Retorna o tipo MIME para formato XML.
     */
    public function getTipoConteudo(): string {
        return 'application/xml; charset=utf-8';
    }

    /**
     * Retorna a extensão recomendada para arquivos XML.
     */
    public function getExtensao(): string {
        return 'xml';
    }
}
?>

