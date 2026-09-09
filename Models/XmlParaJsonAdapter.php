<?php
require_once __DIR__ . '/ExportadorInterface.php';
require_once __DIR__ . '/ExportadorXml.php';

/**
 * Padrão de Projeto Adapter: Adaptador (Adapter)
 * 
 * Papel no padrão GoF:
 * - Implementa a interface alvo (Target: ExportadorInterface).
 * - Encapsula e delega a geração para o objeto adaptado (Adaptee: ExportadorXml).
 * - Adapta a saída XML produzida pelo Adaptee, convertendo-a para a representação JSON
 *   esperada pelo cliente.
 */
class XmlParaJsonAdapter implements ExportadorInterface {
    // Objeto adaptado (Adaptee)
    private ExportadorXml $exportadorXml;

    /**
     * O construtor recebe a instância do Adaptee por injeção de dependência.
     */
    public function __construct(ExportadorXml $exportadorXml) {
        $this->exportadorXml = $exportadorXml;
    }

    /**
     * Executa a exportação adaptada:
     * 1. Solicita a exportação em XML ao Adaptee (ExportadorXml).
     * 2. Faz o parsing do XML via simplexml_load_string.
     * 3. Transforma a estrutura XML em array associativo PHP.
     * 4. Codifica e retorna a saída em formato JSON padronizado.
     */
    public function exportar(): string {
        // 1. Obtém a string XML gerada pelo componente adaptado (Adaptee)
        $xmlString = $this->exportadorXml->exportarXml();

        // 2. Faz o parsing da string XML
        $xmlObj = simplexml_load_string($xmlString);
        if ($xmlObj === false) {
            throw new Exception("Falha ao analisar o XML retornado pelo ExportadorXml.");
        }

        // 3. Converte a árvore SimpleXMLElement recursivamente em array associativo
        $dadosArray = json_decode(json_encode($xmlObj), true);

        // Extrai metadados do elemento raiz
        $geradoEm = (string)($xmlObj['gerado_em'] ?? date('c'));
        $totalRegistros = (int)($xmlObj['total_registros'] ?? 0);

        // Trata os nós de agendamento (0, 1 ou N registros)
        $itens = [];
        if (isset($dadosArray['agendamento'])) {
            $rawAgendamentos = $dadosArray['agendamento'];
            // Se for um único registro associativo, normaliza para array de registros
            if (is_array($rawAgendamentos) && !isset($rawAgendamentos[0]) && !empty($rawAgendamentos)) {
                $itens = [$rawAgendamentos];
            } else {
                $itens = (array)$rawAgendamentos;
            }
        }

        // Monta o payload final em formato JSON adaptado
        $payload = [
            'status' => 'sucesso',
            'padrao_utilizado' => 'Adapter (GoF)',
            'origem' => 'ExportadorXml (Adaptee)',
            'formato_saida' => 'JSON',
            'gerado_em' => $geradoEm,
            'total_registros' => $totalRegistros,
            'agendamentos' => $itens
        ];

        // 4. Retorna a representação formatada em JSON (Pretty Print)
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Retorna o tipo MIME correspondente a JSON.
     */
    public function getTipoConteudo(): string {
        return 'application/json; charset=utf-8';
    }

    /**
     * Retorna a extensão recomendada para arquivos JSON.
     */
    public function getExtensao(): string {
        return 'json';
    }

    /**
     * Método auxiliar para inspecionar o Adaptee encapsulado.
     */
    public function getAdaptee(): ExportadorXml {
        return $this->exportadorXml;
    }
}
?>

