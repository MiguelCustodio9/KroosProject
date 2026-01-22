<?php
/**
 * Funções Auxiliares para Base de Dados
 * Operações comuns e reutilizáveis
 */

require_once 'config.php';

// ==================== UTILIZADORES ====================

/**
 * Obter utilizador por ID
 */
function obter_utilizador($id_utilizador) {
    global $conn;
    
    $sql = "SELECT * FROM utilizador WHERE id_utilizador = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_utilizador);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Verificar se email existe
 */
function email_existe($email) {
    global $conn;
    
    $sql = "SELECT id_utilizador FROM utilizador WHERE email_utilizador = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Listar todos os utilizadores
 */
function listar_utilizadores($tipo = null) {
    global $conn;
    
    if ($tipo) {
        $sql = "SELECT * FROM utilizador WHERE tipo_utilizador = ? ORDER BY nome_utilizador";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tipo);
    } else {
        $sql = "SELECT * FROM utilizador ORDER BY nome_utilizador";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== CLUBES ====================

/**
 * Obter clube por ID
 */
function obter_clube($id_clube) {
    global $conn;
    
    $sql = "SELECT * FROM clube WHERE id_clube = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_clube);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Listar todos os clubes
 */
function listar_clubes() {
    global $conn;
    
    $sql = "SELECT * FROM clube ORDER BY nome_clube";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Procurar clube por nome
 */
function procurar_clube($nome) {
    global $conn;
    
    $sql = "SELECT * FROM clube WHERE nome_clube LIKE ? ORDER BY nome_clube";
    $stmt = $conn->prepare($sql);
    $busca = "%$nome%";
    $stmt->bind_param("s", $busca);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== EQUIPAS ====================

/**
 * Obter equipa por ID
 */
function obter_equipa($id_equipa) {
    global $conn;
    
    $sql = "SELECT e.*, c.nome_clube FROM equipa e 
            LEFT JOIN clube c ON e.id_clube = c.id_clube 
            WHERE e.id_equipa = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_equipa);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Listar equipas de um clube
 */
function listar_equipas_clube($id_clube) {
    global $conn;
    
    $sql = "SELECT * FROM equipa WHERE id_clube = ? ORDER BY escalão";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_clube);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== JOGADORES ====================

/**
 * Obter jogador por ID
 */
function obter_jogador($id_jogador) {
    global $conn;
    
    $sql = "SELECT * FROM jogadores WHERE id_jogador = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_jogador);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Listar jogadores de uma equipa
 */
function listar_jogadores_equipa($id_equipa) {
    global $conn;
    
    $sql = "SELECT * FROM jogadores WHERE id_equipa = ? ORDER BY nome_completo";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_equipa);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Listar jogadores de um clube
 */
function listar_jogadores_clube($id_clube) {
    global $conn;
    
    $sql = "SELECT j.* FROM jogadores j 
            INNER JOIN equipa e ON j.id_equipa = e.id_equipa 
            WHERE e.id_clube = ? 
            ORDER BY j.nome_completo";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_clube);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== MENSAGENS ====================

/**
 * Obter mensagens não lidas de um utilizador
 */
function obter_mensagens_nao_lidas($id_utilizador) {
    global $conn;
    
    $sql = "SELECT m.*, u.nome_utilizador FROM mensagens m 
            INNER JOIN utilizador u ON m.origem = u.id_utilizador 
            WHERE m.destino = ? AND m.estado = 'Não Lida' 
            ORDER BY m.id_mensagem DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_utilizador);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Enviar mensagem
 */
function enviar_mensagem($origem, $destino, $conteudo) {
    global $conn;
    
    $sql = "INSERT INTO mensagens (origem, destino, conteúdo, estado) 
            VALUES (?, ?, ?, 'Não Lida')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $origem, $destino, $conteudo);
    
    return $stmt->execute();
}

// ==================== LESÕES ====================

/**
 * Obter lesões ativas de um jogador
 */
function obter_lesoes_ativas($id_jogador) {
    global $conn;
    
    $sql = "SELECT * FROM lesões WHERE id_jogador = ? 
            AND estado_lesão != 'Recuperado' 
            ORDER BY id_lesão DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_jogador);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== HISTÓRICO ====================

/**
 * Obter histórico de carreira de um jogador
 */
function obter_historico_carreira($id_jogador) {
    global $conn;
    
    $sql = "SELECT hc.*, c.nome_clube, e.época FROM histórico_carreira hc 
            INNER JOIN época e ON hc.id_época = e.id_época 
            INNER JOIN clube c ON hc.id_clube = c.id_clube 
            WHERE hc.id_jogador = ? 
            ORDER BY e.época DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_jogador);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ==================== VALIDAÇÕES ====================

/**
 * Obter utilizadores pendentes de validação
 */
function obter_utilizadores_pendentes() {
    global $conn;
    
    $sql = "SELECT * FROM validação_utilizador ORDER BY id_validação DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Aprovar utilizador (admin)
 */
function aprovar_utilizador($id_validacao) {
    global $conn;
    
    // Obter dados do utilizador pendente
    $sql_get = "SELECT * FROM validação_utilizador WHERE id_validação = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $id_validacao);
    $stmt_get->execute();
    $user_data = $stmt_get->get_result()->fetch_assoc();
    
    if (!$user_data) {
        return false;
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Inserir na tabela principal
        $sql_insert = "INSERT INTO utilizador 
                      (nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador, 
                       primeiro_nome, último_nome, data_nascimento, password, tipo_utilizador) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("sssssssss",
            $user_data['nome_utilizador'],
            $user_data['foto_perfil'],
            $user_data['email_utilizador'],
            $user_data['telefone_utilizador'],
            $user_data['primeiro_nome'],
            $user_data['último_nome'],
            $user_data['data_nascimento'],
            $user_data['password'],
            $user_data['tipo_utilizador']
        );
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Erro ao inserir utilizador");
        }
        
        // Eliminar da tabela de validação
        $sql_delete = "DELETE FROM validação_utilizador WHERE id_validação = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id_validacao);
        
        if (!$stmt_delete->execute()) {
            throw new Exception("Erro ao eliminar validação");
        }
        
        // Commit da transação
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Rejeitar utilizador (admin)
 */
function rejeitar_utilizador($id_validacao) {
    global $conn;
    
    $sql = "DELETE FROM validação_utilizador WHERE id_validação = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_validacao);
    
    return $stmt->execute();
}

// ==================== ÉPOCAS ====================

/**
 * Obter todas as épocas
 */
function listar_epocas() {
    global $conn;
    
    $sql = "SELECT * FROM época ORDER BY época DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Obter época atual
 */
function obter_epoca_atual() {
    global $conn;
    
    $sql = "SELECT * FROM época ORDER BY id_época DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

?>
