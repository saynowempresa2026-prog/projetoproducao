<?php
header('Content-Type: application/json');
require_once 'config/conexao.php';
require_once 'config/sessao.php';

$data = json_decode(file_get_contents("php://input"), true);
$item_id = $data['id'] ?? null;
$pedido_id = $data['pedido_id'] ?? null;
$motivo = $data['motivo'] ?? '';

if (!$item_id || !$pedido_id) {
    echo json_encode(['success' => false, 'erro' => 'Dados incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Busca dados do item e o nome do produto para o log
    $stmtProd = $pdo->prepare("
        SELECT p.nome 
        FROM pedidos_itens pi 
        JOIN produtos p ON p.id = pi.produto_id 
        WHERE pi.id = :id
    ");
    $stmtProd->execute([':id' => $item_id]);
    $nomeProduto = $stmtProd->fetchColumn();

    // 2. Registra o Log
    $msgLog = "Item removido: $nomeProduto. Motivo: $motivo";
    
    $stmtLog = $pdo->prepare("
        INSERT INTO logs_sistema 
        (usuario_id, usuario_nome, acao, tabela_afetada, descricao, data_hora) 
        VALUES 
        (:u_id, :u_nome, :acao, :tabela, :desc, NOW())
    ");
    
    $stmtLog->execute([
        ':u_id'   => $_SESSION['usuario_id'],
        ':u_nome' => $_SESSION['usuario_nome'],
        ':acao'   => 'EXCLUSAO',
        ':tabela' => 'pedidos_itens',
        ':desc'   => $msgLog
    ]);

    // 3. Remove o item
    $stmtDel = $pdo->prepare("DELETE FROM pedidos_itens WHERE id = :id");
    $stmtDel->execute([':id' => $item_id]);

    // 4. Recalcula o total do pedido
    $stmtUpd = $pdo->prepare("
        UPDATE pedidos 
        SET valor_total = (
            SELECT COALESCE(SUM(valor_total), 0) 
            FROM pedidos_itens 
            WHERE pedido_id = :pedido
        ) 
        WHERE id = :pedido
    ");
    $stmtUpd->execute([':pedido' => $pedido_id]);

    // 5. Verifica se ainda sobrou algum item neste pedido e descobre a origem
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pedidos_itens WHERE pedido_id = :pedido");
    $stmtCheck->execute([':pedido' => $pedido_id]);
    $itensRestantes = $stmtCheck->fetchColumn();

    $pedidoVazio = false;
    $tipoOrigem = 'comanda'; // Padrão de segurança caso não encontre

    if ($itensRestantes == 0) {
        // CORREÇÃO AQUI: Busca se o pedido original era de 'mesa' ou 'comanda' antes de atualizar
        $stmtOrigem = $pdo->prepare("SELECT origem_tipo FROM pedidos WHERE id = :pedido");
        $stmtOrigem->execute([':pedido' => $pedido_id]);
        $tipoOrigem = $stmtOrigem->fetchColumn() ?: 'comanda';

        // Se zerou os itens, muda a situação do pedido para 'cancelado'
        $stmtFecharPedido = $pdo->prepare("UPDATE pedidos SET situacao = 'cancelado' WHERE id = :pedido");
        $stmtFecharPedido->execute([':pedido' => $pedido_id]);
        $pedidoVazio = true;
    }

    $pdo->commit();
    
    // RETORNO ATUALIZADO: Passando o tipoOrigem explicitamente para o JS
    echo json_encode([
        'success' => true, 
        'pedidoVazio' => $pedidoVazio,
        'tipoOrigem' => $tipoOrigem
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
}