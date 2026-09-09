<?php
/**
 * Padrão de Projeto Adapter: Interface Alvo (Target)
 * Define a interface padronizada de exportação que o cliente/sistema espera consumir.
 */
interface ExportadorInterface {
    /**
     * Executa a exportação e retorna os dados formatados como string.
     */
    public function exportar(): string;

    /**
     * Retorna o tipo MIME correspondente ao formato (ex: application/json, application/xml).
     */
    public function getTipoConteudo(): string;

    /**
     * Retorna a extensão recomendada para download do arquivo gerado (ex: json, xml).
     */
    public function getExtensao(): string;
}
?>

