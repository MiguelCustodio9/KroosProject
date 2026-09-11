<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ── AJAX: detalhe de jogador (carreira + lesões) ── */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'jogador_detalhe') {
    if (!isset($_SESSION['id_utilizador']) || !isset($_SESSION['id_clube'])) {
        http_response_code(403); exit;
    }
    $idJ = (int)($_GET['id'] ?? 0);
    $idC = (int)$_SESSION['id_clube'];
    $resposta = ['carreira' => [], 'lesoes' => []];
    $idU = (int)$_SESSION['id_utilizador'];
    $stmtCkJ = $conn->prepare("SELECT j.id_jogador FROM jogadores j JOIN equipa eq ON eq.id_equipa=j.id_equipa JOIN acesso_equipa ae ON ae.id_equipa=eq.id_equipa WHERE j.id_jogador=? AND eq.id_clube=? AND ae.id_utilizador=? LIMIT 1");
    $stmtCkJ->bind_param("iii", $idJ, $idC, $idU);
    $stmtCkJ->execute();
    if ($stmtCkJ->get_result()->fetch_assoc()) {
        $resCarr = $conn->query("SELECT hc.id_carreira, hc.jogos, hc.golos_marcados, hc.assistências, ep.`época` AS epoca, c.nome_clube AS clube FROM `histórico_carreira` hc LEFT JOIN `época` ep ON ep.id_época=hc.id_época LEFT JOIN clube c ON c.id_clube=hc.id_clube WHERE hc.id_jogador=$idJ ORDER BY ep.id_época DESC");
        while ($r = $resCarr->fetch_assoc()) $resposta['carreira'][] = $r;
        $resLes = $conn->query("SELECT nome_lesão, tipo_lesão, tempo_recuperação, estado_lesão FROM `lesões` WHERE id_jogador=$idJ ORDER BY id_lesão DESC");
        while ($r = $resLes->fetch_assoc()) $resposta['lesoes'][] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode($resposta, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Protecção da página ── */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador']) ||
    !isset($_SESSION['id_clube'])
) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] === 'admin_clube') {
    header('Location: index-admin.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] !== 'treinador') {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];
$id_clube      = $_SESSION['id_clube'];
$tipo_utilizador_sessao = $_SESSION['tipo_utilizador'];
$isAdminClube = false;

$erro = '';
$sucesso = '';
$activeTab = 'tab-info';
$viewMode = $_GET['view'] ?? 'home';
$mostrarMensagens = ($viewMode === 'mensagens');
$activeSidebarView = match ($viewMode) {
    'mensagens' => 'mensagens',
    'calendario' => 'calendario',
    'jogos' => 'jogos',
    'campeonato' => 'campeonato',
    'home' => 'home',
    default => 'treinos',
};
$chatSelecionadoId = (int)($_GET['chat'] ?? 0);
$jogoSelecionadoId = (int)($_GET['jogo'] ?? 0);

$listaEscaloesDisponiveis = [
    'S5','S6','S7','S8','S9','S10','S11','S12','S13','S14','S15',
    'S16','S17','S18','S19','S20','S21','S22','S23','Seniores'
];

$listaHierarquiasDisponiveis = range('A', 'Z');

$tiposTreinadorDisponiveis = [
    'Treinador Principal',
    'Treinador Adjunto',
    'Treinador Estagiário',
    'Treinador de Guarda Redes',
    'Preparador Físico',
    'Cientista Desportivo',
    'Analista'
];

$checkTipoTreinador = $conn->query("SHOW COLUMNS FROM utilizador LIKE 'tipo_treinador'");
if ($checkTipoTreinador && $checkTipoTreinador->num_rows === 0) {
    $conn->query("ALTER TABLE utilizador ADD COLUMN tipo_treinador ENUM('Treinador Principal','Treinador Adjunto','Treinador Estagiário','Treinador de Guarda Redes','Preparador Físico','Cientista Desportivo','Analista') DEFAULT NULL AFTER tipo_utilizador");
}

/* ── Colunas adicionais em eventos_clube ── */
$checkHoraEvento = $conn->query("SHOW COLUMNS FROM eventos_clube LIKE 'hora_evento'");
if ($checkHoraEvento && $checkHoraEvento->num_rows === 0) {
    $conn->query("ALTER TABLE eventos_clube ADD COLUMN hora_evento TIME DEFAULT NULL AFTER data_evento");
}
$checkLocalEvento = $conn->query("SHOW COLUMNS FROM eventos_clube LIKE 'local_evento'");
if ($checkLocalEvento && $checkLocalEvento->num_rows === 0) {
    $conn->query("ALTER TABLE eventos_clube ADD COLUMN local_evento VARCHAR(200) DEFAULT NULL AFTER hora_evento");
}

/* ── Coluna id_utilizador em jogadores ── */
$ckJogUtil = $conn->query("SHOW COLUMNS FROM jogadores LIKE 'id_utilizador'");
if ($ckJogUtil && $ckJogUtil->num_rows === 0) {
    $conn->query("ALTER TABLE jogadores ADD COLUMN id_utilizador INT DEFAULT NULL AFTER id_equipa");
}

/* ── Ligação dos treinos às equipas ── */
$checkTreinoEquipa = $conn->query("SHOW COLUMNS FROM treino LIKE 'id_equipa'");
if ($checkTreinoEquipa && $checkTreinoEquipa->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD COLUMN id_equipa INT DEFAULT NULL AFTER id_treino");
}

$checkTreinoEquipaIndex = $conn->query("SHOW INDEX FROM treino WHERE Key_name = 'idx_treino_equipa'");
if ($checkTreinoEquipaIndex && $checkTreinoEquipaIndex->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD KEY idx_treino_equipa (id_equipa)");
}

/* ── Ligação dos treinos ao calendário ── */
$checkTreinoEvento = $conn->query("SHOW COLUMNS FROM treino LIKE 'id_evento_clube'");
if ($checkTreinoEvento && $checkTreinoEvento->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD COLUMN id_evento_clube INT DEFAULT NULL AFTER id_plano");
}

$checkTreinoEventoIndex = $conn->query("SHOW INDEX FROM treino WHERE Key_name = 'idx_treino_evento_clube'");
if ($checkTreinoEventoIndex && $checkTreinoEventoIndex->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD KEY idx_treino_evento_clube (id_evento_clube)");
}

function diaSemanaPt(string $data): string
{
    $dias = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo'
    ];

    $n = (int)date('N', strtotime($data));
    return $dias[$n] ?? 'Segunda-feira';
}

function estadoEventoTreinoCalendario(string $dataTreino): string
{
    return $dataTreino < date('Y-m-d') ? 'Realizado' : 'Por realizar';
}

function descricaoEventoTreinoCalendario(int $numeroTreino, string $conteudoTreino): string
{
    $conteudoTreino = trim($conteudoTreino);
    $descricao = 'Treino ' . $numeroTreino;

    if ($conteudoTreino !== '') {
        $descricao .= ' — ' . $conteudoTreino;
    }

    return substr($descricao, 0, 500);
}

function sincronizarEventoTreinoCalendario(
    mysqli $conn,
    int $idTreino,
    int $idEquipa,
    int $numeroTreino,
    string $dataTreino,
    string $horaTreino,
    string $conteudoTreino
): void {
    if ($idTreino <= 0 || $idEquipa <= 0 || $dataTreino === '') {
        return;
    }

    $tipoEvento = 'Treino';
    $descricaoEvento = descricaoEventoTreinoCalendario($numeroTreino, $conteudoTreino);
    $estadoEvento = estadoEventoTreinoCalendario($dataTreino);
    $localEvento = null;
    $idEventoAtual = 0;

    $stmtGetEvento = $conn->prepare("SELECT id_evento_clube FROM treino WHERE id_treino = ? LIMIT 1");
    if ($stmtGetEvento) {
        $stmtGetEvento->bind_param("i", $idTreino);
        $stmtGetEvento->execute();
        $rowEvento = $stmtGetEvento->get_result()->fetch_assoc();
        $idEventoAtual = (int)($rowEvento['id_evento_clube'] ?? 0);
    }

    if ($idEventoAtual > 0) {
        $stmtUpdateEvento = $conn->prepare("
            UPDATE eventos_clube
            SET id_equipa = ?,
                tipo_evento = ?,
                `descrição_evento` = ?,
                estado_evento = ?,
                data_evento = ?,
                hora_evento = ?,
                local_evento = ?
            WHERE id_evento = ?
        ");

        if ($stmtUpdateEvento) {
            $stmtUpdateEvento->bind_param(
                "issssssi",
                $idEquipa,
                $tipoEvento,
                $descricaoEvento,
                $estadoEvento,
                $dataTreino,
                $horaTreino,
                $localEvento,
                $idEventoAtual
            );
            $stmtUpdateEvento->execute();

            if ($stmtUpdateEvento->affected_rows >= 0) {
                return;
            }
        }
    }

    $stmtInsertEvento = $conn->prepare("
        INSERT INTO eventos_clube
        (id_equipa, tipo_evento, `descrição_evento`, estado_evento, data_evento, hora_evento, local_evento)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmtInsertEvento) {
        return;
    }

    $stmtInsertEvento->bind_param(
        "issssss",
        $idEquipa,
        $tipoEvento,
        $descricaoEvento,
        $estadoEvento,
        $dataTreino,
        $horaTreino,
        $localEvento
    );

    if ($stmtInsertEvento->execute()) {
        $idNovoEvento = (int)$stmtInsertEvento->insert_id;
        $stmtLinkEvento = $conn->prepare("UPDATE treino SET id_evento_clube = ? WHERE id_treino = ?");
        if ($stmtLinkEvento) {
            $stmtLinkEvento->bind_param("ii", $idNovoEvento, $idTreino);
            $stmtLinkEvento->execute();
        }
    }
}

function sincronizarTreinosAntigosCalendarioTreinador(mysqli $conn, int $idUtilizador, int $idClube): void
{
    $stmtTreinosSemEvento = $conn->prepare("
        SELECT DISTINCT
            t.id_treino,
            t.id_equipa,
            t.`número_treino` AS numero_treino,
            t.`data` AS data_treino,
            t.hora AS hora_treino,
            t.`conteúdo` AS conteudo_treino
        FROM treino t
        INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
        INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
        WHERE ae.id_utilizador = ?
          AND eq.id_clube = ?
          AND (t.id_evento_clube IS NULL OR t.id_evento_clube = 0)
        ORDER BY t.`data` DESC, t.hora DESC
        LIMIT 200
    ");

    if (!$stmtTreinosSemEvento) {
        return;
    }

    $stmtTreinosSemEvento->bind_param("ii", $idUtilizador, $idClube);
    $stmtTreinosSemEvento->execute();
    $resTreinosSemEvento = $stmtTreinosSemEvento->get_result();

    while ($treino = $resTreinosSemEvento->fetch_assoc()) {
        sincronizarEventoTreinoCalendario(
            $conn,
            (int)$treino['id_treino'],
            (int)$treino['id_equipa'],
            (int)$treino['numero_treino'],
            (string)$treino['data_treino'],
            (string)$treino['hora_treino'],
            (string)$treino['conteudo_treino']
        );
    }
}

/* ── Equipas atribuídas ao treinador ── */
$equipasTreinador = [];
$stmtEquipasTreinador = $conn->prepare("
    SELECT DISTINCT
        eq.id_equipa,
        eq.`escalão`,
        eq.hierarquia,
        ep.`época`,
        ep.`id_época`
    FROM acesso_equipa ae
    INNER JOIN equipa eq ON eq.id_equipa = ae.id_equipa
    LEFT JOIN `época` ep ON ep.`id_época` = eq.`id_época`
    WHERE ae.id_utilizador = ?
      AND eq.id_clube = ?
    ORDER BY ep.`id_época` DESC, eq.`escalão`, eq.hierarquia
");
$stmtEquipasTreinador->bind_param("ii", $id_utilizador, $id_clube);
$stmtEquipasTreinador->execute();
$resEquipasTreinador = $stmtEquipasTreinador->get_result();
while ($row = $resEquipasTreinador->fetch_assoc()) {
    $equipasTreinador[] = $row;
}

$idsEquipasTreinador = array_map(static fn($eq) => (int)$eq['id_equipa'], $equipasTreinador);

/* ── Tabelas de competições e jogos do clube ── */
$conn->query("CREATE TABLE IF NOT EXISTS `competicoes_clube` (
    `id_competicao` INT AUTO_INCREMENT PRIMARY KEY,
    `id_clube` INT NOT NULL,
    `id_equipa` INT NOT NULL,
    `nome` VARCHAR(200) NOT NULL,
    `tipo` ENUM('Liga','Taça','Torneio','Campeonato','Amigável','Outro') NOT NULL DEFAULT 'Liga',
    `epoca` VARCHAR(20) DEFAULT NULL,
    `estado` ENUM('A decorrer','Finalizada','Suspensa') NOT NULL DEFAULT 'A decorrer',
    `descricao` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `jogos_clube` (
    `id_jogo` INT AUTO_INCREMENT PRIMARY KEY,
    `id_competicao` INT NOT NULL,
    `adversario` VARCHAR(200) NOT NULL,
    `data_jogo` DATE NOT NULL,
    `hora_jogo` TIME DEFAULT NULL,
    `casa` TINYINT(1) NOT NULL DEFAULT 1,
    `local_jogo` VARCHAR(200) DEFAULT NULL,
    `resultado_nos` INT DEFAULT NULL,
    `resultado_adv` INT DEFAULT NULL,
    `estado` ENUM('Agendado','Realizado','Cancelado','Adiado') NOT NULL DEFAULT 'Agendado',
    `id_evento_clube` INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Detalhe e estatísticas dos jogos ── */
$conn->query("CREATE TABLE IF NOT EXISTS `jogo_configuracao` (
    `id_jogo` INT NOT NULL PRIMARY KEY,
    `numero_partes` INT NOT NULL DEFAULT 2,
    `minutos_por_parte` INT NOT NULL DEFAULT 45,
    `presenca_equipa_tecnica` TEXT DEFAULT NULL,
    `tatica` VARCHAR(30) DEFAULT '4-3-3',
    `posicoes_titulares` LONGTEXT DEFAULT NULL,
    `parte_atual` INT NOT NULL DEFAULT 0,
    `jogo_terminado` TINYINT(1) NOT NULL DEFAULT 0,
    `jogo_iniciado` TINYINT(1) NOT NULL DEFAULT 0,
    `posse_inicial` VARCHAR(10) DEFAULT NULL,
    `segundos_jogo` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

foreach ([
    "ALTER TABLE jogo_configuracao ADD COLUMN tatica VARCHAR(30) DEFAULT '4-3-3'",
    "ALTER TABLE jogo_configuracao ADD COLUMN posicoes_titulares LONGTEXT DEFAULT NULL",
    "ALTER TABLE jogo_configuracao ADD COLUMN parte_atual INT NOT NULL DEFAULT 0",
    "ALTER TABLE jogo_configuracao ADD COLUMN jogo_terminado TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE jogo_configuracao ADD COLUMN jogo_iniciado TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE jogo_configuracao ADD COLUMN posse_inicial VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE jogo_configuracao ADD COLUMN segundos_jogo INT NOT NULL DEFAULT 0"
] as $alterarConfiguracaoJogo) {
    $colunaConfiguracao = preg_replace('/^ALTER TABLE jogo_configuracao ADD COLUMN ([a-z_]+).*/', '$1', $alterarConfiguracaoJogo);
    $existeColunaConfiguracao = $conn->query("SHOW COLUMNS FROM jogo_configuracao LIKE '" . $colunaConfiguracao . "'");
    if ($existeColunaConfiguracao && $existeColunaConfiguracao->num_rows === 0) $conn->query($alterarConfiguracaoJogo);
}

$conn->query("CREATE TABLE IF NOT EXISTS `jogo_participantes` (
    `id_participante` INT AUTO_INCREMENT PRIMARY KEY,
    `id_jogo` INT NOT NULL,
    `id_jogador` INT NOT NULL,
    `tipo` ENUM('Convocado','Titular','Suplente','Suplente Utilizado') NOT NULL,
    UNIQUE KEY `uq_jogo_participante_tipo` (`id_jogo`, `id_jogador`, `tipo`),
    KEY `idx_jogo_participantes_jogo` (`id_jogo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `jogo_substituicoes` (
    `id_substituicao` INT AUTO_INCREMENT PRIMARY KEY,
    `id_jogo` INT NOT NULL,
    `id_jogador_entrada` INT NOT NULL,
    `id_jogador_saida` INT NOT NULL,
    `minuto` INT NOT NULL,
    KEY `idx_jogo_substituicoes_jogo` (`id_jogo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `jogo_golos` (
    `id_golo` INT AUTO_INCREMENT PRIMARY KEY,
    `id_jogo` INT NOT NULL,
    `id_jogador_marcador` INT NOT NULL,
    `id_jogador_assistente` INT DEFAULT NULL,
    `minuto` INT NOT NULL,
    `zona` VARCHAR(50) DEFAULT NULL,
    `forma` VARCHAR(80) DEFAULT NULL,
    KEY `idx_jogo_golos_jogo` (`id_jogo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `jogo_estatisticas_coletivas` (
    `id_jogo` INT NOT NULL PRIMARY KEY,
    `posse_casa_segundos` INT NOT NULL DEFAULT 0,
    `sem_posse_segundos` INT NOT NULL DEFAULT 0,
    `posse_visitante_segundos` INT NOT NULL DEFAULT 0,
    `posse_casa_percentagem` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `posse_visitante_percentagem` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `remates_nos` INT NOT NULL DEFAULT 0,
    `remates_adversario` INT NOT NULL DEFAULT 0,
    `remates_baliza_nos` INT NOT NULL DEFAULT 0,
    `remates_baliza_adversario` INT NOT NULL DEFAULT 0,
    `dribles_tentados_nos` INT NOT NULL DEFAULT 0,
    `dribles_tentados_adversario` INT NOT NULL DEFAULT 0,
    `dribles_conseguidos_nos` INT NOT NULL DEFAULT 0,
    `dribles_conseguidos_adversario` INT NOT NULL DEFAULT 0,
    `defesas_nos` INT NOT NULL DEFAULT 0,
    `defesas_adversario` INT NOT NULL DEFAULT 0,
    `cantos_nos` INT NOT NULL DEFAULT 0,
    `cantos_adversario` INT NOT NULL DEFAULT 0,
    `passes_nos` INT NOT NULL DEFAULT 0,
    `passes_adversario` INT NOT NULL DEFAULT 0,
    `passes_certos_nos` INT NOT NULL DEFAULT 0,
    `passes_certos_adversario` INT NOT NULL DEFAULT 0,
    `faltas_nos` INT NOT NULL DEFAULT 0,
    `faltas_adversario` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `jogo_estatisticas_individuais` (
    `id_estatistica` INT AUTO_INCREMENT PRIMARY KEY,
    `id_jogo` INT NOT NULL,
    `id_jogador` INT NOT NULL,
    `minutos_jogados` INT NOT NULL DEFAULT 0,
    `golos` INT NOT NULL DEFAULT 0,
    `remates` INT NOT NULL DEFAULT 0,
    `remates_baliza` INT NOT NULL DEFAULT 0,
    `assistencias` INT NOT NULL DEFAULT 0,
    `passes` INT NOT NULL DEFAULT 0,
    `passes_certos` INT NOT NULL DEFAULT 0,
    `dribles_tentados` INT NOT NULL DEFAULT 0,
    `dribles_conseguidos` INT NOT NULL DEFAULT 0,
    `defesas` INT NOT NULL DEFAULT 0,
    `desarmes` INT NOT NULL DEFAULT 0,
    `intercecoes` INT NOT NULL DEFAULT 0,
    `faltas_sofridas` INT NOT NULL DEFAULT 0,
    `faltas_cometidas` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_estatistica_individual` (`id_jogo`, `id_jogador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colunaGolosIndividual = $conn->query("SHOW COLUMNS FROM jogo_estatisticas_individuais LIKE 'golos'");
if ($colunaGolosIndividual && $colunaGolosIndividual->num_rows === 0) {
    $conn->query("ALTER TABLE jogo_estatisticas_individuais ADD COLUMN golos INT NOT NULL DEFAULT 0 AFTER minutos_jogados");
}

/* ── Exercícios visuais do plano de treino ── */
$conn->query("CREATE TABLE IF NOT EXISTS `treino_exercicio` (
    `id_exercicio` INT AUTO_INCREMENT PRIMARY KEY,
    `id_treino` INT NOT NULL,
    `ordem` INT NOT NULL,
    `titulo` VARCHAR(120) DEFAULT NULL,
    `desenho_json` LONGTEXT DEFAULT NULL,
    `descricao` TEXT DEFAULT NULL,
    `objetivos` TEXT DEFAULT NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_treino_exercicio_treino` (`id_treino`),
    KEY `idx_treino_exercicio_ordem` (`id_treino`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Folha de presenças dos treinos ── */
$conn->query("CREATE TABLE IF NOT EXISTS `presenca_treino` (
    `id_presenca` INT AUTO_INCREMENT PRIMARY KEY,
    `id_treino` INT NOT NULL,
    `id_jogador` INT NOT NULL,
    `estado_presenca` ENUM('Presente','Não Presente Justificado','Não Presente Injustificado') NOT NULL DEFAULT 'Presente',
    `lesionado` TINYINT(1) NOT NULL DEFAULT 0,
    `observacoes` TEXT DEFAULT NULL,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_presenca_treino_jogador` (`id_treino`, `id_jogador`),
    KEY `idx_presenca_treino` (`id_treino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Avaliação de esforço (RPE) reportada pelo próprio atleta ── */
$conn->query("CREATE TABLE IF NOT EXISTS `avaliacao_esforco` (
    `id_avaliacao` INT AUTO_INCREMENT PRIMARY KEY,
    `id_treino` INT NOT NULL,
    `id_jogador` INT NOT NULL,
    `nivel_esforco` TINYINT NOT NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_avaliacao_esforco` (`id_treino`, `id_jogador`),
    KEY `idx_avaliacao_esforco_treino` (`id_treino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$checkEnviadaEm = $conn->query("SHOW COLUMNS FROM mensagens LIKE 'enviada_em'");
if ($checkEnviadaEm && $checkEnviadaEm->num_rows === 0) {
    $conn->query("ALTER TABLE mensagens ADD COLUMN enviada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER estado");
}

/* ── Flash messages via sessão (após redirect de eventos) ── */
if (isset($_SESSION['flash_sucesso'])) {
    $sucesso = $_SESSION['flash_sucesso'];
    unset($_SESSION['flash_sucesso']);
}
if (isset($_SESSION['flash_erro'])) {
    $erro = $_SESSION['flash_erro'];
    unset($_SESSION['flash_erro']);
}

/* ── Garantir que treinos antigos aparecem no calendário ── */
sincronizarTreinosAntigosCalendarioTreinador($conn, (int)$id_utilizador, (int)$id_clube);

/* ══════════════════════════════════
   AÇÕES POST
══════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    $acoesApenasAdmin = [
        'editar_clube',
        'criar_escalao',
        'editar_escalao',
        'remover_escalao',
        'criar_treinador',
        'editar_treinador',
        'remover_treinador',
        'criar_jogador',
        'editar_jogador',
        'remover_jogador',
        'criar_competicao',
        'editar_competicao',
        'remover_competicao',
        'criar_jogo',
        'resultado_jogo',
        'remover_jogo',
        'criar_evento',
        'editar_evento',
        'remover_evento'
    ];

    if (in_array($acao, $acoesApenasAdmin, true)) {
        $_SESSION['flash_erro'] = 'Não tens permissão para executar essa ação.';
        header('Location: index-treinador.php');
        exit;
    }

    /* ── Criar treino ── */
    if ($acao === 'criar_treino') {
        $viewMode = 'treinos';
        $idEquipaTreino = (int)($_POST['id_equipa_treino'] ?? 0);
        $numeroTreino   = (int)($_POST['numero_treino'] ?? 0);
        $dataTreino     = trim($_POST['data_treino'] ?? '');
        $horaTreino     = trim($_POST['hora_treino'] ?? '');
        $conteudoTreino = trim($_POST['conteudo_treino'] ?? '');
        $observacoes    = trim($_POST['observacoes_treino'] ?? '');

        if ($idEquipaTreino <= 0 || $numeroTreino <= 0 || $dataTreino === '' || $horaTreino === '' || $conteudoTreino === '') {
            $erro = 'Preenche os campos obrigatórios do treino.';
        } else {
            $stmtCheckEquipaTreino = $conn->prepare("
                SELECT eq.id_equipa
                FROM acesso_equipa ae
                INNER JOIN equipa eq ON eq.id_equipa = ae.id_equipa
                WHERE ae.id_utilizador = ?
                  AND eq.id_clube = ?
                  AND eq.id_equipa = ?
                LIMIT 1
            ");
            $stmtCheckEquipaTreino->bind_param("iii", $id_utilizador, $id_clube, $idEquipaTreino);
            $stmtCheckEquipaTreino->execute();
            $equipaTreinoValida = $stmtCheckEquipaTreino->get_result()->fetch_assoc();

            if (!$equipaTreinoValida) {
                $erro = 'Não tens acesso a essa equipa.';
            } else {
                $diaSemana = diaSemanaPt($dataTreino);
                $idPlano = null;
                $observacoes = $observacoes !== '' ? $observacoes : null;

                $stmtCriarTreino = $conn->prepare("
                    INSERT INTO treino
                    (id_equipa, `número_treino`, `data`, hora, `conteúdo`, id_plano, `observações`, dia_da_semana)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtCriarTreino->bind_param(
                    "iisssiss",
                    $idEquipaTreino,
                    $numeroTreino,
                    $dataTreino,
                    $horaTreino,
                    $conteudoTreino,
                    $idPlano,
                    $observacoes,
                    $diaSemana
                );

                if ($stmtCriarTreino->execute()) {
                    $idTreinoCriado = (int)$stmtCriarTreino->insert_id;
                    sincronizarEventoTreinoCalendario(
                        $conn,
                        $idTreinoCriado,
                        $idEquipaTreino,
                        $numeroTreino,
                        $dataTreino,
                        $horaTreino,
                        $conteudoTreino
                    );
                    $sucesso = 'Treino criado com sucesso.';
                } else {
                    $erro = 'Erro ao criar treino.';
                }
            }
        }
    }

    /* ── Editar treino ── */
    if ($acao === 'editar_treino') {
        $viewMode = 'treinos';
        $idTreino       = (int)($_POST['id_treino'] ?? 0);
        $idEquipaTreino = (int)($_POST['id_equipa_treino'] ?? 0);
        $numeroTreino   = (int)($_POST['numero_treino'] ?? 0);
        $dataTreino     = trim($_POST['data_treino'] ?? '');
        $horaTreino     = trim($_POST['hora_treino'] ?? '');
        $conteudoTreino = trim($_POST['conteudo_treino'] ?? '');
        $observacoes    = trim($_POST['observacoes_treino'] ?? '');

        if ($idTreino <= 0 || $idEquipaTreino <= 0 || $numeroTreino <= 0 || $dataTreino === '' || $horaTreino === '' || $conteudoTreino === '') {
            $erro = 'Dados de treino inválidos.';
        } else {
            $stmtCheckTreino = $conn->prepare("
                SELECT t.id_treino
                FROM treino t
                INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
                INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                WHERE t.id_treino = ?
                  AND ae.id_utilizador = ?
                  AND eq.id_clube = ?
                LIMIT 1
            ");
            $stmtCheckTreino->bind_param("iii", $idTreino, $id_utilizador, $id_clube);
            $stmtCheckTreino->execute();
            $treinoAtual = $stmtCheckTreino->get_result()->fetch_assoc();

            $stmtCheckEquipaTreino = $conn->prepare("
                SELECT eq.id_equipa
                FROM acesso_equipa ae
                INNER JOIN equipa eq ON eq.id_equipa = ae.id_equipa
                WHERE ae.id_utilizador = ?
                  AND eq.id_clube = ?
                  AND eq.id_equipa = ?
                LIMIT 1
            ");
            $stmtCheckEquipaTreino->bind_param("iii", $id_utilizador, $id_clube, $idEquipaTreino);
            $stmtCheckEquipaTreino->execute();
            $equipaTreinoValida = $stmtCheckEquipaTreino->get_result()->fetch_assoc();

            if (!$treinoAtual || !$equipaTreinoValida) {
                $erro = 'Não tens acesso a esse treino.';
            } else {
                $diaSemana = diaSemanaPt($dataTreino);
                $observacoes = $observacoes !== '' ? $observacoes : null;

                $stmtEditarTreino = $conn->prepare("
                    UPDATE treino
                    SET id_equipa = ?,
                        `número_treino` = ?,
                        `data` = ?,
                        hora = ?,
                        `conteúdo` = ?,
                        `observações` = ?,
                        dia_da_semana = ?
                    WHERE id_treino = ?
                ");
                $stmtEditarTreino->bind_param(
                    "iisssssi",
                    $idEquipaTreino,
                    $numeroTreino,
                    $dataTreino,
                    $horaTreino,
                    $conteudoTreino,
                    $observacoes,
                    $diaSemana,
                    $idTreino
                );

                if ($stmtEditarTreino->execute()) {
                    sincronizarEventoTreinoCalendario(
                        $conn,
                        $idTreino,
                        $idEquipaTreino,
                        $numeroTreino,
                        $dataTreino,
                        $horaTreino,
                        $conteudoTreino
                    );
                    $sucesso = 'Treino atualizado com sucesso.';
                } else {
                    $erro = 'Erro ao atualizar treino.';
                }
            }
        }
    }

    /* ── Guardar folha de presenças de um treino ── */
    if ($acao === 'guardar_presencas') {
        $viewMode = 'treinos';
        $idTreinoPresencas = (int)($_POST['id_treino'] ?? 0);
        $presencasPost = $_POST['presenca'] ?? [];

        $stmtCheckTreinoPresenca = $conn->prepare("
            SELECT t.id_equipa
            FROM treino t
            INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
            INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
            WHERE t.id_treino = ?
              AND ae.id_utilizador = ?
              AND eq.id_clube = ?
            LIMIT 1
        ");
        $stmtCheckTreinoPresenca->bind_param("iii", $idTreinoPresencas, $id_utilizador, $id_clube);
        $stmtCheckTreinoPresenca->execute();
        $treinoPresencaValido = $stmtCheckTreinoPresenca->get_result()->fetch_assoc();

        if ($idTreinoPresencas <= 0 || !$treinoPresencaValido || !is_array($presencasPost)) {
            $erro = 'Não tens acesso a esse treino.';
        } else {
            $idEquipaPresenca = (int)$treinoPresencaValido['id_equipa'];
            $jogadoresEquipaPresenca = [];
            $stmtJogadoresEquipaPresenca = $conn->prepare("SELECT id_jogador FROM jogadores WHERE id_equipa = ?");
            $stmtJogadoresEquipaPresenca->bind_param("i", $idEquipaPresenca);
            $stmtJogadoresEquipaPresenca->execute();
            $resJogadoresEquipaPresenca = $stmtJogadoresEquipaPresenca->get_result();
            while ($rowJogPresenca = $resJogadoresEquipaPresenca->fetch_assoc()) {
                $jogadoresEquipaPresenca[(int)$rowJogPresenca['id_jogador']] = true;
            }

            $estadosPresencaValidos = ['Presente', 'Não Presente Justificado', 'Não Presente Injustificado'];
            $stmtGuardarPresenca = $conn->prepare("
                INSERT INTO presenca_treino (id_treino, id_jogador, estado_presenca, lesionado, observacoes)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    estado_presenca = VALUES(estado_presenca),
                    lesionado = VALUES(lesionado),
                    observacoes = VALUES(observacoes)
            ");

            foreach ($presencasPost as $idJogadorPresencaRaw => $dadosPresenca) {
                $idJogadorPresenca = (int)$idJogadorPresencaRaw;
                if ($idJogadorPresenca <= 0 || !isset($jogadoresEquipaPresenca[$idJogadorPresenca]) || !is_array($dadosPresenca)) {
                    continue;
                }

                $estadoPresenca = (string)($dadosPresenca['estado'] ?? 'Presente');
                if (!in_array($estadoPresenca, $estadosPresencaValidos, true)) {
                    $estadoPresenca = 'Presente';
                }
                $lesionadoPresenca = !empty($dadosPresenca['lesionado']) ? 1 : 0;
                $observacoesPresenca = trim((string)($dadosPresenca['observacoes'] ?? ''));
                $observacoesPresenca = $observacoesPresenca !== '' ? substr($observacoesPresenca, 0, 1000) : null;

                $stmtGuardarPresenca->bind_param(
                    "iisis",
                    $idTreinoPresencas,
                    $idJogadorPresenca,
                    $estadoPresenca,
                    $lesionadoPresenca,
                    $observacoesPresenca
                );
                $stmtGuardarPresenca->execute();
            }

            $sucesso = 'Folha de presenças guardada com sucesso.';
        }
    }

    /* ── Remover treino ── */
    if ($acao === 'remover_treino') {
        $viewMode = 'treinos';
        $idTreino = (int)($_POST['id_treino'] ?? 0);

        if ($idTreino <= 0) {
            $erro = 'Treino inválido.';
        } else {
            $stmtEventoTreinoRemover = $conn->prepare("
                SELECT t.id_evento_clube
                FROM treino t
                INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
                INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                WHERE t.id_treino = ?
                  AND ae.id_utilizador = ?
                  AND eq.id_clube = ?
                LIMIT 1
            ");
            $stmtEventoTreinoRemover->bind_param("iii", $idTreino, $id_utilizador, $id_clube);
            $stmtEventoTreinoRemover->execute();
            $eventoTreinoRemover = $stmtEventoTreinoRemover->get_result()->fetch_assoc();

            if (!empty($eventoTreinoRemover['id_evento_clube'])) {
                $idEventoTreinoRemover = (int)$eventoTreinoRemover['id_evento_clube'];
                $stmtDeleteEventoTreino = $conn->prepare("DELETE FROM eventos_clube WHERE id_evento = ?");
                $stmtDeleteEventoTreino->bind_param("i", $idEventoTreinoRemover);
                $stmtDeleteEventoTreino->execute();
            }

            $stmtApagarExerciciosTreino = $conn->prepare("
                DELETE te
                FROM treino_exercicio te
                INNER JOIN treino t ON t.id_treino = te.id_treino
                INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
                INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                WHERE t.id_treino = ?
                  AND ae.id_utilizador = ?
                  AND eq.id_clube = ?
            ");
            $stmtApagarExerciciosTreino->bind_param("iii", $idTreino, $id_utilizador, $id_clube);
            $stmtApagarExerciciosTreino->execute();

            $stmtApagarPresencasTreino = $conn->prepare("DELETE FROM presenca_treino WHERE id_treino = ?");
            $stmtApagarPresencasTreino->bind_param("i", $idTreino);
            $stmtApagarPresencasTreino->execute();

            $stmtApagarEsforcosTreino = $conn->prepare("DELETE FROM avaliacao_esforco WHERE id_treino = ?");
            $stmtApagarEsforcosTreino->bind_param("i", $idTreino);
            $stmtApagarEsforcosTreino->execute();

            $stmtRemoverTreino = $conn->prepare("
                DELETE t
                FROM treino t
                INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
                INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                WHERE t.id_treino = ?
                  AND ae.id_utilizador = ?
                  AND eq.id_clube = ?
            ");
            $stmtRemoverTreino->bind_param("iii", $idTreino, $id_utilizador, $id_clube);

            if ($stmtRemoverTreino->execute() && $stmtRemoverTreino->affected_rows > 0) {
                $sucesso = 'Treino removido com sucesso.';
            } else {
                $erro = 'Não foi possível remover o treino.';
            }
        }
    }

    /* ── Criar/atualizar plano visual de treino ── */
    if ($acao === 'criar_plano_treino' || $acao === 'atualizar_plano_treino') {
        $viewMode = 'treinos';
        $idTreinoPlano   = (int)($_POST['id_treino'] ?? 0);
        $idEquipaTreino  = (int)($_POST['id_equipa_treino'] ?? 0);
        $numeroTreino    = (int)($_POST['numero_treino'] ?? 0);
        $dataTreino      = trim($_POST['data_treino'] ?? '');
        $horaTreino      = trim($_POST['hora_treino'] ?? '');
        $conteudoTreino  = trim($_POST['conteudo_treino'] ?? '');
        $observacoes     = trim($_POST['observacoes_treino'] ?? '');
        $exerciciosRaw   = $_POST['plano_exercicios_json'] ?? '';
        $exerciciosPlano = json_decode($exerciciosRaw, true);

        if ($idEquipaTreino <= 0 || $numeroTreino <= 0 || $dataTreino === '' || $horaTreino === '' || $conteudoTreino === '') {
            $erro = 'Preenche os dados gerais do treino.';
        } elseif (!is_array($exerciciosPlano) || count($exerciciosPlano) === 0) {
            $erro = 'Adiciona pelo menos um exercício ao plano.';
        } elseif (count($exerciciosPlano) > 25) {
            $erro = 'O plano não pode ter mais de 25 exercícios.';
        } else {
            $stmtCheckEquipaTreino = $conn->prepare("
                SELECT eq.id_equipa
                FROM acesso_equipa ae
                INNER JOIN equipa eq ON eq.id_equipa = ae.id_equipa
                WHERE ae.id_utilizador = ?
                  AND eq.id_clube = ?
                  AND eq.id_equipa = ?
                LIMIT 1
            ");
            $stmtCheckEquipaTreino->bind_param("iii", $id_utilizador, $id_clube, $idEquipaTreino);
            $stmtCheckEquipaTreino->execute();
            $equipaTreinoValida = $stmtCheckEquipaTreino->get_result()->fetch_assoc();

            if (!$equipaTreinoValida) {
                $erro = 'Não tens acesso a essa equipa.';
            }

            if (!$erro && $acao === 'atualizar_plano_treino') {
                $stmtCheckTreinoPlano = $conn->prepare("
                    SELECT t.id_treino
                    FROM treino t
                    INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
                    INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                    WHERE t.id_treino = ?
                      AND ae.id_utilizador = ?
                      AND eq.id_clube = ?
                    LIMIT 1
                ");
                $stmtCheckTreinoPlano->bind_param("iii", $idTreinoPlano, $id_utilizador, $id_clube);
                $stmtCheckTreinoPlano->execute();
                if (!$stmtCheckTreinoPlano->get_result()->fetch_assoc()) {
                    $erro = 'Não tens acesso a esse plano de treino.';
                }
            }

            if (!$erro) {
                $diaSemana = diaSemanaPt($dataTreino);
                $observacoes = $observacoes !== '' ? $observacoes : null;
                $conn->begin_transaction();

                try {
                    if ($acao === 'criar_plano_treino') {
                        $idPlano = null;
                        $stmtTreinoPlano = $conn->prepare("
                            INSERT INTO treino
                            (id_equipa, `número_treino`, `data`, hora, `conteúdo`, id_plano, `observações`, dia_da_semana)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtTreinoPlano->bind_param(
                            "iisssiss",
                            $idEquipaTreino,
                            $numeroTreino,
                            $dataTreino,
                            $horaTreino,
                            $conteudoTreino,
                            $idPlano,
                            $observacoes,
                            $diaSemana
                        );
                        $stmtTreinoPlano->execute();
                        $idTreinoPlano = (int)$stmtTreinoPlano->insert_id;
                    } else {
                        $stmtTreinoPlano = $conn->prepare("
                            UPDATE treino
                            SET id_equipa = ?,
                                `número_treino` = ?,
                                `data` = ?,
                                hora = ?,
                                `conteúdo` = ?,
                                `observações` = ?,
                                dia_da_semana = ?
                            WHERE id_treino = ?
                        ");
                        $stmtTreinoPlano->bind_param(
                            "iisssssi",
                            $idEquipaTreino,
                            $numeroTreino,
                            $dataTreino,
                            $horaTreino,
                            $conteudoTreino,
                            $observacoes,
                            $diaSemana,
                            $idTreinoPlano
                        );
                        $stmtTreinoPlano->execute();

                        $stmtDeleteExercicios = $conn->prepare("DELETE FROM treino_exercicio WHERE id_treino = ?");
                        $stmtDeleteExercicios->bind_param("i", $idTreinoPlano);
                        $stmtDeleteExercicios->execute();
                    }

                    sincronizarEventoTreinoCalendario(
                        $conn,
                        $idTreinoPlano,
                        $idEquipaTreino,
                        $numeroTreino,
                        $dataTreino,
                        $horaTreino,
                        $conteudoTreino
                    );

                    $stmtInsertExercicio = $conn->prepare("
                        INSERT INTO treino_exercicio
                        (id_treino, ordem, titulo, desenho_json, descricao, objetivos)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($exerciciosPlano as $idx => $exercicio) {
                        if (!is_array($exercicio)) {
                            continue;
                        }

                        $ordem = $idx + 1;
                        $titulo = trim((string)($exercicio['titulo'] ?? ('Exercício ' . $ordem)));
                        $titulo = $titulo !== '' ? substr($titulo, 0, 120) : ('Exercício ' . $ordem);
                        $descricao = trim((string)($exercicio['descricao'] ?? ''));
                        $objetivos = trim((string)($exercicio['objetivos'] ?? ''));
                        $canvas = $exercicio['canvas'] ?? ['template' => 'campo_inteiro', 'objects' => []];
                        if (!is_array($canvas)) {
                            $canvas = ['template' => 'campo_inteiro', 'objects' => []];
                        }
                        $desenhoJson = json_encode($canvas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                        $stmtInsertExercicio->bind_param(
                            "iissss",
                            $idTreinoPlano,
                            $ordem,
                            $titulo,
                            $desenhoJson,
                            $descricao,
                            $objetivos
                        );
                        $stmtInsertExercicio->execute();
                    }

                    $conn->commit();
                    $_SESSION['flash_sucesso'] = $acao === 'criar_plano_treino'
                        ? 'Plano de treino criado com sucesso.'
                        : 'Plano de treino atualizado com sucesso.';
                    header('Location: index-treinador.php?view=treinos');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $erro = 'Erro ao guardar o plano de treino.';
                }
            }
        }
    }

    /* ── Atualizar resultado de jogo ── */
    if ($acao === 'atualizar_resultado_treinador') {
        $viewMode = 'jogos';
        $idJogoResultado = (int)($_POST['id_jogo_resultado'] ?? 0);
        $resultadoNos = ($_POST['resultado_nos'] ?? '') !== '' ? (int)$_POST['resultado_nos'] : null;
        $resultadoAdv = ($_POST['resultado_adv'] ?? '') !== '' ? (int)$_POST['resultado_adv'] : null;
        $estadoJogoResultado = trim($_POST['estado_jogo_resultado'] ?? 'Realizado');
        $estadosJogoValidos = ['Agendado','Realizado','Cancelado','Adiado'];

        if ($idJogoResultado <= 0 || !in_array($estadoJogoResultado, $estadosJogoValidos, true)) {
            $erro = 'Dados do jogo inválidos.';
        } else {
            $stmtCheckJogo = $conn->prepare("
                SELECT jc.id_jogo, jc.id_evento_clube
                FROM jogos_clube jc
                INNER JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao
                INNER JOIN equipa eq ON eq.id_equipa = cc.id_equipa
                INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
                WHERE jc.id_jogo = ?
                  AND ae.id_utilizador = ?
                  AND cc.id_clube = ?
                LIMIT 1
            ");
            $stmtCheckJogo->bind_param("iii", $idJogoResultado, $id_utilizador, $id_clube);
            $stmtCheckJogo->execute();
            $jogoValido = $stmtCheckJogo->get_result()->fetch_assoc();

            if (!$jogoValido) {
                $erro = 'Não tens acesso a esse jogo.';
            } else {
                $stmtAtualizarResultado = $conn->prepare("
                    UPDATE jogos_clube
                    SET resultado_nos = ?,
                        resultado_adv = ?,
                        estado = ?
                    WHERE id_jogo = ?
                ");
                $stmtAtualizarResultado->bind_param("iisi", $resultadoNos, $resultadoAdv, $estadoJogoResultado, $idJogoResultado);

                if ($stmtAtualizarResultado->execute()) {
                    if (!empty($jogoValido['id_evento_clube'])) {
                        $estadoEvento = $estadoJogoResultado === 'Realizado'
                            ? 'Realizado'
                            : ($estadoJogoResultado === 'Cancelado' ? 'Cancelado' : 'Por realizar');
                        $idEventoJogo = (int)$jogoValido['id_evento_clube'];
                        $stmtAtualizarEvento = $conn->prepare("UPDATE eventos_clube SET estado_evento = ? WHERE id_evento = ?");
                        $stmtAtualizarEvento->bind_param("si", $estadoEvento, $idEventoJogo);
                        $stmtAtualizarEvento->execute();
                    }

                    $sucesso = 'Resultado atualizado com sucesso.';
                } else {
                    $erro = 'Erro ao atualizar resultado.';
                }
            }
        }
    }

    /* ── Guardar detalhe completo de jogo ── */
    if ($acao === 'guardar_detalhe_jogo') {
        $viewMode = 'jogos';
        $idJogoDetalhe = (int)($_POST['id_jogo'] ?? 0);
        $stmtAcessoJogo = $conn->prepare("SELECT cc.id_equipa FROM jogos_clube jc INNER JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao INNER JOIN acesso_equipa ae ON ae.id_equipa = cc.id_equipa WHERE jc.id_jogo = ? AND ae.id_utilizador = ? AND cc.id_clube = ? LIMIT 1");
        $stmtAcessoJogo->bind_param("iii", $idJogoDetalhe, $id_utilizador, $id_clube);
        $stmtAcessoJogo->execute();
        $jogoAcesso = $stmtAcessoJogo->get_result()->fetch_assoc();

        if (!$jogoAcesso) {
            $erro = 'Não tens acesso a esse jogo.';
        } else {
            $idEquipaJogo = (int)$jogoAcesso['id_equipa'];
            $jogadoresValidos = [];
            $stmtJogadoresJogo = $conn->prepare("SELECT id_jogador FROM jogadores WHERE id_equipa = ?");
            $stmtJogadoresJogo->bind_param("i", $idEquipaJogo);
            $stmtJogadoresJogo->execute();
            $resJogadoresJogo = $stmtJogadoresJogo->get_result();
            while ($jogadorJogo = $resJogadoresJogo->fetch_assoc()) {
                $jogadoresValidos[(int)$jogadorJogo['id_jogador']] = true;
            }

            $normalizarJogadores = static function ($valores) use ($jogadoresValidos): array {
                $resultado = [];
                foreach ((array)$valores as $valor) {
                    $idJogador = (int)$valor;
                    if ($idJogador > 0 && isset($jogadoresValidos[$idJogador])) {
                        $resultado[$idJogador] = $idJogador;
                    }
                }
                return array_values($resultado);
            };

            $partes = max(1, min(6, (int)($_POST['numero_partes'] ?? 2)));
            $minutosParte = max(1, min(120, (int)($_POST['minutos_por_parte'] ?? 45)));
            $convocados = $normalizarJogadores($_POST['convocados'] ?? []);
            $titulares = $normalizarJogadores($_POST['titulares'] ?? []);
            $suplentes = $normalizarJogadores($_POST['suplentes'] ?? []);
            if (isset($_POST['papel_jogador'])) {
                $convocados = $normalizarJogadores($_POST['convocados'] ?? []);
                $titulares = [];
                $suplentes = [];
                foreach ((array)$_POST['papel_jogador'] as $idJogadorPapel => $papelJogador) {
                    $idJogadorPapel = (int)$idJogadorPapel;
                    if (!isset($jogadoresValidos[$idJogadorPapel]) || !in_array($idJogadorPapel, $convocados, true)) continue;
                    if ($papelJogador === 'Titular') {
                        $titulares[] = $idJogadorPapel;
                    } elseif ($papelJogador === 'Suplente') {
                        $suplentes[] = $idJogadorPapel;
                    }
                }
                $convocados = $normalizarJogadores($convocados);
                $titulares = $normalizarJogadores($titulares);
                $suplentes = $normalizarJogadores($suplentes);
            }
            $stmtJogoJaIniciado = $conn->prepare("SELECT jogo_iniciado, posse_inicial FROM jogo_configuracao WHERE id_jogo = ? LIMIT 1");
            $stmtJogoJaIniciado->bind_param("i", $idJogoDetalhe);
            $stmtJogoJaIniciado->execute();
            $configJaGuardada = $stmtJogoJaIniciado->get_result()->fetch_assoc() ?: [];
            $jogoJaIniciado = (int)($configJaGuardada['jogo_iniciado'] ?? 0) === 1;
            $posseInicialGuardada = $configJaGuardada['posse_inicial'] ?? null;
            if ($jogoJaIniciado) {
                $convocadosGuardados = [];
                $resConvocadosGuardados = $conn->query("SELECT id_jogador FROM jogo_participantes WHERE id_jogo = " . $idJogoDetalhe . " AND tipo = 'Convocado'");
                while ($convocadoGuardado = $resConvocadosGuardados->fetch_assoc()) $convocadosGuardados[] = (int)$convocadoGuardado['id_jogador'];
                sort($convocados);
                sort($convocadosGuardados);
                if ($convocados !== $convocadosGuardados) {
                    $erro = 'A convocatória não pode ser alterada depois do início do jogo.';
                }
            }
            $suplentesUtilizados = [];
            $equipaTecnica = implode("\n", array_filter(array_map('trim', (array)($_POST['equipa_tecnica'] ?? []))));
            $tatica = trim($_POST['tatica'] ?? '4-3-3');
            $taticasValidas = ['4-3-3','4-2-3-1','4-4-2','3-5-2','3-4-3','4-1-4-1','5-3-2'];
            if (!in_array($tatica, $taticasValidas, true)) $tatica = '4-3-3';
            $posicoesTitulares = [];
            foreach ((array)($_POST['posicao_titular'] ?? []) as $posicao => $idJogadorPosicao) {
                $idJogadorPosicao = (int)$idJogadorPosicao;
                if (isset($jogadoresValidos[$idJogadorPosicao]) && in_array($idJogadorPosicao, $convocados, true)) $posicoesTitulares[$posicao] = $idJogadorPosicao;
            }
            $parteAtual = max(0, (int)($_POST['parte_atual'] ?? 0));
            $jogoTerminado = !empty($_POST['jogo_terminado']) ? 1 : 0;
            $jogoIniciado = !empty($_POST['jogo_iniciado']) || $parteAtual > 0 ? 1 : 0;
            // Depois de o jogo ter sido iniciado uma vez, a equipa que começou com a bola já não pode mudar,
            // mesmo que o pedido POST traga outro valor (bloqueio também no servidor, não só no ecrã).
            $posseInicialPost = trim($_POST['posse_inicial'] ?? '');
            $posseInicialPost = in_array($posseInicialPost, ['casa', 'visitante'], true) ? $posseInicialPost : null;
            $posseInicial = $posseInicialGuardada ?: $posseInicialPost;
            // Tempo real de jogo decorrido (em segundos) — usado para calcular os minutos jogados de forma correta,
            // em vez de assumir que cada parte iniciada já contou por inteiro.
            $segundosJogo = max(0, (int)($_POST['segundos_jogo'] ?? 0));
            $resultadoNosDetalhe = ($_POST['resultado_nos'] ?? '') === '' ? null : max(0, (int)$_POST['resultado_nos']);
            $resultadoAdvDetalhe = ($_POST['resultado_adv'] ?? '') === '' ? null : max(0, (int)$_POST['resultado_adv']);

            $camposColetivos = ['remates_nos','remates_adversario','remates_baliza_nos','remates_baliza_adversario','dribles_tentados_nos','dribles_tentados_adversario','dribles_conseguidos_nos','dribles_conseguidos_adversario','defesas_nos','defesas_adversario','cantos_nos','cantos_adversario','passes_nos','passes_adversario','passes_certos_nos','passes_certos_adversario','faltas_nos','faltas_adversario'];
            $coletivas = [];
            foreach ($camposColetivos as $campo) $coletivas[$campo] = max(0, (int)($_POST[$campo] ?? 0));
            foreach ([['remates_baliza_nos', 'remates_nos'], ['remates_baliza_adversario', 'remates_adversario'], ['dribles_conseguidos_nos', 'dribles_tentados_nos'], ['dribles_conseguidos_adversario', 'dribles_tentados_adversario'], ['passes_certos_nos', 'passes_nos'], ['passes_certos_adversario', 'passes_adversario']] as [$campoDependente, $campoBase]) {
                $coletivas[$campoBase] = max($coletivas[$campoBase], $coletivas[$campoDependente]);
            }
            $posseCasaSegundos = max(0, (int)($_POST['posse_casa_segundos'] ?? 0));
            $semPosseSegundos = max(0, (int)($_POST['sem_posse_segundos'] ?? 0));
            $posseVisitanteSegundos = max(0, (int)($_POST['posse_visitante_segundos'] ?? 0));
            $tempoComPosse = $posseCasaSegundos + $posseVisitanteSegundos;
            $posseCasaPercentagem = $tempoComPosse > 0 ? round($posseCasaSegundos * 100 / $tempoComPosse, 2) : 0;
            $posseVisitantePercentagem = $tempoComPosse > 0 ? round($posseVisitanteSegundos * 100 / $tempoComPosse, 2) : 0;

            try {
                if ($erro) throw new RuntimeException($erro);
                $conn->begin_transaction();
                $posicoesTitularesJson = json_encode($posicoesTitulares);
                $stmtConfig = $conn->prepare("INSERT INTO jogo_configuracao (id_jogo,numero_partes,minutos_por_parte,presenca_equipa_tecnica,tatica,posicoes_titulares,parte_atual,jogo_terminado,jogo_iniciado,posse_inicial,segundos_jogo) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE numero_partes=VALUES(numero_partes),minutos_por_parte=VALUES(minutos_por_parte),presenca_equipa_tecnica=VALUES(presenca_equipa_tecnica),tatica=VALUES(tatica),posicoes_titulares=VALUES(posicoes_titulares),parte_atual=VALUES(parte_atual),jogo_terminado=VALUES(jogo_terminado),jogo_iniciado=VALUES(jogo_iniciado),posse_inicial=VALUES(posse_inicial),segundos_jogo=VALUES(segundos_jogo)");
                $stmtConfig->bind_param("iiisssiiisi", $idJogoDetalhe, $partes, $minutosParte, $equipaTecnica, $tatica, $posicoesTitularesJson, $parteAtual, $jogoTerminado, $jogoIniciado, $posseInicial, $segundosJogo);
                $stmtConfig->execute();

                $conn->query("DELETE FROM jogo_participantes WHERE id_jogo=" . $idJogoDetalhe);
                $conn->query("DELETE FROM jogo_substituicoes WHERE id_jogo=" . $idJogoDetalhe);
                $conn->query("DELETE FROM jogo_golos WHERE id_jogo=" . $idJogoDetalhe);
                $conn->query("DELETE FROM jogo_estatisticas_individuais WHERE id_jogo=" . $idJogoDetalhe);
                $stmtParticipante = $conn->prepare("INSERT INTO jogo_participantes (id_jogo,id_jogador,tipo) VALUES (?,?,?)");
                foreach (['Convocado' => $convocados, 'Titular' => $titulares, 'Suplente' => $suplentes] as $tipoParticipante => $listaParticipantes) {
                    foreach ($listaParticipantes as $idJogadorParticipante) {
                        $stmtParticipante->bind_param("iis", $idJogoDetalhe, $idJogadorParticipante, $tipoParticipante);
                        $stmtParticipante->execute();
                    }
                }

                $entradas = (array)($_POST['substituicao_entrada'] ?? []);
                $saidas = (array)($_POST['substituicao_saida'] ?? []);
                $minutosSubs = (array)($_POST['substituicao_minuto'] ?? []);
                $stmtSubstituicao = $conn->prepare("INSERT INTO jogo_substituicoes (id_jogo,id_jogador_entrada,id_jogador_saida,minuto) VALUES (?,?,?,?)");
                $jogadoresEmJogo = array_fill_keys($titulares, true);
                $substituicoesValidas = [];
                foreach ($entradas as $indice => $entrada) {
                    $idEntrada = (int)$entrada; $idSaida = (int)($saidas[$indice] ?? 0); $minuto = max(0, (int)($minutosSubs[$indice] ?? 0));
                    if (isset($jogadoresValidos[$idEntrada], $jogadoresValidos[$idSaida]) && in_array($idEntrada, $convocados, true) && in_array($idSaida, $convocados, true) && isset($jogadoresEmJogo[$idSaida]) && !isset($jogadoresEmJogo[$idEntrada]) && $idEntrada !== $idSaida && $minuto > 0) {
                        $stmtSubstituicao->bind_param("iiii", $idJogoDetalhe, $idEntrada, $idSaida, $minuto);
                        $stmtSubstituicao->execute();
                        $suplentesUtilizados[$idEntrada] = $idEntrada;
                        unset($jogadoresEmJogo[$idSaida]);
                        $jogadoresEmJogo[$idEntrada] = true;
                        $substituicoesValidas[] = ['entrada' => $idEntrada, 'saida' => $idSaida, 'minuto' => $minuto];
                    }
                }

                foreach ($suplentesUtilizados as $idSuplenteUtilizado) {
                    $tipoSuplenteUtilizado = 'Suplente Utilizado';
                    $stmtParticipante->bind_param("iis", $idJogoDetalhe, $idSuplenteUtilizado, $tipoSuplenteUtilizado);
                    $stmtParticipante->execute();
                }

                $marcadores = (array)($_POST['golo_marcador'] ?? []);
                $assistentes = (array)($_POST['golo_assistente'] ?? []);
                $minutosGolos = (array)($_POST['golo_minuto'] ?? []);
                $zonasGolo = (array)($_POST['golo_zona'] ?? []);
                $formasGolo = (array)($_POST['golo_forma'] ?? []);
                $stmtGolo = $conn->prepare("INSERT INTO jogo_golos (id_jogo,id_jogador_marcador,id_jogador_assistente,minuto,zona,forma) VALUES (?,?,?,?,?,?)");
                foreach ($marcadores as $indice => $marcador) {
                    $idMarcador = (int)$marcador;
                    $idAssistente = (int)($assistentes[$indice] ?? 0);
                    $minutoGolo = (int)($minutosGolos[$indice] ?? 0);
                    $minuto = max(0, $minutoGolo);
                    $zona = trim($zonasGolo[$indice] ?? '');
                    $forma = trim($formasGolo[$indice] ?? '');
                    $jogadoresNoMinuto = array_fill_keys($titulares, true);
                    foreach ($substituicoesValidas as $substituicaoValida) {
                        if ($substituicaoValida['minuto'] > $minuto) continue;
                        unset($jogadoresNoMinuto[$substituicaoValida['saida']]);
                        $jogadoresNoMinuto[$substituicaoValida['entrada']] = true;
                    }
                    if (isset($jogadoresNoMinuto[$idMarcador]) && $minuto > 0) {
                        $assistenteNulo = isset($jogadoresNoMinuto[$idAssistente]) ? $idAssistente : null;
                        $stmtGolo->bind_param("iiiiss", $idJogoDetalhe, $idMarcador, $assistenteNulo, $minuto, $zona, $forma);
                        $stmtGolo->execute();
                    }
                }

                $conn->query("INSERT INTO jogo_estatisticas_coletivas (id_jogo,posse_casa_segundos,sem_posse_segundos,posse_visitante_segundos,posse_casa_percentagem,posse_visitante_percentagem," . implode(',', $camposColetivos) . ") VALUES (" . $idJogoDetalhe . "," . $posseCasaSegundos . "," . $semPosseSegundos . "," . $posseVisitanteSegundos . "," . $posseCasaPercentagem . "," . $posseVisitantePercentagem . "," . implode(',', $coletivas) . ") ON DUPLICATE KEY UPDATE posse_casa_segundos=VALUES(posse_casa_segundos),sem_posse_segundos=VALUES(sem_posse_segundos),posse_visitante_segundos=VALUES(posse_visitante_segundos),posse_casa_percentagem=VALUES(posse_casa_percentagem),posse_visitante_percentagem=VALUES(posse_visitante_percentagem)," . implode(',', array_map(static fn($campo) => $campo . '=VALUES(' . $campo . ')', $camposColetivos)));

                $camposIndividuais = ['minutos_jogados','golos','remates','remates_baliza','assistencias','passes','passes_certos','dribles_tentados','dribles_conseguidos','defesas','desarmes','intercecoes','faltas_sofridas','faltas_cometidas'];
                foreach ((array)($_POST['estatisticas_individuais'] ?? []) as $idJogador => $estatisticasJogador) {
                    $idJogador = (int)$idJogador;
                    if (!in_array($idJogador, $convocados, true)) continue;
                    $valores = array_map(static fn($campo) => max(0, (int)($estatisticasJogador[$campo] ?? 0)), $camposIndividuais);
                    $minutosJogados = in_array($idJogador, $titulares, true) ? $parteAtual * $minutosParte : 0;
                    foreach ($substituicoesValidas as $substituicaoValida) {
                        if ($substituicaoValida['saida'] === $idJogador) $minutosJogados = min($minutosJogados, $substituicaoValida['minuto']);
                        if ($substituicaoValida['entrada'] === $idJogador) $minutosJogados = max(0, ($parteAtual * $minutosParte) - $substituicaoValida['minuto']);
                    }
                    $valores[0] = max(0, $minutosJogados);
                    foreach ([['remates_baliza', 'remates'], ['dribles_conseguidos', 'dribles_tentados'], ['passes_certos', 'passes']] as [$campoDependente, $campoBase]) {
                        $indiceDependente = array_search($campoDependente, $camposIndividuais, true);
                        $indiceBase = array_search($campoBase, $camposIndividuais, true);
                        $valores[$indiceBase] = max($valores[$indiceBase], $valores[$indiceDependente]);
                    }
                    if (array_sum($valores) === 0) continue;
                    $conn->query("INSERT INTO jogo_estatisticas_individuais (id_jogo,id_jogador," . implode(',', $camposIndividuais) . ") VALUES (" . $idJogoDetalhe . "," . $idJogador . "," . implode(',', $valores) . ")");
                }

                $stmtResultadoDetalhe = $conn->prepare("UPDATE jogos_clube SET resultado_nos=?, resultado_adv=?, estado='Realizado' WHERE id_jogo=?");
                $stmtResultadoDetalhe->bind_param("iii", $resultadoNosDetalhe, $resultadoAdvDetalhe, $idJogoDetalhe);
                $stmtResultadoDetalhe->execute();
                $conn->commit();
                $sucesso = 'Detalhe e estatísticas do jogo guardados.';
            } catch (Throwable $e) {
                $conn->rollback();
                if (!$erro) $erro = 'Não foi possível guardar o detalhe do jogo.';
            }
        }
    }

    if ($acao === 'enviar_mensagem') {
        $destinoMensagem = (int)($_POST['destino_mensagem'] ?? 0);
        $conteudoMensagem = trim($_POST['conteudo_mensagem'] ?? '');

        $mostrarMensagens = true;
        $viewMode = 'mensagens';
        $chatSelecionadoId = $destinoMensagem;

        if ($destinoMensagem <= 0 || $conteudoMensagem === '') {
            $erro = 'Seleciona um destinatário e escreve uma mensagem.';
        } else {
            $stmtCheckDestinoMensagem = $conn->prepare(" 
                SELECT id_utilizador
                FROM utilizador
                WHERE id_utilizador = ?
                  AND id_clube = ?
                  AND id_utilizador <> ?
                LIMIT 1
            ");
            $stmtCheckDestinoMensagem->bind_param("iii", $destinoMensagem, $id_clube, $id_utilizador);
            $stmtCheckDestinoMensagem->execute();
            $destinoExiste = $stmtCheckDestinoMensagem->get_result()->fetch_assoc();

            if (!$destinoExiste) {
                $erro = 'Esse utilizador não está disponível para mensagens.';
            } else {
                $stmtInserirMensagem = $conn->prepare(" 
                    INSERT INTO mensagens (origem, destino, `conteúdo`, estado, enviada_em)
                    VALUES (?, ?, ?, 'Não Lida', NOW())
                ");
                $stmtInserirMensagem->bind_param("iis", $id_utilizador, $destinoMensagem, $conteudoMensagem);

                if ($stmtInserirMensagem->execute()) {
                    $sucesso = 'Mensagem enviada com sucesso.';
                } else {
                    $erro = 'Erro ao enviar mensagem.';
                }
            }
        }
    }

    if ($acao === 'editar_perfil') {
        $nomeUtilizador = trim($_POST['nome_utilizador'] ?? '');
        $emailUtilizador = trim($_POST['email'] ?? '');
        $primeiroNome = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome = trim($_POST['ultimo_nome'] ?? '');
        $telefoneUtilizador = trim($_POST['telemovel'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');
        $fotoPerfilAjustada = trim($_POST['foto_perfil_ajustada'] ?? '');
        $novaFotoPerfil = null;

        $emailObrigatorio = $isAdminClube;

        if ($nomeUtilizador === '' || $primeiroNome === '' || $ultimoNome === '' || ($emailObrigatorio && $emailUtilizador === '')) {
            $erro = 'Preenche todos os campos obrigatórios do perfil.';
        } elseif ($emailUtilizador !== '' && !filter_var($emailUtilizador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O email do perfil não é válido.';
        } elseif ($emailUtilizador !== '') {
            $stmtCheckPerfilEmail = $conn->prepare("
                SELECT id_utilizador
                FROM utilizador
                WHERE email_utilizador = ?
                  AND id_utilizador <> ?
                LIMIT 1
            ");
            $stmtCheckPerfilEmail->bind_param("si", $emailUtilizador, $id_utilizador);
            $stmtCheckPerfilEmail->execute();
            $perfilEmailExiste = $stmtCheckPerfilEmail->get_result()->fetch_assoc();

            if ($perfilEmailExiste) {
                $erro = 'Já existe outro utilizador com este email.';
            }
        }

        if (!$erro) {
            if ($fotoPerfilAjustada !== '') {
                if (!preg_match('/^data:(image\/(jpeg|png|webp));base64,([A-Za-z0-9+\/=]+)$/', $fotoPerfilAjustada, $matchesFotoAjustada)) {
                    $erro = 'Formato da foto ajustada inválido.';
                } else {
                    $binarioFotoAjustada = base64_decode($matchesFotoAjustada[3], true);

                    if ($binarioFotoAjustada === false) {
                        $erro = 'Não foi possível processar a foto ajustada.';
                    } elseif (strlen($binarioFotoAjustada) > 2 * 1024 * 1024) {
                        $erro = 'A foto de perfil ajustada deve ter no máximo 2MB.';
                    } else {
                        $infoFotoAjustada = @getimagesizefromstring($binarioFotoAjustada);
                        if ($infoFotoAjustada === false) {
                            $erro = 'A foto ajustada não é uma imagem válida.';
                        } else {
                            $novaFotoPerfil = $binarioFotoAjustada;
                        }
                    }
                }
            }

            if (!empty($_FILES['foto_perfil']['tmp_name'])) {
                if ($novaFotoPerfil !== null) {
                    // Já temos uma versão ajustada pronta para guardar.
                } elseif (!isset($_FILES['foto_perfil']) || (int)($_FILES['foto_perfil']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $erro = 'Não foi possível carregar a foto de perfil.';
                }

                $tmpFotoPerfil = $_FILES['foto_perfil']['tmp_name'];
                $tamanhoFotoPerfil = (int)($_FILES['foto_perfil']['size'] ?? 0);

                if (!$erro && $tamanhoFotoPerfil > 2 * 1024 * 1024) {
                    $erro = 'A foto de perfil deve ter no máximo 2MB.';
                }

                $infoFotoPerfil = !$erro ? @getimagesize($tmpFotoPerfil) : false;

                if (!$erro && $infoFotoPerfil === false) {
                    $erro = 'O ficheiro da foto de perfil não é uma imagem válida.';
                }

                $tiposPermitidosFotoPerfil = ['image/jpeg', 'image/png', 'image/webp'];
                $mimeFotoPerfil = $infoFotoPerfil['mime'] ?? '';

                if (!$erro && !in_array($mimeFotoPerfil, $tiposPermitidosFotoPerfil, true)) {
                    $erro = 'A foto de perfil deve estar em JPG, PNG ou WEBP.';
                }

                if (!$erro) {
                    $novaFotoPerfil = file_get_contents($tmpFotoPerfil);
                }
            }

            if (!$erro) {
                if ($novaFotoPerfil !== null) {
                    $stmtUpdatePerfil = $conn->prepare(" 
                        UPDATE utilizador
                        SET nome_utilizador = ?,
                            foto_perfil = ?,
                            email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''),
                            primeiro_nome = ?,
                            `último_nome` = ?,
                            data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                          AND id_clube = ?
                    ");

                    $stmtUpdatePerfil->bind_param(
                        "sssssssii",
                        $nomeUtilizador,
                        $novaFotoPerfil,
                        $emailUtilizador,
                        $telefoneUtilizador,
                        $primeiroNome,
                        $ultimoNome,
                        $dataNascimento,
                        $id_utilizador,
                        $id_clube
                    );
                } else {
                    $stmtUpdatePerfil = $conn->prepare(" 
                        UPDATE utilizador
                        SET nome_utilizador = ?,
                            email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''),
                            primeiro_nome = ?,
                            `último_nome` = ?,
                            data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                          AND id_clube = ?
                    ");

                    $stmtUpdatePerfil->bind_param(
                        "ssssssii",
                        $nomeUtilizador,
                        $emailUtilizador,
                        $telefoneUtilizador,
                        $primeiroNome,
                        $ultimoNome,
                        $dataNascimento,
                        $id_utilizador,
                        $id_clube
                    );
                }
            }

            if ($erro) {
                // Erro de validação da imagem já definido acima.
            } elseif (!$stmtUpdatePerfil->execute()) {
                $erro = 'Erro ao guardar as alterações do perfil.';
            } else {
                $sucesso = 'Perfil atualizado com sucesso.';
            }
        }
    }

    if ($acao === 'alterar_estado_notificacao' || $acao === 'marcar_notificacao_lida') {
        $idNotificacao = (int)($_POST['id_notificacao'] ?? 0);
        $estadoNotificacao = $_POST['estado'] ?? 'Lida';

        if ($acao === 'marcar_notificacao_lida') {
            $estadoNotificacao = 'Lida';
        }

        if (!in_array($estadoNotificacao, ['Lida', 'Nao Lida'], true)) {
            $estadoNotificacao = 'Lida';
        }

        if ($idNotificacao > 0) {
            $stmtMarcaLida = $conn->prepare("
                UPDATE notificacao
                SET estado = ?,
                    lida_em = CASE WHEN ? = 'Lida' THEN NOW() ELSE NULL END
                WHERE id_notificacao = ?
                  AND id_utilizador = ?
            ");
            $stmtMarcaLida->bind_param("ssii", $estadoNotificacao, $estadoNotificacao, $idNotificacao, $id_utilizador);
            $stmtMarcaLida->execute();
        }

        exit;
    }

    /* ── Editar informações do clube ── */
    if ($acao === 'editar_clube') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para editar o clube.';
        } else {

        $activeTab = 'tab-info';

        $editNome       = trim($_POST['nome_clube'] ?? '');
        $editSigla      = strtoupper(trim($_POST['sigla'] ?? ''));
        $editCor        = strtoupper(trim($_POST['cor'] ?? ''));
        $editData       = trim($_POST['data_fundacao'] ?? '');
        $editMorada     = trim($_POST['sede_morada'] ?? '');
        $editCidade     = trim($_POST['cidade_clube'] ?? '');
        $editPais       = trim($_POST['pais_clube'] ?? '');
        $editTelefone   = trim($_POST['telefone_clube'] ?? '');
        $editEmail      = trim($_POST['email_clube'] ?? '');
        $editWebsite    = trim($_POST['website_clube'] ?? '');
        $editPresidente = trim($_POST['presidente_clube'] ?? '');
        $editEstadio    = trim($_POST['nome_estadio'] ?? '');

        if ($editNome === '' || $editSigla === '' || $editCor === '') {
            $erro = 'Nome, sigla e cor são obrigatórios.';
        } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $editCor)) {
            $erro = 'A cor tem de estar no formato hexadecimal. Exemplo: #32329E';
        } elseif ($editEmail !== '' && !filter_var($editEmail, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O email do clube não é válido.';
        } else {

            $editData       = $editData !== '' ? $editData : null;
            $editMorada     = $editMorada !== '' ? $editMorada : null;
            $editCidade     = $editCidade !== '' ? $editCidade : null;
            $editPais       = $editPais !== '' ? $editPais : null;
            $editTelefone   = $editTelefone !== '' ? $editTelefone : null;
            $editEmail      = $editEmail !== '' ? $editEmail : null;
            $editWebsite    = $editWebsite !== '' ? $editWebsite : null;
            $editPresidente = $editPresidente !== '' ? $editPresidente : null;

            if ($editWebsite !== null && !preg_match('/^https?:\/\//i', $editWebsite)) {
                $editWebsite = 'https://' . $editWebsite;
            }

            if (!empty($_FILES['logotipo']['tmp_name'])) {

                $novoLogo = file_get_contents($_FILES['logotipo']['tmp_name']);

                $stmtUpdate = $conn->prepare("
                    UPDATE clube
                    SET nome_clube = ?,
                        sigla = ?,
                        cor = ?,
                        `data_fundação` = ?,
                        sede_morada = ?,
                        cidade_clube = ?,
                        `país_clube` = ?,
                        telefone_clube = ?,
                        email_clube = ?,
                        website_clube = ?,
                        presidente_clube = ?,
                        logotipo = ?
                    WHERE id_clube = ?
                ");

                if ($stmtUpdate) {
                    $stmtUpdate->bind_param(
                        "ssssssssssssi",
                        $editNome,
                        $editSigla,
                        $editCor,
                        $editData,
                        $editMorada,
                        $editCidade,
                        $editPais,
                        $editTelefone,
                        $editEmail,
                        $editWebsite,
                        $editPresidente,
                        $novoLogo,
                        $id_clube
                    );
                }

            } else {

                $stmtUpdate = $conn->prepare("
                    UPDATE clube
                    SET nome_clube = ?,
                        sigla = ?,
                        cor = ?,
                        `data_fundação` = ?,
                        sede_morada = ?,
                        cidade_clube = ?,
                        `país_clube` = ?,
                        telefone_clube = ?,
                        email_clube = ?,
                        website_clube = ?,
                        presidente_clube = ?
                    WHERE id_clube = ?
                ");

                if ($stmtUpdate) {
                    $stmtUpdate->bind_param(
                        "sssssssssssi",
                        $editNome,
                        $editSigla,
                        $editCor,
                        $editData,
                        $editMorada,
                        $editCidade,
                        $editPais,
                        $editTelefone,
                        $editEmail,
                        $editWebsite,
                        $editPresidente,
                        $id_clube
                    );
                }
            }

            if (!$stmtUpdate) {
                $erro = 'Erro na preparação da atualização do clube.';
            } elseif (!$stmtUpdate->execute()) {
                $erro = 'Erro ao atualizar os dados do clube.';
            } else {

                /* Atualizar ou criar estádio */
                $stmtCheckEstadio = $conn->prepare("
                    SELECT id_estádio
                    FROM estádio
                    WHERE id_clube = ?
                    LIMIT 1
                ");
                $stmtCheckEstadio->bind_param("i", $id_clube);
                $stmtCheckEstadio->execute();
                $estadioAtual = $stmtCheckEstadio->get_result()->fetch_assoc();

                if ($estadioAtual) {
                    $stmtEstadio = $conn->prepare("
                        UPDATE estádio
                        SET nome_estádio = ?
                        WHERE id_estádio = ?
                    ");
                    $stmtEstadio->bind_param(
                        "si",
                        $editEstadio,
                        $estadioAtual['id_estádio']
                    );
                    $stmtEstadio->execute();
                } elseif ($editEstadio !== '') {
                    $capacidadeDefault = 0;

                    $stmtEstadio = $conn->prepare("
                        INSERT INTO estádio
                        (id_clube, nome_estádio, capacidade)
                        VALUES (?, ?, ?)
                    ");
                    $stmtEstadio->bind_param(
                        "isi",
                        $id_clube,
                        $editEstadio,
                        $capacidadeDefault
                    );
                    $stmtEstadio->execute();
                }

                $sucesso = 'Informações do clube atualizadas com sucesso.';
            }
        }
        }
    }

    /* ── Criar escalão ── */
    if ($acao === 'criar_escalao') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para criar escalões.';
        } else {

        $activeTab = 'tab-escaloes';

        $escalao    = trim($_POST['escalao'] ?? '');
        $hierarquia = trim($_POST['hierarquia'] ?? '');
        $id_epoca   = (int)($_POST['id_epoca'] ?? 0);

        if (!in_array($escalao, $listaEscaloesDisponiveis, true)) {
            $erro = 'Escalão inválido.';
        } elseif (!in_array($hierarquia, $listaHierarquiasDisponiveis, true)) {
            $erro = 'Hierarquia inválida.';
        } elseif ($id_epoca <= 0) {
            $erro = 'Seleciona uma época.';
        } else {

            $stmtCheckEscalao = $conn->prepare("
                SELECT id_equipa
                FROM equipa
                WHERE `escalão` = ?
                  AND hierarquia = ?
                  AND `id_época` = ?
                  AND id_clube = ?
                LIMIT 1
            ");
            $stmtCheckEscalao->bind_param("ssii", $escalao, $hierarquia, $id_epoca, $id_clube);
            $stmtCheckEscalao->execute();
            $escalaoExiste = $stmtCheckEscalao->get_result()->fetch_assoc();

            if ($escalaoExiste) {
                $erro = 'Esse escalão já existe para esta época.';
            } else {
                $stmtCreateEscalao = $conn->prepare("
                    INSERT INTO equipa
                    (`escalão`, hierarquia, `id_época`, id_clube)
                    VALUES (?, ?, ?, ?)
                ");

                $stmtCreateEscalao->bind_param(
                    "ssii",
                    $escalao,
                    $hierarquia,
                    $id_epoca,
                    $id_clube
                );

                if ($stmtCreateEscalao->execute()) {
                    $sucesso = 'Escalão criado com sucesso.';
                } else {
                    $erro = 'Erro ao criar escalão.';
                }
            }
        }
        }
    }

    /* ── Editar escalão ── */
    if ($acao === 'editar_escalao') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para editar escalões.';
        } else {

        $activeTab = 'tab-escaloes';

        $idEquipa   = (int)($_POST['id_equipa'] ?? 0);
        $escalao    = trim($_POST['escalao'] ?? '');
        $hierarquia = trim($_POST['hierarquia'] ?? '');
        $id_epoca   = (int)($_POST['id_epoca'] ?? 0);

        if ($idEquipa <= 0) {
            $erro = 'Escalão inválido.';
        } elseif (!in_array($escalao, $listaEscaloesDisponiveis, true)) {
            $erro = 'Escalão inválido.';
        } elseif (!in_array($hierarquia, $listaHierarquiasDisponiveis, true)) {
            $erro = 'Hierarquia inválida.';
        } elseif ($id_epoca <= 0) {
            $erro = 'Seleciona uma época.';
        } else {

            $stmtCheckEquipa = $conn->prepare("
                SELECT id_equipa
                FROM equipa
                WHERE id_equipa = ?
                  AND id_clube = ?
                LIMIT 1
            ");
            $stmtCheckEquipa->bind_param("ii", $idEquipa, $id_clube);
            $stmtCheckEquipa->execute();
            $equipaAtual = $stmtCheckEquipa->get_result()->fetch_assoc();

            if (!$equipaAtual) {
                $erro = 'Esse escalão não pertence ao teu clube.';
            } else {

                $stmtCheckDuplicado = $conn->prepare("
                    SELECT id_equipa
                    FROM equipa
                    WHERE `escalão` = ?
                      AND hierarquia = ?
                      AND `id_época` = ?
                      AND id_clube = ?
                      AND id_equipa <> ?
                    LIMIT 1
                ");
                $stmtCheckDuplicado->bind_param("ssiii", $escalao, $hierarquia, $id_epoca, $id_clube, $idEquipa);
                $stmtCheckDuplicado->execute();
                $duplicado = $stmtCheckDuplicado->get_result()->fetch_assoc();

                if ($duplicado) {
                    $erro = 'Já existe outro escalão igual nessa época.';
                } else {

                    $stmtUpdateEscalao = $conn->prepare("
                        UPDATE equipa
                        SET `escalão` = ?,
                            hierarquia = ?,
                            `id_época` = ?
                        WHERE id_equipa = ?
                          AND id_clube = ?
                    ");

                    $stmtUpdateEscalao->bind_param(
                        "ssiii",
                        $escalao,
                        $hierarquia,
                        $id_epoca,
                        $idEquipa,
                        $id_clube
                    );

                    if ($stmtUpdateEscalao->execute()) {
                        $sucesso = 'Escalão atualizado com sucesso.';
                    } else {
                        $erro = 'Erro ao atualizar escalão.';
                    }
                }
            }
        }
        }
    }

    /* ── Remover escalão ── */
    if ($acao === 'remover_escalao') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para remover escalões.';
        } else {

        $activeTab = 'tab-escaloes';

        $idEquipa = (int)($_POST['id_equipa'] ?? 0);

        if ($idEquipa <= 0) {
            $erro = 'Escalão inválido.';
        } else {
            $stmtCheckEquipa = $conn->prepare(" 
                SELECT id_equipa
                FROM equipa
                WHERE id_equipa = ?
                  AND id_clube = ?
                LIMIT 1
            ");
            $stmtCheckEquipa->bind_param("ii", $idEquipa, $id_clube);
            $stmtCheckEquipa->execute();
            $equipaAtual = $stmtCheckEquipa->get_result()->fetch_assoc();

            if (!$equipaAtual) {
                $erro = 'Esse escalão não pertence ao teu clube.';
            } else {
                $stmtCountJogadores = $conn->prepare("SELECT COUNT(*) AS total FROM jogadores WHERE id_equipa = ?");
                $stmtCountJogadores->bind_param("i", $idEquipa);
                $stmtCountJogadores->execute();
                $totalJogadores = (int)($stmtCountJogadores->get_result()->fetch_assoc()['total'] ?? 0);

                if ($totalJogadores > 0) {
                    $erro = 'Não podes remover este escalão porque ainda tem jogadores associados.';
                } else {
                    $stmtDeleteAcesso = $conn->prepare("DELETE FROM acesso_equipa WHERE id_equipa = ?");
                    $stmtDeleteAcesso->bind_param("i", $idEquipa);
                    $stmtDeleteAcesso->execute();

                    $stmtDeleteEventos = $conn->prepare("DELETE FROM eventos_clube WHERE id_equipa = ?");
                    $stmtDeleteEventos->bind_param("i", $idEquipa);
                    $stmtDeleteEventos->execute();

                    $stmtDeleteEquipa = $conn->prepare(" 
                        DELETE FROM equipa
                        WHERE id_equipa = ?
                          AND id_clube = ?
                    ");
                    $stmtDeleteEquipa->bind_param("ii", $idEquipa, $id_clube);

                    if ($stmtDeleteEquipa->execute()) {
                        $sucesso = 'Escalão removido com sucesso.';
                    } else {
                        $erro = 'Erro ao remover escalão.';
                    }
                }
            }
        }
        }
    }

    /* ── Criar treinador ── */
    if ($acao === 'criar_treinador') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para criar treinadores.';
        } else {

        $activeTab = 'tab-treinadores';

        $nomeUtilizadorTreinador = trim($_POST['nome_utilizador_treinador'] ?? '');
        $primeiroNome       = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome         = trim($_POST['ultimo_nome'] ?? '');
        $emailTreinador     = trim($_POST['email_treinador'] ?? '');
        $passwordTreinador  = $_POST['password_treinador'] ?? '';
        $tipoTreinador      = trim($_POST['tipo_treinador'] ?? '');
        $idEquipaTreinador  = (int)($_POST['id_equipa'] ?? 0);

        if ($nomeUtilizadorTreinador === '' || $primeiroNome === '' || $ultimoNome === '' || $passwordTreinador === '' || $tipoTreinador === '') {
            $erro = 'Preenche todos os campos obrigatórios do treinador.';
        } elseif (!in_array($tipoTreinador, $tiposTreinadorDisponiveis, true)) {
            $erro = 'Seleciona um tipo de treinador válido.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $nomeUtilizadorTreinador)) {
            $erro = 'O nome de utilizador deve ter 3 a 30 caracteres (letras, números, ., _ ou -).';
        } elseif ($emailTreinador !== '' && !filter_var($emailTreinador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Email do treinador inválido.';
        } else {

            $stmtCheckUsername = $conn->prepare(" 
                SELECT id_utilizador
                FROM utilizador
                WHERE nome_utilizador = ?
                LIMIT 1
            ");
            $stmtCheckUsername->bind_param("s", $nomeUtilizadorTreinador);
            $stmtCheckUsername->execute();
            $usernameExiste = $stmtCheckUsername->get_result()->fetch_assoc();

            if ($usernameExiste) {
                $erro = 'Já existe um utilizador com esse nome de utilizador.';
            }

            if (!$erro && $emailTreinador !== '') {
                $stmtCheckEmail = $conn->prepare(" 
                    SELECT id_utilizador
                    FROM utilizador
                    WHERE email_utilizador = ?
                    LIMIT 1
                ");
                $stmtCheckEmail->bind_param("s", $emailTreinador);
                $stmtCheckEmail->execute();
                $emailExiste = $stmtCheckEmail->get_result()->fetch_assoc();

                if ($emailExiste) {
                    $erro = 'Já existe um utilizador com esse email.';
                }
            }

            if (!$erro) {
                $stmtCreateTreinador = $conn->prepare("
                    INSERT INTO utilizador
                    (nome_utilizador, email_utilizador, primeiro_nome, `último_nome`,
                     password, tipo_utilizador, tipo_treinador, id_clube)
                    VALUES (?, ?, ?, ?, ?, 'treinador', ?, ?)
                ");

                if (!$stmtCreateTreinador) {
                    $erro = 'Erro na preparação da criação do treinador.';
                } else {

                    $emailTreinadorInsert = $emailTreinador;

                    $stmtCreateTreinador->bind_param(
                        "ssssssi",
                        $nomeUtilizadorTreinador,
                        $emailTreinadorInsert,
                        $primeiroNome,
                        $ultimoNome,
                        $passwordTreinador,
                        $tipoTreinador,
                        $id_clube
                    );

                    if ($stmtCreateTreinador->execute()) {

                        $idNovoTreinador = $stmtCreateTreinador->insert_id;

                        if ($idEquipaTreinador > 0) {
                            $stmtCheckEquipa = $conn->prepare("
                                SELECT id_equipa
                                FROM equipa
                                WHERE id_equipa = ?
                                  AND id_clube = ?
                                LIMIT 1
                            ");
                            $stmtCheckEquipa->bind_param("ii", $idEquipaTreinador, $id_clube);
                            $stmtCheckEquipa->execute();
                            $equipaExiste = $stmtCheckEquipa->get_result()->fetch_assoc();

                            if ($equipaExiste) {
                                $stmtAcesso = $conn->prepare("
                                    INSERT INTO acesso_equipa
                                    (id_equipa, id_utilizador)
                                    VALUES (?, ?)
                                ");
                                $stmtAcesso->bind_param("ii", $idEquipaTreinador, $idNovoTreinador);
                                $stmtAcesso->execute();
                            }
                        }

                        $sucesso = 'Treinador criado com sucesso.';
                    } else {
                        $erro = 'Erro ao criar treinador.';
                    }
                }
            }
        }
        }
    }

    /* ── Editar treinador ── */
    if ($acao === 'editar_treinador') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para editar treinadores.';
        } else {

        $activeTab = 'tab-treinadores';

        $idTreinador       = (int)($_POST['id_treinador'] ?? 0);
        $nomeUtilizadorTreinador = trim($_POST['nome_utilizador_treinador'] ?? '');
        $primeiroNome      = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome        = trim($_POST['ultimo_nome'] ?? '');
        $emailTreinador    = trim($_POST['email_treinador'] ?? '');
        $novaPassword      = $_POST['nova_password_treinador'] ?? '';
        $tipoTreinador     = trim($_POST['tipo_treinador'] ?? '');
        $idEquipaTreinador = (int)($_POST['id_equipa'] ?? 0);

        if ($idTreinador <= 0) {
            $erro = 'Treinador inválido.';
        } elseif ($nomeUtilizadorTreinador === '' || $primeiroNome === '' || $ultimoNome === '' || $tipoTreinador === '') {
            $erro = 'Preenche os dados obrigatórios do treinador.';
        } elseif (!in_array($tipoTreinador, $tiposTreinadorDisponiveis, true)) {
            $erro = 'Seleciona um tipo de treinador válido.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $nomeUtilizadorTreinador)) {
            $erro = 'O nome de utilizador deve ter 3 a 30 caracteres (letras, números, ., _ ou -).';
        } elseif ($emailTreinador !== '' && !filter_var($emailTreinador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Email do treinador inválido.';
        } else {

            $stmtCheckTreinador = $conn->prepare("
                SELECT id_utilizador
                FROM utilizador
                WHERE id_utilizador = ?
                  AND id_clube = ?
                  AND tipo_utilizador = 'treinador'
                LIMIT 1
            ");
            $stmtCheckTreinador->bind_param("ii", $idTreinador, $id_clube);
            $stmtCheckTreinador->execute();
            $treinadorAtual = $stmtCheckTreinador->get_result()->fetch_assoc();

            if (!$treinadorAtual) {
                $erro = 'Esse treinador não pertence ao teu clube.';
            } else {

                $stmtCheckUsername = $conn->prepare(" 
                    SELECT id_utilizador
                    FROM utilizador
                    WHERE nome_utilizador = ?
                      AND id_utilizador <> ?
                    LIMIT 1
                ");
                $stmtCheckUsername->bind_param("si", $nomeUtilizadorTreinador, $idTreinador);
                $stmtCheckUsername->execute();
                $usernameExiste = $stmtCheckUsername->get_result()->fetch_assoc();

                if ($usernameExiste) {
                    $erro = 'Já existe outro utilizador com esse nome de utilizador.';
                }

                if (!$erro && $emailTreinador !== '') {
                    $stmtCheckEmail = $conn->prepare(" 
                        SELECT id_utilizador
                        FROM utilizador
                        WHERE email_utilizador = ?
                          AND id_utilizador <> ?
                        LIMIT 1
                    ");
                    $stmtCheckEmail->bind_param("si", $emailTreinador, $idTreinador);
                    $stmtCheckEmail->execute();
                    $emailExiste = $stmtCheckEmail->get_result()->fetch_assoc();

                    if ($emailExiste) {
                        $erro = 'Já existe outro utilizador com esse email.';
                    }
                }

                if (!$erro) {

                    if ($idEquipaTreinador > 0) {
                        $stmtCheckEquipa = $conn->prepare("
                            SELECT id_equipa
                            FROM equipa
                            WHERE id_equipa = ?
                              AND id_clube = ?
                            LIMIT 1
                        ");
                        $stmtCheckEquipa->bind_param("ii", $idEquipaTreinador, $id_clube);
                        $stmtCheckEquipa->execute();
                        $equipaValida = $stmtCheckEquipa->get_result()->fetch_assoc();

                        if (!$equipaValida) {
                            $erro = 'A equipa selecionada não pertence ao teu clube.';
                        }
                    }

                    if (!$erro) {

                        if ($novaPassword !== '') {
                            $stmtUpdateTreinador = $conn->prepare("
                                UPDATE utilizador
                                SET nome_utilizador = ?,
                                    primeiro_nome = ?,
                                    `último_nome` = ?,
                                    email_utilizador = ?,
                                    tipo_treinador = ?,
                                    password = ?
                                WHERE id_utilizador = ?
                                  AND id_clube = ?
                                  AND tipo_utilizador = 'treinador'
                            ");

                            $stmtUpdateTreinador->bind_param(
                                "ssssssii",
                                $nomeUtilizadorTreinador,
                                $primeiroNome,
                                $ultimoNome,
                                $emailTreinador,
                                $tipoTreinador,
                                $novaPassword,
                                $idTreinador,
                                $id_clube
                            );
                        } else {
                            $stmtUpdateTreinador = $conn->prepare("
                                UPDATE utilizador
                                SET nome_utilizador = ?,
                                    primeiro_nome = ?,
                                    `último_nome` = ?,
                                    email_utilizador = ?,
                                    tipo_treinador = ?
                                WHERE id_utilizador = ?
                                  AND id_clube = ?
                                  AND tipo_utilizador = 'treinador'
                            ");

                            $stmtUpdateTreinador->bind_param(
                                "sssssii",
                                $nomeUtilizadorTreinador,
                                $primeiroNome,
                                $ultimoNome,
                                $emailTreinador,
                                $tipoTreinador,
                                $idTreinador,
                                $id_clube
                            );
                        }

                        if ($stmtUpdateTreinador->execute()) {

                            $stmtDeleteAcesso = $conn->prepare("
                                DELETE ae
                                FROM acesso_equipa ae
                                INNER JOIN equipa eq ON eq.id_equipa = ae.id_equipa
                                WHERE ae.id_utilizador = ?
                                  AND eq.id_clube = ?
                            ");
                            $stmtDeleteAcesso->bind_param("ii", $idTreinador, $id_clube);
                            $stmtDeleteAcesso->execute();

                            if ($idEquipaTreinador > 0) {
                                $stmtAcesso = $conn->prepare("
                                    INSERT INTO acesso_equipa
                                    (id_equipa, id_utilizador)
                                    VALUES (?, ?)
                                ");
                                $stmtAcesso->bind_param("ii", $idEquipaTreinador, $idTreinador);
                                $stmtAcesso->execute();
                            }

                            $sucesso = 'Treinador atualizado com sucesso.';
                        } else {
                            $erro = 'Erro ao atualizar treinador.';
                        }
                    }
                }
            }
        }
        }
    }

    /* ── Remover treinador ── */
    if ($acao === 'remover_treinador') {

        if (!$isAdminClube) {
            $erro = 'Não tens permissão para remover treinadores.';
        } else {

        $activeTab = 'tab-treinadores';
        $idTreinador = (int)($_POST['id_treinador'] ?? 0);

        if ($idTreinador <= 0) {
            $erro = 'Treinador inválido.';
        } else {
            $stmtCheckTreinador = $conn->prepare(" 
                SELECT id_utilizador
                FROM utilizador
                WHERE id_utilizador = ?
                  AND id_clube = ?
                  AND tipo_utilizador = 'treinador'
                LIMIT 1
            ");
            $stmtCheckTreinador->bind_param("ii", $idTreinador, $id_clube);
            $stmtCheckTreinador->execute();
            $treinadorAtual = $stmtCheckTreinador->get_result()->fetch_assoc();

            if (!$treinadorAtual) {
                $erro = 'Esse treinador não pertence ao teu clube.';
            } else {
                $conn->begin_transaction();

                try {
                    $stmtDeleteAcesso = $conn->prepare("DELETE FROM acesso_equipa WHERE id_utilizador = ?");
                    $stmtDeleteAcesso->bind_param("i", $idTreinador);
                    $stmtDeleteAcesso->execute();

                    $stmtDeleteMensagens = $conn->prepare("DELETE FROM mensagens WHERE origem = ? OR destino = ?");
                    $stmtDeleteMensagens->bind_param("ii", $idTreinador, $idTreinador);
                    $stmtDeleteMensagens->execute();

                    $stmtDeleteNotificacoes = $conn->prepare("DELETE FROM notificacao WHERE id_utilizador = ?");
                    $stmtDeleteNotificacoes->bind_param("i", $idTreinador);
                    $stmtDeleteNotificacoes->execute();

                    $stmtDeleteTreinador = $conn->prepare(" 
                        DELETE FROM utilizador
                        WHERE id_utilizador = ?
                          AND id_clube = ?
                          AND tipo_utilizador = 'treinador'
                    ");
                    $stmtDeleteTreinador->bind_param("ii", $idTreinador, $id_clube);
                    $stmtDeleteTreinador->execute();

                    $conn->commit();
                    $sucesso = 'Treinador removido com sucesso.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $erro = 'Erro ao remover treinador.';
                }
            }
        }
        }
    }

    /* ── Criar jogador (+ utilizador) ── */
    if ($acao === 'criar_jogador') {
        $nomeCompleto    = trim($_POST['nome_completo'] ?? '');
        $alcunha         = trim($_POST['alcunha_jogador'] ?? '');
        $dataNasc        = trim($_POST['data_nascimento'] ?? '');
        $nacionalidade   = trim($_POST['nacionalidade'] ?? '');
        $paisNasc        = trim($_POST['pais_nascimento'] ?? $nacionalidade);
        $posicao         = trim($_POST['posicao_principal'] ?? '');
        $posicaoSec      = trim($_POST['posicao_secundaria'] ?? '') ?: null;
        $numero          = trim($_POST['numero'] ?? '') ?: null;
        $pe              = trim($_POST['pe_preferencial'] ?? '') ?: null;
        $altura          = trim($_POST['altura'] ?? '') ?: null;
        $peso            = trim($_POST['peso'] ?? '') ?: null;
        $idEquipaJog     = (int)($_POST['id_equipa_jogador'] ?? 0);
        $nomeUtilJog     = trim($_POST['nome_utilizador_jogador'] ?? '');
        $passwordJog     = $_POST['password_jogador'] ?? '';
        $emailJog        = trim($_POST['email_jogador'] ?? '') ?: null;

        $posicoesValidas = ['Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança'];

        if ($nomeCompleto === '' || $dataNasc === '' || $nacionalidade === '' || $posicao === '' || $idEquipaJog <= 0 || $nomeUtilJog === '' || $passwordJog === '') {
            $erro = 'Preenche todos os campos obrigatórios do jogador.';
        } elseif (!in_array($posicao, $posicoesValidas, true)) {
            $erro = 'Posição inválida.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $nomeUtilJog)) {
            $erro = 'Nome de utilizador inválido (3-30 chars, sem espaços).';
        } else {
            $stmtCkJogUser = $conn->prepare("SELECT id_utilizador FROM utilizador WHERE nome_utilizador = ? LIMIT 1");
            $stmtCkJogUser->bind_param("s", $nomeUtilJog);
            $stmtCkJogUser->execute();
            if ($stmtCkJogUser->get_result()->fetch_assoc()) {
                $erro = 'Já existe um utilizador com esse nome.';
            } else {
                $conn->begin_transaction();
                try {
                    $stmtCriarUtilJog = $conn->prepare("INSERT INTO utilizador (nome_utilizador,email_utilizador,primeiro_nome,`último_nome`,password,tipo_utilizador,id_clube) VALUES (?,?,?,?,'temp','jogador',?)");
                    $partes = explode(' ', $nomeCompleto, 2);
                    $prNome = $partes[0]; $ulNome = $partes[1] ?? '';
                    $emailJogIns = $emailJog ?? '';
                    $stmtCriarUtilJog->bind_param("ssssi", $nomeUtilJog, $emailJogIns, $prNome, $ulNome, $id_clube);
                    $stmtCriarUtilJog->execute();
                    $idNovoUtil = $stmtCriarUtilJog->insert_id;

                    /* Actualizar password via trigger */
                    $stmtPwd = $conn->prepare("UPDATE utilizador SET password=? WHERE id_utilizador=?");
                    $stmtPwd->bind_param("si", $passwordJog, $idNovoUtil);
                    $stmtPwd->execute();

                    $stmtCriarJog = $conn->prepare("INSERT INTO jogadores (nome_completo,alcunha_jogador,data_nascimento,nacionalidade,`país_nascimento`,`posição_principal`,`posição_secundária`,`número_favorito`,`pé_preferencial`,altura,peso,id_equipa,id_utilizador,foto_jogador) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'')");
                    $stmtCriarJog->bind_param("sssssssssssii", $nomeCompleto,$alcunha,$dataNasc,$nacionalidade,$paisNasc,$posicao,$posicaoSec,$numero,$pe,$altura,$peso,$idEquipaJog,$idNovoUtil);
                    $stmtCriarJog->execute();

                    $conn->commit();
                    $sucesso = 'Jogador criado com sucesso.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $erro = 'Erro ao criar jogador: ' . $e->getMessage();
                }
            }
        }
    }

    /* ── Editar jogador ── */
    if ($acao === 'editar_jogador') {
        $idJog       = (int)($_POST['id_jogador'] ?? 0);
        $nomeCompleto= trim($_POST['nome_completo'] ?? '');
        $alcunha     = trim($_POST['alcunha_jogador'] ?? '');
        $dataNasc    = trim($_POST['data_nascimento'] ?? '');
        $nacionalidade= trim($_POST['nacionalidade'] ?? '');
        $paisNasc    = trim($_POST['pais_nascimento'] ?? $nacionalidade);
        $posicao     = trim($_POST['posicao_principal'] ?? '');
        $posicaoSec  = trim($_POST['posicao_secundaria'] ?? '') ?: null;
        $numero      = trim($_POST['numero'] ?? '') ?: null;
        $pe          = trim($_POST['pe_preferencial'] ?? '') ?: null;
        $altura      = trim($_POST['altura'] ?? '') ?: null;
        $peso        = trim($_POST['peso'] ?? '') ?: null;
        $idEquipaJog = (int)($_POST['id_equipa_jogador'] ?? 0);

        if ($idJog <= 0 || $nomeCompleto === '' || $posicao === '' || $idEquipaJog <= 0) {
            $erro = 'Dados inválidos.';
        } else {
            $stmtCkJogClub = $conn->prepare("SELECT j.id_jogador FROM jogadores j JOIN equipa eq ON eq.id_equipa=j.id_equipa WHERE j.id_jogador=? AND eq.id_clube=? LIMIT 1");
            $stmtCkJogClub->bind_param("ii", $idJog, $id_clube);
            $stmtCkJogClub->execute();
            if (!$stmtCkJogClub->get_result()->fetch_assoc()) {
                $erro = 'Jogador não encontrado.';
            } else {
                $stmtEditJog = $conn->prepare("UPDATE jogadores SET nome_completo=?,alcunha_jogador=?,data_nascimento=?,nacionalidade=?,`país_nascimento`=?,`posição_principal`=?,`posição_secundária`=?,`número_favorito`=?,`pé_preferencial`=?,altura=?,peso=?,id_equipa=? WHERE id_jogador=?");
                $stmtEditJog->bind_param("sssssssssssii", $nomeCompleto,$alcunha,$dataNasc,$nacionalidade,$paisNasc,$posicao,$posicaoSec,$numero,$pe,$altura,$peso,$idEquipaJog,$idJog);
                if ($stmtEditJog->execute()) { $sucesso = 'Jogador atualizado.'; } else { $erro = 'Erro ao editar.'; }
            }
        }
    }

    /* ── Remover jogador ── */
    if ($acao === 'remover_jogador') {
        $idJog = (int)($_POST['id_jogador'] ?? 0);
        if ($idJog <= 0) { $erro = 'Inválido.'; } else {
            $stmtGetJog = $conn->prepare("SELECT j.id_utilizador FROM jogadores j JOIN equipa eq ON eq.id_equipa=j.id_equipa WHERE j.id_jogador=? AND eq.id_clube=? LIMIT 1");
            $stmtGetJog->bind_param("ii", $idJog, $id_clube);
            $stmtGetJog->execute();
            $jogRow = $stmtGetJog->get_result()->fetch_assoc();
            if (!$jogRow) { $erro = 'Jogador não encontrado.'; } else {
                $conn->begin_transaction();
                try {
                    $conn->query("DELETE FROM `lesões` WHERE id_jogador=$idJog");
                    $conn->query("DELETE FROM `histórico_carreira` WHERE id_jogador=$idJog");
                    $conn->query("DELETE FROM jogadores WHERE id_jogador=$idJog");
                    if (!empty($jogRow['id_utilizador'])) {
                        $uid = (int)$jogRow['id_utilizador'];
                        $conn->query("DELETE FROM mensagens WHERE origem=$uid OR destino=$uid");
                        $conn->query("DELETE FROM notificacao WHERE id_utilizador=$uid");
                        $conn->query("DELETE FROM utilizador WHERE id_utilizador=$uid");
                    }
                    $conn->commit();
                    $sucesso = 'Jogador removido.';
                } catch (Throwable $e) { $conn->rollback(); $erro = 'Erro ao remover jogador.'; }
            }
        }
    }

    /* ── Criar competição ── */
    if ($acao === 'criar_competicao') {
        $nomeComp  = trim($_POST['nome_competicao'] ?? '');
        $tipoComp  = trim($_POST['tipo_competicao'] ?? 'Liga');
        $epocaComp = trim($_POST['epoca_competicao'] ?? '');
        $estadoComp= trim($_POST['estado_competicao'] ?? 'A decorrer');
        $descComp  = trim($_POST['descricao_competicao'] ?? '');
        $idEquipaComp = (int)($_POST['id_equipa_competicao'] ?? 0);
        $tiposValidos = ['Liga','Taça','Torneio','Campeonato','Amigável','Outro'];
        $estadosValidos = ['A decorrer','Finalizada','Suspensa'];

        if ($nomeComp === '' || $idEquipaComp <= 0 || !in_array($tipoComp, $tiposValidos, true) || !in_array($estadoComp, $estadosValidos, true)) {
            $erro = 'Preenche os campos obrigatórios da competição.';
        } else {
            $stmtCkEqC = $conn->prepare("SELECT id_equipa FROM equipa WHERE id_equipa=? AND id_clube=? LIMIT 1");
            $stmtCkEqC->bind_param("ii", $idEquipaComp, $id_clube);
            $stmtCkEqC->execute();
            if (!$stmtCkEqC->get_result()->fetch_assoc()) { $erro = 'Equipa inválida.'; } else {
                $stmtCriarComp = $conn->prepare("INSERT INTO competicoes_clube (id_clube,id_equipa,nome,tipo,epoca,estado,descricao) VALUES (?,?,?,?,?,?,?)");
                $stmtCriarComp->bind_param("iisssss", $id_clube,$idEquipaComp,$nomeComp,$tipoComp,$epocaComp,$estadoComp,$descComp);
                if ($stmtCriarComp->execute()) { $sucesso = 'Competição criada.'; } else { $erro = 'Erro ao criar competição.'; }
            }
        }
    }

    /* ── Editar competição ── */
    if ($acao === 'editar_competicao') {
        $idComp    = (int)($_POST['id_competicao'] ?? 0);
        $nomeComp  = trim($_POST['nome_competicao'] ?? '');
        $tipoComp  = trim($_POST['tipo_competicao'] ?? 'Liga');
        $epocaComp = trim($_POST['epoca_competicao'] ?? '');
        $estadoComp= trim($_POST['estado_competicao'] ?? 'A decorrer');
        $descComp  = trim($_POST['descricao_competicao'] ?? '');
        if ($idComp <= 0 || $nomeComp === '') { $erro = 'Dados inválidos.'; } else {
            $stmtEditComp = $conn->prepare("UPDATE competicoes_clube SET nome=?,tipo=?,epoca=?,estado=?,descricao=? WHERE id_competicao=? AND id_clube=?");
            $stmtEditComp->bind_param("sssssii", $nomeComp,$tipoComp,$epocaComp,$estadoComp,$descComp,$idComp,$id_clube);
            if ($stmtEditComp->execute()) { $sucesso = 'Competição atualizada.'; } else { $erro = 'Erro ao editar.'; }
        }
    }

    /* ── Remover competição ── */
    if ($acao === 'remover_competicao') {
        $idComp = (int)($_POST['id_competicao'] ?? 0);
        if ($idComp <= 0) { $erro = 'Inválido.'; } else {
            $conn->begin_transaction();
            try {
                /* Remover eventos criados pelos jogos */
                $resJogComp = $conn->query("SELECT id_evento_clube FROM jogos_clube WHERE id_competicao=$idComp AND id_evento_clube IS NOT NULL");
                while ($rjc = $resJogComp->fetch_assoc()) {
                    $conn->query("DELETE FROM eventos_clube WHERE id_evento=" . (int)$rjc['id_evento_clube']);
                }
                $conn->query("DELETE FROM jogos_clube WHERE id_competicao=$idComp");
                $stmtDelComp = $conn->prepare("DELETE FROM competicoes_clube WHERE id_competicao=? AND id_clube=?");
                $stmtDelComp->bind_param("ii", $idComp, $id_clube);
                $stmtDelComp->execute();
                $conn->commit();
                $sucesso = 'Competição removida.';
            } catch (Throwable $e) { $conn->rollback(); $erro = 'Erro ao remover.'; }
        }
    }

    /* ── Criar jogo ── */
    if ($acao === 'criar_jogo') {
        $idCompJogo  = (int)($_POST['id_competicao_jogo'] ?? 0);
        $adversario  = trim($_POST['adversario'] ?? '');
        $dataJogo    = trim($_POST['data_jogo'] ?? '');
        $horaJogo    = trim($_POST['hora_jogo'] ?? '') ?: null;
        $casaJogo    = isset($_POST['casa_jogo']) ? 1 : 0;
        $localJogo   = trim($_POST['local_jogo'] ?? '') ?: null;
        $estadoJogo  = trim($_POST['estado_jogo'] ?? 'Agendado');

        if ($idCompJogo <= 0 || $adversario === '' || $dataJogo === '') {
            $erro = 'Preenche os campos obrigatórios do jogo.';
        } else {
            $stmtCkComp = $conn->prepare("SELECT cc.id_competicao, cc.id_equipa FROM competicoes_clube cc WHERE cc.id_competicao=? AND cc.id_clube=? LIMIT 1");
            $stmtCkComp->bind_param("ii", $idCompJogo, $id_clube);
            $stmtCkComp->execute();
            $compRow = $stmtCkComp->get_result()->fetch_assoc();
            if (!$compRow) { $erro = 'Competição inválida.'; } else {
                $conn->begin_transaction();
                try {
                    $stmtCriarJogo = $conn->prepare("INSERT INTO jogos_clube (id_competicao,adversario,data_jogo,hora_jogo,casa,local_jogo,estado) VALUES (?,?,?,?,?,?,?)");
                    $stmtCriarJogo->bind_param("isssiis", $idCompJogo,$adversario,$dataJogo,$horaJogo,$casaJogo,$localJogo,$estadoJogo);
                    $stmtCriarJogo->execute();
                    $idNovoJogo = $stmtCriarJogo->insert_id;

                    /* Auto-criar eventos_clube */
                    $descEvento = 'Jogo vs ' . $adversario;
                    $tipoEvEvento = 'Jogo';
                    $estadoEv = ($estadoJogo === 'Realizado') ? 'Realizado' : (($estadoJogo === 'Cancelado') ? 'Cancelado' : 'Por realizar');
                    $stmtCriarEv = $conn->prepare("INSERT INTO eventos_clube (id_equipa,tipo_evento,`descrição_evento`,estado_evento,data_evento,hora_evento,local_evento) VALUES (?,?,?,?,?,?,?)");
                    $stmtCriarEv->bind_param("issssss", $compRow['id_equipa'],$tipoEvEvento,$descEvento,$estadoEv,$dataJogo,$horaJogo,$localJogo);
                    $stmtCriarEv->execute();
                    $idNovoEvento = $stmtCriarEv->insert_id;

                    $stmtLinkEv = $conn->prepare("UPDATE jogos_clube SET id_evento_clube=? WHERE id_jogo=?");
                    $stmtLinkEv->bind_param("ii", $idNovoEvento, $idNovoJogo);
                    $stmtLinkEv->execute();

                    $conn->commit();
                    $sucesso = 'Jogo criado.';
                } catch (Throwable $e) { $conn->rollback(); $erro = 'Erro ao criar jogo: ' . $e->getMessage(); }
            }
        }
    }

    /* ── Atualizar resultado jogo ── */
    if ($acao === 'resultado_jogo') {
        $idJogoR    = (int)($_POST['id_jogo_resultado'] ?? 0);
        $resultNos  = $_POST['resultado_nos'] !== '' ? (int)$_POST['resultado_nos'] : null;
        $resultAdv  = $_POST['resultado_adv'] !== '' ? (int)$_POST['resultado_adv'] : null;
        $estadoJR   = trim($_POST['estado_jogo_resultado'] ?? 'Realizado');
        if ($idJogoR <= 0) { $erro = 'Jogo inválido.'; } else {
            $stmtResJog = $conn->prepare("UPDATE jogos_clube jc JOIN competicoes_clube cc ON cc.id_competicao=jc.id_competicao SET jc.resultado_nos=?,jc.resultado_adv=?,jc.estado=? WHERE jc.id_jogo=? AND cc.id_clube=?");
            $stmtResJog->bind_param("iisii", $resultNos,$resultAdv,$estadoJR,$idJogoR,$id_clube);
            if ($stmtResJog->execute()) {
                /* Actualizar estado do evento relacionado */
                $stmtEvJog = $conn->query("SELECT id_evento_clube FROM jogos_clube WHERE id_jogo=$idJogoR LIMIT 1");
                if ($evJogRow = $stmtEvJog->fetch_assoc()) {
                    $evId = (int)$evJogRow['id_evento_clube'];
                    if ($evId) $conn->query("UPDATE eventos_clube SET estado_evento='$estadoJR' WHERE id_evento=$evId");
                }
                $sucesso = 'Resultado guardado.';
            } else { $erro = 'Erro ao guardar resultado.'; }
        }
    }

    /* ── Remover jogo ── */
    if ($acao === 'remover_jogo') {
        $idJogoD = (int)($_POST['id_jogo'] ?? 0);
        if ($idJogoD <= 0) { $erro = 'Inválido.'; } else {
            $stmtGetEvJog = $conn->query("SELECT id_evento_clube FROM jogos_clube WHERE id_jogo=$idJogoD LIMIT 1");
            $evJogR = $stmtGetEvJog->fetch_assoc();
            $conn->begin_transaction();
            try {
                if (!empty($evJogR['id_evento_clube'])) {
                    $evIdD = (int)$evJogR['id_evento_clube'];
                    $conn->query("DELETE FROM eventos_clube WHERE id_evento=$evIdD");
                }
                $stmtDelJog = $conn->prepare("DELETE jc FROM jogos_clube jc JOIN competicoes_clube cc ON cc.id_competicao=jc.id_competicao WHERE jc.id_jogo=? AND cc.id_clube=?");
                $stmtDelJog->bind_param("ii", $idJogoD, $id_clube);
                $stmtDelJog->execute();
                $conn->commit();
                $sucesso = 'Jogo removido.';
            } catch (Throwable $e) { $conn->rollback(); $erro = 'Erro ao remover jogo.'; }
        }
    }

    /* ── Criar evento ── */
    if ($acao === 'criar_evento') {
        $idEquipaEvento  = (int)($_POST['id_equipa_evento'] ?? 0);
        $tipoEvento      = trim($_POST['tipo_evento'] ?? '');
        $descricaoEvento = trim($_POST['descricao_evento'] ?? '');
        $estadoEvento    = trim($_POST['estado_evento'] ?? 'Por realizar');
        $dataEvento      = trim($_POST['data_evento'] ?? '');
        $horaEvento      = trim($_POST['hora_evento'] ?? '') ?: null;
        $localEvento     = trim($_POST['local_evento'] ?? '') ?: null;
        $calMonth        = max(1, min(12, (int)($_POST['cal_month'] ?? (int)date('n'))));
        $calYear         = (int)($_POST['cal_year'] ?? (int)date('Y'));
        $calDay          = trim($_POST['cal_day'] ?? '');
        $calDayParam     = $calDay !== '' ? '&cal_day=' . urlencode($calDay) : '';

        $tiposEventoValidos = ['Treino','Jogo','Reunião Técnico-Tática','Sessão de Recuperação','Convívio de Equipa','Outro'];
        $estadosEventoValidos = ['Por realizar','Realizado','Cancelado','Adiado'];

        if ($idEquipaEvento <= 0 || $tipoEvento === '' || $dataEvento === '') {
            $erro = 'Preenche os campos obrigatórios do evento.';
        } elseif (!in_array($tipoEvento, $tiposEventoValidos, true)) {
            $erro = 'Tipo de evento inválido.';
        } elseif (!in_array($estadoEvento, $estadosEventoValidos, true)) {
            $erro = 'Estado do evento inválido.';
        } elseif ($estadoEvento === 'Por realizar' && $dataEvento < date('Y-m-d')) {
            $erro = 'Eventos "Por realizar" devem ter data atual ou futura.';
        } else {
            $stmtCkEqEv = $conn->prepare("SELECT id_equipa FROM equipa WHERE id_equipa = ? AND id_clube = ? LIMIT 1");
            $stmtCkEqEv->bind_param("ii", $idEquipaEvento, $id_clube);
            $stmtCkEqEv->execute();

            if (!$stmtCkEqEv->get_result()->fetch_assoc()) {
                $erro = 'Equipa inválida.';
            } else {
                $stmtCriarEv = $conn->prepare("
                    INSERT INTO eventos_clube (id_equipa, tipo_evento, `descrição_evento`, estado_evento, data_evento, hora_evento, local_evento)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtCriarEv->bind_param("issssss", $idEquipaEvento, $tipoEvento, $descricaoEvento, $estadoEvento, $dataEvento, $horaEvento, $localEvento);

                if ($stmtCriarEv->execute()) {
                    $_SESSION['flash_sucesso'] = 'Evento criado com sucesso.';
                    header("Location: index-treinador.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
                    exit;
                }

                $erro = 'Erro ao criar evento.';
            }
        }
    }

    /* ── Editar evento ── */
    if ($acao === 'editar_evento') {
        $idEvento        = (int)($_POST['id_evento'] ?? 0);
        $idEquipaEvento  = (int)($_POST['id_equipa_evento'] ?? 0);
        $tipoEvento      = trim($_POST['tipo_evento'] ?? '');
        $descricaoEvento = trim($_POST['descricao_evento'] ?? '');
        $estadoEvento    = trim($_POST['estado_evento'] ?? 'Por realizar');
        $dataEvento      = trim($_POST['data_evento'] ?? '');
        $horaEvento      = trim($_POST['hora_evento'] ?? '') ?: null;
        $localEvento     = trim($_POST['local_evento'] ?? '') ?: null;
        $calMonth        = max(1, min(12, (int)($_POST['cal_month'] ?? (int)date('n'))));
        $calYear         = (int)($_POST['cal_year'] ?? (int)date('Y'));
        $calDay          = trim($_POST['cal_day'] ?? '');
        $calDayParam     = $calDay !== '' ? '&cal_day=' . urlencode($calDay) : '';

        $tiposEventoValidos = ['Treino','Jogo','Reunião Técnico-Tática','Sessão de Recuperação','Convívio de Equipa','Outro'];
        $estadosEventoValidos = ['Por realizar','Realizado','Cancelado','Adiado'];

        if ($idEvento <= 0 || $idEquipaEvento <= 0 || $tipoEvento === '' || $dataEvento === '') {
            $erro = 'Preenche os campos obrigatórios do evento.';
        } elseif (!in_array($tipoEvento, $tiposEventoValidos, true)) {
            $erro = 'Tipo de evento inválido.';
        } elseif (!in_array($estadoEvento, $estadosEventoValidos, true)) {
            $erro = 'Estado do evento inválido.';
        } elseif ($estadoEvento === 'Por realizar' && $dataEvento < date('Y-m-d')) {
            $erro = 'Eventos "Por realizar" devem ter data atual ou futura.';
        } else {
            $stmtCkEqEv = $conn->prepare("SELECT id_equipa FROM equipa WHERE id_equipa = ? AND id_clube = ? LIMIT 1");
            $stmtCkEqEv->bind_param("ii", $idEquipaEvento, $id_clube);
            $stmtCkEqEv->execute();

            if (!$stmtCkEqEv->get_result()->fetch_assoc()) {
                $erro = 'Equipa inválida.';
            } else {
                $stmtEditEv = $conn->prepare("
                    UPDATE eventos_clube ec
                    JOIN equipa eq ON eq.id_equipa = ec.id_equipa
                    SET ec.id_equipa = ?,
                        ec.tipo_evento = ?,
                        ec.`descrição_evento` = ?,
                        ec.estado_evento = ?,
                        ec.data_evento = ?,
                        ec.hora_evento = ?,
                        ec.local_evento = ?
                    WHERE ec.id_evento = ?
                      AND eq.id_clube = ?
                ");
                $stmtEditEv->bind_param("issssssii", $idEquipaEvento, $tipoEvento, $descricaoEvento, $estadoEvento, $dataEvento, $horaEvento, $localEvento, $idEvento, $id_clube);

                if ($stmtEditEv->execute()) {
                    $_SESSION['flash_sucesso'] = 'Evento atualizado com sucesso.';
                    header("Location: index-treinador.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
                    exit;
                }

                $erro = 'Erro ao atualizar evento.';
            }
        }
    }

    /* ── Remover evento ── */
    if ($acao === 'remover_evento') {
        $idEvento      = (int)($_POST['id_evento'] ?? 0);
        $calMonth      = max(1, min(12, (int)($_POST['cal_month'] ?? (int)date('n'))));
        $calYear       = (int)($_POST['cal_year'] ?? (int)date('Y'));
        $calDay        = trim($_POST['cal_day'] ?? '');
        $calDayParam   = $calDay !== '' ? '&cal_day=' . urlencode($calDay) : '';

        if ($idEvento <= 0) {
            $erro = 'Evento inválido.';
        } else {
            $stmtDelEv = $conn->prepare("
                DELETE ec
                FROM eventos_clube ec
                JOIN equipa eq ON eq.id_equipa = ec.id_equipa
                WHERE ec.id_evento = ?
                  AND eq.id_clube = ?
            ");
            $stmtDelEv->bind_param("ii", $idEvento, $id_clube);

            if ($stmtDelEv->execute()) {
                $_SESSION['flash_sucesso'] = 'Evento removido com sucesso.';
                header("Location: index-treinador.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
                exit;
            }

            $erro = 'Erro ao remover evento.';
        }
    }
}

/* ══════════════════════════════════
   BUSCAR DADOS DO CLUBE
══════════════════════════════════ */

$stmt = $conn->prepare("
    SELECT c.nome_clube, c.sigla, c.cor, c.logotipo,
           c.data_fundação, c.sede_morada, c.cidade_clube,
           c.país_clube, c.telefone_clube, c.email_clube,
           c.website_clube, c.presidente_clube,
           e.nome_estádio
    FROM clube c
    LEFT JOIN estádio e ON e.id_clube = c.id_clube
    WHERE c.id_clube = ?
    LIMIT 1
");
$stmt->bind_param("i", $id_clube);
$stmt->execute();
$clube = $stmt->get_result()->fetch_assoc();

if (!$clube) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* ── Variáveis de conveniência ── */
$nomeClube  = $clube['nome_clube'];
$siglaClube = $clube['sigla'];
$corClube   = $clube['cor'] ?: '#000000';
$logoClube  = $clube['logotipo']
    ? 'data:image/png;base64,' . base64_encode($clube['logotipo'])
    : null;

/* ── Formatar data de fundação para PT ── */
$dataFundacao = '';
if (!empty($clube['data_fundação'])) {
    $ts = strtotime($clube['data_fundação']);
    $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
              'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $dataFundacao = date('j', $ts) . ' de ' . $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/* ── Morada completa ── */
$morada = implode(', ', array_filter([
    $clube['sede_morada'],
    $clube['cidade_clube'],
    $clube['país_clube'],
]));

/* ── Buscar épocas ── */
$epocas = [];
$resEpocas = $conn->query("
    SELECT `id_época`, `época`
    FROM `época`
    ORDER BY `id_época` DESC
");

while ($row = $resEpocas->fetch_assoc()) {
    $epocas[] = $row;
}

/* ── Buscar escalões do clube ── */
$escaloesClube = [];

$stmtEscaloes = $conn->prepare("
    SELECT eq.id_equipa, eq.`escalão`, eq.hierarquia, ep.`época`, ep.`id_época`
    FROM equipa eq
    LEFT JOIN `época` ep ON ep.`id_época` = eq.`id_época`
    WHERE eq.id_clube = ?
    ORDER BY ep.`id_época` DESC, eq.`escalão`, eq.hierarquia
");
$stmtEscaloes->bind_param("i", $id_clube);
$stmtEscaloes->execute();
$resEscaloes = $stmtEscaloes->get_result();

while ($row = $resEscaloes->fetch_assoc()) {
    $escaloesClube[] = $row;
}

/* No painel do treinador só aparecem as equipas atribuídas a este treinador. */
if (!$isAdminClube) {
    $escaloesClube = $equipasTreinador;
}

/* ── Buscar treinadores do clube ── */
$treinadoresClube = [];

$stmtTreinadores = $conn->prepare("
    SELECT 
        u.id_utilizador,
        u.nome_utilizador,
        u.primeiro_nome,
        u.`último_nome`,
        u.email_utilizador,
        u.tipo_treinador,
        MIN(eq.id_equipa) AS id_equipa_atual,
        COALESCE(
            GROUP_CONCAT(DISTINCT CONCAT(eq.`escalão`, ' ', eq.hierarquia) ORDER BY eq.`escalão`, eq.hierarquia SEPARATOR ', '),
            ''
        ) AS equipas
    FROM utilizador u
    LEFT JOIN acesso_equipa ae ON ae.id_utilizador = u.id_utilizador
    LEFT JOIN equipa eq ON eq.id_equipa = ae.id_equipa
    WHERE u.id_clube = ?
      AND u.tipo_utilizador = 'treinador'
        GROUP BY u.id_utilizador, u.nome_utilizador, u.primeiro_nome, u.`último_nome`, u.email_utilizador, u.tipo_treinador
    ORDER BY u.primeiro_nome, u.`último_nome`
");
$stmtTreinadores->bind_param("i", $id_clube);
$stmtTreinadores->execute();
$resTreinadores = $stmtTreinadores->get_result();

while ($row = $resTreinadores->fetch_assoc()) {
    $treinadoresClube[] = $row;
}

/* ── Buscar utilizadores e conversa para mensagens ── */
$utilizadoresMensagem = [];
$mensagensConversa = [];

$mensagensNaoLidas = 0;

$stmtMensagensNaoLidas = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM mensagens
    WHERE destino = ?
      AND estado = 'Não Lida'
");

if ($stmtMensagensNaoLidas) {
    $stmtMensagensNaoLidas->bind_param("i", $id_utilizador);
    $stmtMensagensNaoLidas->execute();
    $mensagensNaoLidas = (int)($stmtMensagensNaoLidas->get_result()->fetch_assoc()['total'] ?? 0);
}

$stmtUtilizadoresMensagem = $conn->prepare(" 
    SELECT
        u.id_utilizador,
        u.nome_utilizador,
        u.primeiro_nome,
        u.`último_nome`,
        u.foto_perfil,
        u.tipo_utilizador,
        MAX(m.id_mensagem) AS ultima_mensagem_id,
        COALESCE(SUM(CASE
            WHEN m.origem = u.id_utilizador
             AND m.destino = ?
             AND m.estado = 'Não Lida'
            THEN 1 ELSE 0 END), 0) AS nao_lidas
    FROM utilizador u
    LEFT JOIN mensagens m
      ON (
           (m.origem = u.id_utilizador AND m.destino = ?)
        OR (m.origem = ? AND m.destino = u.id_utilizador)
      )
    WHERE u.id_clube = ?
      AND u.id_utilizador <> ?
      AND u.tipo_utilizador IN ('admin_clube', 'treinador', 'jogador')
    GROUP BY
        u.id_utilizador,
        u.nome_utilizador,
        u.primeiro_nome,
        u.`último_nome`,
        u.foto_perfil,
        u.tipo_utilizador
    ORDER BY ultima_mensagem_id DESC, u.primeiro_nome, u.`último_nome`
");
$stmtUtilizadoresMensagem->bind_param("iiiii", $id_utilizador, $id_utilizador, $id_utilizador, $id_clube, $id_utilizador);
$stmtUtilizadoresMensagem->execute();
$resUtilizadoresMensagem = $stmtUtilizadoresMensagem->get_result();

while ($row = $resUtilizadoresMensagem->fetch_assoc()) {
    $row['foto_base64'] = !empty($row['foto_perfil'])
        ? 'data:image/png;base64,' . base64_encode($row['foto_perfil'])
        : null;
    $utilizadoresMensagem[] = $row;
}

$abrirMensagensAgora = $mostrarMensagens || $chatSelecionadoId > 0;

if ($abrirMensagensAgora && $chatSelecionadoId <= 0 && !empty($utilizadoresMensagem)) {
    $chatSelecionadoId = (int)$utilizadoresMensagem[0]['id_utilizador'];
}

$chatSelecionado = null;
foreach ($utilizadoresMensagem as $utilizadorMsg) {
    if ((int)$utilizadorMsg['id_utilizador'] === $chatSelecionadoId) {
        $chatSelecionado = $utilizadorMsg;
        break;
    }
}

if ($abrirMensagensAgora && $chatSelecionado) {
    $stmtMarcarLidas = $conn->prepare(" 
        UPDATE mensagens
        SET estado = 'Lida'
        WHERE origem = ?
          AND destino = ?
          AND estado = 'Não Lida'
    ");
    $stmtMarcarLidas->bind_param("ii", $chatSelecionadoId, $id_utilizador);
    $stmtMarcarLidas->execute();

    $stmtConversa = $conn->prepare(" 
        SELECT id_mensagem, origem, destino, `conteúdo`, estado, enviada_em
        FROM mensagens
        WHERE (origem = ? AND destino = ?)
           OR (origem = ? AND destino = ?)
        ORDER BY id_mensagem ASC
        LIMIT 300
    ");
    $stmtConversa->bind_param("iiii", $id_utilizador, $chatSelecionadoId, $chatSelecionadoId, $id_utilizador);
    $stmtConversa->execute();
    $resConversa = $stmtConversa->get_result();

    while ($row = $resConversa->fetch_assoc()) {
        $mensagensConversa[] = $row;
    }
}

/* ── Buscar perfil do utilizador atual ── */
$perfilUtilizador = [];
$stmtPerfil = $conn->prepare("
        SELECT nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador,
           primeiro_nome, `último_nome`, data_nascimento
    FROM utilizador
    WHERE id_utilizador = ?
      AND id_clube = ?
    LIMIT 1
");
$stmtPerfil->bind_param("ii", $id_utilizador, $id_clube);
$stmtPerfil->execute();
$perfilUtilizador = $stmtPerfil->get_result()->fetch_assoc() ?: [];

$fotoPerfilUtilizador = !empty($perfilUtilizador['foto_perfil'])
    ? 'data:image/png;base64,' . base64_encode($perfilUtilizador['foto_perfil'])
    : null;

/* ── Buscar notificações do utilizador ── */
$notificacoesUtilizador = [];
$stmtNotificacoes = $conn->prepare("
    SELECT id_notificacao, titulo, mensagem, tipo, estado, criada_em, lida_em, link_acao
    FROM notificacao
    WHERE id_utilizador = ?
      AND (id_clube = ? OR id_clube IS NULL)
    ORDER BY criada_em DESC
    LIMIT 20
");
$stmtNotificacoes->bind_param("ii", $id_utilizador, $id_clube);
$stmtNotificacoes->execute();
$resNotificacoes = $stmtNotificacoes->get_result();

while ($row = $resNotificacoes->fetch_assoc()) {
    $notificacoesUtilizador[] = $row;
}
$notificacoesNaoLidas = count(array_filter($notificacoesUtilizador, static fn($notificacao) => ($notificacao['estado'] ?? '') === 'Nao Lida'));
$stmtMensagensNaoLidas = $conn->prepare("SELECT COUNT(*) AS total FROM mensagens WHERE destino = ? AND estado = 'Não Lida'");
$stmtMensagensNaoLidas->bind_param("i", $id_utilizador);
$stmtMensagensNaoLidas->execute();
$mensagensNaoLidas = (int)($stmtMensagensNaoLidas->get_result()->fetch_assoc()['total'] ?? 0);

/* ── Buscar eventos do calendário das equipas do treinador ── */
$eventosCalendario = [];
$stmtEventosCalendario = $conn->prepare("
    SELECT DISTINCT ec.id_evento, ec.tipo_evento, ec.`descrição_evento` AS descricao_evento,
           ec.estado_evento, ec.data_evento, ec.hora_evento, ec.local_evento,
           eq.id_equipa, eq.`escalão`, eq.hierarquia
    FROM eventos_clube ec
    INNER JOIN equipa eq ON eq.id_equipa = ec.id_equipa
    INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
    WHERE ae.id_utilizador = ?
      AND eq.id_clube = ?
    ORDER BY ec.data_evento ASC, ec.hora_evento ASC
");
$stmtEventosCalendario->bind_param("ii", $id_utilizador, $id_clube);
$stmtEventosCalendario->execute();
$resEventosCalendario = $stmtEventosCalendario->get_result();
while ($row = $resEventosCalendario->fetch_assoc()) {
    $eventosCalendario[] = $row;
}

/* ── Buscar treinos das equipas do treinador ── */
$treinosTreinador = [];
$stmtTreinosTreinador = $conn->prepare("
    SELECT
        t.id_treino,
        t.id_equipa,
        t.`número_treino` AS numero_treino,
        t.`data` AS data_treino,
        t.hora AS hora_treino,
        t.`conteúdo` AS conteudo_treino,
        t.`observações` AS observacoes_treino,
        t.dia_da_semana,
        eq.`escalão`,
        eq.hierarquia,
        ep.`época`
    FROM treino t
    INNER JOIN equipa eq ON eq.id_equipa = t.id_equipa
    INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
    LEFT JOIN `época` ep ON ep.`id_época` = eq.`id_época`
    WHERE ae.id_utilizador = ?
      AND eq.id_clube = ?
    ORDER BY t.`data` DESC, t.hora DESC, t.id_treino DESC
");
$stmtTreinosTreinador->bind_param("ii", $id_utilizador, $id_clube);
$stmtTreinosTreinador->execute();
$resTreinosTreinador = $stmtTreinosTreinador->get_result();
while ($row = $resTreinosTreinador->fetch_assoc()) {
    $row['total_exercicios'] = 0;
    $treinosTreinador[] = $row;
}

/* ── Buscar exercícios visuais por treino ── */
$exerciciosPorTreino = [];
if (!empty($treinosTreinador)) {
    $idsTreinos = array_map(static fn($tr) => (int)$tr['id_treino'], $treinosTreinador);
    $idsTreinosSql = implode(',', array_filter($idsTreinos, static fn($id) => $id > 0));

    if ($idsTreinosSql !== '') {
        $resExerciciosPlano = $conn->query("
            SELECT id_exercicio, id_treino, ordem, titulo, desenho_json, descricao, objetivos
            FROM treino_exercicio
            WHERE id_treino IN ($idsTreinosSql)
            ORDER BY id_treino ASC, ordem ASC, id_exercicio ASC
        ");

        if ($resExerciciosPlano) {
            while ($rowEx = $resExerciciosPlano->fetch_assoc()) {
                $canvas = json_decode($rowEx['desenho_json'] ?? '', true);
                if (!is_array($canvas)) {
                    $canvas = ['template' => 'campo_inteiro', 'objects' => []];
                }

                $idTreinoEx = (int)$rowEx['id_treino'];
                $exerciciosPorTreino[$idTreinoEx][] = [
                    'id_exercicio' => (int)$rowEx['id_exercicio'],
                    'id_treino' => $idTreinoEx,
                    'ordem' => (int)$rowEx['ordem'],
                    'titulo' => $rowEx['titulo'],
                    'canvas' => $canvas,
                    'descricao' => $rowEx['descricao'],
                    'objetivos' => $rowEx['objetivos'],
                ];
            }
        }
    }

    foreach ($treinosTreinador as &$treinoRef) {
        $idTrRef = (int)$treinoRef['id_treino'];
        $treinoRef['total_exercicios'] = isset($exerciciosPorTreino[$idTrRef]) ? count($exerciciosPorTreino[$idTrRef]) : 0;
    }
    unset($treinoRef);
}

/* ── Buscar folha de presenças por treino ── */
$presencasPorTreino = [];
if (!empty($treinosTreinador)) {
    $idsTreinosPresenca = array_map(static fn($tr) => (int)$tr['id_treino'], $treinosTreinador);
    $idsTreinosPresencaSql = implode(',', array_filter($idsTreinosPresenca, static fn($id) => $id > 0));

    if ($idsTreinosPresencaSql !== '') {
        $resPresencasTreino = $conn->query("
            SELECT id_treino, id_jogador, estado_presenca, lesionado, observacoes
            FROM presenca_treino
            WHERE id_treino IN ($idsTreinosPresencaSql)
        ");

        if ($resPresencasTreino) {
            while ($rowPresencaTreino = $resPresencasTreino->fetch_assoc()) {
                $presencasPorTreino[(int)$rowPresencaTreino['id_treino']][(int)$rowPresencaTreino['id_jogador']] = $rowPresencaTreino;
            }
        }
    }
}

/* ── Buscar jogadores por equipa (para o ecrã de escalões) ── */
$jogadoresPorEquipa = [];

$stmtJogadores = $conn->prepare("
    SELECT DISTINCT 
           j.id_jogador,
           j.nome_completo,
           j.alcunha_jogador,
           j.`posição_principal`,
           j.`posição_secundária`,
           j.`número_favorito`,
           j.`pé_preferencial`,
           j.data_nascimento,
           j.nacionalidade,
           j.altura,
           j.peso,
           j.id_equipa,
           j.id_utilizador,
           j.foto_jogador,
           u.foto_perfil AS foto_perfil_utilizador
    FROM jogadores j
    INNER JOIN equipa eq 
            ON eq.id_equipa = j.id_equipa
    INNER JOIN acesso_equipa ae 
            ON ae.id_equipa = eq.id_equipa
    LEFT JOIN utilizador u
           ON u.id_utilizador = j.id_utilizador
          AND u.id_clube = eq.id_clube
          AND u.tipo_utilizador = 'jogador'
    WHERE ae.id_utilizador = ?
      AND eq.id_clube = ?
    ORDER BY j.id_equipa, j.`número_favorito` ASC, j.nome_completo ASC
");

$stmtJogadores->bind_param("ii", $id_utilizador, $id_clube);
$stmtJogadores->execute();
$resJogadores = $stmtJogadores->get_result();

while ($row = $resJogadores->fetch_assoc()) {
    if (!empty($row['foto_perfil_utilizador'])) {
        $row['foto_base64'] = 'data:image/png;base64,' . base64_encode($row['foto_perfil_utilizador']);
    } elseif (!empty($row['foto_jogador']) && strlen($row['foto_jogador']) > 10) {
        $row['foto_base64'] = 'data:image/png;base64,' . base64_encode($row['foto_jogador']);
    } else {
        $row['foto_base64'] = null;
    }

    $row['tem_foto'] = !empty($row['foto_base64']);

    unset($row['foto_jogador'], $row['foto_perfil_utilizador']);

    $jogadoresPorEquipa[$row['id_equipa']][] = $row;
}

/* ── Buscar competições das equipas do treinador ── */
$competicoesClube = [];
$stmtCompetiu = $conn->prepare("
    SELECT DISTINCT cc.id_competicao, cc.id_equipa, cc.nome, cc.tipo, cc.epoca, cc.estado, cc.descricao,
           eq.`escalão`, eq.hierarquia
    FROM competicoes_clube cc
    INNER JOIN equipa eq ON eq.id_equipa = cc.id_equipa
    INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
    WHERE ae.id_utilizador = ?
      AND cc.id_clube = ?
    ORDER BY cc.id_competicao DESC
");
$stmtCompetiu->bind_param("ii", $id_utilizador, $id_clube);
$stmtCompetiu->execute();
$resComp = $stmtCompetiu->get_result();
while ($row = $resComp->fetch_assoc()) {
    $competicoesClube[] = $row;
}

/* ── Buscar jogos das competições das equipas do treinador ── */
$jogosPorCompeticao = [];
$jogosTreinador = [];
$stmtJogosClub = $conn->prepare("
    SELECT DISTINCT
        jc.id_jogo,
        jc.id_competicao,
        jc.adversario,
        jc.data_jogo,
        jc.hora_jogo,
        jc.casa,
        jc.local_jogo,
        jc.resultado_nos,
        jc.resultado_adv,
        jc.estado,
        cc.nome AS nome_competicao,
        cc.tipo AS tipo_competicao,
        cc.epoca AS epoca_competicao,
        cc.id_equipa,
        eq.`escalão`,
        eq.hierarquia
    FROM jogos_clube jc
    INNER JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao
    INNER JOIN equipa eq ON eq.id_equipa = cc.id_equipa
    INNER JOIN acesso_equipa ae ON ae.id_equipa = eq.id_equipa
    WHERE ae.id_utilizador = ?
      AND cc.id_clube = ?
    ORDER BY jc.data_jogo ASC, jc.hora_jogo ASC
");
$stmtJogosClub->bind_param("ii", $id_utilizador, $id_clube);
$stmtJogosClub->execute();
$resJogosC = $stmtJogosClub->get_result();
while ($row = $resJogosC->fetch_assoc()) {
    $jogosPorCompeticao[$row['id_competicao']][] = $row;
    $jogosTreinador[] = $row;
}

/* ── Dados do detalhe de jogo selecionado ── */
$jogoDetalhe = null;
$configuracaoJogo = ['numero_partes' => 2, 'minutos_por_parte' => 45, 'presenca_equipa_tecnica' => '', 'tatica' => '4-3-3', 'posicoes_titulares' => null, 'parte_atual' => 0, 'jogo_terminado' => 0, 'jogo_iniciado' => 0, 'posse_inicial' => null, 'segundos_jogo' => 0];
$participantesJogo = ['Convocado' => [], 'Titular' => [], 'Suplente' => [], 'Suplente Utilizado' => []];
$substituicoesJogo = [];
$golosJogo = [];
$estatisticasColetivasJogo = [];
$estatisticasIndividuaisJogo = [];

foreach ($jogosTreinador as $jogoTreinador) {
    if ((int)$jogoTreinador['id_jogo'] === $jogoSelecionadoId) {
        $jogoDetalhe = $jogoTreinador;
        break;
    }
}

if ($jogoDetalhe) {
    $idJogoDetalhe = (int)$jogoDetalhe['id_jogo'];
    $resConfigJogo = $conn->query("SELECT numero_partes, minutos_por_parte, presenca_equipa_tecnica, tatica, posicoes_titulares, parte_atual, jogo_terminado, jogo_iniciado, posse_inicial, segundos_jogo FROM jogo_configuracao WHERE id_jogo=" . $idJogoDetalhe);
    if ($resConfigJogo && ($config = $resConfigJogo->fetch_assoc())) $configuracaoJogo = $config;
    $resParticipantes = $conn->query("SELECT id_jogador, tipo FROM jogo_participantes WHERE id_jogo=" . $idJogoDetalhe);
    while ($resParticipantes && ($participante = $resParticipantes->fetch_assoc())) {
        $participantesJogo[$participante['tipo']][] = (int)$participante['id_jogador'];
    }
    $resSubsJogo = $conn->query("SELECT id_jogador_entrada, id_jogador_saida, minuto FROM jogo_substituicoes WHERE id_jogo=" . $idJogoDetalhe . " ORDER BY minuto");
    while ($resSubsJogo && ($substituicao = $resSubsJogo->fetch_assoc())) $substituicoesJogo[] = $substituicao;
    $resGolosJogo = $conn->query("SELECT id_jogador_marcador, id_jogador_assistente, minuto, zona, forma FROM jogo_golos WHERE id_jogo=" . $idJogoDetalhe . " ORDER BY minuto");
    while ($resGolosJogo && ($golo = $resGolosJogo->fetch_assoc())) $golosJogo[] = $golo;
    $resColetivasJogo = $conn->query("SELECT * FROM jogo_estatisticas_coletivas WHERE id_jogo=" . $idJogoDetalhe);
    if ($resColetivasJogo && ($coletivas = $resColetivasJogo->fetch_assoc())) $estatisticasColetivasJogo = $coletivas;
    $resIndividuaisJogo = $conn->query("SELECT * FROM jogo_estatisticas_individuais WHERE id_jogo=" . $idJogoDetalhe);
    while ($resIndividuaisJogo && ($individual = $resIndividuaisJogo->fetch_assoc())) {
        $estatisticasIndividuaisJogo[(int)$individual['id_jogador']] = $individual;
    }
}

/* ── Estatísticas simples para o ecrã Campeonato ── */
$estatisticasCampeonato = [];
foreach ($competicoesClube as $comp) {
    $idComp = (int)$comp['id_competicao'];
    $stats = [
        'id_competicao' => $idComp,
        'nome' => $comp['nome'],
        'tipo' => $comp['tipo'],
        'epoca' => $comp['epoca'],
        'estado' => $comp['estado'],
        'equipa' => trim($comp['escalão'] . ' ' . $comp['hierarquia']),
        'jogos' => 0,
        'realizados' => 0,
        'vitorias' => 0,
        'empates' => 0,
        'derrotas' => 0,
        'golos_marcados' => 0,
        'golos_sofridos' => 0,
        'pontos' => 0
    ];

    foreach (($jogosPorCompeticao[$idComp] ?? []) as $jogo) {
        $stats['jogos']++;

        if ($jogo['estado'] === 'Realizado' && $jogo['resultado_nos'] !== null && $jogo['resultado_adv'] !== null) {
            $stats['realizados']++;
            $gm = (int)$jogo['resultado_nos'];
            $gs = (int)$jogo['resultado_adv'];
            $stats['golos_marcados'] += $gm;
            $stats['golos_sofridos'] += $gs;

            if ($gm > $gs) {
                $stats['vitorias']++;
                $stats['pontos'] += 3;
            } elseif ($gm === $gs) {
                $stats['empates']++;
                $stats['pontos'] += 1;
            } else {
                $stats['derrotas']++;
            }
        }
    }

    $estatisticasCampeonato[] = $stats;
}

/* ── Estatísticas globais (coletivas e individuais) para o ecrã Estatísticas ── */
$rotulosEstatisticasColetivas = ['remates_nos' => 'Remates nossos', 'remates_adversario' => 'Remates adversário', 'remates_baliza_nos' => 'Remates à baliza nossos', 'remates_baliza_adversario' => 'Remates à baliza adversário', 'dribles_tentados_nos' => 'Dribles tentados nossos', 'dribles_tentados_adversario' => 'Dribles tentados adversário', 'dribles_conseguidos_nos' => 'Dribles conseguidos nossos', 'dribles_conseguidos_adversario' => 'Dribles conseguidos adversário', 'defesas_nos' => 'Defesas nossas', 'defesas_adversario' => 'Defesas adversário', 'cantos_nos' => 'Cantos nossos', 'cantos_adversario' => 'Cantos adversário', 'passes_nos' => 'Passes nossos', 'passes_adversario' => 'Passes adversário', 'passes_certos_nos' => 'Passes certos nossos', 'passes_certos_adversario' => 'Passes certos adversário', 'faltas_nos' => 'Faltas nossas', 'faltas_adversario' => 'Faltas adversário'];
$paresEstatisticasColetivas = ['Remates' => ['remates_nos', 'remates_adversario'], 'Remates à baliza' => ['remates_baliza_nos', 'remates_baliza_adversario'], 'Dribles tentados' => ['dribles_tentados_nos', 'dribles_tentados_adversario'], 'Dribles conseguidos' => ['dribles_conseguidos_nos', 'dribles_conseguidos_adversario'], 'Defesas' => ['defesas_nos', 'defesas_adversario'], 'Cantos' => ['cantos_nos', 'cantos_adversario'], 'Passes' => ['passes_nos', 'passes_adversario'], 'Passes certos' => ['passes_certos_nos', 'passes_certos_adversario'], 'Faltas' => ['faltas_nos', 'faltas_adversario']];
$rotulosEstatisticasIndividuais = ['jogos' => 'J', 'minutos_jogados' => 'Min.', 'golos' => 'Golos', 'assistencias' => 'Assist.', 'remates' => 'Rem.', 'remates_baliza' => 'R. baliza', 'passes' => 'Passes', 'passes_certos' => 'P. certos', 'dribles_tentados' => 'Drib. tent.', 'dribles_conseguidos' => 'Drib. cons.', 'defesas' => 'Defesas', 'desarmes' => 'Desarmes', 'intercecoes' => 'Interceções', 'faltas_sofridas' => 'F. sofridas', 'faltas_cometidas' => 'F. cometidas'];

$idsJogosTreinador = array_values(array_unique(array_map(static fn($jogo) => (int)$jogo['id_jogo'], $jogosTreinador)));
$estatisticasColetivasGlobais = array_fill_keys(array_keys($rotulosEstatisticasColetivas), 0);
$estatisticasColetivasGlobais['jogos_com_registo'] = 0;
$estatisticasColetivasGlobais['posse_casa_segundos'] = 0;
$estatisticasColetivasGlobais['posse_visitante_segundos'] = 0;
$estatisticasIndividuaisGlobais = [];

if ($idsJogosTreinador) {
    $marcadoresJogos = implode(',', array_fill(0, count($idsJogosTreinador), '?'));
    $tiposJogos = str_repeat('i', count($idsJogosTreinador));

    $stmtColGlobais = $conn->prepare("SELECT * FROM jogo_estatisticas_coletivas WHERE id_jogo IN ($marcadoresJogos)");
    $stmtColGlobais->bind_param($tiposJogos, ...$idsJogosTreinador);
    $stmtColGlobais->execute();
    $resColGlobais = $stmtColGlobais->get_result();
    while ($linhaCol = $resColGlobais->fetch_assoc()) {
        $estatisticasColetivasGlobais['jogos_com_registo']++;
        foreach ($rotulosEstatisticasColetivas as $campo => $rotulo) $estatisticasColetivasGlobais[$campo] += (int)($linhaCol[$campo] ?? 0);
        $estatisticasColetivasGlobais['posse_casa_segundos'] += (int)($linhaCol['posse_casa_segundos'] ?? 0);
        $estatisticasColetivasGlobais['posse_visitante_segundos'] += (int)($linhaCol['posse_visitante_segundos'] ?? 0);
    }

    $stmtIndGlobais = $conn->prepare("SELECT * FROM jogo_estatisticas_individuais WHERE id_jogo IN ($marcadoresJogos)");
    $stmtIndGlobais->bind_param($tiposJogos, ...$idsJogosTreinador);
    $stmtIndGlobais->execute();
    $resIndGlobais = $stmtIndGlobais->get_result();
    while ($linhaInd = $resIndGlobais->fetch_assoc()) {
        // só entram jogadores que realmente entraram em campo (minutos > 0) — não jogadores só convocados
        if ((int)($linhaInd['minutos_jogados'] ?? 0) <= 0) continue;
        $idJogadorGlobal = (int)$linhaInd['id_jogador'];
        if (!isset($estatisticasIndividuaisGlobais[$idJogadorGlobal])) {
            $estatisticasIndividuaisGlobais[$idJogadorGlobal] = array_fill_keys(array_keys($rotulosEstatisticasIndividuais), 0);
        }
        $estatisticasIndividuaisGlobais[$idJogadorGlobal]['jogos']++;
        foreach ($rotulosEstatisticasIndividuais as $campo => $rotulo) {
            if ($campo === 'jogos') continue;
            $estatisticasIndividuaisGlobais[$idJogadorGlobal][$campo] += (int)($linhaInd[$campo] ?? 0);
        }
    }
}

$nomesJogadoresGlobais = [];
foreach ($jogadoresPorEquipa as $listaJogadoresEquipa) {
    foreach ($listaJogadoresEquipa as $jogadorEquipa) $nomesJogadoresGlobais[(int)$jogadorEquipa['id_jogador']] = $jogadorEquipa['nome_completo'];
}
uasort($estatisticasIndividuaisGlobais, static fn($a, $b) => $b['golos'] <=> $a['golos'] ?: $b['jogos'] <=> $a['jogos']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos Treinador | <?= htmlspecialchars($nomeClube) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --club: <?= htmlspecialchars($corClube) ?>;
    --sidebar-w: 68px;
    --topbar-h: 64px;
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

html, body { height: 100%; }

body { background: #f0f2f7; }

body.layout-locked { overflow: hidden; }

/* ══════════════════════════════════
   TOP BAR
══════════════════════════════════ */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    background: var(--club);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 calc(var(--sidebar-w) + 20px);
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.18);
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: calc(-1 * var(--sidebar-w));
}

.topbar-club-logo {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.28);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.topbar-club-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}

.topbar-club-logo--placeholder {
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.topbar-club-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.topbar-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

.topbar-sigla {
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,.75);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-logo {
    height: 28px;
    filter: brightness(0) invert(1);
}

.topbar-menu {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.topbar-menu span {
    display: block;
    width: 22px;
    height: 2px;
    background: rgba(255,255,255,.85);
    border-radius: 2px;
}

/* ══════════════════════════════════
   MENU SUPERIOR DIREITO
══════════════════════════════════ */
.topbar-user-menu-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.user-dropdown {
    position: absolute;
    top: calc(100% + 16px);
    right: 0;
    width: 230px;
    background: var(--club);
    display: none;
    flex-direction: column;
    border-radius: 0 0 4px 4px;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(0,0,0,.28);
    z-index: 500;
}

.user-dropdown.active {
    display: flex;
}

.user-dropdown a {
    min-height: 58px;
    padding: 0 22px;
    display: flex;
    align-items: center;
    color: #fff;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    border-bottom: 1px solid rgba(0,0,0,.22);
    transition: background .15s;
}

.user-dropdown a:hover {
    background: rgba(0,0,0,.14);
}

.user-dropdown a.logout-link {
    color: #ff0000;
    font-weight: 600;
}

.user-dropdown a.logout-link:hover {
    background: rgba(0,0,0,.18);
}

/* ══════════════════════════════════
   SIDEBAR
══════════════════════════════════ */
.sidebar {
    position: fixed;
    top: var(--topbar-h);
    left: 0;
    width: var(--sidebar-w);
    height: calc(100vh - var(--topbar-h));
    background: var(--club);
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    padding: 16px 0;
    z-index: 99;
    transition: width .22s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
    box-shadow: 2px 0 12px rgba(0,0,0,.10);
}

.sidebar:hover { width: 210px; }

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 10px 0;
    color: rgba(255,255,255,.78);
    text-decoration: none;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 500;
    border-left: 3px solid transparent;
    transition: background .15s, color .15s, border-color .15s;
}

.sidebar:hover a {
    justify-content: flex-start;
    padding: 10px 20px;
}

.sidebar a:hover,
.sidebar a.active {
    background: rgba(255,255,255,.13);
    color: #fff;
    border-left-color: #fff;
}

.sidebar a img {
    width: 34px;
    height: 34px;
    object-fit: contain;
    flex-shrink: 0;
    filter: brightness(0) invert(1);
    opacity: .85;
    transition: width .22s, height .22s, opacity .15s;
}

.sidebar:hover a img {
    width: 24px;
    height: 24px;
}

.sidebar a:hover img,
.sidebar a.active img { opacity: 1; }

.sidebar a span {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: opacity .18s, width .22s;
}

.sidebar:hover a span {
    opacity: 1;
    width: auto;
}

.sidebar a .sidebar-icon-wrap { position: relative; display: block; width: 34px; height: 34px; flex-shrink: 0; opacity: 1; overflow: visible; }
.sidebar a .sidebar-icon-wrap img { width: 34px; height: 34px; }
.menu-count-badge, .topbar-notification-badge { position: absolute; display: grid; place-items: center; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; background: #dc2626; color: #fff; font-size: 10px; line-height: 1; font-weight: 800; }
.menu-count-badge { top: -6px; right: -8px; }
.topbar-notification-badge { top: -7px; right: -8px; }

/* ══════════════════════════════════
   MAIN CONTENT
══════════════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 28px 28px 40px;
    min-height: calc(100vh - var(--topbar-h));
    transition: margin-left .22s cubic-bezier(.4,0,.2,1);
}

body.layout-locked .main {
    height: calc(100vh - var(--topbar-h));
    min-height: 0;
    overflow: hidden;
}

.sidebar:hover ~ .main { margin-left: 210px; }

/* ══════════════════════════════════
   CARD
══════════════════════════════════ */
.card {
    background: #fff;
    border-radius: 20px;
    padding: 28px 32px 36px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    position: relative;
}

body.layout-locked #dashboardCard {
    height: 100%;
    min-height: 0;
    overflow: hidden;
}

.card-header-actions {
    display: flex;
    justify-content: flex-end;
}

.tabs-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 14px;
    margin-bottom: 28px;
}

/* ── Painel de perfil ── */
.profile-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #dfe3ee;
    box-shadow: 0 8px 22px rgba(23, 42, 88, 0.08);
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.profile-shell.visible {
    display: block;
}

.profile-header {
    background: var(--club);
    border-bottom: 1px solid rgba(0,0,0,.12);
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px 0 18px;
    color: #fff;
}

.profile-title {
    font-size: clamp(1.2rem, 2vw, 1.8rem);
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1;
}

.profile-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-panel {
    background: #f5f7fb;
    padding: 24px 22px 18px;
}

.profile-content {
    display: grid;
    grid-template-columns: 1.5fr 0.9fr;
    gap: 18px 28px;
    align-items: center;
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 18px;
    min-width: 0;
}

.profile-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.profile-field label {
    font-size: 12px;
    font-weight: 700;
    color: var(--club);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.profile-field .profile-input {
    width: 100%;
    min-height: 56px;
    padding: 12px 16px;
    border-radius: 16px;
    border: 1px solid #d9e0f0;
    background: #fff;
    color: #1f2b3d;
    font-weight: 600;
    font-size: 15px;
    box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.02);
    transition: border-color .15s ease, box-shadow .15s ease;
}

.profile-field .profile-input:focus {
    outline: none;
    border-color: var(--club);
    box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.08);
}

.profile-avatar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 220px;
    background: rgba(255,255,255,0.12);
    border: 3px solid var(--club);
    border-radius: 28px;
    padding: 18px 10px;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 4px solid var(--club);
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--club);
    font-size: 56px;
    font-weight: 800;
    margin-bottom: 12px;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar-button {
    width: 100%;
    max-width: 220px;
    border: none;
    border-radius: 18px;
    background: var(--club);
    color: #fff;
    padding: 14px 18px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.profile-adjust-tools {
    width: 100%;
    max-width: 220px;
    margin-top: 10px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
}

.profile-adjust-tools label {
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.profile-adjust-tools input[type="range"] {
    width: 100%;
    accent-color: var(--club);
}

.profile-save-button {
    border: none;
    border-radius: 16px;
    background: var(--club);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    padding: 14px 22px;
    cursor: pointer;
    transition: transform .15s ease, opacity .15s ease;
}

.profile-save-button:hover {
    opacity: .96;
    transform: translateY(-1px);
}

.profile-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
}

@media (max-width: 820px) {
    .profile-content {
        grid-template-columns: 1fr;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Painel de notificações ── */
.notifications-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #dfe3ee;
    box-shadow: 0 8px 22px rgba(23, 42, 88, 0.08);
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.notifications-shell.visible {
    display: block;
}

.notifications-card {
    background: #f5f7fb;
}

.notifications-tabs {
    display: flex;
    gap: 0;
    padding: 0;
    border-bottom: 1px solid rgba(0,0,0,.12);
    background: var(--club);
}

.notification-tab {
    border: none;
    border-left: 3px solid transparent;
    background: transparent;
    padding: 14px 22px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(255,255,255,.75);
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s, padding-left .15s;
}

.notification-tab:hover,
.notification-tab.active {
    background: rgba(255,255,255,.13);
    color: #fff;
    border-left-color: #fff;
    padding-left: 26px;
}

.notifications-list {
    background: #f5f7fb;
    padding: 0 0 8px;
}

.notification-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid rgba(16, 24, 40, 0.08);
    background: transparent;
}

.notification-row:last-child {
    border-bottom: none;
}

.notification-row.unread {
    background: rgba(255,255,255,0.55);
}

.notification-row.read {
    background: rgba(203, 209, 221, 0.35);
    border-left: 3px solid var(--club);
}

.notification-label {
    font-size: 16px;
    font-weight: 500;
    color: #1f2a37;
}

.notification-row.read .notification-label {
    color: #7b8596;
    font-weight: 400;
}

.notification-check {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--club);
    background: #fff;
    color: var(--club);
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.notification-row.read .notification-check {
    background: var(--club);
    border-color: var(--club);
    color: #fff;
}

.notification-row.unread .notification-check {
    background: #fff;
    border-color: var(--club);
    color: var(--club);
}

@media (max-width: 760px) {
    .notifications-tabs {
        gap: 0;
        overflow-x: auto;
    }

    .notification-label {
        font-size: 14px;
    }
}

/* ── Calendário ── */
.calendar-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #dfe3ee;
    box-shadow: 0 8px 22px rgba(23,42,88,.08);
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.calendar-shell.visible {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.calendar-header {
    background: var(--club);
    min-height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px;
    color: #fff;
    flex-wrap: wrap;
    gap: 10px;
}

.calendar-header-title {
    font-size: 18px;
    font-weight: 700;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 10px;
}

.calendar-nav-btn {
    border: none;
    background: rgba(255,255,255,.18);
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
    line-height: 1;
}

.calendar-nav-btn:hover {
    background: rgba(255,255,255,.32);
}

.calendar-month-label {
    font-size: 15px;
    font-weight: 700;
    min-width: 170px;
    text-align: center;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 4px;
}

.calendar-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    flex: 1;
    min-height: 0;
}

.calendar-grid-wrap {
    padding: 18px 16px;
    border-right: 1px solid #e8edf5;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.calendar-weekday {
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: #9aa0ae;
    padding: 6px 0;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    flex: 1;
    align-content: stretch;
}

.calendar-day {
    min-height: 68px;
    border-radius: 10px;
    padding: 6px 4px 4px;
    cursor: pointer;
    transition: background .12s;
    position: relative;
}

.calendar-day:hover { background: #f0f4ff; }

.calendar-day.today { background: #eef2ff; }

.calendar-day.selected {
    background: var(--club);
}

.calendar-day.other-month { opacity: .38; }

.calendar-day-num {
    font-size: 13px;
    font-weight: 600;
    line-height: 1;
    display: block;
    text-align: right;
    padding-right: 4px;
    color: #1f2b3d;
}

.calendar-day.today .calendar-day-num { color: var(--club); font-weight: 800; }

.calendar-day.selected .calendar-day-num { color: #fff; }

.calendar-day.selected:hover { background: var(--club); opacity: .9; }

.calendar-event-dots {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    margin-top: 4px;
    padding: 0 2px;
}

.calendar-event-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.calendar-events-panel {
    padding: 16px 14px;
    background: #f8faff;
    overflow-y: auto;
    min-height: 0;
}

.calendar-events-panel-title {
    font-size: 13px;
    font-weight: 700;
    color: #1f2b3d;
    margin-bottom: 10px;
}

.calendar-event-item {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8edf5;
    padding: 11px 13px;
    margin-bottom: 8px;
    position: relative;
}

.calendar-event-type {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 3px;
}

.calendar-event-team {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 3px;
}

.calendar-event-desc {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
}

.calendar-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 5px;
    font-size: 11px;
    color: #9aa0ae;
}

.calendar-event-actions {
    display: flex;
    gap: 6px;
    margin-top: 7px;
}

.calendar-empty-day {
    text-align: center;
    color: #9aa0ae;
    font-size: 13px;
    padding: 24px 0;
}

/* ── Botão de edição do clube ── */
.btn-edit {
    position: static;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1.5px solid #ddd;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, border-color .15s;
}

.btn-edit:hover { background: #f5f5f5; border-color: #bbb; }

.btn-edit svg { width: 16px; height: 16px; color: #555; }

/* ── Botões de editar nas linhas ── */
.actions-col {
    width: 140px;
    text-align: left;
}

.actions-cell {
    text-align: left;
    white-space: nowrap;
}

.actions-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
}

/* ── Mensagens (estilo DM) ── */
.messages-shell {
    display: none;
    background: #fff;
    border: 1px solid #dfe3ee;
    border-radius: 18px;
    box-shadow: 0 8px 22px rgba(23, 42, 88, 0.08);
    overflow: hidden;
}

.messages-shell.visible {
    display: grid;
    grid-template-columns: 310px 1fr;
    height: 100%;
    min-height: 0;
}

.messages-sidebar {
    border-right: 1px solid #e6eaf2;
    background: #fff;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.messages-sidebar-header {
    padding: 18px 16px;
    border-bottom: 1px solid #eef2f7;
    font-weight: 700;
    color: #1f2b3d;
}

.messages-list {
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    max-height: none;
}

.message-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    text-decoration: none;
    color: #1f2b3d;
    border-bottom: 1px solid #f2f4f9;
}

.message-user:hover {
    background: #f6f8fc;
}

.message-user.active {
    background: #eff4ff;
}

.message-user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eaf0fb;
    border: 1px solid #d9e0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--club);
    overflow: hidden;
    flex-shrink: 0;
}

.message-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.message-user-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.message-user-name {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-user-type {
    font-size: 12px;
    color: #6b7280;
}

.message-user-unread {
    margin-left: auto;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--club);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.messages-chat {
    display: flex;
    flex-direction: column;
    background: #f9fbff;
    min-height: 0;
    overflow: hidden;
}

.messages-chat-header {
    min-height: 64px;
    border-bottom: 1px solid #e5ebf5;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 16px;
    font-weight: 700;
    color: #1f2b3d;
}

.messages-thread {
    flex: 1;
    padding: 18px 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 0;
}

.dm-bubble {
    max-width: min(72%, 520px);
    border-radius: 18px;
    padding: 10px 13px 6px;
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
}

.dm-time {
    display: block;
    font-size: 10px;
    margin-top: 4px;
    opacity: .65;
    text-align: right;
}

.dm-bubble.out {
    align-self: flex-end;
    background: var(--club);
    color: #fff;
    border-bottom-right-radius: 6px;
}

.dm-bubble.in {
    align-self: flex-start;
    background: #fff;
    color: #1f2b3d;
    border: 1px solid #e2e8f4;
    border-bottom-left-radius: 6px;
}

.messages-compose {
    border-top: 1px solid #e5ebf5;
    background: #fff;
    padding: 12px;
    flex-shrink: 0;
}

.messages-compose form {
    display: flex;
    align-items: center;
    gap: 8px;
}

.messages-compose textarea {
    flex: 1;
    min-height: 44px;
    max-height: 120px;
    resize: vertical;
    border: 1px solid #d9e0f0;
    border-radius: 14px;
    padding: 11px 12px;
    font-size: 14px;
    font-family: inherit;
}

.messages-compose button {
    border: none;
    border-radius: 14px;
    background: var(--club);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    padding: 11px 14px;
    cursor: pointer;
}

.messages-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #7b8596;
    padding: 16px;
}

.btn-row-edit {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1.5px solid #ddd;
    background: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #555;
    font-size: 15px;
    font-weight: 700;
    transition: background .15s, border-color .15s, color .15s;
}

.btn-row-edit:hover {
    background: #f5f5f5;
    border-color: #bbb;
    color: var(--club);
}

.btn-row-delete {
    border-color: #f2b4b4;
    color: #b42318;
}

.btn-row-delete:hover {
    background: #fff1f1;
    border-color: #e78b8b;
    color: #b42318;
}

/* ── Alerts ── */
.alert {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.alert-close {
    border: none;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
    padding: 0 4px;
}

.alert-error {
    background: #fff1f1;
    color: #b00020;
    border: 1px solid #ffd0d0;
}

.alert-success {
    background: #eefaf1;
    color: #1f7a3a;
    border: 1px solid #c8edcf;
}

/* ── Tabs ── */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 0;
    border-bottom: 1.5px solid #ebebeb;
    padding-bottom: 0;
}

.tab {
    padding: 9px 22px;
    border-radius: 10px 10px 0 0;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    color: #666;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1.5px;
    transition: color .15s, border-color .15s;
}

.tab:hover { color: #222; }

.tab.active {
    color: var(--club);
    border-bottom-color: var(--club);
    font-weight: 600;
}

/* ── Painel tab ── */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Info layout ── */
.info-layout {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 32px;
}

.info-fields { flex: 1; }

.info-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f3f3;
    font-size: 14.5px;
}

.info-row:last-child { border-bottom: none; }

.info-label {
    font-weight: 600;
    color: #222;
    min-width: 160px;
    flex-shrink: 0;
}

.info-value {
    color: #444;
}

.info-value.empty {
    color: #bbb;
    font-style: italic;
}

/* ── Logo do clube ── */
.club-logo-wrap {
    flex-shrink: 0;
    width: 160px;
    height: 160px;
    background: #f7f7f7;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid #ebebeb;
}

.club-logo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
}

.club-logo-status {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 14px;
    color: #666;
    font-size: 13px;
    line-height: 1.3;
}

.club-logo-placeholder {
    font-size: 32px;
    font-weight: 700;
    color: var(--club);
    letter-spacing: -1px;
}

/* ── Estado vazio ── */
.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #bbb;
    font-size: 14px;
}

.empty-state svg {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: .3;
}

/* ── Header das abas Escalões/Treinadores ── */
.tab-action-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 22px;
}

.tab-action-row h3 {
    font-size: 18px;
    font-weight: 700;
    color: #222;
}

.btn-create {
    border: none;
    background: var(--club);
    color: #fff;
    padding: 11px 18px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
}

.btn-create:hover {
    opacity: .88;
    transform: translateY(-1px);
}

/* ── Tabelas ── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.data-table th {
    text-align: left;
    padding: 12px 14px;
    background: #f5f5f5;
    color: #333;
    font-weight: 700;
    border-bottom: 1px solid #e8e8e8;
}

.data-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #f0f0f0;
    color: #444;
}

.data-table tr:hover td {
    background: #fafafa;
}

.muted {
    color: #aaa;
    font-style: italic;
}

/* ══════════════════════════════════
   MODAIS
══════════════════════════════════ */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 300;
    padding: 24px;
}

.modal-backdrop.active {
    display: flex;
}

.modal {
    width: 100%;
    max-width: 620px;
    max-height: 90vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 24px 90px rgba(0,0,0,.28);
    padding: 28px;
}

.modal.large {
    max-width: 760px;
}

.presenca-legend {
    font-size: 12px;
    color: #6b7280;
    margin: 0 0 14px;
}

.presenca-lista {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 50vh;
    overflow-y: auto;
    margin-bottom: 18px;
}

.presenca-row {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 8px 16px;
    align-items: center;
    padding: 12px;
    border: 1px solid #e6ebf5;
    border-radius: 12px;
}

.presenca-row-nome {
    font-weight: 600;
    font-size: 14px;
    color: #222;
}

.presenca-row-opcoes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
}

.presenca-opcao {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #444;
    cursor: pointer;
}

.presenca-opcao-lesionado {
    color: #b45309;
    font-weight: 600;
}

.presenca-row-obs {
    grid-column: 1 / -1;
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #dfe3ea;
    border-radius: 8px;
    font-size: 13px;
}

@media (max-width: 620px) {
    .presenca-row { grid-template-columns: 1fr; }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #222;
}

.modal-close {
    border: none;
    background: #f3f3f3;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    line-height: 1;
}

.edit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 20px;
}

.edit-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.edit-group.full {
    grid-column: 1 / -1;
}

.edit-group label {
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.edit-group input,
.edit-group select {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 13px 16px;
    font-size: 14px;
    outline: none;
    background: #f8f8f8;
}

.edit-group input:focus,
.edit-group select:focus {
    border-color: var(--club);
    background: #fff;
}

.edit-color-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.edit-color-row input[type="color"] {
    width: 46px;
    height: 46px;
    padding: 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    cursor: pointer;
    background: none;
}

.edit-color-row input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}

.edit-color-row input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 7px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 26px;
}

.btn-cancel,
.btn-save {
    border: none;
    border-radius: 999px;
    padding: 13px 22px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel {
    background: #eee;
    color: #333;
}

.btn-save {
    background: var(--club);
    color: #fff;
}

.btn-remove {
    border: none;
    border-radius: 999px;
    padding: 13px 22px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    background: #c62828;
    color: #fff;
}

@media (max-width: 760px) {
    .edit-grid {
        grid-template-columns: 1fr;
    }

    .tab-action-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .info-layout {
        flex-direction: column-reverse;
    }

    .tabs-row {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .card-header-actions {
        justify-content: flex-start;
    }

    .messages-shell.visible {
        grid-template-columns: 1fr;
    }

    .messages-sidebar {
        border-right: none;
        border-bottom: 1px solid #e6eaf2;
        max-height: 260px;
    }

    .messages-list {
        max-height: none;
    }

    .calendar-body {
        grid-template-columns: 1fr;
    }

    .calendar-grid-wrap {
        border-right: none;
        border-bottom: 1px solid #e8edf5;
    }

    .calendar-events-panel {
        max-height: 260px;
    }
}

/* ── Novos ecrãs (escalões, competições) ── */
.screen-shell {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    padding: 28px 32px 36px;
    display: none;
}
.screen-shell.visible { display: block; }

/* ── Ecrã de Escalões ── */
.escaloes-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 22px;
}
.escaloes-team-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 22px;
}
.escalao-tab-btn {
    padding: 8px 18px;
    border: 1.5px solid #d9e0f0;
    border-radius: 999px;
    background: #f8f9fc;
    font-size: 13px;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    transition: background .14s, color .14s, border-color .14s;
}
.escalao-tab-btn:hover { background: #eef2ff; border-color: var(--club); color: var(--club); }
.escalao-tab-btn.active { background: var(--club); border-color: var(--club); color: #fff; }

.players-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
}
.player-card {
    background: #f8f9fc;
    border: 1.5px solid #e8edf5;
    border-radius: 16px;
    padding: 18px 14px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: box-shadow .15s, transform .15s, border-color .15s;
}
.player-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.10); transform: translateY(-2px); border-color: var(--club); }
.player-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #e4eaf5;
    border: 3px solid var(--club);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    font-weight: 800;
    color: var(--club);
    overflow: hidden;
    flex-shrink: 0;
}
.player-avatar img { width: 100%; height: 100%; object-fit: cover; }
.player-number {
    position: absolute;
    top: 4px;
    right: 4px;
    background: var(--club);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.player-card-wrap { position: relative; }
.player-name { font-size: 13px; font-weight: 700; color: #1f2b3d; text-align: center; line-height: 1.2; }
.player-pos { font-size: 11px; color: #6b7280; text-align: center; }

/* ── Ecrã de Competições ── */
.competicoes-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.competicao-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 18px;
}
.competicao-card {
    background: #fff;
    border: 1.5px solid #e0e7f2;
    border-radius: 20px;
    padding: 22px 18px 18px;
    cursor: pointer;
    transition: box-shadow .15s, transform .15s, border-color .15s;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
}
.competicao-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.10); transform: translateY(-3px); border-color: var(--club); }
.competicao-card-tipo {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: #eef2ff;
    color: var(--club);
    align-self: flex-start;
}
.competicao-card-nome { font-size: 16px; font-weight: 700; color: #1f2b3d; }
.competicao-card-equipa { font-size: 12px; color: #6b7280; }
.competicao-card-estado {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    align-self: flex-start;
}
.estado-decorrer { background: #dcfce7; color: #15803d; }
.estado-finalizada { background: #f3f4f6; color: #6b7280; }
.estado-suspensa { background: #fef9c3; color: #a16207; }
.competicao-card-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity .15s;
}
.competicao-card:hover .competicao-card-actions { opacity: 1; }

/* ── Vista jogos de competição ── */
.jogos-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.btn-back {
    border: none;
    background: #f0f2f7;
    border-radius: 999px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: #333;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background .14s;
}
.btn-back:hover { background: #e4e8f0; }
.jogo-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #e8edf5;
    background: #fff;
    margin-bottom: 10px;
    flex-wrap: wrap;
    transition: box-shadow .14s;
}
.jogo-row:hover { box-shadow: 0 4px 14px rgba(0,0,0,.07); }
.jogo-data { font-size: 12px; color: #6b7280; min-width: 90px; }
.jogo-equipa { font-size: 14px; font-weight: 600; flex: 1; }
.jogo-resultado {
    font-size: 18px;
    font-weight: 800;
    color: var(--club);
    min-width: 60px;
    text-align: center;
}
.jogo-estado {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
}
.jogo-estado-Agendado { background: #dbeafe; color: #1d4ed8; }
.jogo-estado-Realizado { background: #dcfce7; color: #15803d; }
.jogo-estado-Cancelado { background: #fee2e2; color: #dc2626; }
.jogo-estado-Adiado { background: #fef9c3; color: #a16207; }
.jogo-casa-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    background: #f0f2f7;
    color: #555;
}

/* ── Modal tabs (para perfil jogador) ── */
.modal-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    border-bottom: 2px solid #f0f2f7;
}
.modal-tab-btn {
    padding: 10px 18px;
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 600;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    transition: color .14s, border-color .14s;
}
.modal-tab-btn.active { color: var(--club); border-bottom-color: var(--club); }
.modal-tab-panel { display: none; }
.modal-tab-panel.active { display: block; }

.info-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 18px;
    font-size: 13px;
}
.info-grid-2 .lbl { font-weight: 700; color: #374151; }
.info-grid-2 .val { color: #6b7280; }

.historial-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.historial-table th { background: #f5f7fb; padding: 8px 12px; text-align: left; font-weight: 700; color: #374151; border-bottom: 1px solid #e8edf5; }
.historial-table td { padding: 8px 12px; border-bottom: 1px solid #f0f3f9; color: #555; }


/* ══════════════════════════════════
   ECRÃS TREINADOR: TREINOS / JOGOS / CAMPEONATO
══════════════════════════════════ */
.trainer-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.trainer-page-title {
    font-size: 22px;
    font-weight: 800;
    color: #1f2b3d;
    margin-bottom: 5px;
}

.trainer-page-subtitle {
    font-size: 13px;
    color: #6b7280;
}

.trainer-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.trainer-kpi-card {
    background: #f8f9fc;
    border: 1px solid #e6ebf5;
    border-radius: 18px;
    padding: 18px;
}

.trainer-kpi-label {
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 8px;
}

.trainer-kpi-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--club);
}

.trainer-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

.trainer-info-card {
    background: #fff;
    border: 1.5px solid #e2e8f4;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
    transition: transform .14s ease, box-shadow .14s ease, border-color .14s ease;
}

.trainer-info-card:hover {
    transform: translateY(-2px);
    border-color: var(--club);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
}

.trainer-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.trainer-card-title {
    font-size: 16px;
    font-weight: 800;
    color: #1f2b3d;
    line-height: 1.2;
}

.trainer-card-meta {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.trainer-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #eef2ff;
    color: var(--club);
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.trainer-card-body {
    color: #4b5563;
    font-size: 13px;
    line-height: 1.45;
    white-space: pre-wrap;
}

.trainer-card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}

.trainer-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.trainer-game-row {
    display: grid;
    grid-template-columns: 120px 1fr auto auto auto;
    align-items: center;
    gap: 14px;
    border: 1.5px solid #e2e8f4;
    border-radius: 16px;
    padding: 14px 16px;
    background: #fff;
}

.game-detail-form { max-width: 1240px; padding-bottom: 30px; }
.game-detail-title { color: #172033; margin: 0 0 18px; padding: 22px 24px; border-radius: 8px; background: linear-gradient(135deg, #12233c, #1c5a52); color: #fff; font-size: 22px; }
.game-detail-title small { color: #c7e9df; font-size: 13px; font-weight: 600; margin-left: 10px; }
.game-detail-section { background: #fff; border: 1px solid #dce6ed; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 4px 16px rgba(28, 53, 81, .06); }
.game-detail-section h4 { margin: 0 0 16px; color: #172033; font-size: 16px; }
.game-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
.game-checkboxes { display: flex; flex-wrap: wrap; gap: 12px 20px; }
.game-checkboxes label { font-size: 13px; color: #334155; }
.convocatoria-head, .convocado-row { display: grid; grid-template-columns: 38px minmax(110px, 1fr) 82px 130px; gap: 10px; align-items: center; }
.convocatoria-head { padding: 0 12px 8px; color: #748093; font-size: 10px; text-transform: uppercase; font-weight: 800; letter-spacing: .06em; }
.convocatoria-head span:first-child { grid-column: span 2; }
.convocatoria-lista { display: grid; grid-template-columns: repeat(2, minmax(270px, 1fr)); gap: 6px; }
.convocado-row { min-height: 50px; padding: 6px 10px; border: 1px solid #e3ebef; border-radius: 6px; background: #fbfdfd; }
.convocado-row:focus-within { border-color: var(--club); background: color-mix(in srgb, var(--club) 8%, #fff); }
.convocado-row.nao-convocado { opacity: .58; }
.convocado-row select:disabled { cursor: not-allowed; background: #edf1f3; color: #8793a1; }
.convocado-number { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: var(--club); color: #fff; font-size: 11px; font-weight: 800; }
.convocado-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #1f2d3d; font-size: 13px; font-weight: 800; }
.convocado-row select { height: 32px; border: 1px solid #dbe5ea; border-radius: 5px; background: #fff; padding: 0 6px; font-size: 12px; color: #364152; }
.convocado-toggle { display: inline-flex; cursor: pointer; }
.convocado-toggle input { position: absolute; opacity: 0; }
.convocado-toggle span { width: 34px; height: 19px; border-radius: 10px; background: #cbd5df; position: relative; transition: .18s; }
.convocado-toggle span::after { content: ''; position: absolute; width: 15px; height: 15px; left: 2px; top: 2px; border-radius: 50%; background: #fff; transition: .18s; }
.convocado-toggle input:checked + span { background: var(--club); }
.convocado-toggle input:checked + span::after { transform: translateX(15px); }
.game-events-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
.game-add-event { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid var(--club); border-radius: 6px; background: #fff; color: var(--club); font-size: 13px; font-weight: 800; cursor: pointer; }
.game-add-event:hover { background: var(--club); color: #fff; }
.game-add-event-icon { display: grid; place-items: center; width: 19px; height: 19px; border-radius: 50%; background: var(--club); color: #fff; font-size: 17px; line-height: 1; }
.game-add-event:hover .game-add-event-icon { background: #fff; color: var(--club); }
.game-detail-link { display: inline-flex; align-items: center; justify-content: center; min-height: 34px; padding: 0 12px; border: 1px solid var(--club); border-radius: 6px; background: #fff; color: var(--club); font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; }
.game-detail-link:hover { background: var(--club); color: #fff; }
.game-report-button { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid var(--club); border-radius: 6px; background: var(--club); color: #fff; font-size: 13px; font-weight: 800; cursor: pointer; }
.game-report-button:hover { filter: brightness(.88); }
.game-event-row { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)) 34px; gap: 10px; align-items: end; padding: 12px; border: 1px solid #dfe9ee; border-left: 4px solid var(--club); border-radius: 6px; background: #f9fcfc; }
.game-event-row.golo { grid-template-columns: repeat(5, minmax(120px, 1fr)) 34px; border-left-color: var(--club); }
.game-event-row select, .game-event-row input { width: 100%; }
.game-event-row label { display: grid; gap: 5px; color: #6b7787; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.game-event-row label select, .game-event-row label input { box-sizing: border-box; height: 34px; border: 1px solid #d6e1e8; border-radius: 5px; background: #fff; color: #263548; font-size: 12px; font-weight: 600; padding: 0 8px; text-transform: none; letter-spacing: 0; }
.game-event-row .btn-row-delete { width: 34px; height: 34px; padding: 0; }
.match-phase-bar { display: flex; align-items: center; gap: 14px; background: #f2f8f7; padding: 14px; border: 1px solid #c9e2dd; border-radius: 8px; margin-top: 28px; }
.match-phase-bar strong { color: #172033; }
.match-phase-button { border: 0; border-radius: 6px; background: var(--club); color: #fff; padding: 10px 16px; font-weight: 800; cursor: pointer; }
.match-phase-button.running { background: #c2410c; }
.posse-inicial { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; color: #334155; font-size: 13px; font-weight: 700; }
.posse-inicial button { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1f2b3d; padding: 9px 12px; cursor: pointer; font: inherit; font-weight: 700; }
.posse-inicial button.active { background: var(--club); color: #fff; border-color: var(--club); }
.posse-inicial.obrigatorio { color: #b91c1c; }
.posse-inicial.obrigatorio button { border-color: #ef4444; }
.posse-inicial.posse-bloqueada { opacity: .65; }
.posse-inicial.posse-bloqueada button { cursor: not-allowed; }
.convocatoria-bloqueada { opacity: .72; }
.convocatoria-bloqueada .convocado-row { cursor: not-allowed; }
.tactic-layout { display: grid; grid-template-columns: minmax(300px, .9fr) minmax(300px, 1.1fr); gap: 20px; align-items: start; }
.tactic-pitch { position: relative; min-height: 570px; border: 5px solid #d7f0d5; border-radius: 8px; overflow: hidden; background-color: #2d8149; background-image: linear-gradient(90deg, rgba(255,255,255,.07) 50%, transparent 50%); background-size: 84px 100%; box-shadow: inset 0 0 0 2px rgba(255,255,255,.55); }
.tactic-pitch::before { content: ''; position: absolute; inset: 50% 0 auto; border-top: 2px solid rgba(255,255,255,.8); }
.tactic-pitch::after { content: ''; position: absolute; width: 90px; height: 90px; left: calc(50% - 45px); top: calc(50% - 45px); border: 2px solid rgba(255,255,255,.8); border-radius: 50%; }
.tactic-slot { position: absolute; transform: translate(-50%, -50%); width: 94px; z-index: 1; }
.tactic-slot label { display: block; color: #fff; text-align: center; font-size: 10px; font-weight: 800; margin-bottom: 4px; text-shadow: 0 1px 2px #123; }
.tactic-slot select { width: 100%; border: 0; border-radius: 6px; padding: 7px 4px; font-size: 10px; box-shadow: 0 2px 5px rgba(0,0,0,.25); }
.onze-inicial-resumo { display: flex; flex-direction: column; gap: 4px; margin-top: 14px; padding: 12px 14px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
.onze-inicial-resumo:empty::before { content: 'Escolhe os jogadores no relvado para veres aqui o onze inicial.'; color: #94a3b8; font-size: 12px; font-style: italic; }
.onze-inicial-resumo div { display: flex; align-items: baseline; gap: 8px; font-size: 13px; }
.onze-inicial-resumo b { min-width: 52px; color: var(--club); font-weight: 800; }
.onze-inicial-resumo span { color: #334155; }
.onze-inicial-resumo span.vazio { color: #94a3b8; font-style: italic; }
.posse-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px; background: #f7fafc; border-radius: 8px; }
.posse-controls button { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1f2b3d; padding: 9px 12px; cursor: pointer; font-weight: 700; }
.posse-controls button.active { background: var(--club); color: #fff; border-color: var(--club); }
.posse-controls strong { margin-left: 8px; color: #172033; font-variant-numeric: tabular-nums; }
.game-clock-controls { display: flex; align-items: center; gap: 8px; padding-left: 14px; margin-left: 6px; border-left: 1.5px solid #dde3ee; font-size: 13px; font-weight: 700; color: #334155; }
.game-clock-controls strong { color: #172033; font-variant-numeric: tabular-nums; }
.game-clock-controls button { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1f2b3d; padding: 9px 12px; cursor: pointer; font: inherit; font-weight: 700; }
.game-clock-controls button:disabled { opacity: .5; cursor: not-allowed; }
.game-clock-controls button.pausado { background: #b91c1c; color: #fff; border-color: #b91c1c; }
@media (max-width: 760px) { .game-clock-controls { padding-left: 0; margin-left: 0; border-left: 0; border-top: 1px solid #dde3ee; padding-top: 10px; margin-top: 4px; width: 100%; } }
.posse-percent { margin-left: auto; display: flex; gap: 12px; font-weight: 800; color: #172033; }
.stat-counter { display: grid; grid-template-columns: 34px 1fr 34px; border: 1px solid #dbe4ec; border-radius: 6px; overflow: hidden; background: #fff; }
.stat-counter button { border: 0; background: color-mix(in srgb, var(--club) 10%, #fff); color: var(--club); font-weight: 900; font-size: 18px; cursor: pointer; }
.stat-counter input { border: 0; text-align: center; min-width: 0; font-weight: 800; color: #172033; }
.game-stats-scroll { overflow-x: auto; }
.game-stats-scroll table { border-collapse: collapse; width: 100%; min-width: 1120px; font-size: 12px; }
.game-stats-scroll th, .game-stats-scroll td { border-bottom: 1px solid #e2e8f4; padding: 7px; text-align: left; }
.game-stats-scroll th { color: #475569; white-space: nowrap; }
.game-stats-scroll td .stat-counter { width: 100px; }
@media (max-width: 760px) { .tactic-layout { grid-template-columns: 1fr; } .tactic-pitch { min-height: 520px; } .convocatoria-lista { grid-template-columns: 1fr; } .game-event-row, .game-event-row.golo { grid-template-columns: 1fr 1fr; } .posse-percent { width: 100%; margin-left: 0; } }

.trainer-game-date {
    font-size: 12px;
    color: #6b7280;
    font-weight: 700;
}

.trainer-game-main {
    min-width: 0;
}

.trainer-game-title {
    font-size: 15px;
    font-weight: 800;
    color: #1f2b3d;
}

.trainer-game-subtitle {
    font-size: 12px;
    color: #6b7280;
    margin-top: 3px;
}

.trainer-result {
    font-size: 20px;
    font-weight: 900;
    color: var(--club);
    min-width: 72px;
    text-align: center;
}

.trainer-standings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.standings-card {
    border: 1.5px solid #e2e8f4;
    border-radius: 18px;
    background: #fff;
    overflow: hidden;
}

.standings-card-header {
    background: #f8f9fc;
    padding: 16px 18px;
    border-bottom: 1px solid #e8edf5;
}

.standings-card-title {
    font-size: 16px;
    font-weight: 800;
    color: #1f2b3d;
}

.standings-card-subtitle {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.standings-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.standings-table th,
.standings-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f3f9;
    text-align: center;
}

.standings-table th:first-child,
.standings-table td:first-child {
    text-align: left;
}

.standings-table th {
    color: #6b7280;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.stats-overview-section { margin-top: 32px; }
.stats-overview-title { font-size: 16px; font-weight: 800; color: #1f2b3d; margin-bottom: 4px; }
.stats-overview-section .trainer-page-subtitle { margin-bottom: 14px; }
.stats-overview-section .standings-card { overflow-x: auto; }
.standings-points {
    font-size: 22px;
    font-weight: 900;
    color: var(--club);
}

@media (max-width: 900px) {
    .trainer-game-row {
        grid-template-columns: 1fr;
        align-items: flex-start;
    }

    .trainer-result {
        text-align: left;
    }
}


/* ══════════════════════════════════
   PLANO VISUAL DE TREINO
══════════════════════════════════ */
.trainer-header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.btn-plan{border:1.5px solid var(--club);background:#fff;color:var(--club);padding:11px 18px;border-radius:999px;font-size:14px;font-weight:800;cursor:pointer;transition:transform .15s,background .15s,color .15s}.btn-plan:hover{background:var(--club);color:#fff;transform:translateY(-1px)}.plan-badge{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:6px 10px;border-radius:999px;background:#eefaf1;color:#15803d;font-size:11px;font-weight:800}.plan-card-buttons{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-right:auto}.plan-thumb-mini{height:96px;border:1px solid #e6ebf5;background:#fff;border-radius:12px;margin:12px 0 0;overflow:hidden}.plan-thumb-mini canvas{width:100%;height:100%;display:block}.plano-modal{max-width:min(96vw,1320px);width:96vw;height:92vh;max-height:92vh;padding:0;display:flex;flex-direction:column;overflow:hidden}.plano-modal .modal-header{padding:18px 22px;margin-bottom:0;border-bottom:1px solid #e8edf5}.plano-builder-layout{flex:1;min-height:0;display:grid;grid-template-columns:270px minmax(560px,1fr) 330px;background:#f6f8fc}.plano-meta-panel,.plano-text-panel{padding:18px;overflow-y:auto;background:#fff}.plano-meta-panel{border-right:1px solid #e8edf5}.plano-text-panel{border-left:1px solid #e8edf5}.plano-panel-title{font-size:13px;font-weight:900;color:#1f2b3d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}.plano-exercise-list{display:flex;flex-direction:column;gap:8px;margin:12px 0 14px}.plano-exercise-tab{border:1px solid #dfe6f2;background:#f8fafc;border-radius:14px;padding:10px 12px;text-align:left;font-size:13px;font-weight:800;color:#334155;cursor:pointer}.plano-exercise-tab.active{background:var(--club);border-color:var(--club);color:#fff}.plano-canvas-panel{min-width:0;display:flex;flex-direction:column;gap:10px;padding:14px;overflow:hidden}.plano-template-strip{display:flex;align-items:center;justify-content:center;gap:10px;padding:3px 0 6px;overflow-x:auto}.template-btn{width:76px;height:54px;border:2px solid transparent;background:#fff;border-radius:4px;box-shadow:0 1px 5px rgba(15,23,42,.08);cursor:pointer;position:relative}.template-btn.active{border-color:var(--club)}.template-btn::before{content:'';position:absolute;inset:9px 11px;border:1.5px solid #a8b1c2}.template-btn.template-meio::before{inset:9px 18px 9px 11px}.template-btn.template-area::before{inset:7px 13px 20px 13px}.template-btn.template-futsal::before{border-radius:9px}.plano-workspace{flex:1;min-height:0;display:grid;grid-template-columns:58px minmax(0,1fr) 64px;align-items:center;gap:10px}.plano-tool-column,.plano-shape-column{align-self:center;display:flex;flex-direction:column;gap:7px;background:#dfe6ee;border-radius:10px;padding:8px 7px;box-shadow:0 10px 22px rgba(15,23,42,.14)}.plano-tool-btn{width:40px;height:38px;border:1px solid transparent;background:transparent;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;color:#0f172a;transition:background .12s,border-color .12s}.plano-tool-btn:hover,.plano-tool-btn.active{background:#fff;border-color:rgba(15,23,42,.12)}.plano-player-dot{width:28px;height:28px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900}.plano-canvas-wrap{width:100%;height:min(58vh,610px);min-height:410px;background:#fff;border-radius:10px;border:1px solid #d8dee9;overflow:hidden;position:relative}#planoCanvas{width:100%;height:100%;display:block;cursor:crosshair}.plano-bottom-toolbar{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:8px;padding:8px 4px 0}.plano-color-palette{display:grid;grid-template-columns:repeat(5,30px);gap:6px;padding:8px;background:#fff;border:1px solid #dfe6f2;border-radius:14px;box-shadow:0 10px 22px rgba(15,23,42,.12)}.plano-color-choice{width:30px;height:30px;border:none;border-radius:6px;cursor:pointer}.plano-color-choice.active{outline:3px solid #111827;outline-offset:2px}.plano-text-panel textarea{width:100%;min-height:150px;resize:vertical;border:1px solid #d9e0f0;border-radius:16px;padding:12px 14px;font-size:14px;line-height:1.45;font-family:inherit;background:#f8fafc;outline:none}.plano-text-panel textarea:focus{border-color:var(--club);background:#fff}.plano-modal-actions{padding:14px 20px;background:#fff;border-top:1px solid #e8edf5;display:flex;justify-content:space-between;gap:12px;align-items:center}.plano-progress-text{font-size:13px;font-weight:800;color:#6b7280}.plano-view-modal{max-width:min(96vw,1040px)}.plano-view-header{border-bottom:3px solid #111;padding-bottom:10px;margin-bottom:18px}.plano-view-title{font-size:22px;font-weight:900;color:#0f172a}.plano-view-meta{margin-top:5px;font-size:13px;color:#64748b}.plano-exercicio-print{border-top:3px solid #111;padding-top:12px;margin-top:22px;display:grid;grid-template-columns:48% 52%;gap:0;min-height:300px}.plano-exercicio-visual{padding:14px 18px 14px 0;border-right:1px solid #cbd5e1}.plano-exercicio-desc{padding:14px 0 14px 18px}.plano-print-label{font-size:13px;font-weight:900;color:#0f172a;padding-bottom:8px;border-bottom:1px solid #d4d4d8;margin-bottom:10px}.plano-view-canvas{width:100%;height:230px;border:1px solid #e2e8f0;background:#fff}.plano-desc-block{min-height:95px;font-size:14px;line-height:1.45;color:#111827;white-space:pre-wrap;padding-bottom:12px}.plano-objetivo-block{font-size:14px;line-height:1.45;color:#111827;white-space:pre-wrap;padding-top:4px}@media(max-width:1050px){.plano-builder-layout{grid-template-columns:1fr;overflow-y:auto}.plano-canvas-panel{min-height:560px}.plano-workspace{grid-template-columns:1fr}.plano-tool-column,.plano-shape-column{flex-direction:row;flex-wrap:wrap;justify-content:center}.plano-exercicio-print{grid-template-columns:1fr}.plano-exercicio-visual{border-right:none;padding-right:0}.plano-exercicio-desc{padding-left:0}}



/* ══════════════════════════════════
   PATCH V2 — Editor visual de plano
══════════════════════════════════ */
.plano-modal{height:94vh;max-height:94vh;max-width:min(98vw,1460px);width:98vw}.plano-builder-layout{grid-template-columns:265px minmax(660px,1fr) 360px;height:calc(94vh - 142px)}.plano-canvas-panel{background:#f3f6fb;padding:14px 16px 10px;overflow:auto}.plano-workspace{grid-template-columns:58px minmax(640px,1fr)64px;align-items:center}.plano-canvas-wrap{height:clamp(330px,44vh,500px);min-height:330px;box-shadow:0 14px 30px rgba(15,23,42,.08)}#planoCanvas{background:#fff;touch-action:none}.plano-bottom-toolbar{position:sticky;bottom:0;z-index:6;background:rgba(243,246,251,.96);backdrop-filter:blur(6px);border:1px solid #dfe7f2;border-radius:16px;padding:10px;margin-top:4px;box-shadow:0 -8px 24px rgba(15,23,42,.08)}.plano-color-palette{display:flex!important;align-items:center;justify-content:center;flex-wrap:wrap;gap:7px;max-width:690px;padding:8px 10px;border-radius:14px;background:#fff}.plano-color-choice{width:28px;height:28px;border:1px solid rgba(15,23,42,.12);border-radius:5px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.22)}.plano-color-choice.active{outline:3px solid #0f172a;outline-offset:2px}.plano-tool-column,.plano-shape-column{background:#dfe8f1;border:1px solid #cbd7e5}.plano-tool-btn{position:relative}.plano-tool-btn.active::after{content:'';position:absolute;inset:3px;border:2px solid var(--club);border-radius:7px;pointer-events:none}.plano-tool-btn svg{width:24px;height:24px;display:block}.plano-tool-hint{font-size:11px;color:#64748b;text-align:center;line-height:1.25;margin-top:4px}.plano-selected-panel{margin-top:18px;border:1px solid #dbe4f0;background:#f8fafc;border-radius:18px;padding:14px;display:none}.plano-selected-panel.visible{display:block}.plano-selected-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px}.plano-selected-head strong{font-size:13px;color:#172033}.plano-selected-head span{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase}.plano-prop-row{display:flex;flex-direction:column;gap:6px;margin-top:10px}.plano-prop-row label{font-size:11px;font-weight:900;text-transform:uppercase;color:#64748b;letter-spacing:.04em}.plano-prop-row input[type="range"]{width:100%;accent-color:var(--club)}.plano-mini-actions{display:flex;gap:8px;margin-top:12px}.plano-mini-actions button{flex:1;border:none;border-radius:12px;padding:9px 10px;font-size:12px;font-weight:800;cursor:pointer}.plano-mini-actions .soft{background:#e9eef7;color:#182033}.plano-mini-actions .danger{background:#fee2e2;color:#b42318}.plano-view-canvas,.plan-thumb-mini canvas{image-rendering:auto}.plano-exercicio-print{break-inside:avoid}.plano-print-note{font-size:11px;color:#64748b;margin-top:8px}.plano-floating-tip{font-size:12px;color:#64748b;text-align:center;margin-top:2px}.template-btn{height:58px}.template-btn::after{content:'';position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:30px;height:18px;border:1px solid #b6c0d1}.template-btn.active{box-shadow:0 0 0 2px var(--club),0 8px 18px rgba(15,23,42,.12)}@media(max-width:1120px){.plano-builder-layout{grid-template-columns:1fr}.plano-meta-panel,.plano-text-panel{max-height:none}.plano-workspace{grid-template-columns:1fr}.plano-canvas-wrap{min-height:390px}.plano-tool-column,.plano-shape-column{flex-direction:row;flex-wrap:wrap;justify-content:center}.plano-bottom-toolbar{position:relative}.plano-canvas-panel{min-height:620px}}



/* ══════════════════════════════════
   PLANO VISUAL V3 — FLYOUTS / UX
══════════════════════════════════ */
.plano-tool-flyout {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.plano-flyout-menu {
    position: absolute;
    z-index: 50;
    display: none;
    gap: 7px;
    padding: 8px;
    background: #fff;
    border: 1px solid #dfe6f2;
    border-radius: 14px;
    box-shadow: 0 14px 34px rgba(15,23,42,.22);
}

.plano-tool-flyout:hover .plano-flyout-menu,
.plano-tool-flyout:focus-within .plano-flyout-menu {
    display: flex;
}

.plano-line-menu {
    right: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    flex-direction: column;
}

.plano-color-menu {
    left: 50%;
    bottom: calc(100% + 10px);
    transform: translateX(-50%);
    width: 244px;
    grid-template-columns: repeat(6, 30px);
    justify-content: center;
}

.plano-tool-flyout:hover .plano-color-menu,
.plano-tool-flyout:focus-within .plano-color-menu {
    display: grid;
}

.plano-flyout-label {
    grid-column: 1 / -1;
    font-size: 11px;
    font-weight: 900;
    color: #475569;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 2px 2px 4px;
}

.plano-color-main {
    gap: 7px;
    min-width: 108px;
    font-size: 13px;
}

.plano-color-swatch-main {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(15,23,42,.25);
    background: #000;
    display: inline-block;
}

.plano-line-option.active,
.plano-color-choice.active {
    background: #eff6ff;
    border-color: var(--club);
}

.plano-line-option {
    width: 42px;
    height: 40px;
    border: 1px solid transparent;
    background: #fff;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
}

.plano-line-option:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.plano-line-option svg,
#planoLineMainBtn svg {
    width: 24px;
    height: 24px;
}

.plano-color-palette {
    padding: 8px;
}

.plano-bottom-toolbar .btn-cancel {
    height: 38px;
    padding: 0 16px;
}

.plano-tool-btn svg {
    width: 28px;
    height: 28px;
}

.plano-selected-panel.visible {
    display: block;
}



/* ══════════════════════════════════
   PLANO VISUAL V4 — paleta fechada + resize direto no canvas
══════════════════════════════════ */
.plano-bottom-toolbar {
    min-height: 56px;
    padding: 9px 12px;
}

.plano-flyout-menu.plano-color-menu.plano-color-palette {
    display: none !important;
    position: absolute;
    left: 50%;
    bottom: calc(100% + 12px);
    transform: translateX(-50%);
    z-index: 120;
    width: 244px;
}

.plano-color-flyout:hover .plano-color-menu,
.plano-color-flyout:focus-within .plano-color-menu {
    display: grid !important;
}

.plano-line-menu {
    display: none !important;
}

.plano-tool-flyout:hover .plano-line-menu,
.plano-tool-flyout:focus-within .plano-line-menu {
    display: flex !important;
}

.plano-color-main {
    width: auto;
    padding: 0 12px;
    background: #fff;
    border-color: #d8e1ee;
    box-shadow: 0 4px 12px rgba(15,23,42,.08);
}

.plano-color-flyout:not(:hover):not(:focus-within) .plano-color-menu {
    pointer-events: none;
}

.plano-resize-help {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    background: #eef4fb;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
}


/* ══════════════════════════════════
   PLANO VISUAL V5 — equipamento agrupado + ícones vetoriais
══════════════════════════════════ */
.plano-equipment-flyout {
    position: relative;
}

.plano-equipment-menu {
    display: none !important;
    position: absolute;
    left: calc(100% + 10px);
    top: 0;
    z-index: 140;
    min-width: 52px;
    padding: 7px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #d9e3f0;
    box-shadow: 0 14px 32px rgba(15, 23, 42, .18);
    flex-direction: column;
    gap: 6px;
}

.plano-equipment-flyout:hover .plano-equipment-menu,
.plano-equipment-flyout:focus-within .plano-equipment-menu {
    display: flex !important;
}

.plano-equipment-option {
    width: 40px;
    height: 38px;
    border: 1px solid transparent;
    background: #f8fafc;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
}

.plano-equipment-option:hover,
.plano-equipment-option.active {
    background: #fff;
    border-color: var(--club);
    box-shadow: inset 0 0 0 1px var(--club);
}

.plano-equipment-option svg,
.plano-equipment-main svg {
    width: 27px;
    height: 27px;
    display: block;
}

.plano-equipment-main.active::after {
    content: '';
    position: absolute;
    inset: 3px;
    border: 2px solid var(--club);
    border-radius: 7px;
    pointer-events: none;
}

.plano-equipment-menu::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 0;
    width: 10px;
    height: 100%;
}

.plano-color-flyout .plano-color-menu {
    display: none !important;
}

.plano-color-flyout:hover .plano-color-menu,
.plano-color-flyout:focus-within .plano-color-menu {
    display: grid !important;
}

.plano-color-swatch-main {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(15, 23, 42, .35);
    display: inline-block;
    margin-right: 6px;
    vertical-align: -2px;
}



/* ══════════════════════════════════
   PLANO VISUAL V6 — templates limpos, palete perto, rotação
══════════════════════════════════ */
.plano-template-strip .template-btn[data-template="meio_campo"] {
    display: none !important;
}

.plano-color-flyout {
    position: relative;
}

.plano-color-flyout::before {
    content: '';
    position: absolute;
    left: -16px;
    right: -16px;
    bottom: 100%;
    height: 14px;
    z-index: 119;
}

.plano-flyout-menu.plano-color-menu.plano-color-palette {
    bottom: calc(100% + 2px) !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    z-index: 240 !important;
}

.plano-color-flyout:hover .plano-color-menu,
.plano-color-flyout:focus-within .plano-color-menu,
.plano-color-menu:hover {
    display: grid !important;
    pointer-events: auto !important;
}

.plano-rotate-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 8px;
}

.plano-rotate-actions button {
    border: none;
    border-radius: 12px;
    background: #e9eef7;
    color: #182033;
    padding: 8px 10px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.plano-rotate-actions button:hover {
    background: #dbe5f3;
}

/* Miniaturas reais dos templates do plano de treino */
.template-btn.has-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}

.template-btn.has-preview::before {
    display: none;
}

.template-field-preview {
    width: 58px;
    height: 36px;
    display: block;
}

.template-btn.has-preview.active .template-field-preview,
.template-btn.has-preview:hover .template-field-preview {
    color: var(--club);
}

</style>

<!-- Bibliotecas para gerar o PDF do plano de treino no browser -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>
<body>

<!-- ══ TOP BAR ══ -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-club-logo">
            <?php if ($logoClube): ?>
                <img src="<?= $logoClube ?>" alt="Logótipo do clube"
                     onerror="showTopbarLogoFallback(this);">
            <?php else: ?>
                <span class="topbar-club-logo--placeholder"><?= htmlspecialchars($siglaClube) ?></span>
            <?php endif; ?>
        </div>

        <div class="topbar-club-text">
            <span class="topbar-name"><?= htmlspecialchars($nomeClube) ?></span>
            <?php if ($siglaClube): ?>
                <span class="topbar-sigla"><?= htmlspecialchars($siglaClube) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-right">
        <img src="assets/kroos-logo-branco.png" class="topbar-logo" alt="Kroos">

        <div class="topbar-user-menu-wrap">
            <button class="topbar-menu" type="button" aria-label="Menu" onclick="toggleUserMenu(event)">
                <span></span><span></span><span></span>
            </button>
            <?php if ($notificacoesNaoLidas > 0): ?><b class="topbar-notification-badge"><?= $notificacoesNaoLidas ?></b><?php endif; ?>

            <div class="user-dropdown" id="userDropdown">
                <a href="#" onclick="event.preventDefault(); showProfileScreen(); toggleUserMenu(event);">
                    <span>Perfil</span>
                </a>

                <a href="#" onclick="event.preventDefault(); showNotificationsScreen(); toggleUserMenu(event);">
                    <span>Notificações</span>
                </a>

                <a href="logout.php" class="logout-link">
                    <span>Terminar sessão</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar" id="sidebar">
    <a href="#" data-view="treinos" class="<?= $activeSidebarView === 'treinos' ? 'active' : '' ?>" onclick="event.preventDefault(); showTreinosScreen();">
        <img src="assets/treinos.png" alt="">
        <span>Treinos</span>
    </a>
    <a href="#" data-view="jogos" class="<?= $activeSidebarView === 'jogos' ? 'active' : '' ?>" onclick="event.preventDefault(); showJogosScreen();">
        <img src="assets/jogos.png" alt="">
        <span>Jogos</span>
    </a>
    <a href="#" data-view="campeonato" class="<?= $activeSidebarView === 'campeonato' ? 'active' : '' ?>" onclick="event.preventDefault(); showCampeonatoScreen();">
        <img src="assets/campeonato.png" alt="">
        <span>Estatísticas</span>
    </a>
    <a href="#" data-view="calendario" class="<?= $activeSidebarView === 'calendario' ? 'active' : '' ?>" onclick="event.preventDefault(); showCalendarScreen();">
        <img src="assets/calendario.png" alt="">
        <span>Calendário</span>
    </a>
    <a href="index-treinador.php?view=mensagens" data-view="mensagens" class="<?= $activeSidebarView === 'mensagens' ? 'active' : '' ?>">
    <span class="sidebar-icon-wrap">
        <img src="assets/mensagens.png" alt="">
        <?php if ($mensagensNaoLidas > 0): ?>
            <b class="menu-count-badge"><?= $mensagensNaoLidas ?></b>
        <?php endif; ?>
    </span>
    <span>Mensagens</span>
</a>
    <a href="#" data-view="home" class="<?= $activeSidebarView === 'home' ? 'active' : '' ?>" onclick="event.preventDefault(); showMainMenu();">
        <img src="assets/home.png" alt="">
        <span>Página Principal</span>
    </a>
</div>

<!-- ══ MAIN ══ -->
<div class="main">
    <div class="card" id="dashboardCard">

        <div class="profile-shell" id="profileScreen" aria-label="Painel de perfil">
            <div class="profile-header">
                <div class="profile-title">Perfil</div>
            </div>

            <div class="profile-panel">
                <form id="profileForm" method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="editar_perfil">
                    <div class="profile-content">
                        <div class="profile-grid">
                            <div class="profile-field">
                                <label>Nome Utilizador</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['nome_utilizador'] ?? '') ?>" name="nome_utilizador">
                            </div>

                            <div class="profile-field">
                                <label>Email</label>
                                <input class="profile-input" type="email" value="<?= htmlspecialchars($perfilUtilizador['email_utilizador'] ?? '') ?>" name="email">
                            </div>

                            <div class="profile-field">
                                <label>Primeiro Nome</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['primeiro_nome'] ?? '') ?>" name="primeiro_nome">
                            </div>

                            <div class="profile-field">
                                <label>Nº de Telemóvel</label>
                                <input class="profile-input" type="tel" value="<?= htmlspecialchars($perfilUtilizador['telefone_utilizador'] ?? '') ?>" name="telemovel">
                            </div>

                            <div class="profile-field">
                                <label>Último Nome</label>
                                <input class="profile-input" type="text" value="<?= htmlspecialchars($perfilUtilizador['último_nome'] ?? '') ?>" name="ultimo_nome">
                            </div>

                            <div class="profile-field">
                                <label>Data de Nascimento</label>
                                <input class="profile-input" type="date" value="<?= htmlspecialchars($perfilUtilizador['data_nascimento'] ?? '') ?>" name="data_nascimento">
                            </div>
                        </div>

                        <div class="profile-avatar-wrap">
                            <div class="profile-avatar">
                                <?php if ($fotoPerfilUtilizador): ?>
                                    <img id="profileAvatarPreview" src="<?= $fotoPerfilUtilizador ?>" alt="Foto de perfil">
                                <?php else: ?>
                                    <span id="profileAvatarInitial"><?= htmlspecialchars(strtoupper(substr($perfilUtilizador['nome_utilizador'] ?? 'U', 0, 1))) ?></span>
                                    <img id="profileAvatarPreview" src="" alt="Foto de perfil" style="display:none;">
                                <?php endif; ?>
                            </div>
                            <input id="fotoPerfilInput" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <input id="fotoPerfilAjustadaInput" type="hidden" name="foto_perfil_ajustada" value="">
                            <button class="profile-avatar-button" id="btnEditarFotoPerfil" type="button">Editar Foto de Perfil</button>
                            <div class="profile-adjust-tools" id="profileAdjustTools" style="display:none;">
                                <label for="fotoPerfilZoom">Zoom</label>
                                <input id="fotoPerfilZoom" type="range" min="1" max="3" step="0.01" value="1">

                                <label for="fotoPerfilPosX">Horizontal</label>
                                <input id="fotoPerfilPosX" type="range" min="0" max="100" step="1" value="50">

                                <label for="fotoPerfilPosY">Vertical</label>
                                <input id="fotoPerfilPosY" type="range" min="0" max="100" step="1" value="50">
                            </div>
                            <small id="fotoPerfilHint" style="margin-top:8px; color:#6b7280; font-size:12px; text-align:center;">JPG, PNG ou WEBP (máx. 2MB)</small>
                            <small id="fotoPerfilErro" style="margin-top:6px; color:#b42318; font-size:12px; text-align:center; display:none;"></small>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button class="profile-save-button" type="submit" id="submitProfileBtn">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="notifications-shell" id="notificationsScreen" aria-label="Painel de notificações">
            <div class="notifications-card">
                <div class="notifications-tabs">
                    <button class="notification-tab active" type="button">Geral</button>
                    <button class="notification-tab" type="button">Lidas</button>
                    <button class="notification-tab" type="button">Por ler</button>
                </div>

                <div class="notifications-list" id="notificationsList">
                    <?php if (empty($notificacoesUtilizador)): ?>
                        <div class="notification-row read">
                            <span class="notification-label">Sem notificações</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notificacoesUtilizador as $notificacao): ?>
                            <?php $estadoNotificacao = $notificacao['estado'] ?? 'Nao Lida'; ?>
                            <?php $naoLida = ($estadoNotificacao === 'Nao Lida'); ?>
                            <div class="notification-row <?= $naoLida ? 'unread' : 'read' ?>"
                                 data-id="<?= (int)($notificacao['id_notificacao'] ?? 0) ?>"
                                 data-state="<?= htmlspecialchars($estadoNotificacao) ?>"
                                 tabindex="0"
                                 role="button"
                                 aria-label="<?= htmlspecialchars($notificacao['titulo'] ?? 'Notificação') ?>">
                                <div class="notification-content">
                                    <span class="notification-label"><?= htmlspecialchars($notificacao['titulo'] ?? 'Notificação') ?></span>
                                    <small class="notification-message"><?= htmlspecialchars($notificacao['mensagem'] ?? '') ?></small>
                                </div>
                                <span class="notification-check">✓</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="messages-shell <?= $mostrarMensagens ? 'visible' : '' ?>" id="messagesScreen" aria-label="Painel de mensagens">
            <aside class="messages-sidebar">
                <div class="messages-sidebar-header">Mensagens</div>
                <div class="messages-list">
                    <?php if (empty($utilizadoresMensagem)): ?>
                        <div class="messages-empty" style="min-height:120px;">Sem utilizadores disponíveis para conversar.</div>
                    <?php else: ?>
                        <?php foreach ($utilizadoresMensagem as $uMsg): ?>
                            <?php
                                $uId = (int)$uMsg['id_utilizador'];
                                $nomeU = trim(($uMsg['primeiro_nome'] ?? '') . ' ' . ($uMsg['último_nome'] ?? ''));
                                if ($nomeU === '') {
                                    $nomeU = $uMsg['nome_utilizador'];
                                }
                                $initialU = strtoupper(substr($nomeU, 0, 1));
                                $isAtivoChat = ($chatSelecionadoId === $uId);
                                $badgeNaoLidas = (int)($uMsg['nao_lidas'] ?? 0);
                            ?>
                            <a class="message-user <?= $isAtivoChat ? 'active' : '' ?>" href="index-treinador.php?view=mensagens&chat=<?= $uId ?>">
                                <div class="message-user-avatar">
                                    <?php if (!empty($uMsg['foto_base64'])): ?>
                                        <img src="<?= $uMsg['foto_base64'] ?>" alt="<?= htmlspecialchars($nomeU) ?>">
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($initialU ?: 'U') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-user-main">
                                    <span class="message-user-name"><?= htmlspecialchars($nomeU) ?></span>
                                    <span class="message-user-type"><?= htmlspecialchars(['jogador' => 'Jogador', 'treinador' => 'Treinador', 'admin_clube' => 'Admin Clube', 'admin' => 'Admin de Sistema'][$uMsg['tipo_utilizador']] ?? $uMsg['tipo_utilizador']) ?></span>
                                </div>
                                <?php if ($badgeNaoLidas > 0): ?>
                                    <span class="message-user-unread"><?= $badgeNaoLidas ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="messages-chat">
                <?php if ($chatSelecionado): ?>
                    <?php
                        $chatNome = trim(($chatSelecionado['primeiro_nome'] ?? '') . ' ' . ($chatSelecionado['último_nome'] ?? ''));
                        if ($chatNome === '') {
                            $chatNome = $chatSelecionado['nome_utilizador'];
                        }
                    ?>
                    <div class="messages-chat-header"><?= htmlspecialchars($chatNome) ?></div>

                    <div class="messages-thread" id="messagesThread">
                        <?php if (empty($mensagensConversa)): ?>
                            <div class="messages-empty">Sem mensagens ainda. Envia a primeira mensagem.</div>
                        <?php else: ?>
                            <?php foreach ($mensagensConversa as $msg): ?>
                                <?php
                                    $isOut = ((int)$msg['origem'] === (int)$id_utilizador);
                                    $enviadaEm = '';
                                    if (!empty($msg['enviada_em'])) {
                                        $ts = strtotime($msg['enviada_em']);
                                        $hoje = date('Y-m-d');
                                        $enviadaEm = (date('Y-m-d', $ts) === $hoje)
                                            ? date('H:i', $ts)
                                            : date('d/m/Y H:i', $ts);
                                    }
                                ?>
                                <div class="dm-bubble <?= $isOut ? 'out' : 'in' ?>">
                                    <?= nl2br(htmlspecialchars($msg['conteúdo'])) ?>
                                    <?php if ($enviadaEm): ?>
                                        <span class="dm-time"><?= $enviadaEm ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="messages-compose">
                        <form method="POST" action="index-treinador.php?view=mensagens&chat=<?= (int)$chatSelecionadoId ?>">
                            <input type="hidden" name="acao" value="enviar_mensagem">
                            <input type="hidden" name="destino_mensagem" value="<?= (int)$chatSelecionadoId ?>">
                            <textarea name="conteudo_mensagem" placeholder="Escreve uma mensagem..." required></textarea>
                            <button type="submit">Enviar</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="messages-empty">Seleciona uma conversa para começar.</div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ══ CALENDÁRIO ══ -->
        <div class="calendar-shell" id="calendarScreen" aria-label="Calendário">
            <div class="calendar-header">
                <div class="calendar-header-title">Calendário</div>
                <div class="calendar-nav">
                    <button class="calendar-nav-btn" type="button" onclick="calendarPrev()">&#8249;</button>
                    <span class="calendar-month-label" id="calendarMonthLabel"></span>
                    <button class="calendar-nav-btn" type="button" onclick="calendarNext()">&#8250;</button>
                    <?php if ($isAdminClube): ?>
                    <button class="btn-create" type="button" onclick="syncCalMonthFields(); openModal('modalCriarEvento')" style="margin-left:10px;padding:8px 14px;font-size:13px;">+ Evento</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="calendar-body">
                <div class="calendar-grid-wrap">
                    <div class="calendar-weekdays">
                        <div class="calendar-weekday">Dom</div>
                        <div class="calendar-weekday">Seg</div>
                        <div class="calendar-weekday">Ter</div>
                        <div class="calendar-weekday">Qua</div>
                        <div class="calendar-weekday">Qui</div>
                        <div class="calendar-weekday">Sex</div>
                        <div class="calendar-weekday">Sáb</div>
                    </div>
                    <div class="calendar-days" id="calendarDays"></div>
                </div>
                <div class="calendar-events-panel" id="calendarEventsPanel">
                    <div class="calendar-events-panel-title" id="calendarPanelTitle">Seleciona um dia</div>
                    <div id="calendarDayEvents"></div>
                </div>
            </div>
        </div>

        <!-- ══ ECRÃ TREINOS ══ -->
        <div class="screen-shell" id="treinosScreen">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Treinos</h2>
                    <p class="trainer-page-subtitle">Gerir os treinos das equipas que te foram atribuídas.</p>
                </div>
                <?php if (!empty($equipasTreinador)): ?>
                    <div class="trainer-header-actions">
                        <button class="btn-create" type="button" onclick="openModal('modalCriarTreino')">+ Criar Treino</button>
                        <button class="btn-plan" type="button" onclick="abrirCriadorPlanoTreino()">Fazer plano de treino</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="trainer-kpi-grid">
                <div class="trainer-kpi-card">
                    <div class="trainer-kpi-label">Equipas</div>
                    <div class="trainer-kpi-value"><?= count($equipasTreinador) ?></div>
                </div>
                <div class="trainer-kpi-card">
                    <div class="trainer-kpi-label">Treinos registados</div>
                    <div class="trainer-kpi-value"><?= count($treinosTreinador) ?></div>
                </div>
                <div class="trainer-kpi-card">
                    <div class="trainer-kpi-label">Próximo treino</div>
                    <div class="trainer-kpi-value" style="font-size:18px;">
                        <?php
                            $proximoTreinoTexto = '—';
                            foreach (array_reverse($treinosTreinador) as $trProx) {
                                if (($trProx['data_treino'] ?? '') >= date('Y-m-d')) {
                                    $proximoTreinoTexto = date('d/m', strtotime($trProx['data_treino'])) . ' ' . substr($trProx['hora_treino'], 0, 5);
                                    break;
                                }
                            }
                        ?>
                        <?= htmlspecialchars($proximoTreinoTexto) ?>
                    </div>
                </div>
            </div>

            <?php if (empty($equipasTreinador)): ?>
                <div class="empty-state"><p>Ainda não tens equipas associadas. O admin do clube tem de te associar a uma equipa.</p></div>
            <?php elseif (empty($treinosTreinador)): ?>
                <div class="empty-state"><p>Ainda não há treinos registados para as tuas equipas.</p></div>
            <?php else: ?>
                <div class="trainer-card-grid">
                    <?php foreach ($treinosTreinador as $treino): ?>
                        <div class="trainer-info-card">
                            <div class="trainer-card-top">
                                <div>
                                    <div class="trainer-card-title">Treino #<?= (int)$treino['numero_treino'] ?></div>
                                    <div class="trainer-card-meta">
                                        <?= htmlspecialchars($treino['escalão'] . ' ' . $treino['hierarquia']) ?>
                                        <?= !empty($treino['época']) ? ' · ' . htmlspecialchars($treino['época']) : '' ?>
                                    </div>
                                </div>
                                <span class="trainer-pill"><?= htmlspecialchars(date('d/m', strtotime($treino['data_treino'])) . ' · ' . substr($treino['hora_treino'], 0, 5)) ?></span>
                            </div>
                            <div class="trainer-card-meta" style="margin-bottom:8px;"><?= htmlspecialchars($treino['dia_da_semana']) ?></div>
                            <div class="trainer-card-body"><?= htmlspecialchars($treino['conteudo_treino']) ?></div>
                            <?php if (!empty($treino['observacoes_treino'])): ?>
                                <div class="trainer-card-body" style="margin-top:10px;color:#6b7280;"><strong>Obs.:</strong> <?= htmlspecialchars($treino['observacoes_treino']) ?></div>
                            <?php endif; ?>
                            <?php if ((int)($treino['total_exercicios'] ?? 0) > 0): ?>
                                <div class="plan-badge">Plano visual · <?= (int)$treino['total_exercicios'] ?> exercício<?= (int)$treino['total_exercicios'] === 1 ? '' : 's' ?></div>
                                <div class="plan-thumb-mini"><canvas data-plan-thumb="<?= (int)$treino['id_treino'] ?>"></canvas></div>
                            <?php else: ?>
                                <div class="plan-badge" style="background:#f8fafc;color:#64748b;">Sem plano visual</div>
                            <?php endif; ?>
                            <?php
                                $totalPresencasRegistadas = count($presencasPorTreino[(int)$treino['id_treino']] ?? []);
                            ?>
                            <div class="plan-badge" style="background:#eef2ff;color:#3730a3;">
                                <?= $totalPresencasRegistadas > 0 ? $totalPresencasRegistadas . ' presença(s) registada(s)' : 'Presenças por registar' ?>
                            </div>
                            <div class="trainer-card-actions">
                                <div class="plan-card-buttons">
                                    <?php if ((int)($treino['total_exercicios'] ?? 0) > 0): ?>
                                        <button class="btn-plan" type="button" onclick="abrirVisualizacaoPlanoTreino(<?= (int)$treino['id_treino'] ?>)">Ver plano</button>
                                        <button class="btn-plan" type="button" onclick="abrirEditorPlanoTreino(<?= (int)$treino['id_treino'] ?>)">Editar plano</button>
                                    <?php else: ?>
                                        <button class="btn-plan" type="button" onclick="abrirEditorPlanoTreino(<?= (int)$treino['id_treino'] ?>)">+ Plano visual</button>
                                    <?php endif; ?>
                                    <button class="btn-plan" type="button" onclick="abrirPresencasTreino(<?= (int)$treino['id_treino'] ?>)">Folha de presenças</button>
                                </div>
                                <button class="btn-row-edit" type="button" title="Editar treino" onclick="openEditTreinoModal(<?= (int)$treino['id_treino'] ?>)">✎</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remover este treino?');">
                                    <input type="hidden" name="acao" value="remover_treino">
                                    <input type="hidden" name="id_treino" value="<?= (int)$treino['id_treino'] ?>">
                                    <button class="btn-row-edit btn-row-delete" type="submit" title="Remover treino">×</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══ ECRÃ JOGOS ══ -->
        <div class="screen-shell" id="jogosScreen">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Jogos</h2>
                    <p class="trainer-page-subtitle">Jogos das competições associadas às tuas equipas.</p>
                </div>
            </div>

            <?php if ($jogoDetalhe): ?>
                <?php
                    $jogadoresDetalhe = $jogadoresPorEquipa[(int)$jogoDetalhe['id_equipa']] ?? [];
                    $idsConvocados = $participantesJogo['Convocado'];
                    $idsTitulares = $participantesJogo['Titular'];
                    $idsSuplentes = $participantesJogo['Suplente'];
                    $tecnicosPresentes = array_filter(explode("\n", $configuracaoJogo['presenca_equipa_tecnica'] ?? ''));
                    $camposColetivosView = ['remates_nos' => 'Remates nossos', 'remates_adversario' => 'Remates adversário', 'remates_baliza_nos' => 'Remates à baliza nossos', 'remates_baliza_adversario' => 'Remates à baliza adversário', 'dribles_tentados_nos' => 'Dribles tentados nossos', 'dribles_tentados_adversario' => 'Dribles tentados adversário', 'dribles_conseguidos_nos' => 'Dribles conseguidos nossos', 'dribles_conseguidos_adversario' => 'Dribles conseguidos adversário', 'defesas_nos' => 'Defesas nossas', 'defesas_adversario' => 'Defesas adversário', 'cantos_nos' => 'Cantos nossos', 'cantos_adversario' => 'Cantos adversário', 'passes_nos' => 'Passes nossos', 'passes_adversario' => 'Passes adversário', 'passes_certos_nos' => 'Passes certos nossos', 'passes_certos_adversario' => 'Passes certos adversário', 'faltas_nos' => 'Faltas nossas', 'faltas_adversario' => 'Faltas adversário'];
                    $camposIndividuaisView = ['minutos_jogados' => 'Min.', 'golos' => 'Golos', 'remates' => 'Rem.', 'remates_baliza' => 'R. baliza', 'assistencias' => 'Assist.', 'passes' => 'Passes', 'passes_certos' => 'P. certos', 'dribles_tentados' => 'Drib. tent.', 'dribles_conseguidos' => 'Drib. cons.', 'defesas' => 'Defesas', 'desarmes' => 'Desarmes', 'intercecoes' => 'Interceções', 'faltas_sofridas' => 'F. sofridas', 'faltas_cometidas' => 'F. cometidas'];
                ?>
                <a class="btn-cancel" style="display:inline-block;margin-bottom:18px;text-decoration:none;" href="index-treinador.php?view=jogos">Voltar aos jogos</a>
                <form method="POST" id="formDetalheJogo" class="game-detail-form">
                    <input type="hidden" name="acao" value="guardar_detalhe_jogo">
                    <input type="hidden" name="id_jogo" value="<?= (int)$jogoDetalhe['id_jogo'] ?>">
                    <input type="hidden" name="parte_atual" id="parteAtual" value="<?= (int)$configuracaoJogo['parte_atual'] ?>">
                    <input type="hidden" name="jogo_terminado" id="jogoTerminado" value="<?= (int)$configuracaoJogo['jogo_terminado'] ?>">
                    <input type="hidden" name="jogo_iniciado" id="jogoIniciado" value="<?= (int)$configuracaoJogo['jogo_iniciado'] ?>">
                    <input type="hidden" name="posse_inicial" id="posseInicialInput" value="<?= htmlspecialchars($configuracaoJogo['posse_inicial'] ?? '') ?>">
                    <input type="hidden" name="segundos_jogo" id="segundosJogoInput" value="<?= (int)($configuracaoJogo['segundos_jogo'] ?? 0) ?>">
                    <h3 class="game-detail-title">vs <?= htmlspecialchars($jogoDetalhe['adversario']) ?> <small><?= htmlspecialchars($jogoDetalhe['data_jogo']) ?></small></h3>

                    <section class="game-detail-section">
                        <h4>Centro de jogo</h4>
                        <div class="edit-grid">
                            <div class="edit-group"><label>Nº de partes</label><input type="number" name="numero_partes" min="1" max="6" value="<?= (int)$configuracaoJogo['numero_partes'] ?>" required></div>
                            <div class="edit-group"><label>Minutos por parte</label><input type="number" name="minutos_por_parte" min="1" max="120" value="<?= (int)$configuracaoJogo['minutos_por_parte'] ?>" required></div>
                            <div class="edit-group"><label>Resultado nós</label><input type="number" name="resultado_nos" min="0" value="<?= $jogoDetalhe['resultado_nos'] ?? '' ?>"></div>
                            <div class="edit-group"><label>Resultado adversário</label><input type="number" name="resultado_adv" min="0" value="<?= $jogoDetalhe['resultado_adv'] ?? '' ?>"></div>
                        </div>
                        <div class="match-phase-bar" id="matchPhaseBar" data-partes="<?= (int)$configuracaoJogo['numero_partes'] ?>" data-atual="<?= (int)$configuracaoJogo['parte_atual'] ?>" data-terminado="<?= (int)$configuracaoJogo['jogo_terminado'] ?>">
                            <strong id="matchPhaseLabel">Jogo por iniciar</strong><button type="button" class="match-phase-button" id="matchPhaseButton">Iniciar parte</button>
                            <div class="posse-inicial" id="posseInicial" aria-label="Quem inicia com a bola">
                                <span>Quem começa com a bola?</span>
                                <button type="button" data-equipa="casa">A nossa equipa</button>
                                <button type="button" data-equipa="visitante">Adversário</button>
                            </div>
                        </div>
                    </section>

                    <section class="game-detail-section">
                        <h4>Convocatória</h4>
                        <div class="convocatoria-head"><span>Jogador</span><span>Convocado</span><span>Ficha de jogo</span></div>
                        <div class="convocatoria-lista">
                            <?php foreach ($jogadoresDetalhe as $jogador): ?>
                                <?php
                                    $idJogador = (int)$jogador['id_jogador'];
                                    $nomeCurto = trim(($jogador['alcunha_jogador'] ?? '') ?: preg_replace('/\s+.*/', '', $jogador['nome_completo']));
                                    $papelAtual = in_array($idJogador, $idsTitulares, true) ? 'Titular' : (in_array($idJogador, $idsSuplentes, true) ? 'Suplente' : '');
                                ?>
                                <div class="convocado-row">
                                    <span class="convocado-number"><?= htmlspecialchars($jogador['número_favorito'] ?: '—') ?></span>
                                    <span class="convocado-name" title="<?= htmlspecialchars($jogador['nome_completo']) ?>"><?= htmlspecialchars($nomeCurto) ?></span>
                                    <label class="convocado-toggle"><input type="checkbox" name="convocados[]" value="<?= $idJogador ?>" <?= in_array($idJogador, $idsConvocados, true) ? 'checked' : '' ?>><span></span></label>
                                    <select name="papel_jogador[<?= $idJogador ?>]" aria-label="Papel de <?= htmlspecialchars($jogador['nome_completo']) ?>"><option value="">—</option><option value="Titular" <?= $papelAtual === 'Titular' ? 'selected' : '' ?>>Titular</option><option value="Suplente" <?= $papelAtual === 'Suplente' ? 'selected' : '' ?>>Suplente</option></select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="game-detail-section">
                        <h4>Onze inicial e tática</h4>
                        <div class="tactic-layout">
                            <div class="edit-group"><label>Tática</label><select name="tatica" id="taticaJogo">
                                <?php foreach (['4-3-3','4-2-3-1','4-4-2','3-5-2','3-4-3','4-1-4-1','5-3-2'] as $tatica): ?><option value="<?= $tatica ?>" <?= ($configuracaoJogo['tatica'] ?? '4-3-3') === $tatica ? 'selected' : '' ?>><?= $tatica ?></option><?php endforeach; ?>
                            </select><p style="font-size:12px;color:#64748b;line-height:1.5;">Escolhe a tática e atribui um jogador a cada posição no relvado.</p>
                                <div class="onze-inicial-resumo" id="resumoOnzeInicial"></div>
                            </div>
                            <div class="tactic-pitch" id="tacticPitch"></div>
                        </div>
                    </section>

                    <section class="game-detail-section">
                        <h4>Treinadores e equipa técnica presentes</h4>
                        <div class="game-checkboxes">
                            <?php foreach ($treinadoresClube as $tecnico): $nomeTecnico = trim($tecnico['primeiro_nome'] . ' ' . $tecnico['último_nome']); ?>
                                <label><input type="checkbox" name="equipa_tecnica[]" value="<?= htmlspecialchars($nomeTecnico) ?>" <?= in_array($nomeTecnico, $tecnicosPresentes, true) ? 'checked' : '' ?>> <?= htmlspecialchars($nomeTecnico) ?><?= $tecnico['tipo_treinador'] ? ' (' . htmlspecialchars($tecnico['tipo_treinador']) . ')' : '' ?></label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="game-detail-section">
                        <h4>Substituições</h4>
                        <div id="substituicoesLista" class="game-events-list"></div>
                        <button type="button" class="game-add-event" onclick="adicionarSubstituicao()"><span class="game-add-event-icon">+</span>Adicionar substituição</button>
                    </section>

                    <section class="game-detail-section">
                        <h4>Golos</h4>
                        <div id="golosLista" class="game-events-list"></div>
                        <button type="button" class="game-add-event" onclick="adicionarGolo()"><span class="game-add-event-icon">+</span>Adicionar golo</button>
                    </section>

                    <section class="game-detail-section">
                        <h4>Posse de bola</h4>
                        <div class="posse-controls" data-casa="<?= (int)($estatisticasColetivasJogo['posse_casa_segundos'] ?? 0) ?>" data-sem="<?= (int)($estatisticasColetivasJogo['sem_posse_segundos'] ?? 0) ?>" data-visitante="<?= (int)($estatisticasColetivasJogo['posse_visitante_segundos'] ?? 0) ?>">
                            <button type="button" data-posse="casa">Posse da casa</button><button type="button" data-posse="sem">Sem posse</button><button type="button" data-posse="visitante">Posse do visitante</button>
                            <strong id="posseCronometro">00:00:00</strong>
                            <div class="posse-percent"><span>Casa <b id="posseCasaPercentagem">0%</b></span><span>Visitante <b id="posseVisitantePercentagem">0%</b></span></div>
                            <input type="hidden" name="posse_casa_segundos"><input type="hidden" name="sem_posse_segundos"><input type="hidden" name="posse_visitante_segundos">
                            <div class="game-clock-controls" id="cronometroJogoControlo">
                                <span>Tempo de jogo</span>
                                <strong id="cronometroJogo">00:00</strong>
                                <button type="button" id="botaoPausaJogo" disabled>Pausar cronómetro</button>
                            </div>
                        </div>
                    </section>

                    <section class="game-detail-section">
                        <h4>Estatísticas coletivas</h4>
                        <div class="game-detail-grid" id="estatisticasColetivasGrid">
                            <?php foreach ($camposColetivosView as $campo => $rotulo): ?><div class="edit-group"><label><?= $rotulo ?></label><div class="stat-counter"><button type="button" aria-label="Diminuir <?= $rotulo ?>" onclick="alterarContador(this,-1)">−</button><input type="number" min="0" name="<?= $campo ?>" value="<?= (int)($estatisticasColetivasJogo[$campo] ?? 0) ?>"><button type="button" aria-label="Somar <?= $rotulo ?>" onclick="alterarContador(this,1)">+</button></div></div><?php endforeach; ?>
                        </div>
                    </section>

                    <section class="game-detail-section game-individual-stats">
                        <h4>Estatísticas individuais pós-jogo</h4>
                        <div class="game-stats-scroll"><table><thead><tr><th>Jogador</th><?php foreach ($camposIndividuaisView as $rotulo): ?><th><?= $rotulo ?></th><?php endforeach; ?></tr></thead><tbody>
                            <?php foreach ($jogadoresDetalhe as $jogador): $idJogador = (int)$jogador['id_jogador']; $estatistica = $estatisticasIndividuaisJogo[$idJogador] ?? []; ?><tr data-jogador="<?= $idJogador ?>"><td><?= htmlspecialchars($jogador['nome_completo']) ?></td><?php foreach ($camposIndividuaisView as $campo => $rotulo): ?><td><div class="stat-counter"><button type="button" aria-label="Diminuir <?= $rotulo ?>" onclick="alterarContador(this,-1)" <?= $campo === 'minutos_jogados' ? 'disabled' : '' ?>>−</button><input type="number" min="0" name="estatisticas_individuais[<?= $idJogador ?>][<?= $campo ?>]" value="<?= (int)($estatistica[$campo] ?? 0) ?>" <?= $campo === 'minutos_jogados' ? 'readonly' : '' ?>><button type="button" aria-label="Somar <?= $rotulo ?>" onclick="alterarContador(this,1)" <?= $campo === 'minutos_jogados' ? 'disabled' : '' ?>>+</button></div></td><?php endforeach; ?></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </section>
                    <div class="modal-actions"><button class="game-report-button" type="button" onclick="gerarRelatorioJogo()">Relatório PDF</button><button class="btn-save" type="submit">Guardar detalhe do jogo</button></div>
                </form>
                <script>
                    const jogadoresDetalheJogo = <?= json_encode($jogadoresDetalhe, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                    const subsGuardadas = <?= json_encode($substituicoesJogo, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                    const golosGuardados = <?= json_encode($golosJogo, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                    const posicoesTitularesGuardadas = <?= json_encode(json_decode($configuracaoJogo['posicoes_titulares'] ?? '{}', true) ?: [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                    const posseInicialGuardada = <?= json_encode($configuracaoJogo['posse_inicial'] ?? null, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                </script>
            <?php elseif (empty($jogosTreinador)): ?>
                <div class="empty-state"><p>Ainda não há jogos associados às tuas equipas.</p></div>
            <?php else: ?>
                <div class="trainer-list">
                    <?php foreach ($jogosTreinador as $jogo): ?>
                        <?php
                            $resultadoJogo = ($jogo['resultado_nos'] !== null && $jogo['resultado_adv'] !== null)
                                ? $jogo['resultado_nos'] . ' – ' . $jogo['resultado_adv']
                                : '— – —';
                            $estadoClass = 'jogo-estado-' . ($jogo['estado'] ?? 'Agendado');
                        ?>
                        <div class="trainer-game-row">
                            <div class="trainer-game-date">
                                <?= htmlspecialchars(date('d/m/Y', strtotime($jogo['data_jogo']))) ?>
                                <?php if (!empty($jogo['hora_jogo'])): ?><br><small><?= htmlspecialchars(substr($jogo['hora_jogo'], 0, 5)) ?></small><?php endif; ?>
                            </div>
                            <div class="trainer-game-main">
                                <div class="trainer-game-title">vs <?= htmlspecialchars($jogo['adversario']) ?></div>
                                <div class="trainer-game-subtitle">
                                    <?= htmlspecialchars($jogo['nome_competicao']) ?> · <?= htmlspecialchars($jogo['escalão'] . ' ' . $jogo['hierarquia']) ?>
                                    <?php if (!empty($jogo['local_jogo'])): ?> · 📍 <?= htmlspecialchars($jogo['local_jogo']) ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="jogo-casa-badge"><?= $jogo['casa'] ? 'Casa' : 'Fora' ?></span>
                            <div class="trainer-result"><?= htmlspecialchars($resultadoJogo) ?></div>
                            <span class="jogo-estado <?= htmlspecialchars($estadoClass) ?>"><?= htmlspecialchars($jogo['estado']) ?></span>
                            <a class="game-detail-link" title="Abrir detalhe do jogo" href="index-treinador.php?view=jogos&jogo=<?= (int)$jogo['id_jogo'] ?>">Ver detalhe</a>
                            <button class="btn-row-edit" type="button" title="Atualizar resultado" onclick="openResultadoTreinadorModal(<?= (int)$jogo['id_jogo'] ?>)">⚽</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══ ECRÃ CAMPEONATO ══ -->
        <div class="screen-shell" id="campeonatoScreen">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Estatísticas</h2>
                    <p class="trainer-page-subtitle">Resumo das competições e rendimento das tuas equipas.</p>
                </div>
            </div>

            <?php if (empty($estatisticasCampeonato)): ?>
                <div class="empty-state"><p>Ainda não existem competições associadas às tuas equipas.</p></div>
            <?php else: ?>
                <div class="trainer-standings-grid">
                    <?php foreach ($estatisticasCampeonato as $stats): ?>
                        <div class="standings-card">
                            <div class="standings-card-header">
                                <div class="standings-card-title"><?= htmlspecialchars($stats['nome']) ?></div>
                                <div class="standings-card-subtitle">
                                    <?= htmlspecialchars($stats['tipo']) ?><?= $stats['epoca'] ? ' · ' . htmlspecialchars($stats['epoca']) : '' ?> · <?= htmlspecialchars($stats['equipa']) ?>
                                </div>
                            </div>
                            <table class="standings-table">
                                <thead>
                                    <tr>
                                        <th>Pts</th>
                                        <th>J</th>
                                        <th>V</th>
                                        <th>E</th>
                                        <th>D</th>
                                        <th>GM</th>
                                        <th>GS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="standings-points"><?= (int)$stats['pontos'] ?></td>
                                        <td><?= (int)$stats['realizados'] ?></td>
                                        <td><?= (int)$stats['vitorias'] ?></td>
                                        <td><?= (int)$stats['empates'] ?></td>
                                        <td><?= (int)$stats['derrotas'] ?></td>
                                        <td><?= (int)$stats['golos_marcados'] ?></td>
                                        <td><?= (int)$stats['golos_sofridos'] ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="stats-overview-section">
                <h3 class="stats-overview-title">Estatísticas coletivas</h3>
                <p class="trainer-page-subtitle">Soma de todos os jogos com registo (<?= (int)$estatisticasColetivasGlobais['jogos_com_registo'] ?> jogo<?= $estatisticasColetivasGlobais['jogos_com_registo'] === 1 ? '' : 's' ?> registados).</p>
                <?php if ($estatisticasColetivasGlobais['jogos_com_registo'] === 0): ?>
                    <div class="empty-state"><p>Ainda não há estatísticas de jogo registadas.</p></div>
                <?php else: ?>
                    <?php $tempoComPosseGlobal = $estatisticasColetivasGlobais['posse_casa_segundos'] + $estatisticasColetivasGlobais['posse_visitante_segundos']; ?>
                    <div class="standings-card">
                        <table class="standings-table">
                            <thead><tr><th>Estatística</th><th>Nós</th><th>Adversário</th></tr></thead>
                            <tbody>
                                <tr><td>Posse de bola</td><td><?= $tempoComPosseGlobal > 0 ? round($estatisticasColetivasGlobais['posse_casa_segundos'] * 100 / $tempoComPosseGlobal, 1) : 0 ?>%</td><td><?= $tempoComPosseGlobal > 0 ? round($estatisticasColetivasGlobais['posse_visitante_segundos'] * 100 / $tempoComPosseGlobal, 1) : 0 ?>%</td></tr>
                                <?php foreach ($paresEstatisticasColetivas as $rotuloPar => [$campoNos, $campoAdv]): ?>
                                    <tr><td><?= htmlspecialchars($rotuloPar) ?></td><td><?= (int)$estatisticasColetivasGlobais[$campoNos] ?></td><td><?= (int)$estatisticasColetivasGlobais[$campoAdv] ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stats-overview-section">
                <h3 class="stats-overview-title">Estatísticas individuais</h3>
                <p class="trainer-page-subtitle">Totais por jogador em todos os jogos com registo — só jogadores que entraram em campo.</p>
                <?php if (empty($estatisticasIndividuaisGlobais)): ?>
                    <div class="empty-state"><p>Ainda não há estatísticas individuais registadas.</p></div>
                <?php else: ?>
                    <div class="game-stats-scroll">
                        <table>
                            <thead><tr><th>Jogador</th><?php foreach ($rotulosEstatisticasIndividuais as $rotulo): ?><th><?= $rotulo ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php foreach ($estatisticasIndividuaisGlobais as $idJogadorGlobal => $statsJogadorGlobal): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($nomesJogadoresGlobais[$idJogadorGlobal] ?? ('Jogador #' . $idJogadorGlobal)) ?></td>
                                        <?php foreach ($rotulosEstatisticasIndividuais as $campo => $rotulo): ?><td><?= (int)$statsJogadorGlobal[$campo] ?></td><?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ ECRÃ ESCALÕES ══ -->
        <div class="screen-shell" id="escaloesScreen">
            <div class="escaloes-header">
                <h2 style="font-size:20px;font-weight:700;color:#1f2b3d;">Escalões — Jogadores</h2>
                <?php if ($isAdminClube): ?>
                <button class="btn-create" type="button" onclick="openModal('modalCriarJogador')">+ Adicionar Jogador</button>
                <?php endif; ?>
            </div>

            <?php if (empty($escaloesClube)): ?>
                <div class="empty-state"><p>Nenhum escalão criado. Cria escalões no menu <strong>Clube</strong>.</p></div>
            <?php else: ?>
            <div class="escaloes-team-tabs">
                <?php foreach ($escaloesClube as $i => $esc): ?>
                <button class="escalao-tab-btn <?= $i === 0 ? 'active' : '' ?>"
                        onclick="selectEscalao(this, <?= (int)$esc['id_equipa'] ?>)"
                        data-equipa="<?= (int)$esc['id_equipa'] ?>">
                    <?= htmlspecialchars($esc['escalão'] . ' ' . $esc['hierarquia']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div id="playersContent">
                <?php foreach ($escaloesClube as $i => $esc): ?>
                <div id="players-equipa-<?= (int)$esc['id_equipa'] ?>" style="<?= $i > 0 ? 'display:none;' : '' ?>">
                    <?php $jogadoresEquipa = $jogadoresPorEquipa[(int)$esc['id_equipa']] ?? []; ?>
                    <?php if (empty($jogadoresEquipa)): ?>
                        <div class="empty-state"><p>Sem jogadores neste escalão.</p></div>
                    <?php else: ?>
                    <div class="players-grid">
                        <?php foreach ($jogadoresEquipa as $jog): ?>
                        <div class="player-card-wrap">
                            <div class="player-card" onclick="openPlayerProfile(<?= (int)$jog['id_jogador'] ?>)">
                                <div class="player-avatar">
                                    <?php if ($jog['foto_base64']): ?>
                                        <img src="<?= $jog['foto_base64'] ?>" alt="">
                                    <?php else: ?>
                                        <?= htmlspecialchars(strtoupper(substr($jog['alcunha_jogador'] ?: $jog['nome_completo'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($jog['número_favorito']): ?>
                                <div class="player-number"><?= htmlspecialchars($jog['número_favorito']) ?></div>
                                <?php endif; ?>
                                <div class="player-name"><?= htmlspecialchars($jog['alcunha_jogador'] ?: $jog['nome_completo']) ?></div>
                                <div class="player-pos"><?= htmlspecialchars($jog['posição_principal']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══ ECRÃ COMPETIÇÕES ══ -->
        <div class="screen-shell" id="competicoesScreen">
            <!-- Vista: lista de competições -->
            <div id="competicoesLista">
                <div class="competicoes-header">
                    <h2 style="font-size:20px;font-weight:700;color:#1f2b3d;">Competições</h2>
                    <?php if ($isAdminClube): ?>
                    <button class="btn-create" type="button" onclick="openModal('modalCriarCompeticao')">+ Criar Competição</button>
                    <?php endif; ?>
                </div>
                <?php if (empty($competicoesClube)): ?>
                    <div class="empty-state"><p>Sem competições criadas.</p></div>
                <?php else: ?>
                <div class="competicao-cards" id="competicaoCardsGrid">
                    <?php foreach ($competicoesClube as $comp): ?>
                    <?php $estadoCss = ['A decorrer'=>'estado-decorrer','Finalizada'=>'estado-finalizada','Suspensa'=>'estado-suspensa'][$comp['estado']] ?? ''; ?>
                    <div class="competicao-card" onclick="openCompeticao(<?= (int)$comp['id_competicao'] ?>)">
                        <span class="competicao-card-tipo"><?= htmlspecialchars($comp['tipo']) ?></span>
                        <div class="competicao-card-nome"><?= htmlspecialchars($comp['nome']) ?></div>
                        <div class="competicao-card-equipa"><?= htmlspecialchars($comp['escalão'] . ' ' . $comp['hierarquia']) ?><?= $comp['epoca'] ? ' · ' . htmlspecialchars($comp['epoca']) : '' ?></div>
                        <span class="competicao-card-estado <?= $estadoCss ?>"><?= htmlspecialchars($comp['estado']) ?></span>
                        <?php if ($isAdminClube): ?>
                        <div class="competicao-card-actions" onclick="event.stopPropagation()">
                            <button class="btn-row-edit" title="Editar" onclick="openEditCompeticao(<?= (int)$comp['id_competicao'] ?>)">✎</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remover competição e todos os jogos?');">
                                <input type="hidden" name="acao" value="remover_competicao">
                                <input type="hidden" name="id_competicao" value="<?= (int)$comp['id_competicao'] ?>">
                                <button class="btn-row-edit btn-row-delete" type="submit">×</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vista: jogos de uma competição (hidden by default) -->
            <div id="competicaoDetalhe" style="display:none;">
                <div class="jogos-header">
                    <button class="btn-back" onclick="backToCompeticoes()">&#8592; Competições</button>
                    <div>
                        <div style="font-size:18px;font-weight:700;color:#1f2b3d;" id="detalheNome"></div>
                        <div style="font-size:13px;color:#6b7280;" id="detalheInfo"></div>
                    </div>
                    <?php if ($isAdminClube): ?>
                    <button class="btn-create" type="button" style="margin-left:auto;" onclick="openCriarJogoModal()">+ Criar Jogo</button>
                    <?php endif; ?>
                </div>
                <div id="jogosLista"></div>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error" role="alert">
                <span><?= htmlspecialchars($erro) ?></span>
                <button class="alert-close" type="button" aria-label="Fechar" onclick="closeAlert(this)">×</button>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success" role="status">
                <span><?= htmlspecialchars($sucesso) ?></span>
                <button class="alert-close" type="button" aria-label="Fechar" onclick="closeAlert(this)">×</button>
            </div>
        <?php endif; ?>

        <div class="tabs-row">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?= $activeTab === 'tab-info' ? 'active' : '' ?>" onclick="switchTab(this,'tab-info')">Info</button>
                <button class="tab <?= $activeTab === 'tab-escaloes' ? 'active' : '' ?>" onclick="switchTab(this,'tab-escaloes')">Escalões</button>
                <button class="tab <?= $activeTab === 'tab-jogadores' ? 'active' : '' ?>" onclick="switchTab(this,'tab-jogadores')">Jogadores</button>
                <button class="tab <?= $activeTab === 'tab-treinadores' ? 'active' : '' ?>" onclick="switchTab(this,'tab-treinadores')">Treinadores</button>
            </div>

            <?php if ($isAdminClube): ?>
            <div class="card-header-actions">
                <!-- Botão editar clube: só aparece na aba Info -->
                <button
                    id="btnEditClube"
                    class="btn-edit"
                    type="button"
                    title="Editar informações do clube"
                    onclick="openModal('modalEditarClube')"
                    style="<?= $activeTab === 'tab-info' ? '' : 'display:none;' ?>"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Painel Info ── -->
        <div class="tab-panel <?= $activeTab === 'tab-info' ? 'active' : '' ?>" id="tab-info">
            <div class="info-layout">

                <div class="info-fields">

                    <div class="info-row">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?= htmlspecialchars($nomeClube) ?></span>
                    </div>

                    <?php if (!empty($clube['nome_estádio'])): ?>
                    <div class="info-row">
                        <span class="info-label">Estádio:</span>
                        <span class="info-value"><?= htmlspecialchars($clube['nome_estádio']) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Data de Fundação:</span>
                        <span class="info-value <?= $dataFundacao ? '' : 'empty' ?>">
                            <?= $dataFundacao ?: 'Não definida' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Morada:</span>
                        <span class="info-value <?= $morada ? '' : 'empty' ?>">
                            <?= $morada ? htmlspecialchars($morada) : 'Não definida' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Telemóvel:</span>
                        <span class="info-value <?= !empty($clube['telefone_clube']) ? '' : 'empty' ?>">
                            <?= !empty($clube['telefone_clube'])
                                ? htmlspecialchars($clube['telefone_clube'])
                                : 'Não definido' ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value <?= !empty($clube['email_clube']) ? '' : 'empty' ?>">
                            <?php if (!empty($clube['email_clube'])): ?>
                                <a href="mailto:<?= htmlspecialchars($clube['email_clube']) ?>"
                                   style="color:var(--club);text-decoration:none;">
                                    <?= htmlspecialchars($clube['email_clube']) ?>
                                </a>
                            <?php else: ?>
                                Não definido
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (!empty($clube['website_clube'])): ?>
                    <div class="info-row">
                        <span class="info-label">Website:</span>
                        <span class="info-value">
                            <a href="<?= htmlspecialchars($clube['website_clube']) ?>"
                               target="_blank" style="color:var(--club);text-decoration:none;">
                                <?= htmlspecialchars($clube['website_clube']) ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($clube['presidente_clube'])): ?>
                    <div class="info-row">
                        <span class="info-label">Presidente:</span>
                        <span class="info-value"><?= htmlspecialchars($clube['presidente_clube']) ?></span>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Logo -->
                <div class="club-logo-wrap">
                    <?php if ($logoClube): ?>
                        <img id="clubLogoImage" src="<?= $logoClube ?>" alt="Logótipo de <?= htmlspecialchars($nomeClube) ?>" onerror="showClubLogoStatus('Não foi possível carregar o logótipo');">
                        <div id="clubLogoStatus" class="club-logo-status" style="display:none;"></div>
                    <?php else: ?>
                        <div class="club-logo-status">Logótipo não existe</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ── Painel Escalões ── -->
        <div class="tab-panel <?= $activeTab === 'tab-escaloes' ? 'active' : '' ?>" id="tab-escaloes">

            <div class="tab-action-row">
                <h3>Escalões</h3>
                <?php if ($isAdminClube): ?>
                <button class="btn-create" type="button" onclick="openModal('modalCriarEscalao')">
                    + Criar Escalão
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($escaloesClube)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <p>Ainda não há escalões criados.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Escalão</th>
                            <th>Hierarquia</th>
                            <th>Época</th>
                            <?php if ($isAdminClube): ?>
                            <th class="actions-col">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escaloesClube as $esc): ?>
                            <tr>
                                <td><?= htmlspecialchars($esc['escalão']) ?></td>
                                <td><?= htmlspecialchars($esc['hierarquia']) ?></td>
                                <td><?= htmlspecialchars($esc['época'] ?? 'Não definida') ?></td>
                                <?php if ($isAdminClube): ?>
                                <td class="actions-cell">
                                    <div class="actions-wrap">
                                        <button
                                            class="btn-row-edit"
                                            type="button"
                                            title="Editar escalão"
                                            onclick="openModal('modalEditarEscalao<?= (int)$esc['id_equipa'] ?>')"
                                        >
                                            ✎
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Tens a certeza que queres remover este escalão?');">
                                            <input type="hidden" name="acao" value="remover_escalao">
                                            <input type="hidden" name="id_equipa" value="<?= (int)$esc['id_equipa'] ?>">
                                            <button class="btn-row-edit btn-row-delete" type="submit" title="Remover escalão">×</button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>

        <!-- ── Painel Jogadores ── -->
        <div class="tab-panel <?= $activeTab === 'tab-jogadores' ? 'active' : '' ?>" id="tab-jogadores">

            <div class="tab-action-row">
                <div>
                    <h3>Jogadores</h3>
                    <p style="font-size:13px;color:#6b7280;margin-top:4px;">
                        Jogadores dos escalões/equipas associados ao teu perfil de treinador.
                    </p>
                </div>
            </div>

            <?php if (empty($escaloesClube)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <p>Ainda não tens escalões/equipas associados.</p>
                </div>
            <?php else: ?>
                <div class="escaloes-team-tabs">
                    <?php foreach ($escaloesClube as $i => $esc): ?>
                        <button
                            class="escalao-tab-btn menu-jogadores-tab-btn <?= $i === 0 ? 'active' : '' ?>"
                            type="button"
                            onclick="selectMenuJogadores(this, <?= (int)$esc['id_equipa'] ?>)"
                        >
                            <?= htmlspecialchars($esc['escalão'] . ' ' . $esc['hierarquia']) ?>
                            <?= !empty($esc['época']) ? ' · ' . htmlspecialchars($esc['época']) : '' ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="menuPlayersContent">
                    <?php foreach ($escaloesClube as $i => $esc): ?>
                        <div id="menu-players-equipa-<?= (int)$esc['id_equipa'] ?>" style="<?= $i > 0 ? 'display:none;' : '' ?>">
                            <?php $jogadoresEquipa = $jogadoresPorEquipa[(int)$esc['id_equipa']] ?? []; ?>

                            <?php if (empty($jogadoresEquipa)): ?>
                                <div class="empty-state"><p>Sem jogadores neste escalão.</p></div>
                            <?php else: ?>
                                <div class="players-grid">
                                    <?php foreach ($jogadoresEquipa as $jog): ?>
                                        <div class="player-card-wrap">
                                            <div class="player-card" onclick="openPlayerProfile(<?= (int)$jog['id_jogador'] ?>)">
                                                <div class="player-avatar">
                                                    <?php if (!empty($jog['foto_base64'])): ?>
                                                        <img src="<?= $jog['foto_base64'] ?>" alt="">
                                                    <?php else: ?>
                                                        <?= htmlspecialchars(strtoupper(substr($jog['alcunha_jogador'] ?: $jog['nome_completo'], 0, 1))) ?>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($jog['número_favorito'])): ?>
                                                    <div class="player-number"><?= htmlspecialchars($jog['número_favorito']) ?></div>
                                                <?php endif; ?>

                                                <div class="player-name"><?= htmlspecialchars($jog['alcunha_jogador'] ?: $jog['nome_completo']) ?></div>
                                                <div class="player-pos"><?= htmlspecialchars($jog['posição_principal']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- ── Painel Treinadores ── -->
        <div class="tab-panel <?= $activeTab === 'tab-treinadores' ? 'active' : '' ?>" id="tab-treinadores">

            <div class="tab-action-row">
                <h3>Treinadores</h3>
                <?php if ($isAdminClube): ?>
                <button class="btn-create" type="button" onclick="openModal('modalCriarTreinador')">
                    + Criar Treinador
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($treinadoresClube)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <p>Ainda não há treinadores associados.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Utilizador</th>
                            <th>Tipo</th>
                            <th>Email</th>
                            <th>Equipas associadas</th>
                            <?php if ($isAdminClube): ?>
                            <th class="actions-col">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treinadoresClube as $treinador): ?>
                            <tr>
                                <td><?= htmlspecialchars($treinador['primeiro_nome'] . ' ' . $treinador['último_nome']) ?></td>
                                <td><?= htmlspecialchars($treinador['nome_utilizador']) ?></td>
                                <td><?= htmlspecialchars($treinador['tipo_treinador'] ?: 'Não definido') ?></td>
                                <td><?= htmlspecialchars($treinador['email_utilizador'] ?: 'Sem email') ?></td>
                                <td>
                                    <?= $treinador['equipas']
                                        ? htmlspecialchars($treinador['equipas'])
                                        : '<span class="muted">Sem equipa</span>' ?>
                                </td>
                                <?php if ($isAdminClube): ?>
                                <td class="actions-cell">
                                    <div class="actions-wrap">
                                        <button
                                            class="btn-row-edit"
                                            type="button"
                                            title="Editar treinador"
                                            onclick="openModal('modalEditarTreinador<?= (int)$treinador['id_utilizador'] ?>')"
                                        >
                                            ✎
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Tens a certeza que queres remover este treinador?');">
                                            <input type="hidden" name="acao" value="remover_treinador">
                                            <input type="hidden" name="id_treinador" value="<?= (int)$treinador['id_utilizador'] ?>">
                                            <button class="btn-row-edit btn-row-delete" type="submit" title="Remover treinador">×</button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- ══ MODAL EDITAR CLUBE ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalEditarClube">
    <div class="modal large">
        <div class="modal-header">
            <div class="modal-title">Editar informações do clube</div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarClube')">×</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar_clube">

            <div class="edit-grid">

                <div class="edit-group">
                    <label>Nome do clube</label>
                    <input type="text" name="nome_clube" value="<?= htmlspecialchars($clube['nome_clube'] ?? '') ?>" required>
                </div>

                <div class="edit-group">
                    <label>Sigla</label>
                    <input type="text" name="sigla" maxlength="5" value="<?= htmlspecialchars($clube['sigla'] ?? '') ?>" required>
                </div>

                <div class="edit-group">
                    <label>Data de fundação</label>
                    <input type="date" name="data_fundacao" value="<?= htmlspecialchars($clube['data_fundação'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Estádio</label>
                    <input type="text" name="nome_estadio" value="<?= htmlspecialchars($clube['nome_estádio'] ?? '') ?>">
                </div>

                <div class="edit-group full">
                    <label>Morada</label>
                    <input type="text" name="sede_morada" value="<?= htmlspecialchars($clube['sede_morada'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade_clube" value="<?= htmlspecialchars($clube['cidade_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>País</label>
                    <input type="text" name="pais_clube" value="<?= htmlspecialchars($clube['país_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Telemóvel</label>
                    <input type="text" name="telefone_clube" value="<?= htmlspecialchars($clube['telefone_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Email</label>
                    <input type="email" name="email_clube" value="<?= htmlspecialchars($clube['email_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Website</label>
                    <input type="text" name="website_clube" value="<?= htmlspecialchars($clube['website_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Presidente</label>
                    <input type="text" name="presidente_clube" value="<?= htmlspecialchars($clube['presidente_clube'] ?? '') ?>">
                </div>

                <div class="edit-group">
                    <label>Cor principal</label>
                    <div class="edit-color-row">
                        <input type="color" id="editColorPicker" value="<?= htmlspecialchars($clube['cor'] ?? '#000000') ?>">
                        <input type="text" id="editColorHex" name="cor" value="<?= htmlspecialchars($clube['cor'] ?? '#000000') ?>" required>
                    </div>
                </div>

                <div class="edit-group full">
                    <label>Alterar logótipo</label>
                    <input type="file" name="logotipo" accept="image/*">
                </div>

            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalEditarClube')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL CRIAR ESCALÃO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarEscalao">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Criar escalão</div>
            <button class="modal-close" type="button" onclick="closeModal('modalCriarEscalao')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="criar_escalao">

            <div class="edit-grid">

                <div class="edit-group">
                    <label>Escalão</label>
                    <select name="escalao" required>
                        <option value="">Selecionar</option>
                        <?php foreach ($listaEscaloesDisponiveis as $escalaoOption): ?>
                            <option value="<?= $escalaoOption ?>"><?= $escalaoOption ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group">
                    <label>Hierarquia</label>
                    <select name="hierarquia" required>
                        <option value="">Selecionar</option>
                        <?php foreach ($listaHierarquiasDisponiveis as $letra): ?>
                            <option value="<?= $letra ?>"><?= $letra ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group full">
                    <label>Época</label>
                    <select name="id_epoca" required>
                        <option value="">Selecionar época</option>
                        <?php foreach ($epocas as $epoca): ?>
                            <option value="<?= (int)$epoca['id_época'] ?>">
                                <?= htmlspecialchars($epoca['época']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalCriarEscalao')">Cancelar</button>
                <button class="btn-save" type="submit">Criar escalão</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL CRIAR TREINADOR ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarTreinador">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Criar treinador</div>
            <button class="modal-close" type="button" onclick="closeModal('modalCriarTreinador')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="criar_treinador">

            <div class="edit-grid">

                <div class="edit-group">
                    <label>Primeiro nome</label>
                    <input type="text" name="primeiro_nome" required>
                </div>

                <div class="edit-group">
                    <label>Último nome</label>
                    <input type="text" name="ultimo_nome" required>
                </div>

                <div class="edit-group">
                    <label>Nome de utilizador</label>
                    <input type="text" name="nome_utilizador_treinador" minlength="3" maxlength="30" required>
                </div>

                <div class="edit-group">
                    <label>Password inicial</label>
                    <input type="password" name="password_treinador" required>
                </div>

                <div class="edit-group">
                    <label>Tipo de treinador</label>
                    <select name="tipo_treinador" required>
                        <option value="">Selecionar tipo</option>
                        <?php foreach ($tiposTreinadorDisponiveis as $tipoTreinador): ?>
                            <option value="<?= htmlspecialchars($tipoTreinador) ?>"><?= htmlspecialchars($tipoTreinador) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group">
                    <label>Email (opcional)</label>
                    <input type="email" name="email_treinador" placeholder="Pode preencher mais tarde">
                </div>

                <div class="edit-group full">
                    <label>Equipa associada</label>
                    <select name="id_equipa">
                        <option value="0">Sem equipa por agora</option>
                        <?php foreach ($escaloesClube as $equipa): ?>
                            <option value="<?= (int)$equipa['id_equipa'] ?>">
                                <?= htmlspecialchars($equipa['escalão'] . ' ' . $equipa['hierarquia'] . ' - ' . ($equipa['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalCriarTreinador')">Cancelar</button>
                <button class="btn-save" type="submit">Criar treinador</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAIS EDITAR ESCALÕES ══ -->
<?php if ($isAdminClube): ?>
<?php foreach ($escaloesClube as $esc): ?>
<div class="modal-backdrop" id="modalEditarEscalao<?= (int)$esc['id_equipa'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Editar escalão</div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarEscalao<?= (int)$esc['id_equipa'] ?>')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="editar_escalao">
            <input type="hidden" name="id_equipa" value="<?= (int)$esc['id_equipa'] ?>">

            <div class="edit-grid">

                <div class="edit-group">
                    <label>Escalão</label>
                    <select name="escalao" required>
                        <?php foreach ($listaEscaloesDisponiveis as $escalaoOption): ?>
                            <option
                                value="<?= $escalaoOption ?>"
                                <?= $esc['escalão'] === $escalaoOption ? 'selected' : '' ?>
                            >
                                <?= $escalaoOption ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group">
                    <label>Hierarquia</label>
                    <select name="hierarquia" required>
                        <?php foreach ($listaHierarquiasDisponiveis as $letra): ?>
                            <option
                                value="<?= $letra ?>"
                                <?= $esc['hierarquia'] === $letra ? 'selected' : '' ?>
                            >
                                <?= $letra ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group full">
                    <label>Época</label>
                    <select name="id_epoca" required>
                        <?php foreach ($epocas as $epoca): ?>
                            <option
                                value="<?= (int)$epoca['id_época'] ?>"
                                <?= (int)$esc['id_época'] === (int)$epoca['id_época'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($epoca['época']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalEditarEscalao<?= (int)$esc['id_equipa'] ?>')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ══ MODAIS EDITAR TREINADORES ══ -->
<?php if ($isAdminClube): ?>
<?php foreach ($treinadoresClube as $treinador): ?>
<div class="modal-backdrop" id="modalEditarTreinador<?= (int)$treinador['id_utilizador'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Editar treinador</div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarTreinador<?= (int)$treinador['id_utilizador'] ?>')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="editar_treinador">
            <input type="hidden" name="id_treinador" value="<?= (int)$treinador['id_utilizador'] ?>">

            <div class="edit-grid">

                <div class="edit-group">
                    <label>Primeiro nome</label>
                    <input
                        type="text"
                        name="primeiro_nome"
                        value="<?= htmlspecialchars($treinador['primeiro_nome']) ?>"
                        required
                    >
                </div>

                <div class="edit-group">
                    <label>Último nome</label>
                    <input
                        type="text"
                        name="ultimo_nome"
                        value="<?= htmlspecialchars($treinador['último_nome']) ?>"
                        required
                    >
                </div>

                <div class="edit-group full">
                    <label>Nome de utilizador</label>
                    <input
                        type="text"
                        name="nome_utilizador_treinador"
                        value="<?= htmlspecialchars($treinador['nome_utilizador']) ?>"
                        minlength="3"
                        maxlength="30"
                        required
                    >
                </div>

                <div class="edit-group full">
                    <label>Tipo de treinador</label>
                    <select name="tipo_treinador" required>
                        <option value="">Selecionar tipo</option>
                        <?php foreach ($tiposTreinadorDisponiveis as $tipoTreinador): ?>
                            <option
                                value="<?= htmlspecialchars($tipoTreinador) ?>"
                                <?= ($treinador['tipo_treinador'] ?? '') === $tipoTreinador ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($tipoTreinador) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group full">
                    <label>Email (opcional)</label>
                    <input
                        type="email"
                        name="email_treinador"
                        value="<?= htmlspecialchars($treinador['email_utilizador']) ?>"
                        placeholder="Pode ficar vazio"
                    >
                </div>

                <div class="edit-group full">
                    <label>Nova password</label>
                    <input
                        type="password"
                        name="nova_password_treinador"
                        placeholder="Deixa vazio para manter a password atual"
                    >
                </div>

                <div class="edit-group full">
                    <label>Equipa associada</label>
                    <select name="id_equipa">
                        <option value="0" <?= empty($treinador['id_equipa_atual']) ? 'selected' : '' ?>>
                            Sem equipa
                        </option>

                        <?php foreach ($escaloesClube as $equipa): ?>
                            <option
                                value="<?= (int)$equipa['id_equipa'] ?>"
                                <?= (int)($treinador['id_equipa_atual'] ?? 0) === (int)$equipa['id_equipa'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($equipa['escalão'] . ' ' . $equipa['hierarquia'] . ' - ' . ($equipa['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalEditarTreinador<?= (int)$treinador['id_utilizador'] ?>')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ══ MODAL CRIAR EVENTO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarEvento">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Criar evento</div>
            <button class="modal-close" type="button" onclick="closeModal('modalCriarEvento')">×</button>
        </div>
        <form method="POST" action="index-treinador.php?view=calendario" id="formCriarEvento" onsubmit="return validarFormEvento(this)">
            <input type="hidden" name="acao" value="criar_evento">
            <input type="hidden" name="cal_month" class="cal-month-field">
            <input type="hidden" name="cal_year" class="cal-year-field">
            <input type="hidden" name="cal_day" class="cal-day-field">
            <div class="edit-grid">
                <div class="edit-group full">
                    <label>Escalão / Equipa</label>
                    <select name="id_equipa_evento" required>
                        <option value="">Selecionar equipa</option>
                        <?php foreach ($escaloesClube as $eq): ?>
                            <option value="<?= (int)$eq['id_equipa'] ?>">
                                <?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia'] . ' — ' . ($eq['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Tipo de evento</label>
                    <select name="tipo_evento" required>
                        <option value="">Selecionar</option>
                        <?php foreach (['Treino','Jogo','Reunião Técnico-Tática','Sessão de Recuperação','Convívio de Equipa','Outro'] as $te): ?>
                            <option value="<?= $te ?>"><?= $te ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Estado</label>
                    <select name="estado_evento" required>
                        <?php foreach (['Por realizar','Realizado','Cancelado','Adiado'] as $se): ?>
                            <option value="<?= $se ?>"><?= $se ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Data</label>
                    <input type="date" name="data_evento" required>
                </div>
                <div class="edit-group">
                    <label>Hora (opcional)</label>
                    <input type="time" name="hora_evento">
                </div>
                <div class="edit-group full">
                    <label>Local (opcional)</label>
                    <input type="text" name="local_evento" placeholder="Ex: Estádio Municipal">
                </div>
                <div class="edit-group full">
                    <label>Descrição (opcional)</label>
                    <input type="text" name="descricao_evento" placeholder="Breve descrição do evento">
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalCriarEvento')">Cancelar</button>
                <button class="btn-save" type="submit">Criar evento</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL EDITAR EVENTO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Editar evento</div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarEvento')">×</button>
        </div>
        <form method="POST" action="index-treinador.php?view=calendario" id="formEditarEvento" onsubmit="return validarFormEvento(this)">
            <input type="hidden" name="acao" value="editar_evento">
            <input type="hidden" name="id_evento" id="editEventoId">
            <input type="hidden" name="cal_month" class="cal-month-field">
            <input type="hidden" name="cal_year" class="cal-year-field">
            <input type="hidden" name="cal_day" class="cal-day-field">
            <div class="edit-grid">
                <div class="edit-group full">
                    <label>Escalão / Equipa</label>
                    <select name="id_equipa_evento" id="editEventoEquipa" required>
                        <option value="">Selecionar equipa</option>
                        <?php foreach ($escaloesClube as $eq): ?>
                            <option value="<?= (int)$eq['id_equipa'] ?>">
                                <?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia'] . ' — ' . ($eq['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Tipo de evento</label>
                    <select name="tipo_evento" id="editEventoTipo" required>
                        <option value="">Selecionar</option>
                        <?php foreach (['Treino','Jogo','Reunião Técnico-Tática','Sessão de Recuperação','Convívio de Equipa','Outro'] as $te): ?>
                            <option value="<?= $te ?>"><?= $te ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Estado</label>
                    <select name="estado_evento" id="editEventoEstado" required>
                        <?php foreach (['Por realizar','Realizado','Cancelado','Adiado'] as $se): ?>
                            <option value="<?= $se ?>"><?= $se ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edit-group">
                    <label>Data</label>
                    <input type="date" name="data_evento" id="editEventoData" required>
                </div>
                <div class="edit-group">
                    <label>Hora (opcional)</label>
                    <input type="time" name="hora_evento" id="editEventoHora">
                </div>
                <div class="edit-group full">
                    <label>Local (opcional)</label>
                    <input type="text" name="local_evento" id="editEventoLocal">
                </div>
                <div class="edit-group full">
                    <label>Descrição (opcional)</label>
                    <input type="text" name="descricao_evento" id="editEventoDesc">
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalEditarEvento')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL CRIAR JOGADOR ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarJogador">
<div class="modal large">
    <div class="modal-header">
        <div class="modal-title">Adicionar Jogador</div>
        <button class="modal-close" type="button" onclick="closeModal('modalCriarJogador')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="criar_jogador">
        <div class="edit-grid">
            <div class="edit-group full"><label>Nome Completo *</label><input type="text" name="nome_completo" required></div>
            <div class="edit-group"><label>Alcunha</label><input type="text" name="alcunha_jogador"></div>
            <div class="edit-group"><label>Data de Nascimento *</label><input type="date" name="data_nascimento" required></div>
            <div class="edit-group"><label>Nacionalidade *</label><input type="text" name="nacionalidade" required></div>
            <div class="edit-group"><label>País de Nascimento</label><input type="text" name="pais_nascimento"></div>
            <div class="edit-group"><label>Posição Principal *</label>
                <select name="posicao_principal" required>
                    <option value="">Selecionar</option>
                    <?php foreach (['Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança'] as $pos): ?>
                    <option value="<?= $pos ?>"><?= $pos ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Posição Secundária</label>
                <select name="posicao_secundaria">
                    <option value="">Nenhuma</option>
                    <?php foreach (['Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança'] as $pos): ?>
                    <option value="<?= $pos ?>"><?= $pos ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Nº Camisola</label>
                <select name="numero"><option value="">—</option>
                <?php for($n=1;$n<=99;$n++): ?><option value="<?=$n?>"><?=$n?></option><?php endfor; ?>
                </select>
            </div>
            <div class="edit-group"><label>Pé Preferencial</label>
                <select name="pe_preferencial"><option value="">—</option>
                <?php foreach (['Direito','Esquerdo','Ambos'] as $pe): ?><option value="<?=$pe?>"><?=$pe?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Altura (cm)</label><input type="text" name="altura" maxlength="3" placeholder="ex: 178"></div>
            <div class="edit-group"><label>Peso (kg)</label><input type="text" name="peso" maxlength="3" placeholder="ex: 72"></div>
            <div class="edit-group full"><label>Equipa / Escalão *</label>
                <select name="id_equipa_jogador" required>
                    <option value="">Selecionar equipa</option>
                    <?php foreach ($escaloesClube as $eq): ?>
                    <option value="<?= (int)$eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Nome de Utilizador *</label><input type="text" name="nome_utilizador_jogador" minlength="3" maxlength="30" required></div>
            <div class="edit-group"><label>Password Inicial *</label><input type="password" name="password_jogador" required></div>
            <div class="edit-group full"><label>Email (opcional)</label><input type="email" name="email_jogador"></div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalCriarJogador')">Cancelar</button>
            <button class="btn-save" type="submit">Criar Jogador</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<!-- ══ MODAL PERFIL JOGADOR ══ -->
<div class="modal-backdrop" id="modalPerfilJogador">
<div class="modal large">
    <div class="modal-header">
        <div class="modal-title" id="playerProfileTitle">Perfil do Jogador</div>
        <button class="modal-close" type="button" onclick="closeModal('modalPerfilJogador')">×</button>
    </div>
    <div class="modal-tabs">
        <button class="modal-tab-btn active" onclick="switchModalTab(this,'ptab-info')">Info</button>
        <button class="modal-tab-btn" onclick="switchModalTab(this,'ptab-carreira')">Carreira</button>
        <button class="modal-tab-btn" onclick="switchModalTab(this,'ptab-lesoes')">Lesões</button>
    </div>
    <div class="modal-tab-panel active" id="ptab-info"><div id="playerInfoContent"></div></div>
    <div class="modal-tab-panel" id="ptab-carreira"><div id="playerCarreiraContent"></div></div>
    <div class="modal-tab-panel" id="ptab-lesoes"><div id="playerLesoesContent"></div></div>
    <div class="modal-actions" id="playerProfileActions"></div>
</div></div>

<!-- ══ MODAL EDITAR JOGADOR ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalEditarJogador">
<div class="modal large">
    <div class="modal-header">
        <div class="modal-title">Editar Jogador</div>
        <button class="modal-close" type="button" onclick="closeModal('modalEditarJogador')">×</button>
    </div>
    <form method="POST" id="formEditarJogador">
        <input type="hidden" name="acao" value="editar_jogador">
        <input type="hidden" name="id_jogador" id="editJogadorId">
        <div class="edit-grid">
            <div class="edit-group full"><label>Nome Completo *</label><input type="text" name="nome_completo" id="editJogNome" required></div>
            <div class="edit-group"><label>Alcunha</label><input type="text" name="alcunha_jogador" id="editJogAlcunha"></div>
            <div class="edit-group"><label>Data de Nascimento *</label><input type="date" name="data_nascimento" id="editJogData" required></div>
            <div class="edit-group"><label>Nacionalidade *</label><input type="text" name="nacionalidade" id="editJogNac" required></div>
            <div class="edit-group"><label>País de Nascimento</label><input type="text" name="pais_nascimento" id="editJogPais"></div>
            <div class="edit-group"><label>Posição Principal *</label>
                <select name="posicao_principal" id="editJogPos" required>
                    <?php foreach (['Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança'] as $pos): ?>
                    <option value="<?= $pos ?>"><?= $pos ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Posição Secundária</label>
                <select name="posicao_secundaria" id="editJogPosSec">
                    <option value="">Nenhuma</option>
                    <?php foreach (['Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança'] as $pos): ?>
                    <option value="<?= $pos ?>"><?= $pos ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Nº Camisola</label>
                <select name="numero" id="editJogNum"><option value="">—</option>
                <?php for($n=1;$n<=99;$n++): ?><option value="<?=$n?>"><?=$n?></option><?php endfor; ?>
                </select>
            </div>
            <div class="edit-group"><label>Pé Preferencial</label>
                <select name="pe_preferencial" id="editJogPe"><option value="">—</option>
                <?php foreach (['Direito','Esquerdo','Ambos'] as $pe): ?><option value="<?=$pe?>"><?=$pe?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Altura (cm)</label><input type="text" name="altura" id="editJogAltura" maxlength="3"></div>
            <div class="edit-group"><label>Peso (kg)</label><input type="text" name="peso" id="editJogPeso" maxlength="3"></div>
            <div class="edit-group full"><label>Equipa / Escalão *</label>
                <select name="id_equipa_jogador" id="editJogEquipa" required>
                    <?php foreach ($escaloesClube as $eq): ?>
                    <option value="<?= (int)$eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalEditarJogador')">Cancelar</button>
            <button class="btn-save" type="submit">Guardar</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<!-- ══ MODAL CRIAR COMPETIÇÃO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarCompeticao">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Criar Competição</div>
        <button class="modal-close" type="button" onclick="closeModal('modalCriarCompeticao')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="criar_competicao">
        <div class="edit-grid">
            <div class="edit-group full"><label>Nome da Competição *</label><input type="text" name="nome_competicao" required></div>
            <div class="edit-group"><label>Tipo *</label>
                <select name="tipo_competicao" required>
                    <?php foreach (['Liga','Taça','Torneio','Campeonato','Amigável','Outro'] as $tc): ?>
                    <option value="<?=$tc?>"><?=$tc?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Época</label><input type="text" name="epoca_competicao" placeholder="ex: 2025/2026"></div>
            <div class="edit-group"><label>Estado</label>
                <select name="estado_competicao">
                    <?php foreach (['A decorrer','Finalizada','Suspensa'] as $ec): ?>
                    <option value="<?=$ec?>"><?=$ec?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group full"><label>Equipa / Escalão *</label>
                <select name="id_equipa_competicao" required>
                    <option value="">Selecionar equipa</option>
                    <?php foreach ($escaloesClube as $eq): ?>
                    <option value="<?= (int)$eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group full"><label>Descrição (opcional)</label><input type="text" name="descricao_competicao"></div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalCriarCompeticao')">Cancelar</button>
            <button class="btn-save" type="submit">Criar</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<!-- ══ MODAL EDITAR COMPETIÇÃO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalEditarCompeticao">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Editar Competição</div>
        <button class="modal-close" type="button" onclick="closeModal('modalEditarCompeticao')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="editar_competicao">
        <input type="hidden" name="id_competicao" id="editCompId">
        <div class="edit-grid">
            <div class="edit-group full"><label>Nome *</label><input type="text" name="nome_competicao" id="editCompNome" required></div>
            <div class="edit-group"><label>Tipo</label>
                <select name="tipo_competicao" id="editCompTipo">
                    <?php foreach (['Liga','Taça','Torneio','Campeonato','Amigável','Outro'] as $tc): ?>
                    <option value="<?=$tc?>"><?=$tc?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group"><label>Época</label><input type="text" name="epoca_competicao" id="editCompEpoca"></div>
            <div class="edit-group full"><label>Estado</label>
                <select name="estado_competicao" id="editCompEstado">
                    <?php foreach (['A decorrer','Finalizada','Suspensa'] as $ec): ?>
                    <option value="<?=$ec?>"><?=$ec?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group full"><label>Descrição</label><input type="text" name="descricao_competicao" id="editCompDesc"></div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalEditarCompeticao')">Cancelar</button>
            <button class="btn-save" type="submit">Guardar</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<!-- ══ MODAL CRIAR JOGO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalCriarJogo">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Criar Jogo</div>
        <button class="modal-close" type="button" onclick="closeModal('modalCriarJogo')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="criar_jogo">
        <input type="hidden" name="id_competicao_jogo" id="criarJogoCompId">
        <div class="edit-grid">
            <div class="edit-group full"><label>Adversário *</label><input type="text" name="adversario" required></div>
            <div class="edit-group"><label>Data *</label><input type="date" name="data_jogo" required></div>
            <div class="edit-group"><label>Hora</label><input type="time" name="hora_jogo"></div>
            <div class="edit-group"><label>Local</label><input type="text" name="local_jogo"></div>
            <div class="edit-group"><label>Estado</label>
                <select name="estado_jogo">
                    <?php foreach (['Agendado','Realizado','Cancelado','Adiado'] as $ej): ?>
                    <option value="<?=$ej?>"><?=$ej?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="edit-group" style="align-self:end;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="casa_jogo" value="1" checked style="width:auto;border-radius:4px;">
                    Em casa
                </label>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalCriarJogo')">Cancelar</button>
            <button class="btn-save" type="submit">Criar Jogo</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<!-- ══ MODAL RESULTADO JOGO ══ -->
<?php if ($isAdminClube): ?>
<div class="modal-backdrop" id="modalResultadoJogo">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Editar Resultado</div>
        <button class="modal-close" type="button" onclick="closeModal('modalResultadoJogo')">×</button>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="resultado_jogo">
        <input type="hidden" name="id_jogo_resultado" id="resultadoJogoId">
        <div class="edit-grid">
            <div class="edit-group"><label>Nós</label><input type="number" name="resultado_nos" id="resultadoNos" min="0"></div>
            <div class="edit-group"><label>Adversário</label><input type="number" name="resultado_adv" id="resultadoAdv" min="0"></div>
            <div class="edit-group full"><label>Estado</label>
                <select name="estado_jogo_resultado" id="resultadoEstado">
                    <?php foreach (['Agendado','Realizado','Cancelado','Adiado'] as $ej): ?>
                    <option value="<?=$ej?>"><?=$ej?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="closeModal('modalResultadoJogo')">Cancelar</button>
            <button class="btn-save" type="submit">Guardar</button>
        </div>
    </form>
</div></div>
<?php endif; ?>


<!-- ══ MODAL PLANO VISUAL DE TREINO ══ -->
<div class="modal-backdrop" id="modalPlanoTreino">
    <div class="modal plano-modal">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="planoModalTitle">Fazer plano de treino</div>
                <div class="trainer-page-subtitle">Desenha cada exercício no campo, escreve a descrição e os objetivos, depois passa ao exercício seguinte.</div>
            </div>
            <button class="modal-close" type="button" onclick="fecharPlanoTreino()">×</button>
        </div>

        <form method="POST" id="planoTreinoForm" style="display:contents;">
            <input type="hidden" name="acao" id="planoTreinoAcao" value="criar_plano_treino">
            <input type="hidden" name="id_treino" id="planoTreinoId" value="0">
            <input type="hidden" name="plano_exercicios_json" id="planoExerciciosJson">

            <div class="plano-builder-layout">
                <aside class="plano-meta-panel">
                    <div class="plano-panel-title">Dados do treino</div>

                    <div class="edit-group full" style="margin-bottom:12px;">
                        <label>Equipa</label>
                        <select name="id_equipa_treino" id="planoEquipa" required>
                            <option value="">Selecionar equipa</option>
                            <?php foreach ($equipasTreinador as $eq): ?>
                                <option value="<?= (int)$eq['id_equipa'] ?>">
                                    <?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia'] . ' — ' . ($eq['época'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="edit-group full" style="margin-bottom:12px;"><label>Número do treino</label><input type="number" name="numero_treino" id="planoNumero" min="1" required></div>
                    <div class="edit-group full" style="margin-bottom:12px;"><label>Data</label><input type="date" name="data_treino" id="planoData" required></div>
                    <div class="edit-group full" style="margin-bottom:12px;"><label>Hora</label><input type="time" name="hora_treino" id="planoHora" required></div>
                    <div class="edit-group full" style="margin-bottom:12px;"><label>Conteúdo</label><input type="text" name="conteudo_treino" id="planoConteudo" placeholder="Ex: Finalização, posse, GR..." required></div>
                    <div class="edit-group full" style="margin-bottom:16px;"><label>Observações</label><input type="text" name="observacoes_treino" id="planoObservacoes" placeholder="Opcional"></div>

                    <div class="plano-panel-title">Exercícios</div>
                    <div class="plano-exercise-list" id="planoExerciseList"></div>
                    <button class="btn-plan" type="button" onclick="adicionarExercicioPlano()" style="width:100%;">+ Adicionar exercício</button>
                    <button class="btn-cancel" type="button" onclick="removerExercicioAtualPlano()" style="width:100%;margin-top:8px;">Apagar exercício atual</button>
                </aside>

                <section class="plano-canvas-panel">
                    <div class="plano-template-strip">
                        <button class="template-btn template-full has-preview active" type="button" data-template="campo_inteiro" title="Campo inteiro" onclick="selecionarTemplatePlano('campo_inteiro', this)">
                            <svg class="template-field-preview" viewBox="0 0 120 75" aria-hidden="true">
                                <rect x="6" y="6" width="108" height="63" fill="none" stroke="currentColor" stroke-width="3"/>
                                <line x1="60" y1="6" x2="60" y2="69" stroke="currentColor" stroke-width="2"/>
                                <circle cx="60" cy="37.5" r="11" fill="none" stroke="currentColor" stroke-width="2"/>
                                <rect x="6" y="22" width="20" height="31" fill="none" stroke="currentColor" stroke-width="2"/>
                                <rect x="6" y="29" width="9" height="17" fill="none" stroke="currentColor" stroke-width="2"/>
                                <circle cx="20" cy="37.5" r="1.8" fill="currentColor"/>
                                <rect x="94" y="22" width="20" height="31" fill="none" stroke="currentColor" stroke-width="2"/>
                                <rect x="105" y="29" width="9" height="17" fill="none" stroke="currentColor" stroke-width="2"/>
                                <circle cx="100" cy="37.5" r="1.8" fill="currentColor"/>
                            </svg>
                        </button>

                        <button class="template-btn template-area has-preview" type="button" data-template="area" title="Zona da área" onclick="selecionarTemplatePlano('area', this)">
                            <svg class="template-field-preview" viewBox="0 0 120 75" aria-hidden="true">
                                <rect x="6" y="6" width="108" height="63" fill="none" stroke="currentColor" stroke-width="3"/>
                                <rect x="6" y="17" width="50" height="41" fill="none" stroke="currentColor" stroke-width="2.5"/>
                                <rect x="6" y="28" width="24" height="19" fill="none" stroke="currentColor" stroke-width="2"/>
                                <circle cx="41" cy="37.5" r="2" fill="currentColor"/>
                                <path d="M56 24 A16 16 0 0 1 56 51" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>

                        <button class="template-btn template-futsal has-preview" type="button" data-template="futsal" title="Futsal" onclick="selecionarTemplatePlano('futsal', this)">
                            <svg class="template-field-preview" viewBox="0 0 120 75" aria-hidden="true">
                                <rect x="7" y="7" width="106" height="61" rx="8" fill="none" stroke="currentColor" stroke-width="3"/>
                                <line x1="60" y1="7" x2="60" y2="68" stroke="currentColor" stroke-width="2"/>
                                <circle cx="60" cy="37.5" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                                <path d="M7 23 A19 14 0 0 1 7 52" fill="none" stroke="currentColor" stroke-width="2"/>
                                <path d="M113 23 A19 14 0 0 0 113 52" fill="none" stroke="currentColor" stroke-width="2"/>
                                <rect x="7" y="31" width="6" height="13" fill="none" stroke="currentColor" stroke-width="2"/>
                                <rect x="107" y="31" width="6" height="13" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    </div>

                    <div class="plano-workspace">
                        <div class="plano-tool-column">
                            <button class="plano-tool-btn active" type="button" data-tool="select" onclick="setPlanoTool('select', this)" title="Mover/selecionar">↕</button>
                            <button class="plano-tool-btn" type="button" data-tool="player" onclick="setPlanoTool('player', this)" title="Jogador"><span class="plano-player-dot" style="background:#3b82f6;">1</span></button>
                            <button class="plano-tool-btn" type="button" data-tool="opponent" onclick="setPlanoTool('opponent', this)" title="Adversário"><span class="plano-player-dot" style="background:#ef4444;">1</span></button>
                            <button class="plano-tool-btn" type="button" data-tool="gk" onclick="setPlanoTool('gk', this)" title="Guarda-redes"><span class="plano-player-dot" style="background:#111827;font-size:10px;">GK</span></button>
                            <button class="plano-tool-btn" type="button" data-tool="cone" onclick="setPlanoTool('cone', this)" title="Cone">🔶</button>
                            <button class="plano-tool-btn" type="button" data-tool="ball" onclick="setPlanoTool('ball', this)" title="Bola">⚽</button>
                            <button class="plano-tool-btn" type="button" data-tool="barrier" onclick="setPlanoTool('barrier', this)" title="Barreira">▥</button>
                            <button class="plano-tool-btn" type="button" data-tool="goal" onclick="setPlanoTool('goal', this)" title="Baliza">▭</button>
                        </div>

                        <div class="plano-canvas-wrap"><canvas id="planoCanvas" width="980" height="610"></canvas></div>

                        <div class="plano-shape-column">
                            <div class="plano-tool-flyout" title="Linhas">
                                <button class="plano-tool-btn" id="planoLineMainBtn" type="button" data-tool="arrow" onclick="setPlanoLineTool(planoLineTool || 'arrow')" title="Linhas">↗</button>
                                <div class="plano-flyout-menu plano-line-menu" aria-label="Tipos de linha">
                                    <button class="plano-line-option active" type="button" data-line-tool="arrow" onclick="setPlanoLineTool('arrow', this)" title="Passe / seta">↗</button>
                                    <button class="plano-line-option" type="button" data-line-tool="line" onclick="setPlanoLineTool('line', this)" title="Linha simples">╱</button>
                                    <button class="plano-line-option" type="button" data-line-tool="run" onclick="setPlanoLineTool('run', this)" title="Corrida sem bola">〰</button>
                                    <button class="plano-line-option" type="button" data-line-tool="dash" onclick="setPlanoLineTool('dash', this)" title="Linha tracejada">┄</button>
                                </div>
                            </div>
                            <button class="plano-tool-btn" type="button" data-tool="rect" onclick="setPlanoTool('rect', this)" title="Retângulo">▭</button>
                            <button class="plano-tool-btn" type="button" data-tool="circle" onclick="setPlanoTool('circle', this)" title="Círculo">○</button>
                            <button class="plano-tool-btn" type="button" data-tool="text" onclick="setPlanoTool('text', this)" title="Texto">Aa</button>
                            <button class="plano-tool-btn" type="button" data-tool="eraser" onclick="setPlanoTool('eraser', this)" title="Apagar">🗑</button>
                        </div>
                    </div>

                    <div class="plano-bottom-toolbar">
                        <div class="plano-tool-flyout plano-color-flyout">
                            <button class="plano-tool-btn plano-color-main" id="planoColorMainBtn" type="button" title="Cores">
                                <span class="plano-color-swatch-main" id="planoColorSwatchMain"></span>
                                Cor
                            </button>
                            <div class="plano-flyout-menu plano-color-menu plano-color-palette" id="planoColorPalette">
                                <div class="plano-flyout-label">Escolher cor</div>
                            </div>
                        </div>
                        <button class="btn-cancel" type="button" onclick="desfazerPlanoObjeto()">Desfazer</button>
                        <button class="btn-cancel" type="button" onclick="limparPlanoCanvas()">Limpar desenho</button>
                        <div class="plano-floating-tip" style="width:100%;">Linhas, setas e figuras: clica e arrasta. Seleciona um objeto para mover, mudar cor, tamanho ou espessura.</div>
                    </div>
                </section>

                <aside class="plano-text-panel">
                    <div class="plano-panel-title" id="planoCurrentExerciseLabel">Exercício 1</div>
                    <div class="edit-group full" style="margin-bottom:14px;"><label>Título do exercício</label><input type="text" id="planoTituloExercicio" placeholder="Ex: Aquecimento técnico"></div>
                    <div class="edit-group full" style="margin-bottom:16px;"><label>Descrição</label><textarea id="planoDescricaoExercicio" placeholder="Explica como o exercício é feito, organização, rotações, número de séries, etc."></textarea></div>
                    <div class="edit-group full"><label>Objetivos / Indicações / Regras</label><textarea id="planoObjetivosExercicio" placeholder="Ex: trabalhar receção orientada, finalização, coordenação, intensidade, regras do exercício..."></textarea></div>
                </aside>
            </div>

            <div class="plano-modal-actions">
                <div class="plano-progress-text" id="planoProgressText">Exercício 1 de 1</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                    <button class="btn-cancel" type="button" onclick="exercicioAnteriorPlano()">Anterior</button>
                    <button class="btn-cancel" type="button" onclick="exercicioSeguintePlano()">Seguinte</button>
                    <button class="btn-save" type="submit">Guardar plano</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL VER PLANO DE TREINO ══ -->
<div class="modal-backdrop" id="modalVerPlanoTreino">
    <div class="modal plano-view-modal">
        <div class="modal-header">
            <div class="modal-title">Plano de treino</div>
            <button class="modal-close" type="button" onclick="closeModal('modalVerPlanoTreino')">×</button>
        </div>
        <div id="planoViewContent"></div>
        <div class="modal-actions">
            <button class="btn-cancel" type="button" onclick="window.print()">Imprimir</button>
            <button class="btn-plan" type="button" id="btnDescarregarPlanoPdf" onclick="descarregarPlanoTreinoPDF()">Descarregar PDF</button>
            <button class="btn-plan" type="button" id="btnEditarPlanoView">Editar plano</button>
            <button class="btn-save" type="button" onclick="closeModal('modalVerPlanoTreino')">Fechar</button>
        </div>
    </div>
</div>

<!-- ══ MODAL CRIAR TREINO ══ -->
<div class="modal-backdrop" id="modalCriarTreino">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Criar treino</div>
            <button class="modal-close" type="button" onclick="closeModal('modalCriarTreino')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="criar_treino">

            <div class="edit-grid">
                <div class="edit-group full">
                    <label>Equipa</label>
                    <select name="id_equipa_treino" required>
                        <option value="">Selecionar equipa</option>
                        <?php foreach ($equipasTreinador as $eq): ?>
                            <option value="<?= (int)$eq['id_equipa'] ?>">
                                <?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia'] . ' — ' . ($eq['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group">
                    <label>Número do treino</label>
                    <input type="number" name="numero_treino" min="1" required>
                </div>

                <div class="edit-group">
                    <label>Data</label>
                    <input type="date" name="data_treino" required>
                </div>

                <div class="edit-group">
                    <label>Hora</label>
                    <input type="time" name="hora_treino" required>
                </div>

                <div class="edit-group full">
                    <label>Conteúdo</label>
                    <input type="text" name="conteudo_treino" placeholder="Ex: Finalização, posse e bolas paradas" required>
                </div>

                <div class="edit-group full">
                    <label>Observações</label>
                    <input type="text" name="observacoes_treino" placeholder="Opcional">
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalCriarTreino')">Cancelar</button>
                <button class="btn-save" type="submit">Criar treino</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL FOLHA DE PRESENÇAS ══ -->
<div class="modal-backdrop" id="modalPresencasTreino">
    <div class="modal large">
        <div class="modal-header">
            <div class="modal-title" id="presencasTreinoTitulo">Folha de presenças</div>
            <button class="modal-close" type="button" onclick="closeModal('modalPresencasTreino')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="guardar_presencas">
            <input type="hidden" name="id_treino" id="presencasTreinoId">

            <p class="presenca-legend">"Presente", "Não presente justificado" e "Não presente injustificado" são mutuamente exclusivos. "Lesionado" pode ser combinado com qualquer um deles.</p>

            <div id="presencasTreinoLista" class="presenca-lista"></div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalPresencasTreino')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar presenças</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL EDITAR TREINO ══ -->
<div class="modal-backdrop" id="modalEditarTreino">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Editar treino</div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarTreino')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="editar_treino">
            <input type="hidden" name="id_treino" id="editTreinoId">

            <div class="edit-grid">
                <div class="edit-group full">
                    <label>Equipa</label>
                    <select name="id_equipa_treino" id="editTreinoEquipa" required>
                        <?php foreach ($equipasTreinador as $eq): ?>
                            <option value="<?= (int)$eq['id_equipa'] ?>">
                                <?= htmlspecialchars($eq['escalão'] . ' ' . $eq['hierarquia'] . ' — ' . ($eq['época'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="edit-group">
                    <label>Número do treino</label>
                    <input type="number" name="numero_treino" id="editTreinoNumero" min="1" required>
                </div>

                <div class="edit-group">
                    <label>Data</label>
                    <input type="date" name="data_treino" id="editTreinoData" required>
                </div>

                <div class="edit-group">
                    <label>Hora</label>
                    <input type="time" name="hora_treino" id="editTreinoHora" required>
                </div>

                <div class="edit-group full">
                    <label>Conteúdo</label>
                    <input type="text" name="conteudo_treino" id="editTreinoConteudo" required>
                </div>

                <div class="edit-group full">
                    <label>Observações</label>
                    <input type="text" name="observacoes_treino" id="editTreinoObservacoes">
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalEditarTreino')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL RESULTADO JOGO TREINADOR ══ -->
<div class="modal-backdrop" id="modalResultadoTreinador">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Atualizar resultado</div>
            <button class="modal-close" type="button" onclick="closeModal('modalResultadoTreinador')">×</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="atualizar_resultado_treinador">
            <input type="hidden" name="id_jogo_resultado" id="trainerResultadoJogoId">

            <div class="edit-grid">
                <div class="edit-group">
                    <label>Nós</label>
                    <input type="number" name="resultado_nos" id="trainerResultadoNos" min="0">
                </div>

                <div class="edit-group">
                    <label>Adversário</label>
                    <input type="number" name="resultado_adv" id="trainerResultadoAdv" min="0">
                </div>

                <div class="edit-group full">
                    <label>Estado</label>
                    <select name="estado_jogo_resultado" id="trainerResultadoEstado">
                        <?php foreach (['Agendado','Realizado','Cancelado','Adiado'] as $estadoJogo): ?>
                            <option value="<?= $estadoJogo ?>"><?= $estadoJogo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" type="button" onclick="closeModal('modalResultadoTreinador')">Cancelar</button>
                <button class="btn-save" type="submit">Guardar resultado</button>
            </div>
        </form>
    </div>
</div>

<script>
/* Tabs */
function switchTab(btn, panelId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

    btn.classList.add('active');

    const panel = document.getElementById(panelId);
    if (panel) {
        panel.classList.add('active');
    }

    const btnEditClube = document.getElementById('btnEditClube');

    if (btnEditClube) {
        btnEditClube.style.display = panelId === 'tab-info' ? 'flex' : 'none';
    }
}

/* Menu superior direito */
function toggleUserMenu(event) {
    event.stopPropagation();

    const menu = document.getElementById('userDropdown');
    if (menu) {
        menu.classList.toggle('active');
    }
}

function hideDashboardContent() {
    document.querySelectorAll('.card-header-actions, .tabs, .tab-panel, .alert').forEach(el => {
        if (el) {
            if (el.classList.contains('alert') && el.dataset.dismissed === '1') {
                return;
            }
            el.style.display = 'none';
        }
    });
}

function showDashboardContent() {
    document.querySelectorAll('.card-header-actions, .tabs, .tab-panel, .alert').forEach(el => {
        if (el) {
            if (el.classList.contains('alert') && el.dataset.dismissed === '1') {
                el.style.display = 'none';
                return;
            }
            el.style.display = '';
        }
    });
}

function setActiveSidebar(view) {
    document.querySelectorAll('#sidebar a[data-view]').forEach(a => {
        a.classList.toggle('active', a.dataset.view === view);
    });
}

function setLayoutLock(locked) {
    document.body.classList.toggle('layout-locked', locked);
}

function hideAllScreens() {
    ['profileScreen','notificationsScreen','messagesScreen','calendarScreen','treinosScreen','jogosScreen','campeonatoScreen','escaloesScreen','competicoesScreen'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'none'; el.classList.remove('visible'); }
    });
}

function showProfileScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('profileScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showNotificationsScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('notificationsScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showDashboard() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    showDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('clube');
}

function showMainMenu() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    showDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('home');
}

function showMessagesScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(true);
    setActiveSidebar('mensagens');
    const el = document.getElementById('messagesScreen');
    if (el) { el.style.display = ''; el.classList.add('visible'); }
    const thread = document.getElementById('messagesThread');
    if (thread) setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 50);
}

function showCalendarScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(true);
    setActiveSidebar('calendario');
    const el = document.getElementById('calendarScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showEscaloesScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('escaloes');
    const el = document.getElementById('escaloesScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showCompeticoesScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('competicoes');
    const el = document.getElementById('competicoesScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
    renderCompeticoes();
}


function showTreinosScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('treinos');
    const el = document.getElementById('treinosScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showJogosScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('jogos');
    const el = document.getElementById('jogosScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showCampeonatoScreen() {
    const dashboard = document.getElementById('dashboardCard');
    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar('campeonato');
    const el = document.getElementById('campeonatoScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function saveProfileChanges() {
    const form = document.getElementById('profileForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitProfileBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'A guardar...';
    }

    const formData = new FormData(form);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro ao guardar perfil');
        }

        if (submitBtn) {
            submitBtn.textContent = 'Guardado';
        }

        setTimeout(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Salvar alterações';
            }
        }, 1200);
    })
    .catch(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Tentar novamente';
        }
    });
}

function filterNotifications(filter = 'all') {
    const rows = document.querySelectorAll('.notification-row');

    rows.forEach(row => {
        if (!row) return;

        const state = row.dataset.state || 'Nao Lida';
        const shouldShow = filter === 'all' ||
            (filter === 'read' && state === 'Lida') ||
            (filter === 'unread' && state === 'Nao Lida');

        row.style.display = shouldShow ? '' : 'none';
    });
}

function applyNotificationState(row, state) {
    if (!row) return;

    row.dataset.state = state;
    row.classList.remove('read', 'unread');
    row.classList.add(state === 'Lida' ? 'read' : 'unread');
}

function applyCurrentNotificationFilter() {
    const activeTab = document.querySelector('.notification-tab.active');
    const text = activeTab ? activeTab.textContent.trim() : 'Geral';

    if (text === 'Lidas') {
        filterNotifications('read');
    } else if (text === 'Por ler') {
        filterNotifications('unread');
    } else {
        filterNotifications('all');
    }
}

function toggleNotificationState(idNotificacao, row) {
    if (!idNotificacao || !row) return;

    const currentState = row.dataset.state || 'Nao Lida';
    const nextState = currentState === 'Lida' ? 'Nao Lida' : 'Lida';

    const formData = new URLSearchParams();
    formData.append('acao', 'alterar_estado_notificacao');
    formData.append('id_notificacao', String(idNotificacao));
    formData.append('estado', nextState);

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: formData.toString()
    }).then(() => {
        applyNotificationState(row, nextState);
        applyCurrentNotificationFilter();
    }).catch(() => {
        applyNotificationState(row, nextState);
        applyCurrentNotificationFilter();
    });
}

const profileForm = document.getElementById('profileForm');
if (profileForm) {
    profileForm.addEventListener('submit', function (event) {
        event.preventDefault();
        exportAdjustedProfileImage();
        saveProfileChanges();
    });
}

const notificationTabs = document.querySelectorAll('.notification-tab');
notificationTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        notificationTabs.forEach(btn => btn.classList.remove('active'));
        tab.classList.add('active');

        const text = tab.textContent.trim();
        if (text === 'Lidas') {
            filterNotifications('read');
        } else if (text === 'Por ler') {
            filterNotifications('unread');
        } else {
            filterNotifications('all');
        }
    });
});

document.querySelectorAll('.notification-row').forEach(row => {
    row.addEventListener('click', () => {
        const id = row.dataset.id;
        if (id) {
            toggleNotificationState(id, row);
        }
    });

    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const id = row.dataset.id;
            if (id) {
                toggleNotificationState(id, row);
            }
        }
    });
});

/* Fechar menu superior ao clicar fora */
document.addEventListener('click', function () {
    const menu = document.getElementById('userDropdown');
    if (menu) {
        menu.classList.remove('active');
    }
});

/* Impedir que clicar dentro do menu o feche imediatamente */
const userDropdown = document.getElementById('userDropdown');
if (userDropdown) {
    userDropdown.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}

/* Modais */
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
    }
}

function closeAlert(buttonEl) {
    if (!buttonEl) return;
    const alertEl = buttonEl.closest('.alert');
    if (alertEl) {
        alertEl.dataset.dismissed = '1';
        alertEl.style.display = 'none';
    }
}

function showTopbarLogoFallback(imgEl) {
    if (!imgEl || !imgEl.parentElement) return;

    imgEl.style.display = 'none';

    if (!imgEl.parentElement.querySelector('.topbar-club-logo--placeholder')) {
        const fallback = document.createElement('span');
        fallback.className = 'topbar-club-logo--placeholder';
        fallback.textContent = '<?= htmlspecialchars($siglaClube) ?>';
        imgEl.parentElement.appendChild(fallback);
    }
}

function showClubLogoStatus(message) {
    const logo = document.getElementById('clubLogoImage');
    const status = document.getElementById('clubLogoStatus');

    if (logo) {
        logo.style.display = 'none';
    }
    if (status) {
        status.textContent = message;
        status.style.display = 'flex';
    }
}

const btnEditarFotoPerfil = document.getElementById('btnEditarFotoPerfil');
const fotoPerfilInput = document.getElementById('fotoPerfilInput');
const fotoPerfilErro = document.getElementById('fotoPerfilErro');
const fotoPerfilAjustadaInput = document.getElementById('fotoPerfilAjustadaInput');
const fotoPerfilZoom = document.getElementById('fotoPerfilZoom');
const fotoPerfilPosX = document.getElementById('fotoPerfilPosX');
const fotoPerfilPosY = document.getElementById('fotoPerfilPosY');
const profileAdjustTools = document.getElementById('profileAdjustTools');

let imagemOriginalParaAjuste = null;

function setFotoPerfilErro(msg) {
    if (!fotoPerfilErro) return;

    if (!msg) {
        fotoPerfilErro.style.display = 'none';
        fotoPerfilErro.textContent = '';
        return;
    }

    fotoPerfilErro.textContent = msg;
    fotoPerfilErro.style.display = 'block';
}

function applyPreviewTransform() {
    const preview = document.getElementById('profileAvatarPreview');
    if (!preview || !imagemOriginalParaAjuste) return;

    const zoom = Number(fotoPerfilZoom ? fotoPerfilZoom.value : 1);
    const x = Number(fotoPerfilPosX ? fotoPerfilPosX.value : 50);
    const y = Number(fotoPerfilPosY ? fotoPerfilPosY.value : 50);

    preview.style.objectFit = 'cover';
    preview.style.objectPosition = x + '% ' + y + '%';
    preview.style.transform = 'scale(' + zoom + ')';
    preview.style.transformOrigin = 'center center';
}

function exportAdjustedProfileImage() {
    if (!imagemOriginalParaAjuste || !fotoPerfilAjustadaInput) {
        if (fotoPerfilAjustadaInput) fotoPerfilAjustadaInput.value = '';
        return;
    }

    const zoom = Number(fotoPerfilZoom ? fotoPerfilZoom.value : 1);
    const x = Number(fotoPerfilPosX ? fotoPerfilPosX.value : 50) / 100;
    const y = Number(fotoPerfilPosY ? fotoPerfilPosY.value : 50) / 100;

    const canvasSize = 600;
    const canvas = document.createElement('canvas');
    canvas.width = canvasSize;
    canvas.height = canvasSize;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        fotoPerfilAjustadaInput.value = '';
        return;
    }

    const iw = imagemOriginalParaAjuste.naturalWidth || imagemOriginalParaAjuste.width;
    const ih = imagemOriginalParaAjuste.naturalHeight || imagemOriginalParaAjuste.height;

    if (!iw || !ih) {
        fotoPerfilAjustadaInput.value = '';
        return;
    }

    const baseScale = Math.max(canvasSize / iw, canvasSize / ih);
    const finalScale = baseScale * zoom;

    const drawW = iw * finalScale;
    const drawH = ih * finalScale;

    const extraX = Math.max(0, drawW - canvasSize);
    const extraY = Math.max(0, drawH - canvasSize);

    const offsetX = -extraX * x;
    const offsetY = -extraY * y;

    ctx.clearRect(0, 0, canvasSize, canvasSize);
    ctx.drawImage(imagemOriginalParaAjuste, offsetX, offsetY, drawW, drawH);

    fotoPerfilAjustadaInput.value = canvas.toDataURL('image/png', 0.92);
}

if (btnEditarFotoPerfil && fotoPerfilInput) {
    btnEditarFotoPerfil.addEventListener('click', () => {
        fotoPerfilInput.click();
    });

    fotoPerfilInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;

        setFotoPerfilErro('');

        const file = this.files[0];
        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!tiposPermitidos.includes(file.type)) {
            this.value = '';
            setFotoPerfilErro('Formato inválido. Usa JPG, PNG ou WEBP.');
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            this.value = '';
            setFotoPerfilErro('Imagem demasiado grande. Máximo: 2MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const preview = document.getElementById('profileAvatarPreview');
            const initial = document.getElementById('profileAvatarInitial');

            const tempImage = new Image();
            tempImage.onload = () => {
                imagemOriginalParaAjuste = tempImage;

                if (profileAdjustTools) {
                    profileAdjustTools.style.display = 'grid';
                }

                if (fotoPerfilZoom) fotoPerfilZoom.value = '1';
                if (fotoPerfilPosX) fotoPerfilPosX.value = '50';
                if (fotoPerfilPosY) fotoPerfilPosY.value = '50';

                applyPreviewTransform();
                exportAdjustedProfileImage();
            };
            tempImage.src = event.target.result;

            if (preview) {
                preview.src = event.target.result;
                preview.style.display = 'block';
            }

            if (initial) {
                initial.style.display = 'none';
            }
        };

        reader.readAsDataURL(this.files[0]);
    });
}

[fotoPerfilZoom, fotoPerfilPosX, fotoPerfilPosY].forEach((inputEl) => {
    if (!inputEl) return;

    inputEl.addEventListener('input', () => {
        applyPreviewTransform();
        exportAdjustedProfileImage();
    });
});

/* Fechar modal ao clicar fora */
document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

/* Sincronizar color picker */
const editColorPicker = document.getElementById('editColorPicker');
const editColorHex = document.getElementById('editColorHex');

if (editColorPicker && editColorHex) {
    editColorPicker.addEventListener('input', () => {
        editColorHex.value = editColorPicker.value.toUpperCase();
    });

    editColorHex.addEventListener('input', () => {
        if (/^#([0-9A-Fa-f]{6})$/.test(editColorHex.value)) {
            editColorPicker.value = editColorHex.value;
        }
    });
}

/* ══════════════════════════════════
   CALENDÁRIO
══════════════════════════════════ */
const eventosData = <?= json_encode($eventosCalendario, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

const eventosPorData = {};
eventosData.forEach(ev => {
    if (!eventosPorData[ev.data_evento]) eventosPorData[ev.data_evento] = [];
    eventosPorData[ev.data_evento].push(ev);
});

const EVENT_COLORS = {
    'Treino': '#2563eb',
    'Jogo': '#16a34a',
    'Reunião Técnico-Tática': '#9333ea',
    'Sessão de Recuperação': '#ea580c',
    'Convívio de Equipa': '#db2777',
    'Outro': '#6b7280'
};

const MESES_PT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

let calendarYear = new Date().getFullYear();
let calendarMonth = new Date().getMonth();
let selectedCalDate = null;
const isAdminClube = <?= $isAdminClube ? 'true' : 'false' ?>;

function renderCalendar() {
    const label = document.getElementById('calendarMonthLabel');
    if (label) label.textContent = MESES_PT[calendarMonth] + ' ' + calendarYear;

    const daysContainer = document.getElementById('calendarDays');
    if (!daysContainer) return;
    daysContainer.innerHTML = '';

    const firstDay = new Date(calendarYear, calendarMonth, 1).getDay();
    const daysInMonth = new Date(calendarYear, calendarMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(calendarYear, calendarMonth, 0).getDate();

    const today = new Date();
    const todayStr = today.getFullYear() + '-' +
                     String(today.getMonth() + 1).padStart(2, '0') + '-' +
                     String(today.getDate()).padStart(2, '0');

    for (let i = firstDay - 1; i >= 0; i--) {
        daysContainer.appendChild(createDayCell(calendarYear, calendarMonth - 1, daysInPrevMonth - i, true, todayStr));
    }
    for (let d = 1; d <= daysInMonth; d++) {
        daysContainer.appendChild(createDayCell(calendarYear, calendarMonth, d, false, todayStr));
    }
    const total = firstDay + daysInMonth;
    const remaining = total % 7 === 0 ? 0 : 7 - (total % 7);
    for (let d = 1; d <= remaining; d++) {
        daysContainer.appendChild(createDayCell(calendarYear, calendarMonth + 1, d, true, todayStr));
    }
}

function createDayCell(year, month, day, otherMonth, todayStr) {
    let m = month, y = year;
    if (m < 0)  { m += 12; y--; }
    if (m > 11) { m -= 12; y++; }
    const dateStr = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');

    const cell = document.createElement('div');
    cell.className = 'calendar-day' + (otherMonth ? ' other-month' : '');
    if (dateStr === todayStr) cell.classList.add('today');
    if (dateStr === selectedCalDate) cell.classList.add('selected');

    const num = document.createElement('span');
    num.className = 'calendar-day-num';
    num.textContent = day;
    cell.appendChild(num);

    const events = eventosPorData[dateStr] || [];
    if (events.length > 0) {
        const dots = document.createElement('div');
        dots.className = 'calendar-event-dots';
        events.slice(0, 5).forEach(ev => {
            const dot = document.createElement('span');
            dot.className = 'calendar-event-dot';
            dot.style.background = EVENT_COLORS[ev.tipo_evento] || '#6b7280';
            dots.appendChild(dot);
        });
        cell.appendChild(dots);
    }

    cell.addEventListener('click', () => selectCalendarDay(dateStr));
    return cell;
}

function selectCalendarDay(dateStr) {
    selectedCalDate = dateStr;
    renderCalendar();
    renderDayEvents(dateStr);
    syncCalMonthFields();
}

function renderDayEvents(dateStr) {
    const panel = document.getElementById('calendarDayEvents');
    const title = document.getElementById('calendarPanelTitle');
    if (!panel || !title) return;

    const [y, m, d] = dateStr.split('-').map(Number);
    const dateObj = new Date(y, m - 1, d);
    const dayNames = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    title.textContent = dayNames[dateObj.getDay()] + ', ' + d + ' de ' + MESES_PT[m - 1] + ' de ' + y;

    const events = eventosPorData[dateStr] || [];
    if (events.length === 0) {
        panel.innerHTML = '<div class="calendar-empty-day">Sem eventos neste dia.</div>';
        return;
    }

    panel.innerHTML = events.map(ev => {
        const color = EVENT_COLORS[ev.tipo_evento] || '#6b7280';
        const hora  = ev.hora_evento ? ev.hora_evento.substring(0, 5) : '';
        const local = ev.local_evento || '';
        const desc  = ev.descricao_evento || '';

        const adminActions = isAdminClube ? `
            <div class="calendar-event-actions">
                <button class="btn-row-edit" type="button" title="Editar" onclick="openEditEventoModal(${ev.id_evento})">✎</button>
                <form method="POST" action="index-treinador.php?view=calendario" style="display:inline;"
                      onsubmit="return confirm('Remover este evento?');">
                    <input type="hidden" name="acao" value="remover_evento">
                    <input type="hidden" name="id_evento" value="${ev.id_evento}">
                    <input type="hidden" name="cal_month" value="${calendarMonth + 1}">
                    <input type="hidden" name="cal_year" value="${calendarYear}">
                    <input type="hidden" name="cal_day" value="${selectedCalDate || ''}">
                    <button class="btn-row-edit btn-row-delete" type="submit" title="Remover">×</button>
                </form>
            </div>` : '';

        return `<div class="calendar-event-item">
            <div class="calendar-event-type" style="color:${color}">${escCal(ev.tipo_evento)}</div>
            <div class="calendar-event-team">${escCal(ev.escalão + ' ' + ev.hierarquia)}</div>
            ${desc ? `<div class="calendar-event-desc">${escCal(desc)}</div>` : ''}
            <div class="calendar-event-meta">
                ${hora  ? `<span>🕐 ${hora}</span>` : ''}
                ${local ? `<span>📍 ${escCal(local)}</span>` : ''}
                <span style="margin-left:auto;font-weight:600;">${escCal(ev.estado_evento)}</span>
            </div>
            ${adminActions}
        </div>`;
    }).join('');
}

function escCal(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function calendarPrev() {
    calendarMonth--;
    if (calendarMonth < 0) { calendarMonth = 11; calendarYear--; }
    renderCalendar();
    if (selectedCalDate) renderDayEvents(selectedCalDate);
}

function calendarNext() {
    calendarMonth++;
    if (calendarMonth > 11) { calendarMonth = 0; calendarYear++; }
    renderCalendar();
    if (selectedCalDate) renderDayEvents(selectedCalDate);
}

function syncCalMonthFields() {
    document.querySelectorAll('.cal-month-field').forEach(f => f.value = calendarMonth + 1);
    document.querySelectorAll('.cal-year-field').forEach(f  => f.value = calendarYear);
    document.querySelectorAll('.cal-day-field').forEach(f   => f.value = selectedCalDate || '');
}

function openEditEventoModal(idEvento) {
    const ev = eventosData.find(e => e.id_evento == idEvento);
    if (!ev) return;
    document.getElementById('editEventoId').value     = ev.id_evento;
    document.getElementById('editEventoEquipa').value  = ev.id_equipa;
    document.getElementById('editEventoTipo').value    = ev.tipo_evento;
    document.getElementById('editEventoEstado').value  = ev.estado_evento;
    document.getElementById('editEventoData').value    = ev.data_evento;
    document.getElementById('editEventoHora').value    = ev.hora_evento ? ev.hora_evento.substring(0, 5) : '';
    document.getElementById('editEventoLocal').value   = ev.local_evento || '';
    document.getElementById('editEventoDesc').value    = ev.descricao_evento || '';
    syncCalMonthFields();
    openModal('modalEditarEvento');
}

function validarFormEvento(form) {
    const estado = form.querySelector('[name="estado_evento"]')?.value;
    const data   = form.querySelector('[name="data_evento"]')?.value;
    if (estado === 'Por realizar' && data) {
        const hoje = new Date().toISOString().slice(0, 10);
        if (data < hoje) {
            alert('Eventos "Por realizar" devem ter data atual ou futura.');
            return false;
        }
    }
    syncCalMonthFields();
    return true;
}

/* Inicializar calendário e tratar estado inicial */
document.addEventListener('DOMContentLoaded', function () {
    /* Restaurar mês/ano/dia a partir dos parâmetros GET (após redirect PRG de evento) */
    const urlParams  = new URLSearchParams(window.location.search);
    const pMonth = parseInt(urlParams.get('cal_month'));
    const pYear  = parseInt(urlParams.get('cal_year'));
    const pDay   = urlParams.get('cal_day') || '';

    if (pMonth >= 1 && pMonth <= 12) calendarMonth = pMonth - 1;
    if (pYear  >= 2000)              calendarYear  = pYear;

    renderCalendar();

    const today = new Date();
    const todayStr = today.getFullYear() + '-' +
                     String(today.getMonth() + 1).padStart(2, '0') + '-' +
                     String(today.getDate()).padStart(2, '0');

    /* Restaurar dia selecionado ou usar hoje */
    if (pDay) {
        selectCalendarDay(pDay);
    } else if (pMonth >= 1 && pMonth <= 12) {
        const firstDay = calendarYear + '-' + String(calendarMonth + 1).padStart(2, '0') + '-01';
        selectCalendarDay(firstDay);
    } else {
        selectCalendarDay(todayStr);
    }

    /* Data por omissão no form de criar evento */
    const formCriar = document.getElementById('formCriarEvento');
    if (formCriar) {
        const dataInput = formCriar.querySelector('[name="data_evento"]');
        if (dataInput && !dataInput.value) dataInput.value = todayStr;
    }

    /* Definir sidebar activa e modo de ecrã conforme a view inicial */
    <?php if ($mostrarMensagens): ?>
    hideDashboardContent();
    setLayoutLock(true);
    setActiveSidebar('mensagens');
    const thread = document.getElementById('messagesThread');
    if (thread) setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 50);
    <?php elseif (($_GET['view'] ?? '') === 'calendario'): ?>
    showCalendarScreen();
    <?php elseif (($_GET['view'] ?? '') === 'jogos'): ?>
    showJogosScreen();
    <?php elseif (($_GET['view'] ?? '') === 'campeonato'): ?>
    showCampeonatoScreen();
    <?php elseif (($_GET['view'] ?? '') === 'treinos'): ?>
    showTreinosMenu();
    <?php else: ?>
    showMainScreen();
    <?php endif; ?>
});

/* ══════════════════════════════════
   ESCALÕES — JOGADORES
══════════════════════════════════ */
const jogadoresData = <?= json_encode(array_merge(...array_values($jogadoresPorEquipa ?: [[]])), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

function selectEscalao(btn, idEquipa) {
    document.querySelectorAll('.escalao-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[id^="players-equipa-"]').forEach(el => el.style.display = 'none');
    const target = document.getElementById('players-equipa-' + idEquipa);
    if (target) target.style.display = 'block';
}

function selectMenuJogadores(btn, idEquipa) {
    document.querySelectorAll('.menu-jogadores-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[id^="menu-players-equipa-"]').forEach(el => el.style.display = 'none');
    const target = document.getElementById('menu-players-equipa-' + idEquipa);
    if (target) target.style.display = 'block';
}

function switchModalTab(btn, panelId) {
    btn.closest('.modal').querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
    btn.closest('.modal').querySelectorAll('.modal-tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById(panelId);
    if (panel) panel.classList.add('active');
}

function openPlayerProfile(idJogador) {
    const jog = jogadoresData.find(j => j.id_jogador == idJogador);
    if (!jog) return;

    document.getElementById('playerProfileTitle').textContent = jog.alcunha_jogador || jog.nome_completo;

    /* Tab Info */
    const anos = jog.data_nascimento ? Math.floor((Date.now() - new Date(jog.data_nascimento)) / 31557600000) : '—';
    document.getElementById('playerInfoContent').innerHTML = `
        <div class="info-grid-2">
            <span class="lbl">Nome Completo</span><span class="val">${esc(jog.nome_completo)}</span>
            <span class="lbl">Alcunha</span><span class="val">${esc(jog.alcunha_jogador) || '—'}</span>
            <span class="lbl">Posição Principal</span><span class="val">${esc(jog.posição_principal)}</span>
            <span class="lbl">Posição Secundária</span><span class="val">${esc(jog.posição_secundária) || '—'}</span>
            <span class="lbl">Nº Camisola</span><span class="val">${esc(jog.número_favorito) || '—'}</span>
            <span class="lbl">Pé Preferencial</span><span class="val">${esc(jog.pé_preferencial) || '—'}</span>
            <span class="lbl">Data de Nascimento</span><span class="val">${esc(jog.data_nascimento)} (${anos} anos)</span>
            <span class="lbl">Nacionalidade</span><span class="val">${esc(jog.nacionalidade)}</span>
            <span class="lbl">País de Nascimento</span><span class="val">${esc(jog.país_nascimento) || '—'}</span>
            <span class="lbl">Altura</span><span class="val">${jog.altura ? jog.altura + ' cm' : '—'}</span>
            <span class="lbl">Peso</span><span class="val">${jog.peso ? jog.peso + ' kg' : '—'}</span>
        </div>`;

    /* Fetch carreira e lesões via AJAX */
    fetch('index-treinador.php?ajax=jogador_detalhe&id=' + idJogador)
        .then(r => r.json())
        .then(data => {
            const carreira = data.carreira || [];
            const lesoes   = data.lesoes   || [];
            document.getElementById('playerCarreiraContent').innerHTML = carreira.length
                ? `<table class="historial-table"><thead><tr><th>Época</th><th>Clube</th><th>Jogos</th><th>Golos</th><th>Assist.</th></tr></thead><tbody>
                    ${carreira.map(c=>`<tr><td>${esc(c.epoca)}</td><td>${esc(c.clube)}</td><td>${c.jogos}</td><td>${c.golos_marcados}</td><td>${c.assistências}</td></tr>`).join('')}
                   </tbody></table>`
                : '<p style="color:#9aa0ae;padding:16px 0;text-align:center;">Sem histórico de carreira.</p>';

            document.getElementById('playerLesoesContent').innerHTML = lesoes.length
                ? `<table class="historial-table"><thead><tr><th>Lesão</th><th>Tipo</th><th>Recuperação</th><th>Estado</th></tr></thead><tbody>
                    ${lesoes.map(l=>`<tr><td>${esc(l.nome_lesão)}</td><td>${esc(l.tipo_lesão)}</td><td>${esc(l.tempo_recuperação)}</td><td>${esc(l.estado_lesão)}</td></tr>`).join('')}
                   </tbody></table>`
                : '<p style="color:#9aa0ae;padding:16px 0;text-align:center;">Sem lesões registadas.</p>';
        }).catch(() => {});

    /* Botões de acção (admin) */
    const adminHtml = <?= $isAdminClube ? 'true' : 'false' ?>;
    document.getElementById('playerProfileActions').innerHTML = adminHtml
        ? `<button class="btn-cancel" type="button" onclick="closeModal('modalPerfilJogador')">Fechar</button>
           <button class="btn-save" type="button" onclick="openEditJogador(${idJogador})">Editar</button>
           <form method="POST" style="display:inline;" onsubmit="return confirm('Remover jogador?');">
               <input type="hidden" name="acao" value="remover_jogador">
               <input type="hidden" name="id_jogador" value="${idJogador}">
               <button class="btn-remove" type="submit">Remover</button>
           </form>`
        : `<button class="btn-cancel" type="button" onclick="closeModal('modalPerfilJogador')">Fechar</button>`;

    openModal('modalPerfilJogador');
}

function openEditJogador(idJogador) {
    const jog = jogadoresData.find(j => j.id_jogador == idJogador);
    if (!jog) return;
    closeModal('modalPerfilJogador');
    document.getElementById('editJogadorId').value  = jog.id_jogador;
    document.getElementById('editJogNome').value    = jog.nome_completo;
    document.getElementById('editJogAlcunha').value = jog.alcunha_jogador || '';
    document.getElementById('editJogData').value    = jog.data_nascimento || '';
    document.getElementById('editJogNac').value     = jog.nacionalidade   || '';
    document.getElementById('editJogPais').value    = jog.país_nascimento || '';
    document.getElementById('editJogPos').value     = jog.posição_principal || '';
    document.getElementById('editJogPosSec').value  = jog.posição_secundária || '';
    document.getElementById('editJogNum').value     = jog.número_favorito || '';
    document.getElementById('editJogPe').value      = jog.pé_preferencial || '';
    document.getElementById('editJogAltura').value  = jog.altura || '';
    document.getElementById('editJogPeso').value    = jog.peso   || '';
    document.getElementById('editJogEquipa').value  = jog.id_equipa || '';
    openModal('modalEditarJogador');
}

function esc(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══════════════════════════════════
   TREINOS / JOGOS TREINADOR
══════════════════════════════════ */
const treinosTreinadorData = <?= json_encode($treinosTreinador, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const jogosTreinadorData = <?= json_encode($jogosTreinador, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const exerciciosPorTreinoData = <?= json_encode($exerciciosPorTreino, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const equipasTreinadorData = <?= json_encode($equipasTreinador, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const presencasTreinadorData = <?= json_encode($presencasPorTreino, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

const ESTADOS_PRESENCA = [
    ['Presente', 'Presente'],
    ['Não Presente Justificado', 'Não presente (justificado)'],
    ['Não Presente Injustificado', 'Não presente (injustificado)']
];

function abrirPresencasTreino(idTreino) {
    const treino = treinosTreinadorData.find(t => String(t.id_treino) === String(idTreino));
    if (!treino) return;

    const jogadoresEquipa = jogadoresData.filter(j => String(j.id_equipa) === String(treino.id_equipa));
    const presencasTreino = presencasTreinadorData[idTreino] || {};

    document.getElementById('presencasTreinoId').value = treino.id_treino;
    document.getElementById('presencasTreinoTitulo').textContent = 'Folha de presenças — Treino #' + treino.numero_treino + ' (' + treino.data_treino + ')';

    let html = '';
    if (!jogadoresEquipa.length) {
        html = '<p class="empty-state">Esta equipa ainda não tem jogadores.</p>';
    } else {
        jogadoresEquipa.forEach(jog => {
            const atual = presencasTreino[jog.id_jogador] || {};
            const estadoAtual = atual.estado_presenca || 'Presente';
            const lesionadoAtual = Number(atual.lesionado) === 1;
            const campo = 'presenca[' + jog.id_jogador + ']';

            const opcoesHtml = ESTADOS_PRESENCA.map(([valor, label]) => `
                <label class="presenca-opcao">
                    <input type="radio" name="${campo}[estado]" value="${valor}" ${estadoAtual === valor ? 'checked' : ''}>
                    ${label}
                </label>
            `).join('');

            html += `
                <div class="presenca-row">
                    <div class="presenca-row-nome">${esc(jog.alcunha_jogador || jog.nome_completo)}</div>
                    <div class="presenca-row-opcoes">
                        ${opcoesHtml}
                        <label class="presenca-opcao presenca-opcao-lesionado">
                            <input type="checkbox" name="${campo}[lesionado]" value="1" ${lesionadoAtual ? 'checked' : ''}>
                            Lesionado
                        </label>
                    </div>
                    <input type="text" class="presenca-row-obs" name="${campo}[observacoes]" placeholder="Observações" value="${esc(atual.observacoes || '')}">
                </div>
            `;
        });
    }

    document.getElementById('presencasTreinoLista').innerHTML = html;
    openModal('modalPresencasTreino');
}

function openEditTreinoModal(idTreino) {
    const treino = treinosTreinadorData.find(t => String(t.id_treino) === String(idTreino));
    if (!treino) return;

    document.getElementById('editTreinoId').value = treino.id_treino;
    document.getElementById('editTreinoEquipa').value = treino.id_equipa;
    document.getElementById('editTreinoNumero').value = treino.numero_treino;
    document.getElementById('editTreinoData').value = treino.data_treino;
    document.getElementById('editTreinoHora').value = (treino.hora_treino || '').substring(0, 5);
    document.getElementById('editTreinoConteudo').value = treino.conteudo_treino || '';
    document.getElementById('editTreinoObservacoes').value = treino.observacoes_treino || '';

    openModal('modalEditarTreino');
}

function openResultadoTreinadorModal(idJogo) {
    const jogo = jogosTreinadorData.find(j => String(j.id_jogo) === String(idJogo));
    if (!jogo) return;

    document.getElementById('trainerResultadoJogoId').value = jogo.id_jogo;
    document.getElementById('trainerResultadoNos').value = jogo.resultado_nos !== null ? jogo.resultado_nos : '';
    document.getElementById('trainerResultadoAdv').value = jogo.resultado_adv !== null ? jogo.resultado_adv : '';
    document.getElementById('trainerResultadoEstado').value = jogo.estado || 'Agendado';

    openModal('modalResultadoTreinador');
}


/* ══════════════════════════════════
   PLANO VISUAL DE TREINO — V2
══════════════════════════════════ */
const PLANO_BASE_W = 980;
const PLANO_BASE_H = 610;
const planoColors = [
    '#000000','#ffffff','#ef4444','#dc2626','#b91c1c','#f97316','#f59e0b','#facc15',
    '#84cc16','#22c55e','#16a34a','#0f766e','#06b6d4','#2563eb','#1d4ed8','#3730a3',
    '#7c3aed','#c026d3','#ec4899','#64748b','#94a3b8','#cbd5e1'
];
let planoTool = 'select';
let planoLineTool = 'arrow';
let planoColor = '#000000';
let planoCanvas = null;
let planoObjects = [];
let planoTemplate = 'campo_inteiro';
let planoExercises = [];
let planoCurrentIndex = 0;
let planoDragging = null;
let planoPreviewObject = null;
let planoSelectedIndex = -1;
let planoPlayerCounter = 1;
let planoOpponentCounter = 1;
let planoIsPointerDown = false;

function setupPlanoCanvasOnce() {
    planoCanvas = document.getElementById('planoCanvas');
    if (!planoCanvas || planoCanvas.dataset.v2Ready === '1') return;
    planoCanvas.dataset.v2Ready = '1';
    planoCanvas.addEventListener('mousedown', planoCanvasMouseDown);
    planoCanvas.addEventListener('mousemove', planoCanvasMouseMove);
    planoCanvas.addEventListener('mouseup', planoCanvasMouseUp);
    planoCanvas.addEventListener('mouseleave', planoCanvasMouseUp);
    planoCanvas.addEventListener('dblclick', planoCanvasDoubleClick);
    planoCanvas.addEventListener('wheel', planoCanvasWheel, {passive:false});
    planoCanvas.addEventListener('contextmenu', ev => ev.preventDefault());
    initPlanoToolIcons();
    initPlanoPropertyPanel();
    atualizarCursorPlano();
}

function initPlanoToolIcons() {
    const set = (tool, html) => {
        const btn = document.querySelector(`.plano-tool-btn[data-tool="${tool}"]`);
        if (btn) btn.innerHTML = html;
    };
    set('cone', `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 5 8 33h24L20 5z" fill="#f97316" stroke="#111827" stroke-width="2"/><path d="M14 22h12" stroke="#fff" stroke-width="3"/><path d="M11 32h18" stroke="#111827" stroke-width="3"/></svg>`);
    set('barrier', `<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="9" y="8" width="22" height="24" rx="2" fill="#e5e7eb" stroke="#111827" stroke-width="2"/><path d="M14 9v22M20 9v22M26 9v22" stroke="#64748b" stroke-width="2"/><path d="M8 16h24M8 24h24" stroke="#111827" stroke-width="2"/></svg>`);
    set('ball', `<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="13" fill="#fff" stroke="#111827" stroke-width="2.4"/><polygon points="20,13 26,18 24,25 16,25 14,18" fill="#111827"/><path d="M20 13V8M26 18l6-2M24 25l4 5M16 25l-4 5M14 18l-6-2" stroke="#111827" stroke-width="1.5"/></svg>`);
    set('goal', `<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="7" y="13" width="26" height="14" fill="none" stroke="#111827" stroke-width="3"/><path d="M12 14v12M18 14v12M24 14v12M31 18H8M31 23H8" stroke="#94a3b8" stroke-width="1.2"/></svg>`);
    set('arrow', `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M9 31 30 10" fill="none" stroke="#111827" stroke-width="3" stroke-linecap="round"/><path d="M19 10h11v11" fill="none" stroke="#111827" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>`);
    set('run', `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M7 28c7-12 13 10 20-2 2-4 3-7 7-10" fill="none" stroke="#111827" stroke-width="3" stroke-linecap="round"/><path d="M26 13h8v8" fill="none" stroke="#111827" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`);
    set('dash', `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 31 32 8" fill="none" stroke="#111827" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 5"/></svg>`);
    set('rect', `<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="9" y="12" width="22" height="16" fill="none" stroke="#111827" stroke-width="2.6"/></svg>`);
    set('circle', `<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="11" fill="none" stroke="#111827" stroke-width="2.6"/></svg>`);
}

function initPlanoPropertyPanel() {
    if (document.getElementById('planoSelectedPanel')) return;
    const objetivos = document.getElementById('planoObjetivosExercicio');
    if (!objetivos) return;
    const host = objetivos.closest('.edit-group') || objetivos.parentElement;
    const panel = document.createElement('div');
    panel.className = 'plano-selected-panel';
    panel.id = 'planoSelectedPanel';
    panel.innerHTML = `
        <div class="plano-selected-head">
            <strong id="planoSelectedName">Objeto selecionado</strong>
            <span id="planoSelectedType">—</span>
        </div>
        <div class="plano-prop-row">
            <label for="planoSizeRange">Tamanho</label>
            <input id="planoSizeRange" type="range" min="0.4" max="3" step="0.05" value="1">
        </div>
        <div class="plano-prop-row">
            <label for="planoStrokeRange">Espessura da linha</label>
            <input id="planoStrokeRange" type="range" min="1" max="12" step="1" value="4">
        </div>
        <div class="plano-mini-actions">
            <button class="soft" type="button" onclick="duplicarObjetoPlanoSelecionado()">Duplicar</button>
            <button class="danger" type="button" onclick="apagarObjetoPlanoSelecionado()">Apagar</button>
        </div>
        <div class="plano-tool-hint">Seleciona e arrasta para mover. Usa a roda do rato para aumentar/diminuir.</div>
    `;
    host.insertAdjacentElement('afterend', panel);
    document.getElementById('planoSizeRange').addEventListener('input', ev => alterarTamanhoObjetoPlano(ev.target.value));
    document.getElementById('planoStrokeRange').addEventListener('input', ev => alterarEspessuraObjetoPlano(ev.target.value));
}

function initPlanoColorPalette() {
    const palette = document.getElementById('planoColorPalette');
    if (!palette) return;
    palette.dataset.ready = '1';
    palette.innerHTML = '<div class="plano-flyout-label">Escolher cor</div>' + planoColors.map((c) => `
        <button type="button" class="plano-color-choice ${c === planoColor ? 'active' : ''}"
                style="background:${c};${c === '#ffffff' ? 'box-shadow:inset 0 0 0 2px #cbd5e1;' : ''}"
                onclick="setPlanoColor('${c}', this)" title="${c}"></button>`).join('');
    atualizarPlanoColorButton();
}

function atualizarPlanoColorButton() {
    const swatch = document.getElementById('planoColorSwatchMain');
    if (swatch) swatch.style.background = planoColor;
}

function setPlanoColor(color, btn) {
    planoColor = color;
    document.querySelectorAll('.plano-color-choice').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    atualizarPlanoColorButton();
    if (planoSelectedIndex >= 0 && planoObjects[planoSelectedIndex]) {
        const o = planoObjects[planoSelectedIndex];
        if (o.type !== 'ball') {
            o.color = color;
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
    }
}

function planoLineIcon(mode) {
    const icons = {
        arrow: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M9 31 30 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M19 10h11v11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        line: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 32 32 8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>`,
        run: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M7 28c7-12 13 10 20-2 2-4 3-7 7-10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M26 13h8v8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        dash: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 32 32 8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 5"/></svg>`
    };
    return icons[mode] || icons.arrow;
}

function atualizarPlanoLineButton() {
    const main = document.getElementById('planoLineMainBtn');
    if (main) {
        main.innerHTML = planoLineIcon(planoLineTool);
        main.dataset.tool = planoLineTool;
    }
    document.querySelectorAll('.plano-line-option').forEach(b => {
        b.classList.toggle('active', b.dataset.lineTool === planoLineTool);
        b.innerHTML = planoLineIcon(b.dataset.lineTool);
    });
}

function setPlanoLineTool(tool, btn = null) {
    planoLineTool = tool || 'arrow';
    atualizarPlanoLineButton();
    setPlanoTool(planoLineTool, document.getElementById('planoLineMainBtn'));
}

function setPlanoTool(tool, btn) {
    planoTool = tool;
    planoDragging = null;
    planoPreviewObject = null;
    if (tool !== 'select') selecionarObjetoPlano(-1);
    document.querySelectorAll('.plano-tool-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    if (['line','arrow','run','dash'].includes(tool)) {
        planoLineTool = tool;
        atualizarPlanoLineButton();
        const main = document.getElementById('planoLineMainBtn');
        if (main) main.classList.add('active');
    }
    atualizarCursorPlano();
    desenharPlano();
}

function atualizarCursorPlano() {
    if (!planoCanvas) return;
    planoCanvas.style.cursor = planoTool === 'select' ? 'move' : (planoTool === 'eraser' ? 'not-allowed' : 'crosshair');
}

function selecionarTemplatePlano(template, btn) {
    planoTemplate = template;
    document.querySelectorAll('.template-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function normalizarPontoCanvas(ev, canvas = planoCanvas) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: ((ev.clientX - rect.left) / rect.width) * PLANO_BASE_W,
        y: ((ev.clientY - rect.top) / rect.height) * PLANO_BASE_H
    };
}

function planoCanvasMouseDown(ev) {
    if (!planoCanvas) return;
    planoIsPointerDown = true;
    const p = normalizarPontoCanvas(ev);

    if (planoTool === 'select') {
        const idx = encontrarObjetoPlano(p.x, p.y);
        selecionarObjetoPlano(idx);
        if (idx >= 0) {
            const obj = planoObjects[idx];
            planoDragging = {mode:'move', idx, start:p, original:JSON.parse(JSON.stringify(obj))};
        }
        return;
    }

    if (planoTool === 'eraser') {
        const idx = encontrarObjetoPlano(p.x, p.y);
        if (idx >= 0) {
            planoObjects.splice(idx, 1);
            selecionarObjetoPlano(-1);
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
        return;
    }

    if (['line','arrow','run','dash'].includes(planoTool)) {
        planoDragging = {mode:'draw-line', tool:planoTool, start:p};
        planoPreviewObject = {type:'line',mode:planoTool,x1:p.x,y1:p.y,x2:p.x,y2:p.y,color:planoColor,w:4};
        desenharPlano();
        return;
    }

    if (['rect','circle'].includes(planoTool)) {
        planoDragging = {mode:'draw-shape', tool:planoTool, start:p};
        planoPreviewObject = criarShapePlano(planoTool, p, p);
        desenharPlano();
        return;
    }

    if (planoTool === 'text') {
        const txt = prompt('Texto a adicionar:', 'Texto');
        if (txt === null || txt.trim() === '') return;
        const obj = {type:'text',x:p.x,y:p.y,text:txt.trim(),color:planoColor,size:24,scale:1};
        planoObjects.push(obj);
        selecionarObjetoPlano(planoObjects.length - 1);
        desenharPlano();
        persistirExercicioAtualPlano(false);
        return;
    }

    const obj = criarObjetoPontualPlano(planoTool, p);
    if (obj) {
        planoObjects.push(obj);
        selecionarObjetoPlano(planoObjects.length - 1);
        desenharPlano();
        persistirExercicioAtualPlano(false);
    }
}

function planoCanvasMouseMove(ev) {
    if (!planoCanvas || !planoDragging) return;
    const p = normalizarPontoCanvas(ev);

    if (planoDragging.mode === 'move') {
        const obj = planoObjects[planoDragging.idx];
        const original = planoDragging.original;
        if (!obj || !original) return;
        const dx = p.x - planoDragging.start.x;
        const dy = p.y - planoDragging.start.y;
        if (obj.type === 'line') {
            obj.x1 = original.x1 + dx; obj.y1 = original.y1 + dy;
            obj.x2 = original.x2 + dx; obj.y2 = original.y2 + dy;
        } else {
            obj.x = original.x + dx; obj.y = original.y + dy;
        }
        desenharPlano();
        return;
    }

    if (planoDragging.mode === 'draw-line') {
        planoPreviewObject = {
            type:'line', mode:planoDragging.tool,
            x1:planoDragging.start.x, y1:planoDragging.start.y,
            x2:p.x, y2:p.y, color:planoColor, w:4
        };
        desenharPlano();
        return;
    }

    if (planoDragging.mode === 'draw-shape') {
        planoPreviewObject = criarShapePlano(planoDragging.tool, planoDragging.start, p);
        desenharPlano();
    }
}

function planoCanvasMouseUp(ev) {
    if (!planoCanvas || !planoDragging) { planoIsPointerDown = false; return; }
    const p = normalizarPontoCanvas(ev);

    if (planoDragging.mode === 'move') {
        planoDragging = null;
        planoIsPointerDown = false;
        persistirExercicioAtualPlano(false);
        return;
    }

    if (planoDragging.mode === 'draw-line') {
        const start = planoDragging.start;
        if (Math.hypot(p.x - start.x, p.y - start.y) > 8) {
            const obj = {type:'line',mode:planoDragging.tool,x1:start.x,y1:start.y,x2:p.x,y2:p.y,color:planoColor,w:4};
            planoObjects.push(obj);
            selecionarObjetoPlano(planoObjects.length - 1);
        }
        planoDragging = null;
        planoPreviewObject = null;
        planoIsPointerDown = false;
        desenharPlano();
        persistirExercicioAtualPlano(false);
        return;
    }

    if (planoDragging.mode === 'draw-shape') {
        const obj = criarShapePlano(planoDragging.tool, planoDragging.start, p);
        if (obj && ((obj.type === 'rect' && Math.abs(obj.w) > 10 && Math.abs(obj.h) > 10) || (obj.type === 'circle' && obj.r > 6))) {
            planoObjects.push(obj);
            selecionarObjetoPlano(planoObjects.length - 1);
        } else if (obj) {
            const fallback = criarShapePlano(planoDragging.tool, planoDragging.start, {x:planoDragging.start.x + 110, y:planoDragging.start.y + 70});
            planoObjects.push(fallback);
            selecionarObjetoPlano(planoObjects.length - 1);
        }
        planoDragging = null;
        planoPreviewObject = null;
        planoIsPointerDown = false;
        desenharPlano();
        persistirExercicioAtualPlano(false);
    }
}

function planoCanvasDoubleClick(ev) {
    const p = normalizarPontoCanvas(ev);
    const idx = encontrarObjetoPlano(p.x, p.y);
    if (idx < 0) return;
    const obj = planoObjects[idx];
    selecionarObjetoPlano(idx);
    if (obj.type === 'text') {
        const txt = prompt('Editar texto:', obj.text || 'Texto');
        if (txt !== null && txt.trim() !== '') obj.text = txt.trim();
    } else if (obj.type === 'player') {
        const n = prompt('Número / texto do jogador:', String(obj.n ?? ''));
        if (n !== null && n.trim() !== '') obj.n = n.trim();
    }
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function planoCanvasWheel(ev) {
    if (planoSelectedIndex < 0 || !planoObjects[planoSelectedIndex]) return;
    ev.preventDefault();
    const obj = planoObjects[planoSelectedIndex];
    if (obj.type === 'line') {
        obj.w = Math.max(1, Math.min(12, (Number(obj.w) || 4) + (ev.deltaY < 0 ? 1 : -1)));
    } else {
        obj.scale = Math.max(0.4, Math.min(3, (Number(obj.scale) || 1) + (ev.deltaY < 0 ? 0.08 : -0.08)));
    }
    atualizarPainelObjetoPlano();
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function criarObjetoPontualPlano(tool, p) {
    if (tool === 'player') return {type:'player',x:p.x,y:p.y,n:planoPlayerCounter++,color:'#3b82f6',scale:1};
    if (tool === 'opponent') return {type:'player',x:p.x,y:p.y,n:planoOpponentCounter++,color:'#ef4444',scale:1};
    if (tool === 'gk') return {type:'player',x:p.x,y:p.y,n:'GK',color:'#111827',scale:1};
    if (tool === 'cone') return {type:'cone',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#f97316' : planoColor),scale:1};
    if (tool === 'ball') return {type:'ball',x:p.x,y:p.y,color:'#111827',scale:1};
    if (tool === 'barrier') return {type:'barrier',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#94a3b8' : planoColor),scale:1};
    if (tool === 'goal') return {type:'goal',x:p.x,y:p.y,color:planoColor,scale:1,strokeWidth:4};
    return null;
}

function criarShapePlano(tool, start, end) {
    if (tool === 'rect') {
        const x = (start.x + end.x) / 2;
        const y = (start.y + end.y) / 2;
        return {type:'rect',x,y,w:Math.abs(end.x-start.x),h:Math.abs(end.y-start.y),color:planoColor,scale:1,strokeWidth:4};
    }
    if (tool === 'circle') {
        return {type:'circle',x:start.x,y:start.y,r:Math.max(12, Math.hypot(end.x-start.x,end.y-start.y)),color:planoColor,scale:1,strokeWidth:4};
    }
    return null;
}

function selecionarObjetoPlano(idx) {
    planoSelectedIndex = idx;
    atualizarPainelObjetoPlano();
}

function atualizarPainelObjetoPlano() {
    const panel = document.getElementById('planoSelectedPanel');
    if (!panel) return;
    const obj = planoSelectedIndex >= 0 ? planoObjects[planoSelectedIndex] : null;
    if (!obj) {
        panel.classList.remove('visible');
        return;
    }
    panel.classList.add('visible');
    const nomes = {line:'Linha',player:'Jogador',cone:'Cone',ball:'Bola',barrier:'Barreira',goal:'Baliza',rect:'Retângulo',circle:'Círculo',text:'Texto'};
    document.getElementById('planoSelectedName').textContent = nomes[obj.type] || 'Objeto';
    document.getElementById('planoSelectedType').textContent = obj.type === 'line' ? (obj.mode || 'linha') : obj.type;
    const size = document.getElementById('planoSizeRange');
    const stroke = document.getElementById('planoStrokeRange');
    if (size) size.value = String(Number(obj.scale || 1).toFixed(2));
    if (stroke) stroke.value = String(obj.type === 'line' ? (obj.w || 4) : (obj.strokeWidth || 4));
}

function alterarTamanhoObjetoPlano(value) {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    obj.scale = Math.max(0.4, Math.min(3, parseFloat(value) || 1));
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function alterarEspessuraObjetoPlano(value) {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    const v = Math.max(1, Math.min(12, parseInt(value,10) || 4));
    if (obj.type === 'line') obj.w = v;
    else obj.strokeWidth = v;
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function duplicarObjetoPlanoSelecionado() {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    const novo = JSON.parse(JSON.stringify(obj));
    if (novo.type === 'line') { novo.x1 += 22; novo.y1 += 22; novo.x2 += 22; novo.y2 += 22; }
    else { novo.x += 22; novo.y += 22; }
    planoObjects.push(novo);
    selecionarObjetoPlano(planoObjects.length - 1);
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function apagarObjetoPlanoSelecionado() {
    if (planoSelectedIndex < 0) return;
    planoObjects.splice(planoSelectedIndex, 1);
    selecionarObjetoPlano(-1);
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function encontrarObjetoPlano(x, y) {
    for (let i = planoObjects.length - 1; i >= 0; i--) {
        const o = planoObjects[i];
        if (o.type === 'line') {
            if (distanciaPontoLinha(x, y, o.x1, o.y1, o.x2, o.y2) < Math.max(12, (o.w || 4) + 8)) return i;
            continue;
        }
        if (o.type === 'rect') {
            const sw = (o.w || 120) * (o.scale || 1), sh = (o.h || 72) * (o.scale || 1);
            if (x >= o.x - sw/2 - 10 && x <= o.x + sw/2 + 10 && y >= o.y - sh/2 - 10 && y <= o.y + sh/2 + 10) return i;
            continue;
        }
        if (o.type === 'circle') {
            if (Math.abs(Math.hypot(x-o.x,y-o.y) - ((o.r || 42) * (o.scale || 1))) < 15 || Math.hypot(x-o.x,y-o.y) < ((o.r || 42) * (o.scale || 1))) return i;
            continue;
        }
        const radius = raioHitObjetoPlano(o);
        if (Math.hypot(x - (o.x ?? 0), y - (o.y ?? 0)) < radius) return i;
    }
    return -1;
}

function raioHitObjetoPlano(o) {
    const s = o.scale || 1;
    if (o.type === 'text') return Math.max(38, String(o.text || 'Texto').length * 8 * s);
    if (o.type === 'goal') return 52 * s;
    if (o.type === 'barrier') return 34 * s;
    if (o.type === 'cone') return 30 * s;
    if (o.type === 'ball') return 26 * s;
    return 30 * s;
}

function distanciaPontoLinha(px, py, x1, y1, x2, y2) {
    const A = px - x1, B = py - y1, C = x2 - x1, D = y2 - y1;
    let param = (C*C + D*D) ? (A*C + B*D) / (C*C + D*D) : -1;
    param = Math.max(0, Math.min(1, param));
    return Math.hypot(px - (x1 + param*C), py - (y1 + param*D));
}

function desenharPlano(canvas = planoCanvas, data = null) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const state = data || {template: planoTemplate, objects: planoObjects};
    const sx = canvas.width / PLANO_BASE_W;
    const sy = canvas.height / PLANO_BASE_H;
    ctx.save();
    ctx.scale(sx, sy);
    desenharCampo(ctx, PLANO_BASE_W, PLANO_BASE_H, state.template || 'campo_inteiro');
    (state.objects || []).forEach((o, idx) => desenharObjetoPlano(ctx, normalizarObjetoPlano(o), canvas === planoCanvas && idx === planoSelectedIndex));
    if (!data && planoPreviewObject) desenharObjetoPlano(ctx, normalizarObjetoPlano(planoPreviewObject), false, true);
    ctx.restore();
}

function normalizarObjetoPlano(o) {
    const n = {...o};
    if (n.scale == null) n.scale = 1;
    if (n.type === 'circle' && !n.r) n.r = 42;
    if (n.type === 'rect') { if (!n.w) n.w = 120; if (!n.h) n.h = 72; }
    if (n.type === 'line' && !n.w) n.w = 4;
    return n;
}

function desenharCampo(ctx, w, h, template) {
    ctx.clearRect(0,0,w,h);
    ctx.fillStyle = '#fff';
    ctx.fillRect(0,0,w,h);
    ctx.strokeStyle = '#111';
    ctx.lineCap = 'square';
    ctx.lineJoin = 'miter';
    ctx.lineWidth = 4;

    const left=w*.04,right=w*.96,top=h*.08,bottom=h*.92,mid=w/2,cy=(top+bottom)/2;

    if (template === 'area') {
        const fieldW = right-left, fieldH = bottom-top;
        ctx.strokeRect(left,top,fieldW,fieldH);
        const boxW=fieldW*.50, boxH=fieldH*.38, boxX=mid-boxW/2;
        ctx.strokeRect(boxX,top,boxW,boxH);
        ctx.strokeRect(mid-boxW*.22,top,boxW*.44,boxH*.48);
        ctx.beginPath(); ctx.arc(mid,top+boxH,boxW*.16,0,Math.PI); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid,top+boxH*.58,4,0,Math.PI*2); ctx.fillStyle='#111'; ctx.fill();
        return;
    }

    if (template === 'futsal') {
        const r = 16;
        roundRectPath(ctx,left,top,right-left,bottom-top,r); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(mid,top); ctx.lineTo(mid,bottom); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid,cy,(bottom-top)*.12,0,Math.PI*2); ctx.stroke();
        desenharAreaFutsal(ctx,left,cy,1,top,bottom);
        desenharAreaFutsal(ctx,right,cy,-1,top,bottom);
        return;
    }

    ctx.strokeRect(left,top,right-left,bottom-top);

    if (template !== 'meio_campo') {
        ctx.beginPath(); ctx.moveTo(mid,top); ctx.lineTo(mid,bottom); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid,cy,(bottom-top)*.13,0,Math.PI*2); ctx.stroke();
    }

    const bw=(right-left)*.17,bh=(bottom-top)*.48,sw=(right-left)*.06,sh=(bottom-top)*.22,yb=cy-bh/2,ys=cy-sh/2;
    ctx.strokeRect(left,yb,bw,bh); ctx.strokeRect(left,ys,sw,sh);
    ctx.beginPath(); ctx.arc(left+bw,cy,bh*.19,-Math.PI/2,Math.PI/2); ctx.stroke();
    ctx.beginPath(); ctx.arc(left+bw*.55,cy,4,0,Math.PI*2); ctx.fillStyle='#111'; ctx.fill();
    ctx.strokeRect(right-bw,yb,bw,bh); ctx.strokeRect(right-sw,ys,sw,sh);
    ctx.beginPath(); ctx.arc(right-bw,cy,bh*.19,Math.PI/2,Math.PI*1.5); ctx.stroke();
    ctx.beginPath(); ctx.arc(right-bw*.55,cy,4,0,Math.PI*2); ctx.fillStyle='#111'; ctx.fill();

    if (template === 'meio_campo') {
        ctx.beginPath(); ctx.moveTo(mid,top); ctx.lineTo(mid,bottom); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid,cy,(bottom-top)*.13,Math.PI/2,Math.PI*1.5); ctx.stroke();
    }
}

function roundRectPath(ctx,x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.quadraticCurveTo(x+w,y,x+w,y+r);ctx.lineTo(x+w,y+h-r);ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);ctx.lineTo(x+r,y+h);ctx.quadraticCurveTo(x,y+h,x,y+h-r);ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);}
function desenharAreaFutsal(ctx,x,cy,dir,top,bottom){const h=(bottom-top)*.34;ctx.beginPath();ctx.arc(x,cy,h/2,dir>0?-Math.PI/2:Math.PI/2,dir>0?Math.PI/2:Math.PI*1.5);ctx.stroke();ctx.strokeRect(x+(dir>0?0:-42),cy-35,42,70);}

function desenharObjetoPlano(ctx, o, selected = false, preview = false) {
    ctx.save();
    ctx.globalAlpha = preview ? .62 : 1;
    ctx.strokeStyle=o.color||'#000';
    ctx.fillStyle=o.color||'#000';
    ctx.lineCap='round';
    ctx.lineJoin='round';

    if (o.type === 'line') desenharLinhaPlano(ctx, o);
    else if (o.type === 'player') desenharJogadorPlano(ctx, o);
    else if (o.type === 'cone') desenharConePlano(ctx, o);
    else if (o.type === 'ball') desenharBolaPlano(ctx, o);
    else if (o.type === 'barrier') desenharBarreiraPlano(ctx, o);
    else if (o.type === 'goal') desenharBalizaPlano(ctx, o);
    else if (o.type === 'rect') desenharRetanguloPlano(ctx, o);
    else if (o.type === 'circle') desenharCirculoPlano(ctx, o);
    else if (o.type === 'text') desenharTextoPlano(ctx, o);

    if (selected) desenharSelecaoObjetoPlano(ctx, o);
    ctx.restore();
}

function desenharLinhaPlano(ctx, o) {
    const w = o.w || 4;
    ctx.strokeStyle = o.color || '#111';
    ctx.fillStyle = o.color || '#111';
    ctx.lineWidth = w;
    if (o.mode === 'dash') ctx.setLineDash([16, 12]);
    if (o.mode === 'run') {
        desenharLinhaOndulada(ctx, o.x1, o.y1, o.x2, o.y2, w);
        desenharSeta(ctx,o.x1,o.y1,o.x2,o.y2,o.color||'#111',w);
    } else {
        ctx.beginPath(); ctx.moveTo(o.x1,o.y1); ctx.lineTo(o.x2,o.y2); ctx.stroke();
        if (o.mode === 'arrow') desenharSeta(ctx,o.x1,o.y1,o.x2,o.y2,o.color||'#111',w);
    }
    ctx.setLineDash([]);
}

function desenharLinhaOndulada(ctx,x1,y1,x2,y2,w){const dx=x2-x1,dy=y2-y1,len=Math.hypot(dx,dy)||1;const ux=dx/len,uy=dy/len;const px=-uy,py=ux;const amp=7;ctx.beginPath();for(let d=0;d<=len;d+=8){const t=d/len;const wave=Math.sin(t*Math.PI*len/28)*amp;const x=x1+ux*d+px*wave,y=y1+uy*d+py*wave;if(d===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);}ctx.stroke();}
function desenharSeta(ctx,x1,y1,x2,y2,color,w=4){const a=Math.atan2(y2-y1,x2-x1),l=17+w*1.2;ctx.save();ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(x2,y2);ctx.lineTo(x2-l*Math.cos(a-Math.PI/6),y2-l*Math.sin(a-Math.PI/6));ctx.lineTo(x2-l*Math.cos(a+Math.PI/6),y2-l*Math.sin(a+Math.PI/6));ctx.closePath();ctx.fill();ctx.restore();}

function desenharJogadorPlano(ctx,o){const s=o.scale||1,r=18*s;ctx.save();ctx.beginPath();ctx.arc(o.x,o.y,r,0,Math.PI*2);ctx.fillStyle=o.color||'#3b82f6';ctx.fill();ctx.lineWidth=2.5*s;ctx.strokeStyle='#fff';ctx.stroke();ctx.fillStyle='#fff';ctx.font=`900 ${Math.max(9,14*s)}px Inter,Arial`;ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(String(o.n??''),o.x,o.y+1*s);ctx.restore();}
function desenharConePlano(ctx,o){const s=o.scale||1,x=o.x,y=o.y,c=o.color||'#f97316';ctx.save();ctx.fillStyle=c;ctx.strokeStyle='#111827';ctx.lineWidth=2*s;ctx.beginPath();ctx.moveTo(x,y-25*s);ctx.lineTo(x-16*s,y+13*s);ctx.quadraticCurveTo(x,y+19*s,x+16*s,y+13*s);ctx.closePath();ctx.fill();ctx.stroke();ctx.strokeStyle='#fff';ctx.lineWidth=3.2*s;ctx.beginPath();ctx.moveTo(x-8*s,y-4*s);ctx.lineTo(x+8*s,y-4*s);ctx.moveTo(x-12*s,y+6*s);ctx.lineTo(x+12*s,y+6*s);ctx.stroke();ctx.fillStyle='#111827';ctx.beginPath();ctx.ellipse(x,y+20*s,23*s,5*s,0,0,Math.PI*2);ctx.fill();ctx.fillStyle=c;ctx.beginPath();ctx.ellipse(x,y+18*s,19*s,3*s,0,0,Math.PI*2);ctx.fill();ctx.restore();}
function desenharBolaPlano(ctx,o){const s=o.scale||1,x=o.x,y=o.y,r=15*s;ctx.save();ctx.fillStyle='#fff';ctx.strokeStyle='#111827';ctx.lineWidth=2.5*s;ctx.beginPath();ctx.arc(x,y,r,0,Math.PI*2);ctx.fill();ctx.stroke();ctx.fillStyle='#111827';ctx.beginPath();for(let i=0;i<5;i++){const a=-Math.PI/2+i*2*Math.PI/5;const px=x+Math.cos(a)*r*.45,py=y+Math.sin(a)*r*.45;i?ctx.lineTo(px,py):ctx.moveTo(px,py);}ctx.closePath();ctx.fill();ctx.strokeStyle='#111827';ctx.lineWidth=1.4*s;for(let i=0;i<5;i++){const a=-Math.PI/2+i*2*Math.PI/5;ctx.beginPath();ctx.moveTo(x+Math.cos(a)*r*.48,y+Math.sin(a)*r*.48);ctx.lineTo(x+Math.cos(a)*r*.95,y+Math.sin(a)*r*.95);ctx.stroke();}ctx.restore();}
function desenharBarreiraPlano(ctx,o){const s=o.scale||1,x=o.x,y=o.y,c=o.color||'#94a3b8';ctx.save();ctx.strokeStyle='#111827';ctx.fillStyle=c;ctx.lineWidth=2.2*s;for(let i=-1;i<=1;i++){const bx=x+i*16*s;ctx.beginPath();ctx.arc(bx,y-18*s,5*s,0,Math.PI*2);ctx.fill();ctx.stroke();ctx.beginPath();ctx.moveTo(bx,y-13*s);ctx.lineTo(bx,y+14*s);ctx.moveTo(bx-8*s,y-2*s);ctx.lineTo(bx+8*s,y-2*s);ctx.moveTo(bx,y+14*s);ctx.lineTo(bx-7*s,y+28*s);ctx.moveTo(bx,y+14*s);ctx.lineTo(bx+7*s,y+28*s);ctx.stroke();}ctx.strokeStyle='rgba(15,23,42,.45)';ctx.lineWidth=1.4*s;ctx.beginPath();ctx.moveTo(x-30*s,y+30*s);ctx.lineTo(x+30*s,y+30*s);ctx.stroke();ctx.restore();}
function desenharBalizaPlano(ctx,o){const s=o.scale||1,x=o.x,y=o.y,w=72*s,h=28*s;ctx.save();ctx.strokeStyle=o.color||'#111';ctx.lineWidth=(o.strokeWidth||4)*s;ctx.strokeRect(x-w/2,y-h/2,w,h);ctx.strokeStyle='#94a3b8';ctx.lineWidth=1.2*s;for(let i=1;i<4;i++){ctx.beginPath();ctx.moveTo(x-w/2+i*w/4,y-h/2);ctx.lineTo(x-w/2+i*w/4,y+h/2);ctx.stroke();}ctx.beginPath();ctx.moveTo(x-w/2,y);ctx.lineTo(x+w/2,y);ctx.stroke();ctx.restore();}
function desenharRetanguloPlano(ctx,o){const s=o.scale||1,w=(o.w||120)*s,h=(o.h||72)*s;ctx.save();ctx.strokeStyle=o.color||'#111';ctx.lineWidth=o.strokeWidth||4;ctx.strokeRect(o.x-w/2,o.y-h/2,w,h);ctx.restore();}
function desenharCirculoPlano(ctx,o){const s=o.scale||1,r=(o.r||42)*s;ctx.save();ctx.strokeStyle=o.color||'#111';ctx.lineWidth=o.strokeWidth||4;ctx.beginPath();ctx.arc(o.x,o.y,r,0,Math.PI*2);ctx.stroke();ctx.restore();}
function desenharTextoPlano(ctx,o){const s=o.scale||1;ctx.save();ctx.fillStyle=o.color||'#111';ctx.font=`900 ${Math.max(10,(o.size||24)*s)}px Inter,Arial`;ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(o.text||'Texto',o.x,o.y);ctx.restore();}

function desenharSelecaoObjetoPlano(ctx,o){ctx.save();ctx.strokeStyle='#0ea5e9';ctx.fillStyle='#0ea5e9';ctx.lineWidth=2;ctx.setLineDash([6,5]);if(o.type==='line'){ctx.strokeRect(Math.min(o.x1,o.x2)-8,Math.min(o.y1,o.y2)-8,Math.abs(o.x2-o.x1)+16,Math.abs(o.y2-o.y1)+16);ctx.setLineDash([]);ctx.beginPath();ctx.arc(o.x1,o.y1,5,0,Math.PI*2);ctx.arc(o.x2,o.y2,5,0,Math.PI*2);ctx.fill();}else{const b=boundsObjetoPlano(o);ctx.strokeRect(b.x,b.y,b.w,b.h);ctx.setLineDash([]);[[b.x,b.y],[b.x+b.w,b.y],[b.x,b.y+b.h],[b.x+b.w,b.y+b.h]].forEach(([x,y])=>{ctx.beginPath();ctx.rect(x-4,y-4,8,8);ctx.fill();});}ctx.restore();}
function boundsObjetoPlano(o){const s=o.scale||1;if(o.type==='rect')return{x:o.x-(o.w||120)*s/2,y:o.y-(o.h||72)*s/2,w:(o.w||120)*s,h:(o.h||72)*s};if(o.type==='circle')return{x:o.x-(o.r||42)*s,y:o.y-(o.r||42)*s,w:(o.r||42)*2*s,h:(o.r||42)*2*s};const r=raioHitObjetoPlano(o);return{x:(o.x||0)-r,y:(o.y||0)-r,w:r*2,h:r*2};}

function persistirExercicioAtualPlano(refreshTabs = true){
    if(!planoExercises[planoCurrentIndex])return;
    planoExercises[planoCurrentIndex].titulo=document.getElementById('planoTituloExercicio')?.value||`Exercício ${planoCurrentIndex+1}`;
    planoExercises[planoCurrentIndex].descricao=document.getElementById('planoDescricaoExercicio')?.value||'';
    planoExercises[planoCurrentIndex].objetivos=document.getElementById('planoObjetivosExercicio')?.value||'';
    planoExercises[planoCurrentIndex].canvas={template:planoTemplate,objects:JSON.parse(JSON.stringify(planoObjects))};
    if(refreshTabs)renderPlanoExerciseTabs();
}
function carregarExercicioPlano(index, skipPersist=false){if(!skipPersist)persistirExercicioAtualPlano();planoCurrentIndex=Math.max(0,Math.min(index,planoExercises.length-1));const ex=planoExercises[planoCurrentIndex]||{};planoTemplate=ex.canvas?.template||'campo_inteiro';planoObjects=Array.isArray(ex.canvas?.objects)?JSON.parse(JSON.stringify(ex.canvas.objects)):[];planoSelectedIndex=-1;planoPreviewObject=null;document.getElementById('planoTituloExercicio').value=ex.titulo||`Exercício ${planoCurrentIndex+1}`;document.getElementById('planoDescricaoExercicio').value=ex.descricao||'';document.getElementById('planoObjetivosExercicio').value=ex.objetivos||'';document.getElementById('planoCurrentExerciseLabel').textContent=`Exercício ${planoCurrentIndex+1}`;document.getElementById('planoProgressText').textContent=`Exercício ${planoCurrentIndex+1} de ${planoExercises.length}`;document.querySelectorAll('.template-btn').forEach(b=>b.classList.toggle('active',b.dataset.template===planoTemplate));recalcularPlanoCounters();atualizarPainelObjetoPlano();desenharPlano();renderPlanoExerciseTabs();}
function renderPlanoExerciseTabs(){const list=document.getElementById('planoExerciseList');if(!list)return;list.innerHTML=planoExercises.map((ex,i)=>`<button type="button" class="plano-exercise-tab ${i===planoCurrentIndex?'active':''}" onclick="carregarExercicioPlano(${i})">${esc(ex.titulo||('Exercício '+(i+1)))}</button>`).join('');}
function adicionarExercicioPlano(){persistirExercicioAtualPlano();planoExercises.push(novoExercicioPlano(planoExercises.length+1));carregarExercicioPlano(planoExercises.length-1);}
function removerExercicioAtualPlano(){if(planoExercises.length<=1){alert('O plano tem de ter pelo menos um exercício.');return;}if(!confirm('Apagar este exercício do plano?'))return;planoExercises.splice(planoCurrentIndex,1);planoExercises.forEach((ex,i)=>{if(!ex.titulo||/^Exercício \d+$/.test(ex.titulo))ex.titulo=`Exercício ${i+1}`;});carregarExercicioPlano(Math.min(planoCurrentIndex,planoExercises.length-1));}
function exercicioAnteriorPlano(){carregarExercicioPlano(planoCurrentIndex-1)}
function exercicioSeguintePlano(){if(planoCurrentIndex>=planoExercises.length-1)adicionarExercicioPlano();else carregarExercicioPlano(planoCurrentIndex+1)}
function novoExercicioPlano(n){return{titulo:`Exercício ${n}`,descricao:'',objetivos:'',canvas:{template:'campo_inteiro',objects:[]}}}
function abrirCriadorPlanoTreino(){const qtdRaw=prompt('Quantos exercícios queres fazer neste plano?','3');if(qtdRaw===null)return;const qtd=Math.max(1,Math.min(25,parseInt(qtdRaw,10)||1));planoCurrentIndex=0;planoObjects=[];planoPreviewObject=null;planoSelectedIndex=-1;planoExercises=Array.from({length:qtd},(_,i)=>novoExercicioPlano(i+1));document.getElementById('planoTreinoAcao').value='criar_plano_treino';document.getElementById('planoTreinoId').value='0';document.getElementById('planoModalTitle').textContent='Fazer plano de treino';document.getElementById('planoEquipa').value=equipasTreinadorData[0]?.id_equipa||'';document.getElementById('planoNumero').value=proximoNumeroTreinoSugerido();document.getElementById('planoData').value=new Date().toISOString().slice(0,10);document.getElementById('planoHora').value='18:00';document.getElementById('planoConteudo').value='Plano de treino';document.getElementById('planoObservacoes').value='';abrirModalPlanoBase();}
function abrirEditorPlanoTreino(idTreino){const treino=treinosTreinadorData.find(t=>String(t.id_treino)===String(idTreino));if(!treino)return;const existing=exerciciosPorTreinoData[idTreino]||exerciciosPorTreinoData[String(idTreino)]||[];planoCurrentIndex=0;planoObjects=[];planoPreviewObject=null;planoSelectedIndex=-1;planoExercises=existing.length?existing.map((ex,i)=>{const canvas=ex.canvas&&typeof ex.canvas==='object'?JSON.parse(JSON.stringify(ex.canvas)):{template:'campo_inteiro',objects:[]};if(!Array.isArray(canvas.objects))canvas.objects=[];return{titulo:ex.titulo||`Exercício ${i+1}`,descricao:ex.descricao||'',objetivos:ex.objetivos||'',canvas};}):[novoExercicioPlano(1)];document.getElementById('planoTreinoAcao').value='atualizar_plano_treino';document.getElementById('planoTreinoId').value=treino.id_treino;document.getElementById('planoModalTitle').textContent=existing.length?'Editar plano de treino':'Adicionar plano visual ao treino';document.getElementById('planoEquipa').value=treino.id_equipa;document.getElementById('planoNumero').value=treino.numero_treino;document.getElementById('planoData').value=treino.data_treino;document.getElementById('planoHora').value=(treino.hora_treino||'').substring(0,5);document.getElementById('planoConteudo').value=treino.conteudo_treino||'';document.getElementById('planoObservacoes').value=treino.observacoes_treino||'';abrirModalPlanoBase();}
function abrirModalPlanoBase(){setupPlanoCanvasOnce();initPlanoColorPalette();atualizarPlanoLineButton();setPlanoTool('select',document.querySelector('.plano-tool-btn[data-tool="select"]'));openModal('modalPlanoTreino');setTimeout(()=>carregarExercicioPlano(0,true),80)}
function fecharPlanoTreino(){if(confirm('Fechar o editor? As alterações não guardadas vão ser perdidas.'))closeModal('modalPlanoTreino')}
function proximoNumeroTreinoSugerido(){const nums=treinosTreinadorData.map(t=>parseInt(t.numero_treino,10)).filter(n=>!isNaN(n));return nums.length?Math.max(...nums)+1:1}
function desfazerPlanoObjeto(){if(planoObjects.length){planoObjects.pop();selecionarObjetoPlano(-1);desenharPlano();persistirExercicioAtualPlano(false)}}
function limparPlanoCanvas(){if(!confirm('Limpar o desenho deste exercício?'))return;planoObjects=[];selecionarObjetoPlano(-1);desenharPlano();persistirExercicioAtualPlano(false)}
function recalcularPlanoCounters(){const players=planoObjects.filter(o=>o.type==='player'&&o.color==='#3b82f6'&&Number.isFinite(Number(o.n))).map(o=>Number(o.n));const opp=planoObjects.filter(o=>o.type==='player'&&o.color==='#ef4444'&&Number.isFinite(Number(o.n))).map(o=>Number(o.n));planoPlayerCounter=players.length?Math.max(...players)+1:1;planoOpponentCounter=opp.length?Math.max(...opp)+1:1}
function prepararSubmissaoPlano(){persistirExercicioAtualPlano();document.getElementById('planoExerciciosJson').value=JSON.stringify(planoExercises.map((ex,i)=>({titulo:(ex.titulo||`Exercício ${i+1}`).trim(),descricao:ex.descricao||'',objetivos:ex.objetivos||'',canvas:ex.canvas||{template:'campo_inteiro',objects:[]}})));return true}
const planoForm=document.getElementById('planoTreinoForm');if(planoForm)planoForm.addEventListener('submit',prepararSubmissaoPlano);
function abrirVisualizacaoPlanoTreino(idTreino){const treino=treinosTreinadorData.find(t=>String(t.id_treino)===String(idTreino));const exercicios=exerciciosPorTreinoData[idTreino]||exerciciosPorTreinoData[String(idTreino)]||[];if(!treino||!exercicios.length){alert('Este treino ainda não tem plano visual.');return;}const content=document.getElementById('planoViewContent');const metaEquipa=`${esc(treino.escalão||'')} ${esc(treino.hierarquia||'')}${treino.época?' · '+esc(treino.época):''}`;content.innerHTML=`<div class="plano-view-header"><div class="plano-view-title">Treino #${esc(treino.numero_treino)} — ${esc(treino.conteudo_treino||'')}</div><div class="plano-view-meta">${metaEquipa} · ${esc(treino.data_treino)} · ${(treino.hora_treino||'').substring(0,5)} · ${esc(treino.dia_da_semana||'')}</div></div>${exercicios.map((ex,i)=>`<section class="plano-exercicio-print"><div class="plano-exercicio-visual"><div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;font-size:13px;"><strong>Atividade: ${esc(ex.titulo||('Exercício '+(i+1)))}</strong><strong>Duração:</strong></div><div class="plano-print-label">Representação visual</div><canvas class="plano-view-canvas" width="520" height="330" data-view-exercicio="${i}"></canvas></div><div class="plano-exercicio-desc"><div class="plano-print-label">Descrição</div><div class="plano-desc-block">${esc(ex.descricao||'Sem descrição definida.')}</div><div class="plano-print-label">Objetivos / Indicações / Regras</div><div class="plano-objetivo-block">${esc(ex.objetivos||'Sem objetivos definidos.')}</div></div></section>`).join('')}`;document.getElementById('btnEditarPlanoView').onclick=()=>{closeModal('modalVerPlanoTreino');abrirEditorPlanoTreino(idTreino)};const btnPdfPlano=document.getElementById('btnDescarregarPlanoPdf');if(btnPdfPlano){const nomePdfBase=`plano-treino-${String(treino.numero_treino||idTreino)}-${String(treino.data_treino||'').replace(/[^0-9-]/g,'')}`.replace(/[^a-z0-9_-]+/gi,'-');btnPdfPlano.dataset.filename=`${nomePdfBase}.pdf`;}openModal('modalVerPlanoTreino');setTimeout(()=>{document.querySelectorAll('.plano-view-canvas').forEach((canvas,i)=>desenharPlano(canvas,exercicios[i]?.canvas||{template:'campo_inteiro',objects:[]}));},80)}

async function descarregarPlanoTreinoPDF(){
    const content=document.getElementById('planoViewContent');
    const btn=document.getElementById('btnDescarregarPlanoPdf');

    if(!content||!content.innerHTML.trim()){
        alert('Abre primeiro um plano de treino.');
        return;
    }

    if(!window.html2canvas||!window.jspdf||!window.jspdf.jsPDF){
        alert('Não foi possível carregar as bibliotecas para gerar o PDF. Confirma a ligação à internet ou instala html2canvas e jsPDF localmente.');
        return;
    }

    const textoOriginal=btn?btn.textContent:'';

    try{
        if(btn){
            btn.disabled=true;
            btn.textContent='A gerar PDF...';
        }

        await new Promise(resolve=>setTimeout(resolve,120));

        const canvas=await html2canvas(content,{
            scale:2,
            backgroundColor:'#ffffff',
            useCORS:true,
            scrollX:0,
            scrollY:-window.scrollY,
            windowWidth:document.documentElement.scrollWidth
        });

        const {jsPDF}=window.jspdf;
        const pdf=new jsPDF('p','mm','a4');
        const margem=10;
        const larguraPagina=pdf.internal.pageSize.getWidth();
        const alturaPagina=pdf.internal.pageSize.getHeight();
        const larguraUtil=larguraPagina-(margem*2);
        const alturaUtil=alturaPagina-(margem*2);
        const pxPorMm=canvas.width/larguraUtil;
        const alturaSlicePx=Math.floor(alturaUtil*pxPorMm);

        let yPx=0;
        let primeiraPagina=true;

        while(yPx<canvas.height){
            const alturaAtualPx=Math.min(alturaSlicePx,canvas.height-yPx);
            const pageCanvas=document.createElement('canvas');
            pageCanvas.width=canvas.width;
            pageCanvas.height=alturaAtualPx;

            const ctx=pageCanvas.getContext('2d');
            ctx.fillStyle='#ffffff';
            ctx.fillRect(0,0,pageCanvas.width,pageCanvas.height);
            ctx.drawImage(canvas,0,yPx,canvas.width,alturaAtualPx,0,0,canvas.width,alturaAtualPx);

            const imgData=pageCanvas.toDataURL('image/jpeg',0.96);
            const alturaImgMm=alturaAtualPx/pxPorMm;

            if(!primeiraPagina) pdf.addPage();
            pdf.addImage(imgData,'JPEG',margem,margem,larguraUtil,alturaImgMm);

            primeiraPagina=false;
            yPx+=alturaAtualPx;
        }

        const filename=(btn&&btn.dataset.filename)?btn.dataset.filename:'plano-treino.pdf';
        pdf.save(filename);
    }catch(e){
        console.error(e);
        alert('Erro ao gerar o PDF do plano de treino.');
    }finally{
        if(btn){
            btn.disabled=false;
            btn.textContent=textoOriginal||'Descarregar PDF';
        }
    }
}

function renderMiniPlanoThumbs(){document.querySelectorAll('canvas[data-plan-thumb]').forEach(canvas=>{const id=canvas.dataset.planThumb;const ex=exerciciosPorTreinoData[id]||exerciciosPorTreinoData[String(id)]||[];if(ex.length)desenharPlano(canvas,ex[0].canvas||{template:'campo_inteiro',objects:[]});})}
document.addEventListener('DOMContentLoaded',()=>{renderMiniPlanoThumbs();});

/* ══════════════════════════════════
   COMPETIÇÕES
══════════════════════════════════ */
const competicoesData = <?= json_encode($competicoesClube, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
const jogosPorComp    = <?= json_encode($jogosPorCompeticao, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
let currentCompId = null;

function renderCompeticoes() {
    /* Nothing to do — cards rendered by PHP */
}

function openCompeticao(idComp) {
    currentCompId = idComp;
    const comp = competicoesData.find(c => c.id_competicao == idComp);
    if (!comp) return;

    document.getElementById('detalheNome').textContent = comp.nome;
    document.getElementById('detalheInfo').textContent = comp.tipo + (comp.epoca ? ' · ' + comp.epoca : '') + ' · ' + comp.escalão + ' ' + comp.hierarquia;

    document.getElementById('competicoesLista').style.display = 'none';
    document.getElementById('competicaoDetalhe').style.display = 'block';

    renderJogos(idComp);
}

function backToCompeticoes() {
    currentCompId = null;
    document.getElementById('competicoesLista').style.display = 'block';
    document.getElementById('competicaoDetalhe').style.display = 'none';
}

function renderJogos(idComp) {
    const jogos = jogosPorComp[idComp] || [];
    const isAdmin = <?= $isAdminClube ? 'true' : 'false' ?>;
    const lista = document.getElementById('jogosLista');

    if (!jogos.length) {
        lista.innerHTML = '<div class="empty-state"><p>Sem jogos criados nesta competição.</p></div>';
        return;
    }

    lista.innerHTML = jogos.map(j => {
        const estadoClass = 'jogo-estado-' + (j.estado || 'Agendado');
        const resultado = (j.resultado_nos !== null && j.resultado_adv !== null)
            ? `${j.resultado_nos} – ${j.resultado_adv}` : '— – —';
        const casaBadge = j.casa ? '<span class="jogo-casa-badge">Casa</span>' : '<span class="jogo-casa-badge">Fora</span>';
        const adminActions = isAdmin
            ? `<button class="btn-row-edit" onclick="openResultadoModal(${j.id_jogo},${j.resultado_nos ?? 'null'},${j.resultado_adv ?? 'null'},'${j.estado}')" title="Resultado">⚽</button>
               <form method="POST" style="display:inline;" onsubmit="return confirm('Remover jogo?');">
                   <input type="hidden" name="acao" value="remover_jogo">
                   <input type="hidden" name="id_jogo" value="${j.id_jogo}">
                   <button class="btn-row-edit btn-row-delete" type="submit">×</button>
               </form>` : '';
        return `<div class="jogo-row">
            <div class="jogo-data">${esc(j.data_jogo)}${j.hora_jogo ? '<br><small>'+esc(j.hora_jogo.substring(0,5))+'</small>' : ''}</div>
            <div class="jogo-equipa">vs ${esc(j.adversario)}${j.local_jogo ? '<br><small style="color:#9aa0ae">📍'+esc(j.local_jogo)+'</small>' : ''}</div>
            ${casaBadge}
            <div class="jogo-resultado">${resultado}</div>
            <span class="jogo-estado ${estadoClass}">${esc(j.estado)}</span>
            <div style="display:flex;gap:6px;">${adminActions}</div>
        </div>`;
    }).join('');
}

function openCriarJogoModal() {
    if (!currentCompId) return;
    document.getElementById('criarJogoCompId').value = currentCompId;
    openModal('modalCriarJogo');
}

function openResultadoModal(idJogo, nos, adv, estado) {
    document.getElementById('resultadoJogoId').value  = idJogo;
    document.getElementById('resultadoNos').value     = nos !== null ? nos : '';
    document.getElementById('resultadoAdv').value     = adv !== null ? adv : '';
    document.getElementById('resultadoEstado').value  = estado || 'Agendado';
    openModal('modalResultadoJogo');
}

function openEditCompeticao(idComp) {
    const comp = competicoesData.find(c => c.id_competicao == idComp);
    if (!comp) return;
    document.getElementById('editCompId').value     = comp.id_competicao;
    document.getElementById('editCompNome').value   = comp.nome;
    document.getElementById('editCompTipo').value   = comp.tipo;
    document.getElementById('editCompEpoca').value  = comp.epoca || '';
    document.getElementById('editCompEstado').value = comp.estado;
    document.getElementById('editCompDesc').value   = comp.descricao || '';
    openModal('modalEditarCompeticao');
}


/* ══════════════════════════════════
   PLANO VISUAL V4 — correções: paleta hover e resize no canvas
══════════════════════════════════ */
const PLANO_HANDLE_SIZE = 10;

function setupPlanoCanvasOnce() {
    planoCanvas = document.getElementById('planoCanvas');
    if (!planoCanvas || planoCanvas.dataset.v4Ready === '1') return;
    planoCanvas.dataset.v4Ready = '1';
    planoCanvas.addEventListener('mousedown', planoCanvasMouseDown);
    planoCanvas.addEventListener('mousemove', planoCanvasMouseMove);
    planoCanvas.addEventListener('mouseup', planoCanvasMouseUp);
    planoCanvas.addEventListener('mouseleave', planoCanvasMouseUp);
    planoCanvas.addEventListener('dblclick', planoCanvasDoubleClick);
    planoCanvas.addEventListener('wheel', planoCanvasWheel, {passive:false});
    planoCanvas.addEventListener('contextmenu', ev => ev.preventDefault());
    initPlanoToolIcons();
    initPlanoPropertyPanel();
    atualizarCursorPlano();
}

function initPlanoColorPalette() {
    const palette = document.getElementById('planoColorPalette');
    if (!palette) return;
    palette.dataset.ready = '1';
    palette.innerHTML = '<div class="plano-flyout-label">Escolher cor</div>' + planoColors.map((c) => `
        <button type="button" class="plano-color-choice ${c === planoColor ? 'active' : ''}"
                style="background:${c};${c === '#ffffff' ? 'box-shadow:inset 0 0 0 2px #cbd5e1;' : ''}"
                onclick="setPlanoColor('${c}', this)" title="${c}"></button>`).join('');
    atualizarPlanoColorButton();
}

function setPlanoColor(color, btn) {
    planoColor = color;
    document.querySelectorAll('.plano-color-choice').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    atualizarPlanoColorButton();

    if (planoSelectedIndex >= 0 && planoObjects[planoSelectedIndex]) {
        const o = planoObjects[planoSelectedIndex];
        if (o.type !== 'ball') {
            o.color = color;
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
    }

    const colorMain = document.getElementById('planoColorMainBtn');
    if (colorMain) colorMain.blur();
    if (btn) btn.blur();
}

function criarShapePlano(tool, start, end) {
    if (tool === 'rect') {
        const x = (start.x + end.x) / 2;
        const y = (start.y + end.y) / 2;
        return {
            type:'rect',
            x,
            y,
            w:Math.max(18, Math.abs(end.x - start.x)),
            h:Math.max(18, Math.abs(end.y - start.y)),
            color:planoColor,
            scale:1,
            strokeWidth:4
        };
    }

    if (tool === 'circle') {
        return {
            type:'circle',
            x:start.x,
            y:start.y,
            r:Math.max(12, Math.hypot(end.x - start.x, end.y - start.y)),
            color:planoColor,
            scale:1,
            strokeWidth:4
        };
    }

    return null;
}

function planoCanvasMouseDown(ev) {
    if (!planoCanvas) return;
    planoIsPointerDown = true;
    const p = normalizarPontoCanvas(ev);

    if (planoTool === 'select') {
        const resizeHit = encontrarHandleResizePlano(p.x, p.y);
        if (resizeHit) {
            const obj = planoObjects[resizeHit.idx];
            planoDragging = {
                mode:'resize',
                idx:resizeHit.idx,
                handle:resizeHit.handle,
                start:p,
                original:JSON.parse(JSON.stringify(obj))
            };
            selecionarObjetoPlano(resizeHit.idx);
            return;
        }

        const idx = encontrarObjetoPlano(p.x, p.y);
        selecionarObjetoPlano(idx);
        if (idx >= 0) {
            const obj = planoObjects[idx];
            planoDragging = {mode:'move', idx, start:p, original:JSON.parse(JSON.stringify(obj))};
        }
        return;
    }

    if (planoTool === 'eraser') {
        const idx = encontrarObjetoPlano(p.x, p.y);
        if (idx >= 0) {
            planoObjects.splice(idx, 1);
            selecionarObjetoPlano(-1);
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
        return;
    }

    if (['line','arrow','run','dash'].includes(planoTool)) {
        planoDragging = {mode:'draw-line', tool:planoTool, start:p};
        planoPreviewObject = {type:'line',mode:planoTool,x1:p.x,y1:p.y,x2:p.x,y2:p.y,color:planoColor,w:4};
        desenharPlano();
        return;
    }

    if (['rect','circle'].includes(planoTool)) {
        planoDragging = {mode:'draw-shape', tool:planoTool, start:p};
        planoPreviewObject = criarShapePlano(planoTool, p, p);
        desenharPlano();
        return;
    }

    if (planoTool === 'text') {
        const txt = prompt('Texto a adicionar:', 'Texto');
        if (txt === null || txt.trim() === '') return;
        const obj = {type:'text',x:p.x,y:p.y,text:txt.trim(),color:planoColor,size:24,scale:1,strokeWidth:4};
        planoObjects.push(obj);
        selecionarObjetoPlano(planoObjects.length - 1);
        setPlanoTool('select', document.querySelector('.plano-tool-btn[data-tool="select"]'));
        desenharPlano();
        persistirExercicioAtualPlano(false);
        return;
    }

    const obj = criarObjetoPontualPlano(planoTool, p);
    if (obj) {
        planoObjects.push(obj);
        selecionarObjetoPlano(planoObjects.length - 1);
        setPlanoTool('select', document.querySelector('.plano-tool-btn[data-tool="select"]'));
        desenharPlano();
        persistirExercicioAtualPlano(false);
    }
}

function planoCanvasMouseMove(ev) {
    if (!planoCanvas) return;
    const p = normalizarPontoCanvas(ev);

    if (!planoDragging) {
        if (planoTool === 'select') {
            const hit = encontrarHandleResizePlano(p.x, p.y);
            if (hit) {
                planoCanvas.style.cursor = cursorResizePlano(hit.handle);
            } else {
                const idx = encontrarObjetoPlano(p.x, p.y);
                planoCanvas.style.cursor = idx >= 0 ? 'move' : 'default';
            }
        }
        return;
    }

    if (planoDragging.mode === 'move') {
        const obj = planoObjects[planoDragging.idx];
        const original = planoDragging.original;
        if (!obj || !original) return;
        const dx = p.x - planoDragging.start.x;
        const dy = p.y - planoDragging.start.y;
        if (obj.type === 'line') {
            obj.x1 = original.x1 + dx; obj.y1 = original.y1 + dy;
            obj.x2 = original.x2 + dx; obj.y2 = original.y2 + dy;
        } else {
            obj.x = original.x + dx; obj.y = original.y + dy;
        }
        desenharPlano();
        return;
    }

    if (planoDragging.mode === 'resize') {
        redimensionarObjetoPlanoComRato(p);
        atualizarPainelObjetoPlano();
        desenharPlano();
        return;
    }

    if (planoDragging.mode === 'draw-line') {
        planoPreviewObject = {
            type:'line', mode:planoDragging.tool,
            x1:planoDragging.start.x, y1:planoDragging.start.y,
            x2:p.x, y2:p.y, color:planoColor, w:4
        };
        desenharPlano();
        return;
    }

    if (planoDragging.mode === 'draw-shape') {
        planoPreviewObject = criarShapePlano(planoDragging.tool, planoDragging.start, p);
        desenharPlano();
    }
}

function planoCanvasMouseUp(ev) {
    if (!planoCanvas || !planoDragging) {
        planoIsPointerDown = false;
        atualizarCursorPlano();
        return;
    }
    const p = normalizarPontoCanvas(ev);

    if (planoDragging.mode === 'move' || planoDragging.mode === 'resize') {
        planoDragging = null;
        planoIsPointerDown = false;
        atualizarCursorPlano();
        desenharPlano();
        persistirExercicioAtualPlano(false);
        return;
    }

    if (planoDragging.mode === 'draw-line') {
        const start = planoDragging.start;
        if (Math.hypot(p.x - start.x, p.y - start.y) > 8) {
            const obj = {type:'line',mode:planoDragging.tool,x1:start.x,y1:start.y,x2:p.x,y2:p.y,color:planoColor,w:4};
            planoObjects.push(obj);
            selecionarObjetoPlano(planoObjects.length - 1);
            setPlanoTool('select', document.querySelector('.plano-tool-btn[data-tool="select"]'));
        }
        planoDragging = null;
        planoPreviewObject = null;
        planoIsPointerDown = false;
        desenharPlano();
        persistirExercicioAtualPlano(false);
        return;
    }

    if (planoDragging.mode === 'draw-shape') {
        const obj = criarShapePlano(planoDragging.tool, planoDragging.start, p);
        if (obj && ((obj.type === 'rect' && Math.abs(obj.w) > 10 && Math.abs(obj.h) > 10) || (obj.type === 'circle' && obj.r > 6))) {
            planoObjects.push(obj);
            selecionarObjetoPlano(planoObjects.length - 1);
            setPlanoTool('select', document.querySelector('.plano-tool-btn[data-tool="select"]'));
        }
        planoDragging = null;
        planoPreviewObject = null;
        planoIsPointerDown = false;
        desenharPlano();
        persistirExercicioAtualPlano(false);
    }
}

function encontrarHandleResizePlano(x, y) {
    if (planoSelectedIndex < 0 || !planoObjects[planoSelectedIndex]) return null;
    const handles = pontosResizePlano(planoObjects[planoSelectedIndex]);
    for (const h of handles) {
        if (Math.abs(x - h.x) <= PLANO_HANDLE_SIZE && Math.abs(y - h.y) <= PLANO_HANDLE_SIZE) {
            return {idx: planoSelectedIndex, handle: h.handle};
        }
    }
    return null;
}

function pontosResizePlano(o) {
    if (!o) return [];
    if (o.type === 'line') {
        return [
            {handle:'start', x:o.x1, y:o.y1},
            {handle:'end', x:o.x2, y:o.y2}
        ];
    }
    const b = boundsObjetoPlano(o);
    return [
        {handle:'nw', x:b.x, y:b.y},
        {handle:'ne', x:b.x + b.w, y:b.y},
        {handle:'sw', x:b.x, y:b.y + b.h},
        {handle:'se', x:b.x + b.w, y:b.y + b.h}
    ];
}

function cursorResizePlano(handle) {
    if (handle === 'nw' || handle === 'se') return 'nwse-resize';
    if (handle === 'ne' || handle === 'sw') return 'nesw-resize';
    if (handle === 'start' || handle === 'end') return 'crosshair';
    return 'grab';
}

function redimensionarObjetoPlanoComRato(p) {
    const obj = planoObjects[planoDragging.idx];
    const original = planoDragging.original;
    const handle = planoDragging.handle;
    if (!obj || !original) return;

    if (obj.type === 'line') {
        if (handle === 'start') {
            obj.x1 = p.x; obj.y1 = p.y;
        } else {
            obj.x2 = p.x; obj.y2 = p.y;
        }
        return;
    }

    if (obj.type === 'rect') {
        const b = boundsObjetoPlano(original);
        let left = b.x, right = b.x + b.w, top = b.y, bottom = b.y + b.h;
        if (handle.includes('w')) left = p.x;
        if (handle.includes('e')) right = p.x;
        if (handle.includes('n')) top = p.y;
        if (handle.includes('s')) bottom = p.y;
        obj.x = (left + right) / 2;
        obj.y = (top + bottom) / 2;
        obj.w = Math.max(12, Math.abs(right - left));
        obj.h = Math.max(12, Math.abs(bottom - top));
        obj.scale = 1;
        return;
    }

    if (obj.type === 'circle') {
        obj.r = Math.max(8, Math.hypot(p.x - original.x, p.y - original.y));
        obj.scale = 1;
        return;
    }

    const b = boundsObjetoPlano(original);
    const cx = original.x ?? (b.x + b.w / 2);
    const cy = original.y ?? (b.y + b.h / 2);
    const startHandle = pontosResizePlano(original).find(h => h.handle === handle) || {x:b.x+b.w, y:b.y+b.h};
    const d0 = Math.max(10, Math.hypot(startHandle.x - cx, startHandle.y - cy));
    const d1 = Math.max(10, Math.hypot(p.x - cx, p.y - cy));
    obj.scale = Math.max(0.25, Math.min(5, (Number(original.scale) || 1) * (d1 / d0)));
}

function boundsObjetoPlano(o) {
    const s = Number(o.scale || 1);

    if (o.type === 'rect') {
        const w = (o.w || 120) * s;
        const h = (o.h || 72) * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'circle') {
        const r = (o.r || 42) * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    if (o.type === 'text') {
        const fontSize = Math.max(10, (o.size || 24) * s);
        const width = Math.max(44, String(o.text || 'Texto').length * fontSize * 0.62);
        const height = fontSize * 1.35;
        return {x:o.x - width/2, y:o.y - height/2, w:width, h:height};
    }

    if (o.type === 'goal') {
        const w = 84 * s, h = 40 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'barrier') {
        const w = 74 * s, h = 62 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'cone') {
        const w = 52 * s, h = 58 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'ball') {
        const r = 18 * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    if (o.type === 'player') {
        const r = 22 * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    const r = raioHitObjetoPlano(o);
    return {x:(o.x||0)-r, y:(o.y||0)-r, w:r*2, h:r*2};
}

function encontrarObjetoPlano(x, y) {
    for (let i = planoObjects.length - 1; i >= 0; i--) {
        const o = planoObjects[i];
        if (o.type === 'line') {
            if (distanciaPontoLinha(x, y, o.x1, o.y1, o.x2, o.y2) < Math.max(12, (o.w || 4) + 8)) return i;
            continue;
        }
        const b = boundsObjetoPlano(o);
        if (x >= b.x - 8 && x <= b.x + b.w + 8 && y >= b.y - 8 && y <= b.y + b.h + 8) return i;
    }
    return -1;
}

function desenharSelecaoObjetoPlano(ctx, o) {
    ctx.save();
    ctx.strokeStyle = '#0ea5e9';
    ctx.fillStyle = '#0ea5e9';
    ctx.lineWidth = 2;
    ctx.setLineDash([6,5]);

    if (o.type === 'line') {
        ctx.strokeRect(
            Math.min(o.x1,o.x2)-8,
            Math.min(o.y1,o.y2)-8,
            Math.abs(o.x2-o.x1)+16,
            Math.abs(o.y2-o.y1)+16
        );
    } else {
        const b = boundsObjetoPlano(o);
        ctx.strokeRect(b.x, b.y, b.w, b.h);
    }

    ctx.setLineDash([]);
    const handles = pontosResizePlano(o);
    handles.forEach(h => {
        ctx.beginPath();
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = '#0ea5e9';
        ctx.lineWidth = 2;
        ctx.rect(h.x - 5, h.y - 5, 10, 10);
        ctx.fill();
        ctx.stroke();
    });
    ctx.restore();
}

function atualizarPainelObjetoPlano() {
    const panel = document.getElementById('planoSelectedPanel');
    if (!panel) return;
    const obj = planoSelectedIndex >= 0 ? planoObjects[planoSelectedIndex] : null;
    if (!obj) {
        panel.classList.remove('visible');
        return;
    }
    panel.classList.add('visible');
    const nomes = {line:'Linha',player:'Jogador',cone:'Cone',ball:'Bola',barrier:'Barreira',goal:'Baliza',rect:'Retângulo',circle:'Círculo',text:'Texto'};
    document.getElementById('planoSelectedName').textContent = nomes[obj.type] || 'Objeto';
    document.getElementById('planoSelectedType').textContent = obj.type === 'line' ? (obj.mode || 'linha') : obj.type;
    const size = document.getElementById('planoSizeRange');
    const stroke = document.getElementById('planoStrokeRange');
    if (size) {
        size.value = String(Number(obj.scale || 1).toFixed(2));
        size.parentElement.style.display = obj.type === 'line' ? 'none' : '';
    }
    if (stroke) {
        stroke.value = String(obj.type === 'line' ? (obj.w || 4) : (obj.strokeWidth || 4));
        stroke.parentElement.style.display = ['line','rect','circle','goal'].includes(obj.type) ? '' : 'none';
    }
}

function planoCanvasWheel(ev) {
    if (planoSelectedIndex < 0 || !planoObjects[planoSelectedIndex]) return;
    ev.preventDefault();
    const obj = planoObjects[planoSelectedIndex];
    if (obj.type === 'line') {
        obj.w = Math.max(1, Math.min(18, (Number(obj.w) || 4) + (ev.deltaY < 0 ? 1 : -1)));
    } else if (obj.type === 'rect') {
        const f = ev.deltaY < 0 ? 1.08 : 0.92;
        obj.w = Math.max(12, (obj.w || 120) * f);
        obj.h = Math.max(12, (obj.h || 72) * f);
        obj.scale = 1;
    } else if (obj.type === 'circle') {
        obj.r = Math.max(8, (obj.r || 42) * (ev.deltaY < 0 ? 1.08 : 0.92));
        obj.scale = 1;
    } else {
        obj.scale = Math.max(0.25, Math.min(5, (Number(obj.scale) || 1) + (ev.deltaY < 0 ? 0.08 : -0.08)));
    }
    atualizarPainelObjetoPlano();
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function alterarTamanhoObjetoPlano(value) {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    const v = Math.max(0.25, Math.min(5, parseFloat(value) || 1));
    obj.scale = v;
    desenharPlano();
    persistirExercicioAtualPlano(false);
}

function planoCanvasDoubleClick(ev) {
    const p = normalizarPontoCanvas(ev);
    const idx = encontrarObjetoPlano(p.x, p.y);
    if (idx < 0) return;
    const obj = planoObjects[idx];
    selecionarObjetoPlano(idx);
    if (obj.type === 'text') {
        const txt = prompt('Editar texto:', obj.text || 'Texto');
        if (txt !== null && txt.trim() !== '') obj.text = txt.trim();
    } else if (obj.type === 'player') {
        const n = prompt('Número / texto do jogador:', String(obj.n ?? ''));
        if (n !== null && n.trim() !== '') obj.n = n.trim();
    }
    desenharPlano();
    persistirExercicioAtualPlano(false);
}


/* ══════════════════════════════════
   PLANO VISUAL V5 — cones/barreiras/escada agrupados e recoloríveis
══════════════════════════════════ */
let planoConeTool = 'cone';
let planoBarrierTool = 'barrier';

function planoEquipmentIcon(tool) {
    const icons = {
        cone: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 5 9 31h22L20 5z" fill="currentColor" stroke="#111827" stroke-width="2" stroke-linejoin="round"/><path d="M14 20h12M12 27h16" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M9 32h22" stroke="#111827" stroke-width="3" stroke-linecap="round"/></svg>`,
        lowcone: `<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="13" fill="currentColor" stroke="#111827" stroke-width="2"/><circle cx="20" cy="20" r="5.5" fill="#fff" stroke="#111827" stroke-width="2"/></svg>`,
        barrier: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M7 12h26" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/><path d="M11 12v22M29 12v22" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><path d="M8 34h6M26 34h6" fill="none" stroke="#111827" stroke-width="2.4" stroke-linecap="round"/></svg>`,
        ladder: `<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M13 5v30M27 5v30" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/><path d="M13 11h14M13 17h14M13 23h14M13 29h14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>`
    };
    return icons[tool] || icons.cone;
}

function initPlanoEquipmentFlyouts() {
    const toolCol = document.querySelector('.plano-tool-column');
    if (!toolCol) return;

    const coneBtn = toolCol.querySelector(':scope > .plano-tool-btn[data-tool="cone"]');
    if (coneBtn && !document.getElementById('planoConeFlyout')) {
        coneBtn.outerHTML = `
            <div class="plano-tool-flyout plano-equipment-flyout" id="planoConeFlyout" title="Cones">
                <button class="plano-tool-btn plano-equipment-main" id="planoConeMainBtn" type="button" data-tool="cone" onclick="setPlanoConeTool(planoConeTool || 'cone')" title="Cones">${planoEquipmentIcon(planoConeTool)}</button>
                <div class="plano-flyout-menu plano-equipment-menu" aria-label="Tipos de cones">
                    <button class="plano-equipment-option active" type="button" data-equip-tool="cone" onclick="setPlanoConeTool('cone', this)" title="Cone alto">${planoEquipmentIcon('cone')}</button>
                    <button class="plano-equipment-option" type="button" data-equip-tool="lowcone" onclick="setPlanoConeTool('lowcone', this)" title="Cone baixo / marcador">${planoEquipmentIcon('lowcone')}</button>
                </div>
            </div>`;
    }

    const barrierBtn = toolCol.querySelector(':scope > .plano-tool-btn[data-tool="barrier"]');
    if (barrierBtn && !document.getElementById('planoBarrierFlyout')) {
        barrierBtn.outerHTML = `
            <div class="plano-tool-flyout plano-equipment-flyout" id="planoBarrierFlyout" title="Material de treino">
                <button class="plano-tool-btn plano-equipment-main" id="planoBarrierMainBtn" type="button" data-tool="barrier" onclick="setPlanoBarrierTool(planoBarrierTool || 'barrier')" title="Barreira / escada">${planoEquipmentIcon(planoBarrierTool)}</button>
                <div class="plano-flyout-menu plano-equipment-menu" aria-label="Material de treino">
                    <button class="plano-equipment-option active" type="button" data-equip-tool="barrier" onclick="setPlanoBarrierTool('barrier', this)" title="Barreira">${planoEquipmentIcon('barrier')}</button>
                    <button class="plano-equipment-option" type="button" data-equip-tool="ladder" onclick="setPlanoBarrierTool('ladder', this)" title="Escada de coordenação">${planoEquipmentIcon('ladder')}</button>
                </div>
            </div>`;
    }

    atualizarPlanoConeButton();
    atualizarPlanoBarrierButton();
    normalizarMenuCoresPlanoV5();

    const sizeRange = document.getElementById('planoSizeRange');
    if (sizeRange) {
        sizeRange.min = '0.25';
        sizeRange.max = '5';
        sizeRange.step = '0.05';
    }
}

function normalizarMenuCoresPlanoV5() {
    const palette = document.getElementById('planoColorPalette');
    if (palette) {
        palette.style.removeProperty('display');
    }
}

function atualizarPlanoConeButton() {
    const main = document.getElementById('planoConeMainBtn');
    if (main) {
        main.innerHTML = planoEquipmentIcon(planoConeTool || 'cone');
        main.dataset.tool = planoConeTool || 'cone';
        main.classList.toggle('active', planoTool === planoConeTool);
    }
    document.querySelectorAll('#planoConeFlyout .plano-equipment-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.equipTool === planoConeTool);
    });
}

function atualizarPlanoBarrierButton() {
    const main = document.getElementById('planoBarrierMainBtn');
    if (main) {
        main.innerHTML = planoEquipmentIcon(planoBarrierTool || 'barrier');
        main.dataset.tool = planoBarrierTool || 'barrier';
        main.classList.toggle('active', planoTool === planoBarrierTool);
    }
    document.querySelectorAll('#planoBarrierFlyout .plano-equipment-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.equipTool === planoBarrierTool);
    });
}

function setPlanoConeTool(tool, btn = null) {
    planoConeTool = tool || 'cone';
    atualizarPlanoConeButton();
    setPlanoTool(planoConeTool, document.getElementById('planoConeMainBtn'));
}

function setPlanoBarrierTool(tool, btn = null) {
    planoBarrierTool = tool || 'barrier';
    atualizarPlanoBarrierButton();
    setPlanoTool(planoBarrierTool, document.getElementById('planoBarrierMainBtn'));
}

const setPlanoToolV4 = typeof setPlanoTool === 'function' ? setPlanoTool : null;
setPlanoTool = function(tool, btn) {
    if (setPlanoToolV4) {
        setPlanoToolV4(tool, btn);
    } else {
        planoTool = tool;
    }
    atualizarPlanoConeButton();
    atualizarPlanoBarrierButton();
};

const abrirModalPlanoBaseV4 = typeof abrirModalPlanoBase === 'function' ? abrirModalPlanoBase : null;
if (abrirModalPlanoBaseV4) {
    abrirModalPlanoBase = function() {
        abrirModalPlanoBaseV4();
        setTimeout(() => {
            initPlanoEquipmentFlyouts();
            atualizarPlanoConeButton();
            atualizarPlanoBarrierButton();
            desenharPlano();
        }, 0);
    };
}

document.addEventListener('DOMContentLoaded', initPlanoEquipmentFlyouts);

criarObjetoPontualPlano = function(tool, p) {
    if (tool === 'player') return {type:'player',x:p.x,y:p.y,n:planoPlayerCounter++,color:'#3b82f6',scale:1};
    if (tool === 'opponent') return {type:'player',x:p.x,y:p.y,n:planoOpponentCounter++,color:'#ef4444',scale:1};
    if (tool === 'gk') return {type:'player',x:p.x,y:p.y,n:'GK',color:'#111827',scale:1};
    if (tool === 'cone') return {type:'cone',variant:'tall',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#f97316' : planoColor),scale:1};
    if (tool === 'lowcone') return {type:'cone',variant:'flat',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#f97316' : planoColor),scale:1};
    if (tool === 'ball') return {type:'ball',x:p.x,y:p.y,color:'#111827',scale:1};
    if (tool === 'barrier') return {type:'barrier',variant:'hurdle',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#f97316' : planoColor),scale:1,strokeWidth:4};
    if (tool === 'ladder') return {type:'ladder',x:p.x,y:p.y,color:(planoColor === '#000000' ? '#facc15' : planoColor),scale:1,strokeWidth:4};
    if (tool === 'goal') return {type:'goal',x:p.x,y:p.y,color:planoColor,scale:1,strokeWidth:4};
    return null;
};

const desenharObjetoPlanoV4 = typeof desenharObjetoPlano === 'function' ? desenharObjetoPlano : null;
desenharObjetoPlano = function(ctx, o, selected = false, preview = false) {
    ctx.save();
    ctx.globalAlpha = preview ? .62 : 1;
    ctx.strokeStyle = o.color || '#000';
    ctx.fillStyle = o.color || '#000';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (o.type === 'line') desenharLinhaPlano(ctx, o);
    else if (o.type === 'player') desenharJogadorPlano(ctx, o);
    else if (o.type === 'cone') desenharConePlano(ctx, o);
    else if (o.type === 'ball') desenharBolaPlano(ctx, o);
    else if (o.type === 'barrier') desenharBarreiraPlano(ctx, o);
    else if (o.type === 'ladder') desenharEscadaPlano(ctx, o);
    else if (o.type === 'goal') desenharBalizaPlano(ctx, o);
    else if (o.type === 'rect') desenharRetanguloPlano(ctx, o);
    else if (o.type === 'circle') desenharCirculoPlano(ctx, o);
    else if (o.type === 'text') desenharTextoPlano(ctx, o);
    else if (desenharObjetoPlanoV4) desenharObjetoPlanoV4(ctx, o, false, preview);

    if (selected) desenharSelecaoObjetoPlano(ctx, o);
    ctx.restore();
};

function desenharConePlano(ctx, o) {
    if ((o.variant || 'tall') === 'flat') {
        desenharConeBaixoPlano(ctx, o);
        return;
    }

    const s = Number(o.scale || 1), x = o.x, y = o.y, c = o.color || '#f97316';
    ctx.save();
    ctx.fillStyle = c;
    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2 * s;

    ctx.beginPath();
    ctx.moveTo(x, y - 27*s);
    ctx.lineTo(x - 16*s, y + 13*s);
    ctx.quadraticCurveTo(x, y + 20*s, x + 16*s, y + 13*s);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 3.2*s;
    ctx.beginPath();
    ctx.moveTo(x - 8*s, y - 5*s); ctx.lineTo(x + 8*s, y - 5*s);
    ctx.moveTo(x - 12*s, y + 7*s); ctx.lineTo(x + 12*s, y + 7*s);
    ctx.stroke();

    ctx.fillStyle = '#111827';
    ctx.beginPath();
    ctx.ellipse(x, y + 21*s, 23*s, 5*s, 0, 0, Math.PI*2);
    ctx.fill();
    ctx.fillStyle = c;
    ctx.beginPath();
    ctx.ellipse(x, y + 19*s, 19*s, 3*s, 0, 0, Math.PI*2);
    ctx.fill();
    ctx.restore();
}

function desenharConeBaixoPlano(ctx, o) {
    const s = Number(o.scale || 1), x = o.x, y = o.y, c = o.color || '#f97316';
    ctx.save();
    ctx.fillStyle = c;
    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2.4*s;

    ctx.beginPath();
    ctx.arc(x, y, 18*s, 0, Math.PI*2);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = '#fff';
    ctx.beginPath();
    ctx.arc(x, y, 7*s, 0, Math.PI*2);
    ctx.fill();
    ctx.stroke();

    ctx.strokeStyle = 'rgba(255,255,255,.7)';
    ctx.lineWidth = 1.5*s;
    ctx.beginPath();
    ctx.arc(x, y, 12*s, 0, Math.PI*2);
    ctx.stroke();
    ctx.restore();
}

function desenharBarreiraPlano(ctx, o) {
    const s = Number(o.scale || 1), x = o.x, y = o.y, c = o.color || '#f97316';
    const sw = Number(o.strokeWidth || 4);
    ctx.save();
    ctx.strokeStyle = c;
    ctx.lineWidth = sw * s;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(x - 30*s, y - 18*s);
    ctx.lineTo(x + 30*s, y - 18*s);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(x - 22*s, y - 18*s);
    ctx.lineTo(x - 22*s, y + 28*s);
    ctx.moveTo(x + 22*s, y - 18*s);
    ctx.lineTo(x + 22*s, y + 28*s);
    ctx.stroke();

    ctx.strokeStyle = '#111827';
    ctx.lineWidth = Math.max(1.8, 2.2*s);
    ctx.beginPath();
    ctx.moveTo(x - 32*s, y + 29*s);
    ctx.lineTo(x - 12*s, y + 29*s);
    ctx.moveTo(x + 12*s, y + 29*s);
    ctx.lineTo(x + 32*s, y + 29*s);
    ctx.stroke();
    ctx.restore();
}

function desenharEscadaPlano(ctx, o) {
    const s = Number(o.scale || 1), x = o.x, y = o.y, c = o.color || '#facc15';
    const sw = Number(o.strokeWidth || 4);
    const w = 46*s, h = 110*s;
    const left = x - w/2, right = x + w/2, top = y - h/2, bottom = y + h/2;
    ctx.save();
    ctx.strokeStyle = c;
    ctx.lineWidth = sw * s;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(left, top); ctx.lineTo(left, bottom);
    ctx.moveTo(right, top); ctx.lineTo(right, bottom);
    ctx.stroke();

    const rungs = 6;
    for (let i = 1; i < rungs; i++) {
        const yy = top + (h / rungs) * i;
        ctx.beginPath();
        ctx.moveTo(left, yy);
        ctx.lineTo(right, yy);
        ctx.stroke();
    }
    ctx.restore();
}

boundsObjetoPlano = function(o) {
    const s = Number(o.scale || 1);

    if (o.type === 'line') {
        const x = Math.min(o.x1, o.x2), y = Math.min(o.y1, o.y2);
        return {x, y, w:Math.abs(o.x2-o.x1), h:Math.abs(o.y2-o.y1)};
    }

    if (o.type === 'rect') {
        const w = (o.w || 120) * s;
        const h = (o.h || 72) * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'circle') {
        const r = (o.r || 42) * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    if (o.type === 'text') {
        const fontSize = Math.max(10, (o.size || 24) * s);
        const width = Math.max(44, String(o.text || 'Texto').length * fontSize * 0.62);
        const height = fontSize * 1.35;
        return {x:o.x - width/2, y:o.y - height/2, w:width, h:height};
    }

    if (o.type === 'goal') {
        const w = 84 * s, h = 40 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'barrier') {
        const w = 76 * s, h = 70 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'ladder') {
        const w = 58 * s, h = 122 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'cone') {
        if ((o.variant || 'tall') === 'flat') {
            const w = 46 * s, h = 46 * s;
            return {x:o.x - w/2, y:o.y - h/2, w, h};
        }
        const w = 52 * s, h = 60 * s;
        return {x:o.x - w/2, y:o.y - h/2, w, h};
    }

    if (o.type === 'ball') {
        const r = 18 * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    if (o.type === 'player') {
        const r = 22 * s;
        return {x:o.x - r, y:o.y - r, w:r*2, h:r*2};
    }

    const r = raioHitObjetoPlano(o);
    return {x:(o.x||0)-r, y:(o.y||0)-r, w:r*2, h:r*2};
};

raioHitObjetoPlano = function(o) {
    const s = Number(o.scale || 1);
    if (o.type === 'text') return Math.max(38, String(o.text || 'Texto').length * 8 * s);
    if (o.type === 'goal') return 52 * s;
    if (o.type === 'barrier') return 42 * s;
    if (o.type === 'ladder') return 66 * s;
    if (o.type === 'cone') return ((o.variant || 'tall') === 'flat' ? 26 : 32) * s;
    if (o.type === 'ball') return 26 * s;
    return 30 * s;
};

atualizarPainelObjetoPlano = function() {
    const panel = document.getElementById('planoSelectedPanel');
    if (!panel) return;
    const obj = planoSelectedIndex >= 0 ? planoObjects[planoSelectedIndex] : null;
    if (!obj) {
        panel.classList.remove('visible');
        return;
    }
    panel.classList.add('visible');

    const nomes = {
        line:'Linha', player:'Jogador', cone:((obj.variant || 'tall') === 'flat' ? 'Cone baixo' : 'Cone'),
        ball:'Bola', barrier:'Barreira', ladder:'Escada', goal:'Baliza', rect:'Retângulo', circle:'Círculo', text:'Texto'
    };
    const nameEl = document.getElementById('planoSelectedName');
    const typeEl = document.getElementById('planoSelectedType');
    if (nameEl) nameEl.textContent = nomes[obj.type] || 'Objeto';
    if (typeEl) typeEl.textContent = obj.type === 'line' ? (obj.mode || 'linha') : (obj.variant || obj.type);

    const size = document.getElementById('planoSizeRange');
    const stroke = document.getElementById('planoStrokeRange');
    if (size) {
        size.min = '0.25'; size.max = '5'; size.step = '0.05';
        size.value = String(Number(obj.scale || 1).toFixed(2));
        size.parentElement.style.display = obj.type === 'line' ? 'none' : '';
    }
    if (stroke) {
        stroke.value = String(obj.type === 'line' ? (obj.w || 4) : (obj.strokeWidth || 4));
        stroke.parentElement.style.display = ['line','rect','circle','goal','barrier','ladder'].includes(obj.type) ? '' : 'none';
    }
};

alterarEspessuraObjetoPlano = function(value) {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    const v = Math.max(1, Math.min(18, parseInt(value, 10) || 4));
    if (obj.type === 'line') obj.w = v;
    else obj.strokeWidth = v;
    desenharPlano();
    persistirExercicioAtualPlano(false);
};

alterarTamanhoObjetoPlano = function(value) {
    const obj = planoObjects[planoSelectedIndex];
    if (!obj) return;
    const v = Math.max(0.25, Math.min(5, parseFloat(value) || 1));
    obj.scale = v;
    desenharPlano();
    persistirExercicioAtualPlano(false);
};

const planoCanvasWheelV4 = typeof planoCanvasWheel === 'function' ? planoCanvasWheel : null;
planoCanvasWheel = function(ev) {
    if (planoSelectedIndex < 0 || !planoObjects[planoSelectedIndex]) return;
    ev.preventDefault();
    const obj = planoObjects[planoSelectedIndex];
    if (obj.type === 'line') {
        obj.w = Math.max(1, Math.min(18, (Number(obj.w) || 4) + (ev.deltaY < 0 ? 1 : -1)));
    } else if (obj.type === 'rect') {
        const f = ev.deltaY < 0 ? 1.08 : 0.92;
        obj.w = Math.max(12, (obj.w || 120) * f);
        obj.h = Math.max(12, (obj.h || 72) * f);
        obj.scale = 1;
    } else if (obj.type === 'circle') {
        obj.r = Math.max(8, (obj.r || 42) * (ev.deltaY < 0 ? 1.08 : 0.92));
        obj.scale = 1;
    } else {
        obj.scale = Math.max(0.25, Math.min(5, (Number(obj.scale) || 1) + (ev.deltaY < 0 ? 0.08 : -0.08)));
    }
    atualizarPainelObjetoPlano();
    desenharPlano();
    persistirExercicioAtualPlano(false);
};



/* ══════════════════════════════════
   PLANO VISUAL V6 — remover template extra, rotação e ferramenta contínua
══════════════════════════════════ */
(function planoVisualV6(){
    function normalizarAnguloPlano(deg) {
        const n = Number(deg) || 0;
        return ((n % 360) + 360) % 360;
    }

    function ensureTemplateV6() {
        const meio = document.querySelector('.plano-template-strip .template-btn[data-template="meio_campo"]');
        if (meio) meio.remove();
    }

    const initPropertyPanelBaseV6 = typeof initPlanoPropertyPanel === 'function' ? initPlanoPropertyPanel : null;
    if (initPropertyPanelBaseV6) {
        initPlanoPropertyPanel = function() {
            initPropertyPanelBaseV6();
            ensurePlanoRotationControl();
        };
    }

    window.ensurePlanoRotationControl = function ensurePlanoRotationControl() {
        const panel = document.getElementById('planoSelectedPanel');
        if (!panel || document.getElementById('planoRotationRow')) return;

        const strokeRow = document.getElementById('planoStrokeRange')?.closest('.plano-prop-row');
        const miniActions = panel.querySelector('.plano-mini-actions');
        const row = document.createElement('div');
        row.className = 'plano-prop-row';
        row.id = 'planoRotationRow';
        row.innerHTML = `
            <label for="planoRotationRange">Rotação</label>
            <input id="planoRotationRange" type="range" min="0" max="359" step="1" value="0">
            <div class="plano-rotate-actions">
                <button type="button" onclick="rodarObjetoPlano(-15)">↺ -15°</button>
                <button type="button" onclick="rodarObjetoPlano(15)">↻ +15°</button>
            </div>
        `;

        if (strokeRow) strokeRow.insertAdjacentElement('afterend', row);
        else if (miniActions) miniActions.insertAdjacentElement('beforebegin', row);
        else panel.appendChild(row);

        document.getElementById('planoRotationRange').addEventListener('input', ev => alterarRotacaoObjetoPlano(ev.target.value));
    };

    window.alterarRotacaoObjetoPlano = function alterarRotacaoObjetoPlano(value) {
        const obj = planoObjects?.[planoSelectedIndex];
        if (!obj || obj.type === 'line') return;
        obj.rotation = normalizarAnguloPlano(value);
        desenharPlano();
        persistirExercicioAtualPlano(false);
    };

    window.rodarObjetoPlano = function rodarObjetoPlano(delta) {
        const obj = planoObjects?.[planoSelectedIndex];
        if (!obj || obj.type === 'line') return;
        obj.rotation = normalizarAnguloPlano((Number(obj.rotation) || 0) + Number(delta || 0));
        const range = document.getElementById('planoRotationRange');
        if (range) range.value = String(obj.rotation);
        desenharPlano();
        persistirExercicioAtualPlano(false);
    };

    const atualizarPainelObjetoBaseV6 = typeof atualizarPainelObjetoPlano === 'function' ? atualizarPainelObjetoPlano : null;
    if (atualizarPainelObjetoBaseV6) {
        atualizarPainelObjetoPlano = function() {
            ensurePlanoRotationControl();
            atualizarPainelObjetoBaseV6();

            const row = document.getElementById('planoRotationRow');
            const range = document.getElementById('planoRotationRange');
            const obj = planoSelectedIndex >= 0 ? planoObjects[planoSelectedIndex] : null;

            if (!row || !range) return;

            if (!obj || obj.type === 'line') {
                row.style.display = 'none';
                return;
            }

            row.style.display = '';
            range.value = String(Math.round(normalizarAnguloPlano(obj.rotation || 0)));
        };
    }

    const boundsObjetoBaseV6 = typeof boundsObjetoPlano === 'function' ? boundsObjetoPlano : null;
    if (boundsObjetoBaseV6) {
        boundsObjetoPlano = function(o) {
            const b = boundsObjetoBaseV6(o);
            const angle = normalizarAnguloPlano(o?.rotation || 0);
            if (!o || !angle || o.type === 'line') return b;

            const cx = o.x ?? (b.x + b.w / 2);
            const cy = o.y ?? (b.y + b.h / 2);
            const rad = angle * Math.PI / 180;
            const cos = Math.cos(rad);
            const sin = Math.sin(rad);
            const corners = [
                [b.x, b.y], [b.x + b.w, b.y],
                [b.x + b.w, b.y + b.h], [b.x, b.y + b.h]
            ].map(([x, y]) => {
                const dx = x - cx;
                const dy = y - cy;
                return {
                    x: cx + dx * cos - dy * sin,
                    y: cy + dx * sin + dy * cos
                };
            });
            const xs = corners.map(p => p.x);
            const ys = corners.map(p => p.y);
            const minX = Math.min(...xs);
            const maxX = Math.max(...xs);
            const minY = Math.min(...ys);
            const maxY = Math.max(...ys);
            return {x:minX, y:minY, w:maxX-minX, h:maxY-minY};
        };
    }

    const desenharObjetoBaseV6 = typeof desenharObjetoPlano === 'function' ? desenharObjetoPlano : null;
    if (desenharObjetoBaseV6) {
        desenharObjetoPlano = function(ctx, o, selected = false, preview = false) {
            const angle = normalizarAnguloPlano(o?.rotation || 0);
            if (!o || !angle || o.type === 'line') {
                desenharObjetoBaseV6(ctx, o, selected, preview);
                return;
            }

            ctx.save();
            const cx = o.x || 0;
            const cy = o.y || 0;
            ctx.translate(cx, cy);
            ctx.rotate(angle * Math.PI / 180);
            const local = {...o, x:0, y:0};
            desenharObjetoBaseV6(ctx, local, false, preview);
            ctx.restore();

            if (selected) desenharSelecaoObjetoPlano(ctx, o);
        };
    }

    const criarObjetoPontualBaseV6 = typeof criarObjetoPontualPlano === 'function' ? criarObjetoPontualPlano : null;
    if (criarObjetoPontualBaseV6) {
        criarObjetoPontualPlano = function(tool, p) {
            const obj = criarObjetoPontualBaseV6(tool, p);
            if (obj && obj.type !== 'line' && obj.rotation === undefined) obj.rotation = 0;
            return obj;
        };
    }

    const criarShapeBaseV6 = typeof criarShapePlano === 'function' ? criarShapePlano : null;
    if (criarShapeBaseV6) {
        criarShapePlano = function(tool, start, end) {
            const obj = criarShapeBaseV6(tool, start, end);
            if (obj && obj.type !== 'line' && obj.rotation === undefined) obj.rotation = 0;
            return obj;
        };
    }

    const setPlanoToolBaseV6 = typeof setPlanoTool === 'function' ? setPlanoTool : null;
    if (setPlanoToolBaseV6) {
        setPlanoTool = function(tool, btn) {
            setPlanoToolBaseV6(tool, btn);
            ensureTemplateV6();
        };
    }

    const setPlanoLineToolBaseV6 = typeof setPlanoLineTool === 'function' ? setPlanoLineTool : null;
    if (setPlanoLineToolBaseV6) {
        setPlanoLineTool = function(tool, btn = null) {
            setPlanoLineToolBaseV6(tool, btn);
            ensureTemplateV6();
        };
    }

    const abrirModalPlanoBasePrevV6 = typeof abrirModalPlanoBase === 'function' ? abrirModalPlanoBase : null;
    if (abrirModalPlanoBasePrevV6) {
        abrirModalPlanoBase = function() {
            ensureTemplateV6();
            abrirModalPlanoBasePrevV6();
            setTimeout(() => {
                ensureTemplateV6();
                ensurePlanoRotationControl();
                atualizarPainelObjetoPlano();
                desenharPlano();
            }, 0);
        };
    }

    function criarTextoPlanoContinuo(p) {
        const txt = prompt('Texto a adicionar:', 'Texto');
        if (txt === null || txt.trim() === '') return;
        const obj = {
            type:'text',
            x:p.x,
            y:p.y,
            text:txt.trim(),
            color:planoColor,
            size:24,
            scale:1,
            strokeWidth:4,
            rotation:0
        };
        planoObjects.push(obj);
        selecionarObjetoPlano(planoObjects.length - 1);
        desenharPlano();
        persistirExercicioAtualPlano(false);
    }

    planoCanvasMouseDown = function(ev) {
        if (!planoCanvas) return;
        planoIsPointerDown = true;
        const p = normalizarPontoCanvas(ev);

        if (planoTool === 'select') {
            const resizeHit = encontrarHandleResizePlano(p.x, p.y);
            if (resizeHit) {
                const obj = planoObjects[resizeHit.idx];
                planoDragging = {
                    mode:'resize',
                    idx:resizeHit.idx,
                    handle:resizeHit.handle,
                    start:p,
                    original:JSON.parse(JSON.stringify(obj))
                };
                selecionarObjetoPlano(resizeHit.idx);
                return;
            }

            const idx = encontrarObjetoPlano(p.x, p.y);
            selecionarObjetoPlano(idx);
            if (idx >= 0) {
                const obj = planoObjects[idx];
                planoDragging = {mode:'move', idx, start:p, original:JSON.parse(JSON.stringify(obj))};
            }
            return;
        }

        if (planoTool === 'eraser') {
            const idx = encontrarObjetoPlano(p.x, p.y);
            if (idx >= 0) {
                planoObjects.splice(idx, 1);
                selecionarObjetoPlano(-1);
                desenharPlano();
                persistirExercicioAtualPlano(false);
            }
            return;
        }

        if (['line','arrow','run','dash'].includes(planoTool)) {
            planoDragging = {mode:'draw-line', tool:planoTool, start:p};
            planoPreviewObject = {type:'line',mode:planoTool,x1:p.x,y1:p.y,x2:p.x,y2:p.y,color:planoColor,w:4};
            desenharPlano();
            return;
        }

        if (['rect','circle'].includes(planoTool)) {
            planoDragging = {mode:'draw-shape', tool:planoTool, start:p};
            planoPreviewObject = criarShapePlano(planoTool, p, p);
            desenharPlano();
            return;
        }

        if (planoTool === 'text') {
            criarTextoPlanoContinuo(p);
            return;
        }

        const obj = criarObjetoPontualPlano(planoTool, p);
        if (obj) {
            if (obj.rotation === undefined) obj.rotation = 0;
            planoObjects.push(obj);
            selecionarObjetoPlano(planoObjects.length - 1);
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
    };

    planoCanvasMouseUp = function(ev) {
        if (!planoCanvas || !planoDragging) {
            planoIsPointerDown = false;
            atualizarCursorPlano();
            return;
        }
        const p = normalizarPontoCanvas(ev);

        if (planoDragging.mode === 'move' || planoDragging.mode === 'resize') {
            planoDragging = null;
            planoIsPointerDown = false;
            atualizarCursorPlano();
            desenharPlano();
            persistirExercicioAtualPlano(false);
            return;
        }

        if (planoDragging.mode === 'draw-line') {
            const start = planoDragging.start;
            if (Math.hypot(p.x - start.x, p.y - start.y) > 8) {
                const obj = {type:'line',mode:planoDragging.tool,x1:start.x,y1:start.y,x2:p.x,y2:p.y,color:planoColor,w:4};
                planoObjects.push(obj);
                selecionarObjetoPlano(planoObjects.length - 1);
            }
            planoDragging = null;
            planoPreviewObject = null;
            planoIsPointerDown = false;
            desenharPlano();
            persistirExercicioAtualPlano(false);
            return;
        }

        if (planoDragging.mode === 'draw-shape') {
            const obj = criarShapePlano(planoDragging.tool, planoDragging.start, p);
            if (obj && ((obj.type === 'rect' && Math.abs(obj.w) > 10 && Math.abs(obj.h) > 10) || (obj.type === 'circle' && obj.r > 6))) {
                if (obj.rotation === undefined) obj.rotation = 0;
                planoObjects.push(obj);
                selecionarObjetoPlano(planoObjects.length - 1);
            }
            planoDragging = null;
            planoPreviewObject = null;
            planoIsPointerDown = false;
            desenharPlano();
            persistirExercicioAtualPlano(false);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        ensureTemplateV6();
        ensurePlanoRotationControl();
    });
})();


/* ══════════════════════════════════
   PLANO VISUAL V7 — rotação pelo seletor azul + calendário dos treinos
══════════════════════════════════ */
(function planoVisualV7(){
    const ROTATE_HANDLE_GAP = 26;
    const ROTATE_HANDLE_RADIUS = 8;

    function normalizarAnguloPlanoV7(deg) {
        const n = Number(deg) || 0;
        return ((n % 360) + 360) % 360;
    }

    function centroObjetoPlanoV7(o) {
        if (!o) return {x:0, y:0};
        if (o.type === 'line') {
            return {x:(Number(o.x1) + Number(o.x2)) / 2, y:(Number(o.y1) + Number(o.y2)) / 2};
        }
        if (typeof o.x === 'number' && typeof o.y === 'number') {
            return {x:o.x, y:o.y};
        }
        const b = boundsObjetoPlano(o);
        return {x:b.x + b.w / 2, y:b.y + b.h / 2};
    }

    function pontoRotacaoPlanoV7(o) {
        if (!o) return null;
        const b = boundsObjetoPlano(o);
        return {
            x: b.x + b.w / 2,
            y: b.y - ROTATE_HANDLE_GAP
        };
    }

    function desenharIconRotacaoPlanoV7(ctx, x, y) {
        ctx.save();
        ctx.strokeStyle = '#0f172a';
        ctx.fillStyle = '#0f172a';
        ctx.lineWidth = 1.8;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.arc(x, y, 3.8, Math.PI * 0.2, Math.PI * 1.75);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x + 3.3, y - 4.7);
        ctx.lineTo(x + 7.3, y - 4.3);
        ctx.lineTo(x + 5.4, y - 0.9);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    window.encontrarHandleRotacaoPlano = function encontrarHandleRotacaoPlano(x, y) {
        if (planoSelectedIndex < 0 || !planoObjects[planoSelectedIndex]) return null;
        const p = pontoRotacaoPlanoV7(planoObjects[planoSelectedIndex]);
        if (!p) return null;
        if (Math.hypot(x - p.x, y - p.y) <= ROTATE_HANDLE_RADIUS + 7) {
            return {idx: planoSelectedIndex, handle: 'rotate'};
        }
        return null;
    };

    const desenharSelecaoObjetoBaseV7 = typeof desenharSelecaoObjetoPlano === 'function' ? desenharSelecaoObjetoPlano : null;
    window.desenharSelecaoObjetoPlano = function desenharSelecaoObjetoPlano(ctx, o) {
        ctx.save();
        ctx.strokeStyle = '#0ea5e9';
        ctx.fillStyle = '#0ea5e9';
        ctx.lineWidth = 2;
        ctx.setLineDash([6,5]);

        const b = boundsObjetoPlano(o);
        ctx.strokeRect(b.x, b.y, b.w, b.h);

        ctx.setLineDash([]);
        const handles = pontosResizePlano(o);
        handles.forEach(h => {
            ctx.beginPath();
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = '#0ea5e9';
            ctx.lineWidth = 2;
            ctx.rect(h.x - 5, h.y - 5, 10, 10);
            ctx.fill();
            ctx.stroke();
        });

        const rot = pontoRotacaoPlanoV7(o);
        if (rot) {
            const anchorX = b.x + b.w / 2;
            const anchorY = b.y;
            ctx.beginPath();
            ctx.strokeStyle = '#0ea5e9';
            ctx.lineWidth = 2;
            ctx.moveTo(anchorX, anchorY);
            ctx.lineTo(rot.x, rot.y + ROTATE_HANDLE_RADIUS);
            ctx.stroke();

            ctx.beginPath();
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = '#0ea5e9';
            ctx.lineWidth = 2;
            ctx.arc(rot.x, rot.y, ROTATE_HANDLE_RADIUS, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            desenharIconRotacaoPlanoV7(ctx, rot.x, rot.y);
        }
        ctx.restore();
    };

    function rotacionarPontoPlanoV7(px, py, cx, cy, rad) {
        const dx = px - cx;
        const dy = py - cy;
        const cos = Math.cos(rad);
        const sin = Math.sin(rad);
        return {
            x: cx + dx * cos - dy * sin,
            y: cy + dx * sin + dy * cos
        };
    }

    const mouseDownBaseV7 = typeof planoCanvasMouseDown === 'function' ? planoCanvasMouseDown : null;
    planoCanvasMouseDown = function(ev) {
        if (!planoCanvas) return;
        const p = normalizarPontoCanvas(ev);

        if (planoTool === 'select') {
            const rotateHit = encontrarHandleRotacaoPlano(p.x, p.y);
            if (rotateHit) {
                const obj = planoObjects[rotateHit.idx];
                const center = centroObjetoPlanoV7(obj);
                planoIsPointerDown = true;
                planoDragging = {
                    mode: 'rotate',
                    idx: rotateHit.idx,
                    start: p,
                    center,
                    startAngle: Math.atan2(p.y - center.y, p.x - center.x) * 180 / Math.PI,
                    originalRotation: Number(obj.rotation || 0),
                    original: JSON.parse(JSON.stringify(obj))
                };
                selecionarObjetoPlano(rotateHit.idx);
                planoCanvas.style.cursor = 'grabbing';
                return;
            }
        }

        if (mouseDownBaseV7) {
            mouseDownBaseV7(ev);
        }
    };

    const mouseMoveBaseV7 = typeof planoCanvasMouseMove === 'function' ? planoCanvasMouseMove : null;
    planoCanvasMouseMove = function(ev) {
        if (!planoCanvas) return;
        const p = normalizarPontoCanvas(ev);

        if (!planoDragging && planoTool === 'select') {
            if (encontrarHandleRotacaoPlano(p.x, p.y)) {
                planoCanvas.style.cursor = 'grab';
                return;
            }
        }

        if (planoDragging && planoDragging.mode === 'rotate') {
            const obj = planoObjects[planoDragging.idx];
            const original = planoDragging.original;
            const center = planoDragging.center;
            if (!obj || !original || !center) return;

            const currentAngle = Math.atan2(p.y - center.y, p.x - center.x) * 180 / Math.PI;
            const deltaDeg = currentAngle - planoDragging.startAngle;

            if (obj.type === 'line') {
                const rad = deltaDeg * Math.PI / 180;
                const p1 = rotacionarPontoPlanoV7(original.x1, original.y1, center.x, center.y, rad);
                const p2 = rotacionarPontoPlanoV7(original.x2, original.y2, center.x, center.y, rad);
                obj.x1 = p1.x;
                obj.y1 = p1.y;
                obj.x2 = p2.x;
                obj.y2 = p2.y;
            } else {
                obj.rotation = normalizarAnguloPlanoV7((Number(planoDragging.originalRotation) || 0) + deltaDeg);
                const range = document.getElementById('planoRotationRange');
                if (range) range.value = String(Math.round(obj.rotation));
            }

            atualizarPainelObjetoPlano();
            desenharPlano();
            return;
        }

        if (mouseMoveBaseV7) {
            mouseMoveBaseV7(ev);
        }
    };

    const mouseUpBaseV7 = typeof planoCanvasMouseUp === 'function' ? planoCanvasMouseUp : null;
    planoCanvasMouseUp = function(ev) {
        if (planoDragging && planoDragging.mode === 'rotate') {
            planoDragging = null;
            planoIsPointerDown = false;
            atualizarCursorPlano();
            desenharPlano();
            persistirExercicioAtualPlano(false);
            return;
        }

        if (mouseUpBaseV7) {
            mouseUpBaseV7(ev);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const palette = document.getElementById('planoColorPalette');
        const colorFlyout = document.querySelector('.plano-color-flyout');
        if (palette) {
            palette.style.marginTop = '4px';
            palette.style.transform = 'translateY(-2px)';
        }
        if (colorFlyout) {
            colorFlyout.style.gap = '2px';
            colorFlyout.style.paddingTop = '0';
        }
    });
})();

/* ══════════════════════════════════
   DETALHE DE JOGO
══════════════════════════════════ */
function jogadoresConvocadosDetalhe() {
    return (typeof jogadoresDetalheJogo === 'undefined' ? [] : jogadoresDetalheJogo).filter(j => document.querySelector(`input[name="convocados[]"][value="${j.id_jogador}"]`)?.checked);
}

function jogadoresTitularesDetalhe() {
    return jogadoresConvocadosDetalhe().filter(j => document.querySelector(`select[name="papel_jogador[${j.id_jogador}]"]`)?.value === 'Titular');
}

function jogadoresEmJogoDetalhe(minuto = 0) {
    const convocados = jogadoresConvocadosDetalhe();
    const emJogo = new Map(convocados.filter(j => document.querySelector(`select[name="papel_jogador[${j.id_jogador}]"]`)?.value === 'Titular').map(j => [String(j.id_jogador), j]));
    [...document.querySelectorAll('#substituicoesLista .game-event-row')]
        .map(linha => ({entrada: linha.querySelector('[name="substituicao_entrada[]"]')?.value, saida: linha.querySelector('[name="substituicao_saida[]"]')?.value, minuto: Number(linha.querySelector('[name="substituicao_minuto[]"]')?.value || 0)}))
        .filter(substituicao => substituicao.minuto > 0 && substituicao.minuto <= minuto)
        .sort((a, b) => a.minuto - b.minuto)
        .forEach(substituicao => { emJogo.delete(String(substituicao.saida)); const jogador = convocados.find(j => String(j.id_jogador) === String(substituicao.entrada)); if (jogador) emJogo.set(String(jogador.id_jogador), jogador); });
    return [...emJogo.values()];
}

function opcoesJogadoresDetalhe(selecionado, permitirVazio, jogadores = jogadoresConvocadosDetalhe()) {
    const vazio = permitirVazio ? '<option value="">Sem assistência</option>' : '<option value="">Selecionar</option>';
    return vazio + jogadores.map(j => `<option value="${j.id_jogador}" ${String(j.id_jogador) === String(selecionado || '') ? 'selected' : ''}>${esc(j.nome_completo)}</option>`).join('');
}

function adicionarSubstituicao(valor = {}) {
    const lista = document.getElementById('substituicoesLista');
    if (!lista) return;
    const linha = document.createElement('div');
    linha.className = 'game-event-row';
    linha.innerHTML = `<label>Entra<select name="substituicao_entrada[]">${opcoesJogadoresDetalhe(valor.id_jogador_entrada)}</select></label><label>Sai<select name="substituicao_saida[]">${opcoesJogadoresDetalhe(valor.id_jogador_saida)}</select></label><label>Minuto<input type="number" min="1" name="substituicao_minuto[]" value="${valor.minuto || ''}"></label><button type="button" class="btn-row-edit btn-row-delete" title="Remover substituição" aria-label="Remover substituição" onclick="this.parentElement.remove();atualizarOpcoesDetalheJogo()">×</button>`;
    lista.appendChild(linha);
    linha.querySelectorAll('select,input').forEach(campo => campo.addEventListener('change', atualizarOpcoesDetalheJogo));
}

function adicionarGolo(valor = {}) {
    const lista = document.getElementById('golosLista');
    if (!lista) return;
    const linha = document.createElement('div');
    linha.className = 'game-event-row golo';
    linha.innerHTML = `<label>Marcador<select name="golo_marcador[]" onchange="sincronizarGolos()">${opcoesJogadoresDetalhe(valor.id_jogador_marcador)}</select></label><label>Assistência<select name="golo_assistente[]" onchange="sincronizarGolos()">${opcoesJogadoresDetalhe(valor.id_jogador_assistente, true)}</select></label><label>Minuto<input type="number" min="1" name="golo_minuto[]" value="${valor.minuto || ''}"></label><label>Zona<select name="golo_zona[]"><option value="">Selecionar</option><option ${valor.zona === 'Dentro da área' ? 'selected' : ''}>Dentro da área</option><option ${valor.zona === 'Fora da área' ? 'selected' : ''}>Fora da área</option><option ${valor.zona === 'Pequena área' ? 'selected' : ''}>Pequena área</option></select></label><label>Origem<select name="golo_forma[]"><option value="">Selecionar</option><option ${valor.forma === 'Bola corrida' ? 'selected' : ''}>Bola corrida</option><option ${valor.forma === 'Bola parada' ? 'selected' : ''}>Bola parada</option><option ${valor.forma === 'Grande penalidade' ? 'selected' : ''}>Grande penalidade</option><option ${valor.forma === 'Canto' ? 'selected' : ''}>Canto</option><option ${valor.forma === 'Livre' ? 'selected' : ''}>Livre</option></select></label><button type="button" class="btn-row-edit btn-row-delete" title="Remover golo" aria-label="Remover golo" onclick="this.parentElement.remove();sincronizarGolos()">×</button>`;
    lista.appendChild(linha);
    linha.querySelector('[name="golo_minuto[]"]').addEventListener('change', atualizarOpcoesDetalheJogo);
    sincronizarGolos();
}

function substituirOpcoes(select, opcoes) {
    if (!select) return;
    const selecionado = select.value;
    select.innerHTML = opcoes;
    if ([...select.options].some(opcao => opcao.value === selecionado)) select.value = selecionado;
}

function atualizarOpcoesDetalheJogo() {
    const convocados = jogadoresConvocadosDetalhe();
    // No relvado só podem entrar jogadores convocados E marcados como Titular — nunca suplentes nem não convocados.
    document.querySelectorAll('#tacticPitch select[name^="posicao_titular"]').forEach(select => substituirOpcoes(select, opcoesJogadoresDetalhe(select.value, false, jogadoresTitularesDetalhe())));
    atualizarResumoOnzeInicial();

    document.querySelectorAll('#substituicoesLista .game-event-row').forEach(linha => {
        const minuto = Number(linha.querySelector('[name="substituicao_minuto[]"]')?.value || 0);
        const emJogo = jogadoresEmJogoDetalhe(Math.max(0, minuto - 0.01));
        const entrada = linha.querySelector('[name="substituicao_entrada[]"]');
        const saida = linha.querySelector('[name="substituicao_saida[]"]');
        substituirOpcoes(entrada, opcoesJogadoresDetalhe(entrada?.value, false, convocados.filter(jogador => !emJogo.some(ativo => ativo.id_jogador === jogador.id_jogador))));
        substituirOpcoes(saida, opcoesJogadoresDetalhe(saida?.value, false, emJogo));
    });

    document.querySelectorAll('#golosLista .game-event-row').forEach(linha => {
        const minuto = Number(linha.querySelector('[name="golo_minuto[]"]')?.value || 0);
        const emJogo = jogadoresEmJogoDetalhe(minuto);
        substituirOpcoes(linha.querySelector('[name="golo_marcador[]"]'), opcoesJogadoresDetalhe(linha.querySelector('[name="golo_marcador[]"]')?.value, false, emJogo));
        substituirOpcoes(linha.querySelector('[name="golo_assistente[]"]'), opcoesJogadoresDetalhe(linha.querySelector('[name="golo_assistente[]"]')?.value, true, emJogo));
    });

    const jogadoresComMinutos = jogadoresConvocadosDetalhe();
    document.querySelectorAll('.game-stats-scroll tbody tr').forEach(linha => {
        linha.style.display = jogadoresComMinutos.some(jogador => String(jogador.id_jogador) === linha.dataset.jogador) ? '' : 'none';
    });
}

function atualizarMinutosJogados(totalMinutos) {
    totalMinutos = Number(totalMinutos || 0);
    const minutosPorJogador = new Map(jogadoresTitularesDetalhe().map(jogador => [String(jogador.id_jogador), totalMinutos]));
    [...document.querySelectorAll('#substituicoesLista .game-event-row')]
        .map(linha => ({entrada: linha.querySelector('[name="substituicao_entrada[]"]')?.value, saida: linha.querySelector('[name="substituicao_saida[]"]')?.value, minuto: Number(linha.querySelector('[name="substituicao_minuto[]"]')?.value || 0)}))
        .filter(substituicao => substituicao.minuto > 0 && substituicao.minuto <= totalMinutos)
        .sort((a, b) => a.minuto - b.minuto)
        .forEach(substituicao => {
            if (minutosPorJogador.has(String(substituicao.saida))) minutosPorJogador.set(String(substituicao.saida), substituicao.minuto);
            minutosPorJogador.set(String(substituicao.entrada), Math.max(0, totalMinutos - substituicao.minuto));
        });
    document.querySelectorAll('.game-stats-scroll tbody tr').forEach(linha => {
        const input = linha.querySelector('[name$="[minutos_jogados]"]');
        if (input) input.value = minutosPorJogador.get(linha.dataset.jogador) || 0;
    });
}

function bloquearConvocatoria() {
    document.querySelectorAll('.convocado-row input, .convocado-row select').forEach(campo => {
        campo.dataset.bloqueado = '1';
        campo.setAttribute('aria-disabled', 'true');
        campo.tabIndex = -1;
        campo.addEventListener('click', evento => evento.preventDefault());
        campo.addEventListener('change', evento => evento.preventDefault());
        campo.addEventListener('keydown', evento => evento.preventDefault());
    });
    document.querySelector('.convocatoria-lista')?.classList.add('convocatoria-bloqueada');

    // A partir do momento em que o jogo é iniciado, quem começou com a bola fica fixo e não pode ser alterado.
    document.querySelectorAll('#posseInicial button[data-equipa]').forEach(botao => { botao.disabled = true; });
    document.getElementById('posseInicial')?.classList.add('posse-bloqueada');
}

function alterarContador(botao, diferenca) {
    const input = botao.parentElement.querySelector('input');
    input.value = Math.max(0, Number(input.value || 0) + diferenca);
    const dependencias = { remates_baliza: 'remates', passes_certos: 'passes', dribles_conseguidos: 'dribles_tentados', remates_baliza_nos: 'remates_nos', remates_baliza_adversario: 'remates_adversario', passes_certos_nos: 'passes_nos', passes_certos_adversario: 'passes_adversario', dribles_conseguidos_nos: 'dribles_tentados_nos', dribles_conseguidos_adversario: 'dribles_tentados_adversario' };
    const nome = input.name.match(/\[([^\]]+)\]$/)?.[1] || input.name;
    const base = dependencias[nome];
    if (base) {
        const seletor = input.name.includes('estatisticas_individuais') ? input.name.replace(`[${nome}]`, `[${base}]`) : `[name="${base}"]`;
        const inputBase = document.querySelector(seletor);
        if (inputBase) inputBase.value = Math.max(0, Number(inputBase.value || 0) + diferenca);
    }
    input.dispatchEvent(new Event('change'));
}

function sincronizarGolos() {
    const marcadores = [...document.querySelectorAll('[name="golo_marcador[]"]')].map(el => el.value).filter(Boolean);
    const assistentes = [...document.querySelectorAll('[name="golo_assistente[]"]')].map(el => el.value).filter(Boolean);
    const porJogador = valores => valores.reduce((resultado, id) => ({...resultado, [id]: (resultado[id] || 0) + 1}), {});
    const golos = porJogador(marcadores); const assistencias = porJogador(assistentes);
    document.querySelectorAll('[name^="estatisticas_individuais["]').forEach(input => {
        const resultado = input.name.match(/^estatisticas_individuais\[(\d+)\]\[(golos|assistencias)\]$/);
        if (resultado) input.value = resultado[2] === 'golos' ? (golos[resultado[1]] || 0) : (assistencias[resultado[1]] || 0);
    });
    const resultadoNos = document.querySelector('[name="resultado_nos"]');
    if (resultadoNos) resultadoNos.value = marcadores.length;
    ['remates_nos', 'remates_baliza_nos'].forEach(nome => {
        const input = document.querySelector(`[name="${nome}"]`);
        if (input) input.value = Math.max(Number(input.value || 0), marcadores.length);
    });
}

function gerarRelatorioJogo() {
    const obterTextoSelecionado = seletor => seletor?.options?.[seletor.selectedIndex]?.text || '—';
    const valorCampo = campo => campo?.tagName === 'SELECT' ? obterTextoSelecionado(campo) : (campo?.value || '—');
    const titulo = document.querySelector('.game-detail-title')?.textContent.trim() || 'Relatório de jogo';
    const partes = document.querySelector('[name="numero_partes"]')?.value || '—';
    const minutos = document.querySelector('[name="minutos_por_parte"]')?.value || '—';
    const resultadoNos = document.querySelector('[name="resultado_nos"]')?.value || '—';
    const resultadoAdv = document.querySelector('[name="resultado_adv"]')?.value || '—';
    const tatica = obterTextoSelecionado(document.getElementById('taticaJogo'));
    const convocados = [...document.querySelectorAll('.convocado-row')].filter(linha => linha.querySelector('input[type="checkbox"]')?.checked || linha.querySelector('select')?.value).map(linha => {
        const numero = linha.querySelector('.convocado-number')?.textContent.trim() || '';
        const nome = linha.querySelector('.convocado-name')?.textContent.trim() || '';
        const papel = obterTextoSelecionado(linha.querySelector('select'));
        return [numero, nome, papel === '—' ? 'Convocado' : papel];
    });
    const linhasEvento = (id, campos) => [...document.querySelectorAll(`#${id} .game-event-row`)].map(linha => campos.map(campo => valorCampo(linha.querySelector(campo))));
    const substituicoes = linhasEvento('substituicoesLista', ['[name="substituicao_entrada[]"]','[name="substituicao_saida[]"]','[name="substituicao_minuto[]"]']);
    const golos = linhasEvento('golosLista', ['[name="golo_marcador[]"]','[name="golo_assistente[]"]','[name="golo_minuto[]"]','[name="golo_zona[]"]','[name="golo_forma[]"]']);
    const coletivas = [...document.querySelectorAll('#estatisticasColetivasGrid .edit-group')].map(grupo => {
        const input = grupo.querySelector('input[name]');
        return input ? [grupo.querySelector('label')?.textContent.trim() || '', input.value] : null;
    }).filter(Boolean);
    // No relatório só devem entrar jogadores que realmente entraram em campo (minutos jogados > 0) —
    // não convocados que ficaram no banco nem jogadores que a convocatória escondeu.
    const individuais = [...document.querySelectorAll('.game-stats-scroll tbody tr')]
        .filter(linha => linha.style.display !== 'none' && Number(linha.querySelector('[name$="[minutos_jogados]"]')?.value || 0) > 0)
        .map(linha => [...linha.querySelectorAll('td')].map((celula, indice) => indice ? (celula.querySelector('input')?.value || '0') : celula.textContent.trim()));
    const cabecalhoIndividuais = [...document.querySelectorAll('.game-stats-scroll th')].map(celula => celula.textContent.trim());
    const posseCasa = document.getElementById('posseCasaPercentagem')?.textContent || '0%';
    const posseVisitante = document.getElementById('posseVisitantePercentagem')?.textContent || '0%';
    descarregarPdfJogo({ titulo, partes, minutos, resultadoNos, resultadoAdv, tatica, convocados, substituicoes, golos, coletivas, individuais, cabecalhoIndividuais, posseCasa, posseVisitante }, titulo);
}

function descarregarPdfJogo(dados, titulo) {
    const limpar = valor => String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\x20-\x7E]/g, ' ').replace(/[\\()]/g, '\\$&');
    const corHex = getComputedStyle(document.documentElement).getPropertyValue('--club').trim().replace('#', '') || '17334F';
    const cor = [parseInt(corHex.substring(0, 2), 16) / 255, parseInt(corHex.substring(2, 4), 16) / 255, parseInt(corHex.substring(4, 6), 16) / 255].map(valor => Number.isFinite(valor) ? valor.toFixed(3) : '0.100');
    const paginas = [[]]; let y = 794;
    const pagina = () => paginas[paginas.length - 1];
    const cmd = texto => pagina().push(texto);
    const texto = (conteudo, x, posY, tamanho = 10, negrito = false, corTexto = '0.12 0.16 0.22') => cmd(`BT /F${negrito ? '2' : '1'} ${tamanho} Tf ${corTexto} rg 1 0 0 1 ${x} ${posY} Tm (${limpar(conteudo)}) Tj ET`);
    const retangulo = (x, posY, largura, altura, corPreenchimento) => cmd(`${corPreenchimento} rg ${x} ${posY} ${largura} ${altura} re f`);
    const novaPagina = () => { paginas.push([]); y = 794; desenharTopo(false); };
    const garantir = altura => { if (y - altura < 48) novaPagina(); };
    const linha = () => { cmd(`0.86 0.89 0.92 RG 48 ${y} m 547 ${y} l S`); y -= 13; };
    const desenharTopo = primeira => {
        retangulo(0, 770, 595, 72, cor.join(' '));
        texto(primeira ? 'KROOS PROJECT  |  RELATORIO DE JOGO' : 'KROOS PROJECT  |  RELATORIO DE JOGO', 48, 810, 9, true, '1 1 1');
        texto(primeira ? dados.titulo : 'Continuação do relatório', 48, 786, 18, true, '1 1 1');
        y = 744;
    };
    const secao = nome => { garantir(38); texto(nome.toUpperCase(), 48, y, 10, true, cor.join(' ')); y -= 8; linha(); };
    const tabela = (cabecalhos, linhas, larguras) => {
        garantir(30); const x = 48; const alturaLinha = 18;
        retangulo(x, y - alturaLinha + 4, 499, alturaLinha, cor.join(' '));
        let cursor = x + 6; cabecalhos.forEach((cabecalho, indice) => { texto(cabecalho, cursor, y - 8, 7, true, '1 1 1'); cursor += larguras[indice]; }); y -= alturaLinha;
        linhas.forEach((dadosLinha, indice) => { garantir(alturaLinha + 6); if (indice % 2 === 0) retangulo(x, y - alturaLinha + 4, 499, alturaLinha, '0.965 0.975 0.980'); cursor = x + 6; dadosLinha.forEach((celula, coluna) => { texto(String(celula).slice(0, Math.floor(larguras[coluna] / 5.5)), cursor, y - 8, 7.5, false); cursor += larguras[coluna]; }); y -= alturaLinha; });
        y -= 10;
    };
    desenharTopo(true);
    texto(`Tática  ${dados.tatica}`, 48, y, 10, true); texto(`${dados.partes} parte(s) de ${dados.minutos} min.`, 365, y, 10); y -= 28;
    retangulo(48, y - 55, 499, 55, '0.955 0.970 0.980');
    texto('RESULTADO FINAL', 66, y - 18, 8, true, '0.35 0.42 0.50');
    texto(`${dados.resultadoNos}  -  ${dados.resultadoAdv}`, 66, y - 43, 24, true, cor.join(' '));
    texto('POSSE DE BOLA', 350, y - 18, 8, true, '0.35 0.42 0.50');
    texto(`Casa ${dados.posseCasa}   |   Visitante ${dados.posseVisitante}`, 350, y - 40, 11, true); y -= 78;
    secao('Convocatória');
    tabela(['#', 'JOGADOR', 'ESTATUTO'], dados.convocados.length ? dados.convocados : [['—', 'Sem convocados registados', '—']], [42, 285, 160]);
    secao('Eventos do jogo');
    tabela(['SUBSTITUIÇÕES', 'SAI', 'MIN.'], dados.substituicoes.length ? dados.substituicoes : [['Sem registos', '—', '—']], [210, 210, 67]);
    tabela(['GOLO', 'ASSIST.', 'MIN.', 'ZONA', 'ORIGEM'], dados.golos.length ? dados.golos : [['Sem registos', '—', '—', '—', '—']], [115, 115, 52, 105, 100]);
    secao('Estatísticas coletivas');
    const coletivasDuasColunas = [];
    for (let indice = 0; indice < dados.coletivas.length; indice += 2) coletivasDuasColunas.push([dados.coletivas[indice]?.[0] || '', dados.coletivas[indice]?.[1] || '', dados.coletivas[indice + 1]?.[0] || '', dados.coletivas[indice + 1]?.[1] || '']);
    tabela(['MÉTRICA', 'VALOR', 'MÉTRICA', 'VALOR'], coletivasDuasColunas, [190, 58, 190, 58]);
    secao('Estatísticas individuais');
    const indicesResumo = [0, 1, 2, 3, 4, 5, 6];
    const cabecalhosResumo = indicesResumo.map(indice => dados.cabecalhoIndividuais[indice] || '');
    const linhasResumo = dados.individuais.map(linhaDados => indicesResumo.map(indice => linhaDados[indice] || '0'));
    tabela(cabecalhosResumo, linhasResumo.length ? linhasResumo : [['Sem estatísticas']], [130, 45, 45, 45, 45, 75, 75]);
    paginas.forEach((paginaComandos, indice) => {
        paginaComandos.push(`BT /F1 8 Tf 0.40 0.46 0.54 rg 1 0 0 1 470 24 Tm (Pagina ${indice + 1} de ${paginas.length}) Tj ET`);
    });
    const objetos = ['<< /Type /Catalog /Pages 2 0 R >>', `<< /Type /Pages /Kids [${paginas.map((_, indice) => `${3 + indice * 2} 0 R`).join(' ')}] /Count ${paginas.length} >>`];
    paginas.forEach((paginaComandos, indice) => {
        const paginaId = 3 + indice * 2;
        const conteudoId = paginaId + 1;
        const conteudo = paginaComandos.join('\n');
        objetos.push(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> >> >> /Contents ${conteudoId} 0 R >>`);
        objetos.push(`<< /Length ${conteudo.length} >>\nstream\n${conteudo}\nendstream`);
    });
    let pdf = '%PDF-1.4\n'; const offsets = [0];
    objetos.forEach((objeto, indice) => { offsets.push(pdf.length); pdf += `${indice + 1} 0 obj\n${objeto}\nendobj\n`; });
    const xref = pdf.length;
    pdf += `xref\n0 ${objetos.length + 1}\n0000000000 65535 f \n${offsets.slice(1).map(offset => String(offset).padStart(10, '0') + ' 00000 n ').join('\n')}\ntrailer\n<< /Size ${objetos.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
    const url = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
    const download = document.createElement('a');
    download.href = url;
    download.download = `${limpar(titulo).replace(/\s+/g, '-').toLowerCase() || 'relatorio-jogo'}.pdf`;
    download.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function sincronizarConvocatoria() {
    document.querySelectorAll('.convocado-row').forEach(linha => {
        const convocado = linha.querySelector('input[name="convocados[]"]');
        const papel = linha.querySelector('select[name^="papel_jogador"]');
        if (!convocado || !papel) return;
        papel.disabled = !convocado.checked;
        if (!convocado.checked) papel.value = '';
        linha.classList.toggle('nao-convocado', !convocado.checked);
    });
    desenharTaticaJogo();
    atualizarOpcoesDetalheJogo();
}

function desenharTaticaJogo() {
    const relvado = document.getElementById('tacticPitch'); const seletor = document.getElementById('taticaJogo');
    if (!relvado || !seletor || typeof jogadoresDetalheJogo === 'undefined') return;
    const esquemas = {
        '4-3-3': [['GR',50,89],['DE',17,70],['DC E',39,70],['DC D',61,70],['DD',83,70],['MC E',28,49],['MC',50,45],['MC D',72,49],['EE',18,23],['PL',50,16],['ED',82,23]],
        '4-2-3-1': [['GR',50,89],['DE',17,70],['DC E',39,70],['DC D',61,70],['DD',83,70],['MDC E',38,53],['MDC D',62,53],['EE',20,35],['MO',50,31],['ED',80,35],['PL',50,14]],
        '4-4-2': [['GR',50,89],['DE',17,70],['DC E',39,70],['DC D',61,70],['DD',83,70],['ME',18,49],['MC E',39,49],['MC D',61,49],['MD',82,49],['PL E',38,18],['PL D',62,18]],
        '3-5-2': [['GR',50,89],['DC E',25,69],['DC',50,72],['DC D',75,69],['ALA E',14,48],['MC E',34,49],['MC',50,43],['MC D',66,49],['ALA D',86,48],['PL E',38,18],['PL D',62,18]],
        '3-4-3': [['GR',50,89],['DC E',25,69],['DC',50,72],['DC D',75,69],['ALA E',17,49],['MC E',39,48],['MC D',61,48],['ALA D',83,49],['EE',20,22],['PL',50,15],['ED',80,22]],
        '4-1-4-1': [['GR',50,89],['DE',17,70],['DC E',39,70],['DC D',61,70],['DD',83,70],['MDC',50,55],['ME',17,34],['MC E',39,34],['MC D',61,34],['MD',83,34],['PL',50,14]],
        '5-3-2': [['GR',50,89],['ALA E',12,57],['DC E',31,69],['DC',50,72],['DC D',69,69],['ALA D',88,57],['MC E',31,47],['MC',50,43],['MC D',69,47],['PL E',38,18],['PL D',62,18]]
    };
    relvado.innerHTML = (esquemas[seletor.value] || esquemas['4-3-3']).map(([posicao, x, y]) => `<div class="tactic-slot" style="left:${x}%;top:${y}%"><label>${posicao}</label><select name="posicao_titular[${posicao}]">${opcoesJogadoresDetalhe((posicoesTitularesGuardadas || {})[posicao], false, jogadoresTitularesDetalhe())}</select></div>`).join('');
    relvado.querySelectorAll('select[name^="posicao_titular"]').forEach(select => select.addEventListener('change', () => {
        if (select.value) {
            const papel = document.querySelector(`select[name="papel_jogador[${select.value}]"]`);
            if (papel) papel.value = 'Titular';
        }
        atualizarOpcoesDetalheJogo();
    }));
    atualizarResumoOnzeInicial();
}

function atualizarResumoOnzeInicial() {
    const resumo = document.getElementById('resumoOnzeInicial');
    if (!resumo) return;
    resumo.innerHTML = [...document.querySelectorAll('#tacticPitch .tactic-slot')].map(slot => {
        const posicao = slot.querySelector('label')?.textContent || '';
        const select = slot.querySelector('select');
        const opcaoSelecionada = select?.options[select.selectedIndex];
        const nome = select?.value ? (opcaoSelecionada?.textContent || '') : '';
        return `<div><b>${posicao}</b><span${nome ? '' : ' class="vazio"'}>${nome || 'por definir'}</span></div>`;
    }).join('');
}

function iniciarPartesJogo() {
    const barra = document.getElementById('matchPhaseBar'); if (!barra) return;
    const botao = document.getElementById('matchPhaseButton'); const rotulo = document.getElementById('matchPhaseLabel');
    const botaoPausa = document.getElementById('botaoPausaJogo');
    const segundosJogoInput = document.getElementById('segundosJogoInput');
    let atual = Number(barra.dataset.atual || 0), total = Number(barra.dataset.partes || 2), terminado = Number(barra.dataset.terminado || 0), aDecorrer = false, posseInicial = (typeof posseInicialGuardada !== 'undefined' ? posseInicialGuardada : null), iniciado = Number(document.getElementById('jogoIniciado')?.value || 0) === 1;
    // Tempo real decorrido de jogo (segundos), independente do número de partes já iniciadas — é isto que
    // decide os minutos jogados de cada atleta, não uma suposição de que uma parte iniciada já foi toda jogada.
    let segundosJogo = Number(segundosJogoInput?.value || 0);
    let pausado = false;
    window.jogoPausado = false;
    const formatarCronometroJogo = segundos => {
        const m = String(Math.floor(segundos / 60)).padStart(2, '0'); const s = String(segundos % 60).padStart(2, '0');
        return `${m}:${s}`;
    };
    const atualizarCronometroJogo = () => {
        document.getElementById('cronometroJogo').textContent = formatarCronometroJogo(segundosJogo);
        if (segundosJogoInput) segundosJogoInput.value = segundosJogo;
    };
    const atualizar = () => {
        document.getElementById('parteAtual').value = atual; document.getElementById('jogoTerminado').value = terminado ? 1 : 0;
        atualizarCronometroJogo();
        atualizarMinutosJogados(Math.floor(segundosJogo / 60));
        if (botaoPausa) botaoPausa.disabled = !aDecorrer || terminado;
        if (terminado) { rotulo.textContent = 'Jogo terminado'; botao.style.display = 'none'; return; }
        rotulo.textContent = atual ? `Parte ${atual} de ${total}${aDecorrer ? ' em curso' : ' concluída'}` : `Pronto para iniciar a parte 1 de ${total}`;
        botao.textContent = aDecorrer ? (atual === total ? 'Terminar jogo' : 'Terminar parte') : `Iniciar parte ${atual + 1}`;
        botao.classList.toggle('running', aDecorrer);
    };
    botao.addEventListener('click', () => {
        if (!aDecorrer) {
            if (atual === 0) {
                if (!posseInicial) {
                    document.getElementById('posseInicial')?.classList.add('obrigatorio');
                    return;
                }
            }
            atual++;
            aDecorrer = true;
            pausado = false;
            window.jogoPausado = false;
            if (botaoPausa) { botaoPausa.textContent = 'Pausar cronómetro'; botaoPausa.classList.remove('pausado'); }
            iniciado = true;
            document.getElementById('jogoIniciado').value = '1';
            bloquearConvocatoria();
            const equipaComBola = atual === 1 ? posseInicial : (posseInicial === 'casa' ? 'visitante' : 'casa');
            window.iniciarPosseParte?.(equipaComBola);
        } else {
            aDecorrer = false;
            pausado = false;
            window.jogoPausado = false;
            window.pararPosseParte?.();
            if (atual === total) terminado = true;
        }
        atualizar();
    });
    botaoPausa?.addEventListener('click', () => {
        if (!aDecorrer) return;
        pausado = !pausado;
        window.jogoPausado = pausado;
        botaoPausa.textContent = pausado ? 'Retomar cronómetro' : 'Pausar cronómetro';
        botaoPausa.classList.toggle('pausado', pausado);
    });
    // Cronómetro real do jogo — só avança enquanto uma parte está a decorrer e não está em pausa.
    setInterval(() => {
        if (aDecorrer && !pausado) {
            segundosJogo++;
            atualizarCronometroJogo();
            atualizarMinutosJogados(Math.floor(segundosJogo / 60));
        }
    }, 1000);
    document.querySelectorAll('#posseInicial button[data-equipa]').forEach(botaoPosse => botaoPosse.addEventListener('click', () => {
        if (iniciado) return; // depois de iniciado, a escolha já está fixa — o botão fica disabled, isto é só uma segurança extra
        posseInicial = botaoPosse.dataset.equipa;
        document.getElementById('posseInicialInput').value = posseInicial;
        document.querySelectorAll('#posseInicial button[data-equipa]').forEach(botao => botao.classList.toggle('active', botao === botaoPosse));
        document.getElementById('posseInicial')?.classList.remove('obrigatorio');
    }));
    document.querySelector('[name="numero_partes"]').addEventListener('change', event => { total = Math.max(1, Number(event.target.value || 1)); if (atual > total) atual = total; atualizar(); });
    // Se já havia uma escolha guardada (jogo já iniciado ou reload a meio), reflete-a nos botões antes de bloquear.
    if (posseInicial) {
        document.querySelectorAll('#posseInicial button[data-equipa]').forEach(botao => botao.classList.toggle('active', botao.dataset.equipa === posseInicial));
    }
    if (iniciado) bloquearConvocatoria();
    atualizar();
}

function iniciarPosseJogo() {
    const painel = document.querySelector('.posse-controls');
    if (!painel) return;
    const valores = { casa: Number(painel.dataset.casa || 0), sem: Number(painel.dataset.sem || 0), visitante: Number(painel.dataset.visitante || 0) };
    let ativa = null;
    const mostrar = () => {
        const total = valores.casa + valores.sem + valores.visitante;
        const h = String(Math.floor(total / 3600)).padStart(2, '0'); const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0'); const s = String(total % 60).padStart(2, '0');
        document.getElementById('posseCronometro').textContent = `${h}:${m}:${s}`;
        painel.querySelector('[name="posse_casa_segundos"]').value = valores.casa;
        painel.querySelector('[name="sem_posse_segundos"]').value = valores.sem;
        painel.querySelector('[name="posse_visitante_segundos"]').value = valores.visitante;
        const comPosse = valores.casa + valores.visitante;
        document.getElementById('posseCasaPercentagem').textContent = comPosse ? `${(valores.casa * 100 / comPosse).toFixed(1)}%` : '0%';
        document.getElementById('posseVisitantePercentagem').textContent = comPosse ? `${(valores.visitante * 100 / comPosse).toFixed(1)}%` : '0%';
    };
    painel.querySelectorAll('button[data-posse]').forEach(botao => botao.addEventListener('click', () => {
        ativa = ativa === botao.dataset.posse ? null : botao.dataset.posse;
        painel.querySelectorAll('button[data-posse]').forEach(b => b.classList.toggle('active', b.dataset.posse === ativa));
    }));
    window.iniciarPosseParte = equipa => {
        ativa = equipa;
        painel.querySelectorAll('button[data-posse]').forEach(botao => botao.classList.toggle('active', botao.dataset.posse === ativa));
    };
    window.pararPosseParte = () => {
        ativa = null;
        painel.querySelectorAll('button[data-posse]').forEach(botao => botao.classList.remove('active'));
    };
    // Enquanto o cronómetro do jogo estiver em pausa, a posse também não avança — não faz sentido
    // continuar a contar posse de bola com o jogo parado.
    setInterval(() => { if (ativa && !window.jogoPausado) { valores[ativa]++; mostrar(); } }, 1000);
    mostrar();
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof subsGuardadas !== 'undefined') (subsGuardadas.length ? subsGuardadas : [{}]).forEach(adicionarSubstituicao);
    if (typeof golosGuardados !== 'undefined') (golosGuardados.length ? golosGuardados : [{}]).forEach(adicionarGolo);
    desenharTaticaJogo();
    document.getElementById('taticaJogo')?.addEventListener('change', desenharTaticaJogo);
    document.querySelectorAll('.convocado-toggle input').forEach(input => input.addEventListener('change', sincronizarConvocatoria));
    document.querySelectorAll('select[name^="papel_jogador"]').forEach(input => input.addEventListener('change', () => { desenharTaticaJogo(); atualizarOpcoesDetalheJogo(); }));
    sincronizarConvocatoria();
    iniciarPartesJogo();
    iniciarPosseJogo();
});

</script>

</body>
</html>