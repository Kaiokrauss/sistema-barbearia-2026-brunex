<?php
require_once __DIR__ . '/Database.php';

/**
 * Model: Produto
 * Responsável pelo gerenciamento do catálogo de produtos da Mini-Loja VIP da Barbearia,
 * incluindo movimentação de estoque, precificação e categorização.
 */
class Produto {
    private PDO $conn;

    public function __construct(?PDO $conn = null) {
        $this->conn = $conn ?? Database::getInstance()->getConnection();
    }

    /**
     * Retorna a lista de produtos com filtros opcionais.
     */
    public function lerTodos(?string $categoria = null, bool $apenasAtivos = true, ?bool $destaque = null): array {
        $sql = "SELECT * FROM `produtos` WHERE 1=1";
        $params = [];

        if ($apenasAtivos) {
            $sql .= " AND `ativo` = 1";
        }

        if (!empty($categoria) && strtolower($categoria) !== 'todos') {
            $sql .= " AND LOWER(`categoria`) = LOWER(:categoria)";
            $params[':categoria'] = $categoria;
        }

        if ($destaque !== null) {
            $sql .= " AND `destaque` = :destaque";
            $params[':destaque'] = $destaque ? 1 : 0;
        }

        $sql .= " ORDER BY `destaque` DESC, `nome` ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um produto pelo ID.
     */
    public function lerPorId(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM `produtos` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca um produto pelo slug amigável.
     */
    public function lerPorSlug(string $slug): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM `produtos` WHERE `slug` = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cria um novo produto no catálogo.
     */
    public function criar(array $dados): int {
        $nome = trim($dados['nome'] ?? '');
        $categoria = trim($dados['categoria'] ?? 'Geral');
        $preco = (float)($dados['preco'] ?? 0.0);
        $estoque = max(0, (int)($dados['estoque'] ?? 0));
        $descricao = trim($dados['descricao'] ?? '');
        $imagem = trim($dados['imagem'] ?? '');
        $destaque = !empty($dados['destaque']) ? 1 : 0;
        $ativo = isset($dados['ativo']) ? (int)$dados['ativo'] : 1;

        // Gera slug automático se não informado
        $slug = trim($dados['slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
            $slug = trim($slug, '-');
        }

        // Garante slug único
        $stCheck = $this->conn->prepare("SELECT COUNT(*) FROM `produtos` WHERE `slug` = :slug");
        $stCheck->execute([':slug' => $slug]);
        if ((int)$stCheck->fetchColumn() > 0) {
            $slug .= '-' . rand(100, 999);
        }

        $sql = "INSERT INTO `produtos` (`nome`, `slug`, `categoria`, `preco`, `estoque`, `descricao`, `imagem`, `destaque`, `ativo`)
                VALUES (:nome, :slug, :categoria, :preco, :estoque, :descricao, :imagem, :destaque, :ativo)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':slug' => $slug,
            ':categoria' => $categoria,
            ':preco' => $preco,
            ':estoque' => $estoque,
            ':descricao' => $descricao,
            ':imagem' => $imagem,
            ':destaque' => $destaque,
            ':ativo' => $ativo
        ]);

        return (int)$this->conn->lastInsertId();
    }

    /**
     * Atualiza um produto existente.
     */
    public function atualizar(int $id, array $dados): bool {
        $prodAtual = $this->lerPorId($id);
        if (!$prodAtual) return false;

        $nome = isset($dados['nome']) ? trim($dados['nome']) : $prodAtual['nome'];
        $categoria = isset($dados['categoria']) ? trim($dados['categoria']) : $prodAtual['categoria'];
        $preco = isset($dados['preco']) ? (float)$dados['preco'] : (float)$prodAtual['preco'];
        $estoque = isset($dados['estoque']) ? max(0, (int)$dados['estoque']) : (int)$prodAtual['estoque'];
        $descricao = isset($dados['descricao']) ? trim($dados['descricao']) : $prodAtual['descricao'];
        $imagem = isset($dados['imagem']) ? trim($dados['imagem']) : $prodAtual['imagem'];
        $destaque = isset($dados['destaque']) ? ((int)$dados['destaque'] ? 1 : 0) : (int)$prodAtual['destaque'];
        $ativo = isset($dados['ativo']) ? ((int)$dados['ativo'] ? 1 : 0) : (int)$prodAtual['ativo'];

        $sql = "UPDATE `produtos` SET
                    `nome` = :nome,
                    `categoria` = :categoria,
                    `preco` = :preco,
                    `estoque` = :estoque,
                    `descricao` = :descricao,
                    `imagem` = :imagem,
                    `destaque` = :destaque,
                    `ativo` = :ativo
                WHERE `id` = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nome' => $nome,
            ':categoria' => $categoria,
            ':preco' => $preco,
            ':estoque' => $estoque,
            ':descricao' => $descricao,
            ':imagem' => $imagem,
            ':destaque' => $destaque,
            ':ativo' => $ativo,
            ':id' => $id
        ]);
    }

    /**
     * Ajusta a quantidade em estoque (positivo = entrada, negativo = saída/venda).
     * Impede que o estoque fique negativo.
     */
    public function ajustarEstoque(int $id, int $quantidadeDelta): bool {
        $prod = $this->lerPorId($id);
        if (!$prod) return false;

        $novoEstoque = (int)$prod['estoque'] + $quantidadeDelta;
        if ($novoEstoque < 0) {
            return false; // Não há estoque suficiente
        }

        $stmt = $this->conn->prepare("UPDATE `produtos` SET `estoque` = :estoque WHERE `id` = :id");
        return $stmt->execute([':estoque' => $novoEstoque, ':id' => $id]);
    }

    /**
     * Exclui ou desativa um produto.
     */
    public function deletar(int $id, bool $softDelete = true): bool {
        if ($softDelete) {
            $stmt = $this->conn->prepare("UPDATE `produtos` SET `ativo` = 0 WHERE `id` = :id");
            return $stmt->execute([':id' => $id]);
        } else {
            $stmt = $this->conn->prepare("DELETE FROM `produtos` WHERE `id` = :id");
            return $stmt->execute([':id' => $id]);
        }
    }

    /**
     * Retorna a lista de categorias distintas cadastradas.
     */
    public function getCategorias(): array {
        $stmt = $this->conn->query("SELECT DISTINCT `categoria` FROM `produtos` WHERE `ativo` = 1 ORDER BY `categoria` ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Retorna métricas analíticas de estoque para o painel administrativo.
     */
    public function getEstatisticasEstoque(): array {
        $sql = "SELECT
                    COUNT(*) as total_produtos,
                    COALESCE(SUM(estoque), 0) as total_unidades,
                    COALESCE(SUM(preco * estoque), 0) as valor_total_estoque,
                    SUM(CASE WHEN estoque <= 5 AND ativo = 1 THEN 1 ELSE 0 END) as alerta_baixo_estoque
                FROM `produtos` WHERE `ativo` = 1";
        $stmt = $this->conn->query($sql);
        $res = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        return [
            'total_produtos' => (int)($res['total_produtos'] ?? 0),
            'total_unidades' => (int)($res['total_unidades'] ?? 0),
            'valor_total_estoque' => (float)($res['valor_total_estoque'] ?? 0.0),
            'alerta_baixo_estoque' => (int)($res['alerta_baixo_estoque'] ?? 0)
        ];
    }
}

