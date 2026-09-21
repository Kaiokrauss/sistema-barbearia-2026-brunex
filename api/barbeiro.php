<?php
/**
 * API: Barbeiros & Links para Bio do Instagram
 * Fornece a lista de profissionais, especialidades e links personalizados para redes sociais.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Models/Database.php';

try {
    $conn = Database::getInstance()->getConnection();

    // Consulta por slug ou ID específico (para Bio do Instagram)
    $busca = $_GET['barbeiro'] ?? ($_GET['slug'] ?? ($_GET['id'] ?? null));

    $sql = "SELECT id, nome, email, telefone, especialidade, slug, avatar 
            FROM usuarios 
            WHERE perfil = 'barbeiro' AND ativo = 1";

    if ($busca) {
        if (is_numeric($busca)) {
            $sql .= " AND id = :busca";
        } else {
            $sql .= " AND (slug = :busca OR LOWER(nome) LIKE :busca_like)";
        }
        $sql .= " LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (is_numeric($busca)) {
            $stmt->execute([':busca' => (int)$busca]);
        } else {
            $buscaClean = strtolower(trim($busca));
            $stmt->execute([
                ':busca' => $buscaClean,
                ':busca_like' => "%{$buscaClean}%"
            ]);
        }
        $barbeiro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($barbeiro) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = dirname(dirname($_SERVER['SCRIPT_NAME']));
            $linkBio = "{$protocol}{$host}{$base}/Frontend/index.html?barbeiro=" . ($barbeiro['slug'] ?: $barbeiro['id']);

            $barbeiro['link_bio_instagram'] = $linkBio;
            echo json_encode([
                'success' => true,
                'barbeiro' => $barbeiro
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Barbeiro não encontrado.'
            ]);
        }
        exit;
    }

    // Listagem completa de todos os barbeiros ativos
    $stmt = $conn->query("SELECT id, nome, email, telefone, especialidade, slug, avatar FROM usuarios WHERE perfil = 'barbeiro' AND ativo = 1 ORDER BY id ASC");
    $barbeiros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = dirname(dirname($_SERVER['SCRIPT_NAME']));

    foreach ($barbeiros as &$b) {
        $slugVal = $b['slug'] ?: $b['id'];
        $b['link_bio_instagram'] = "{$protocol}{$host}{$base}/Frontend/index.html?barbeiro={$slugVal}";
    }

    echo json_encode([
        'success' => true,
        'barbeiros' => $barbeiros
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dados dos barbeiros: ' . $e->getMessage()
    ]);
    exit;
}

