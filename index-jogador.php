<?php
session_start();
require_once __DIR__ . '/basedados.h';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ── Proteção da página ── */
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

if ($_SESSION['tipo_utilizador'] === 'treinador') {
    header('Location: index-treinador.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] !== 'jogador') {
    header('Location: login.php');
    exit;
}

$id_utilizador = (int)$_SESSION['id_utilizador'];
$id_clube      = (int)$_SESSION['id_clube'];

$erro = '';
$sucesso = '';
$viewMode = $_GET['view'] ?? 'home';
$activeSidebarView = match ($viewMode) {
    'equipa' => 'equipa',
    'jogos' => 'jogos',
    'campeonato' => 'campeonato',
    'calendario' => 'calendario',
    'treino' => 'calendario',
    'mensagens' => 'mensagens',
    default => 'home',
};
$chatSelecionadoId = (int)($_GET['chat'] ?? 0);

/* ── Compatibilidade com versões antigas da BD ── */
$ckJogUtil = $conn->query("SHOW COLUMNS FROM jogadores LIKE 'id_utilizador'");
if ($ckJogUtil && $ckJogUtil->num_rows === 0) {
    $conn->query("ALTER TABLE jogadores ADD COLUMN id_utilizador INT DEFAULT NULL AFTER id_equipa");
}

$checkHoraEvento = $conn->query("SHOW COLUMNS FROM eventos_clube LIKE 'hora_evento'");
if ($checkHoraEvento && $checkHoraEvento->num_rows === 0) {
    $conn->query("ALTER TABLE eventos_clube ADD COLUMN hora_evento TIME DEFAULT NULL AFTER data_evento");
}

$checkLocalEvento = $conn->query("SHOW COLUMNS FROM eventos_clube LIKE 'local_evento'");
if ($checkLocalEvento && $checkLocalEvento->num_rows === 0) {
    $conn->query("ALTER TABLE eventos_clube ADD COLUMN local_evento VARCHAR(200) DEFAULT NULL AFTER hora_evento");
}

$checkEnviadaEm = $conn->query("SHOW COLUMNS FROM mensagens LIKE 'enviada_em'");
if ($checkEnviadaEm && $checkEnviadaEm->num_rows === 0) {
    $conn->query("ALTER TABLE mensagens ADD COLUMN enviada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER estado");
}

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

$checkTreinoEquipa = $conn->query("SHOW COLUMNS FROM treino LIKE 'id_equipa'");
if ($checkTreinoEquipa && $checkTreinoEquipa->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD COLUMN id_equipa INT DEFAULT NULL AFTER id_treino");
}

$checkTreinoEvento = $conn->query("SHOW COLUMNS FROM treino LIKE 'id_evento_clube'");
if ($checkTreinoEvento && $checkTreinoEvento->num_rows === 0) {
    $conn->query("ALTER TABLE treino ADD COLUMN id_evento_clube INT DEFAULT NULL AFTER id_plano");
}

$conn->query("CREATE TABLE IF NOT EXISTS `treino_exercicio` (
    `id_exercicio` INT AUTO_INCREMENT PRIMARY KEY,
    `id_treino` INT NOT NULL,
    `ordem` INT NOT NULL DEFAULT 1,
    `titulo` VARCHAR(120) NOT NULL,
    `desenho_json` LONGTEXT DEFAULT NULL,
    `descricao` TEXT DEFAULT NULL,
    `objetivos` TEXT DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_treino_exercicio_treino` (`id_treino`),
    KEY `idx_treino_exercicio_ordem` (`id_treino`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Flash messages ── */
if (isset($_SESSION['flash_sucesso'])) {
    $sucesso = $_SESSION['flash_sucesso'];
    unset($_SESSION['flash_sucesso']);
}
if (isset($_SESSION['flash_erro'])) {
    $erro = $_SESSION['flash_erro'];
    unset($_SESSION['flash_erro']);
}

/* ── Ações POST permitidas ao jogador ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'enviar_mensagem') {
        $destinoMensagem = (int)($_POST['destino_mensagem'] ?? 0);
        $conteudoMensagem = trim($_POST['conteudo_mensagem'] ?? '');

        if ($destinoMensagem <= 0 || $conteudoMensagem === '') {
            $erro = 'Seleciona um destinatário e escreve uma mensagem.';
        } else {
            $stmtCheckDestino = $conn->prepare(" 
                SELECT id_utilizador
                FROM utilizador
                WHERE id_utilizador = ?
                  AND id_clube = ?
                  AND id_utilizador <> ?
                LIMIT 1
            ");
            $stmtCheckDestino->bind_param("iii", $destinoMensagem, $id_clube, $id_utilizador);
            $stmtCheckDestino->execute();
            $destinoExiste = $stmtCheckDestino->get_result()->fetch_assoc();

            if (!$destinoExiste) {
                $erro = 'Esse utilizador não está disponível para mensagens.';
            } else {
                $stmtInserirMensagem = $conn->prepare(" 
                    INSERT INTO mensagens (origem, destino, `conteúdo`, estado, enviada_em)
                    VALUES (?, ?, ?, 'Não Lida', NOW())
                ");
                $stmtInserirMensagem->bind_param("iis", $id_utilizador, $destinoMensagem, $conteudoMensagem);

                if ($stmtInserirMensagem->execute()) {
                    header('Location: index-jogador.php?view=mensagens&chat=' . $destinoMensagem);
                    exit;
                }

                $erro = 'Erro ao enviar mensagem.';
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
        $novaFotoPerfil = null;

        if ($nomeUtilizador === '' || $primeiroNome === '' || $ultimoNome === '') {
            $erro = 'Preenche os campos obrigatórios do perfil.';
        } elseif ($emailUtilizador !== '' && !filter_var($emailUtilizador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O email do perfil não é válido.';
        } else {
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

            if ($emailUtilizador !== '' && $perfilEmailExiste) {
                $erro = 'Já existe outro utilizador com este email.';
            }
        }

        if (!$erro && !empty($_FILES['foto_perfil']['tmp_name'])) {
            if (!isset($_FILES['foto_perfil']) || (int)($_FILES['foto_perfil']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $erro = 'Não foi possível carregar a foto de perfil.';
            } elseif ((int)($_FILES['foto_perfil']['size'] ?? 0) > 2 * 1024 * 1024) {
                $erro = 'A foto de perfil deve ter no máximo 2MB.';
            } else {
                $infoFotoPerfil = @getimagesize($_FILES['foto_perfil']['tmp_name']);
                $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
                $mimeFotoPerfil = $infoFotoPerfil['mime'] ?? '';

                if ($infoFotoPerfil === false || !in_array($mimeFotoPerfil, $tiposPermitidos, true)) {
                    $erro = 'A foto de perfil deve estar em JPG, PNG ou WEBP.';
                } else {
                    $novaFotoPerfil = file_get_contents($_FILES['foto_perfil']['tmp_name']);
                }
            }
        }

        if (!$erro) {
            if ($novaFotoPerfil !== null) {
                $stmtUpdatePerfil = $conn->prepare(" 
                    UPDATE utilizador
                    SET nome_utilizador = ?,
                        foto_perfil = ?,
                        email_utilizador = NULLIF(?, ''),
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
                        email_utilizador = NULLIF(?, ''),
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

            if ($stmtUpdatePerfil->execute()) {
                $sucesso = 'Perfil atualizado com sucesso.';
            } else {
                $erro = 'Erro ao guardar as alterações do perfil.';
            }
        }
    }

    if ($acao === 'marcar_notificacao_lida') {
        $idNotificacao = (int)($_POST['id_notificacao'] ?? 0);

        if ($idNotificacao > 0) {
            $stmtMarcaLida = $conn->prepare(" 
                UPDATE notificacao
                SET estado = 'Lida',
                    lida_em = NOW()
                WHERE id_notificacao = ?
                  AND id_utilizador = ?
            ");
            $stmtMarcaLida->bind_param("ii", $idNotificacao, $id_utilizador);
            $stmtMarcaLida->execute();
        }

        exit;
    }
}

/* ── Buscar clube ── */
$stmtClube = $conn->prepare(" 
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
$stmtClube->bind_param("i", $id_clube);
$stmtClube->execute();
$clube = $stmtClube->get_result()->fetch_assoc();

if (!$clube) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeClube  = $clube['nome_clube'];
$siglaClube = $clube['sigla'];
$corClube   = $clube['cor'] ?: '#000000';
$logoClube  = $clube['logotipo']
    ? 'data:image/png;base64,' . base64_encode($clube['logotipo'])
    : null;

/* ── Buscar jogador associado ao utilizador ── */
$stmtJogador = $conn->prepare(" 
    SELECT j.id_jogador, j.nome_completo, j.alcunha_jogador, j.data_nascimento,
           j.nacionalidade, j.`país_nascimento`, j.`posição_principal`,
           j.`posição_secundária`, j.`número_favorito`, j.`pé_preferencial`,
           j.altura, j.peso, j.id_equipa, j.foto_jogador,
           eq.`escalão`, eq.hierarquia,
           ep.`época` AS epoca
    FROM jogadores j
    JOIN equipa eq ON eq.id_equipa = j.id_equipa
    LEFT JOIN `época` ep ON ep.`id_época` = eq.`id_época`
    WHERE j.id_utilizador = ?
      AND eq.id_clube = ?
    LIMIT 1
");
$stmtJogador->bind_param("ii", $id_utilizador, $id_clube);
$stmtJogador->execute();
$jogador = $stmtJogador->get_result()->fetch_assoc();

$id_equipa = $jogador ? (int)$jogador['id_equipa'] : 0;
$fotoJogador = ($jogador && !empty($jogador['foto_jogador']) && strlen($jogador['foto_jogador']) > 10)
    ? 'data:image/png;base64,' . base64_encode($jogador['foto_jogador'])
    : null;

/* ── Perfil do utilizador ── */
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

/* ── Colegas da equipa ── */
$jogadoresEquipa = [];
if ($id_equipa > 0) {
    $stmtColegas = $conn->prepare(" 
        SELECT j.id_jogador, j.nome_completo, j.alcunha_jogador, j.`posição_principal`,
               j.`número_favorito`, j.foto_jogador
        FROM jogadores j
        WHERE j.id_equipa = ?
        ORDER BY CAST(NULLIF(j.`número_favorito`, '') AS UNSIGNED), j.nome_completo
    ");
    $stmtColegas->bind_param("i", $id_equipa);
    $stmtColegas->execute();
    $resColegas = $stmtColegas->get_result();
    while ($row = $resColegas->fetch_assoc()) {
        $row['foto_base64'] = (!empty($row['foto_jogador']) && strlen($row['foto_jogador']) > 10)
            ? 'data:image/png;base64,' . base64_encode($row['foto_jogador'])
            : null;
        unset($row['foto_jogador']);
        $jogadoresEquipa[] = $row;
    }
}

/* ── Eventos da equipa ── */
$eventosEquipa = [];
if ($id_equipa > 0) {
    $stmtEventos = $conn->prepare(" 
        SELECT
            ec.id_evento,
            ec.tipo_evento,
            ec.`descrição_evento` AS descricao_evento,
            ec.estado_evento,
            ec.data_evento,
            ec.hora_evento,
            ec.local_evento,
            t.id_treino,
            t.`número_treino` AS numero_treino,
            t.`conteúdo` AS conteudo_treino
        FROM eventos_clube ec
        LEFT JOIN treino t
          ON t.id_equipa = ec.id_equipa
         AND (
                t.id_evento_clube = ec.id_evento
             OR (
                    ec.tipo_evento = 'Treino'
                AND t.`data` = ec.data_evento
                AND (
                        t.hora = ec.hora_evento
                     OR (t.hora IS NULL AND ec.hora_evento IS NULL)
                    )
                )
             )
        WHERE ec.id_equipa = ?
        ORDER BY ec.data_evento ASC, ec.hora_evento ASC, ec.id_evento ASC
    ");
    $stmtEventos->bind_param("i", $id_equipa);
    $stmtEventos->execute();
    $resEventos = $stmtEventos->get_result();
    while ($row = $resEventos->fetch_assoc()) {
        $eventosEquipa[] = $row;
    }
}

/* ── Competições e jogos da equipa ── */
$competicoesEquipa = [];
$jogosEquipa = [];
$jogosPorCompeticao = [];
$estatisticasPorCompeticao = [];

if ($id_equipa > 0) {
    $stmtComp = $conn->prepare(" 
        SELECT id_competicao, nome, tipo, epoca, estado, descricao
        FROM competicoes_clube
        WHERE id_clube = ?
          AND id_equipa = ?
        ORDER BY id_competicao DESC
    ");
    $stmtComp->bind_param("ii", $id_clube, $id_equipa);
    $stmtComp->execute();
    $resComp = $stmtComp->get_result();
    while ($row = $resComp->fetch_assoc()) {
        $competicoesEquipa[] = $row;
        $estatisticasPorCompeticao[(int)$row['id_competicao']] = [
            'jogos' => 0,
            'vitorias' => 0,
            'empates' => 0,
            'derrotas' => 0,
            'gm' => 0,
            'gs' => 0,
            'pontos' => 0,
        ];
    }

    $stmtJogos = $conn->prepare(" 
        SELECT jc.id_jogo, jc.id_competicao, jc.adversario, jc.data_jogo, jc.hora_jogo,
               jc.casa, jc.local_jogo, jc.resultado_nos, jc.resultado_adv, jc.estado,
               cc.nome AS competicao_nome, cc.tipo AS competicao_tipo, cc.epoca AS competicao_epoca
        FROM jogos_clube jc
        JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao
        WHERE cc.id_clube = ?
          AND cc.id_equipa = ?
        ORDER BY jc.data_jogo ASC, jc.hora_jogo ASC
    ");
    $stmtJogos->bind_param("ii", $id_clube, $id_equipa);
    $stmtJogos->execute();
    $resJogos = $stmtJogos->get_result();
    while ($row = $resJogos->fetch_assoc()) {
        $jogosEquipa[] = $row;
        $jogosPorCompeticao[(int)$row['id_competicao']][] = $row;

        if ($row['estado'] === 'Realizado' && $row['resultado_nos'] !== null && $row['resultado_adv'] !== null) {
            $idComp = (int)$row['id_competicao'];
            if (!isset($estatisticasPorCompeticao[$idComp])) {
                $estatisticasPorCompeticao[$idComp] = [
                    'jogos' => 0,
                    'vitorias' => 0,
                    'empates' => 0,
                    'derrotas' => 0,
                    'gm' => 0,
                    'gs' => 0,
                    'pontos' => 0,
                ];
            }

            $nos = (int)$row['resultado_nos'];
            $adv = (int)$row['resultado_adv'];

            $estatisticasPorCompeticao[$idComp]['jogos']++;
            $estatisticasPorCompeticao[$idComp]['gm'] += $nos;
            $estatisticasPorCompeticao[$idComp]['gs'] += $adv;

            if ($nos > $adv) {
                $estatisticasPorCompeticao[$idComp]['vitorias']++;
                $estatisticasPorCompeticao[$idComp]['pontos'] += 3;
            } elseif ($nos === $adv) {
                $estatisticasPorCompeticao[$idComp]['empates']++;
                $estatisticasPorCompeticao[$idComp]['pontos'] += 1;
            } else {
                $estatisticasPorCompeticao[$idComp]['derrotas']++;
            }
        }
    }
}

/* ── Histórico e lesões ── */
$historicoCarreira = [];
$lesoesJogador = [];
if ($jogador) {
    $idJogador = (int)$jogador['id_jogador'];

    $resHist = $conn->query(" 
        SELECT hc.jogos, hc.golos_marcados, hc.`assistências`, ep.`época` AS epoca, c.nome_clube AS clube
        FROM `histórico_carreira` hc
        LEFT JOIN `época` ep ON ep.id_época = hc.id_época
        LEFT JOIN clube c ON c.id_clube = hc.id_clube
        WHERE hc.id_jogador = $idJogador
        ORDER BY ep.id_época DESC
    ");
    if ($resHist) {
        while ($row = $resHist->fetch_assoc()) {
            $historicoCarreira[] = $row;
        }
    }

    $resLes = $conn->query(" 
        SELECT nome_lesão, tipo_lesão, tempo_recuperação, estado_lesão
        FROM `lesões`
        WHERE id_jogador = $idJogador
        ORDER BY id_lesão DESC
    ");
    if ($resLes) {
        while ($row = $resLes->fetch_assoc()) {
            $lesoesJogador[] = $row;
        }
    }
}

/* ── Notificações ── */
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

/* ── Mensagens ── */
$utilizadoresMensagem = [];
$mensagensConversa = [];

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

if ($chatSelecionadoId <= 0 && !empty($utilizadoresMensagem)) {
    $chatSelecionadoId = (int)$utilizadoresMensagem[0]['id_utilizador'];
}

$chatSelecionado = null;
foreach ($utilizadoresMensagem as $utilizadorMsg) {
    if ((int)$utilizadorMsg['id_utilizador'] === $chatSelecionadoId) {
        $chatSelecionado = $utilizadorMsg;
        break;
    }
}

if ($chatSelecionado) {
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

/* ── Treino selecionado pelo jogador, aberto a partir do calendário ── */
$idTreinoSelecionado = (int)($_GET['id'] ?? $_GET['treino'] ?? 0);
$treinoSelecionado = null;
$exerciciosTreinoSelecionado = [];

if ($id_equipa > 0 && $idTreinoSelecionado > 0) {
    $stmtTreinoSelecionado = $conn->prepare(" 
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
            ep.`época` AS epoca
        FROM treino t
        JOIN equipa eq ON eq.id_equipa = t.id_equipa
        LEFT JOIN `época` ep ON ep.`id_época` = eq.`id_época`
        WHERE t.id_treino = ?
          AND t.id_equipa = ?
          AND eq.id_clube = ?
        LIMIT 1
    ");
    $stmtTreinoSelecionado->bind_param("iii", $idTreinoSelecionado, $id_equipa, $id_clube);
    $stmtTreinoSelecionado->execute();
    $treinoSelecionado = $stmtTreinoSelecionado->get_result()->fetch_assoc() ?: null;

    if ($treinoSelecionado) {
        $stmtExerciciosTreino = $conn->prepare(" 
            SELECT id_exercicio, ordem, titulo, desenho_json, descricao, objetivos
            FROM treino_exercicio
            WHERE id_treino = ?
            ORDER BY ordem ASC, id_exercicio ASC
        ");
        $stmtExerciciosTreino->bind_param("i", $idTreinoSelecionado);
        $stmtExerciciosTreino->execute();
        $resExerciciosTreino = $stmtExerciciosTreino->get_result();

        while ($row = $resExerciciosTreino->fetch_assoc()) {
            $canvas = json_decode($row['desenho_json'] ?? '', true);
            if (!is_array($canvas)) {
                $canvas = ['template' => 'campo_inteiro', 'objects' => []];
            }
            if (!isset($canvas['objects']) || !is_array($canvas['objects'])) {
                $canvas['objects'] = [];
            }

            $exerciciosTreinoSelecionado[] = [
                'id_exercicio' => (int)$row['id_exercicio'],
                'ordem' => (int)$row['ordem'],
                'titulo' => $row['titulo'] ?: ('Exercício ' . (int)$row['ordem']),
                'descricao' => $row['descricao'] ?? '',
                'objetivos' => $row['objetivos'] ?? '',
                'canvas' => $canvas,
            ];
        }
    }
}

function formatDatePt($date) {
    if (!$date) return '';
    $ts = strtotime($date);
    if (!$ts) return '';
    return date('d/m/Y', $ts);
}

function formatTimePt($time) {
    if (!$time) return '';
    return substr($time, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos Jogador | <?= h($nomeClube) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --club: <?= h($corClube) ?>;
    --sidebar-w: 68px;
    --topbar-h: 64px;
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
html, body { min-height: 100%; }
body { background: #f0f2f7; color: #1f2b3d; }
body.layout-locked { overflow: hidden; }

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
.topbar-left { display: flex; align-items: center; gap: 12px; margin-left: calc(-1 * var(--sidebar-w)); }
.topbar-club-logo {
    width: 38px; height: 38px;
    border-radius: 8px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.28);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.topbar-club-logo img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }
.topbar-club-logo--placeholder { color: #fff; font-size: 11px; font-weight: 800; }
.topbar-club-text { display: flex; flex-direction: column; gap: 1px; }
.topbar-name { font-size: 14px; font-weight: 800; color: #fff; line-height: 1.2; }
.topbar-sigla { font-size: 12px; font-weight: 500; color: rgba(255,255,255,.75); }
.topbar-right { display: flex; align-items: center; gap: 14px; }
.topbar-logo { height: 28px; filter: brightness(0) invert(1); }
.topbar-menu { background: none; border: none; cursor: pointer; padding: 4px; display: flex; flex-direction: column; gap: 5px; }
.topbar-menu span { display: block; width: 22px; height: 2px; background: rgba(255,255,255,.85); border-radius: 2px; }
.topbar-user-menu-wrap { position: relative; display: flex; align-items: center; }
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
.user-dropdown.active { display: flex; }
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
.user-dropdown a:hover { background: rgba(0,0,0,.14); }
.user-dropdown a.logout-link { color: #ff0000; font-weight: 700; }

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
    display: flex; align-items: center; justify-content: center; gap: 14px;
    padding: 10px 0;
    color: rgba(255,255,255,.78);
    text-decoration: none;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 600;
    border-left: 3px solid transparent;
    transition: background .15s, color .15s, border-color .15s;
}
.sidebar:hover a { justify-content: flex-start; padding: 10px 20px; }
.sidebar a:hover,
.sidebar a.active { background: rgba(255,255,255,.13); color: #fff; border-left-color: #fff; }
.sidebar a img {
    width: 34px; height: 34px; object-fit: contain; flex-shrink: 0;
    filter: brightness(0) invert(1); opacity: .85; transition: width .22s, height .22s, opacity .15s;
}
.sidebar:hover a img { width: 24px; height: 24px; }
.sidebar a:hover img,
.sidebar a.active img { opacity: 1; }
.sidebar a span { opacity: 0; width: 0; overflow: hidden; transition: opacity .18s, width .22s; }
.sidebar:hover a span { opacity: 1; width: auto; }

.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 28px 28px 40px;
    min-height: calc(100vh - var(--topbar-h));
    transition: margin-left .22s cubic-bezier(.4,0,.2,1);
}
.sidebar:hover ~ .main { margin-left: 210px; }
.card, .screen-shell {
    background: #fff;
    border-radius: 20px;
    padding: 28px 32px 36px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    position: relative;
}
.screen-shell { display: none; }
.screen-shell.visible { display: block; }

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
.alert-error { background: #fff1f1; color: #b00020; border: 1px solid #ffd0d0; }
.alert-success { background: #eefaf1; color: #1f7a3a; border: 1px solid #c8edcf; }
.alert-close { border: none; background: transparent; color: inherit; cursor: pointer; font-size: 18px; font-weight: 800; }

.hero {
    display: grid;
    grid-template-columns: 1fr 220px;
    gap: 26px;
    align-items: center;
    margin-bottom: 26px;
}
.hero-kicker { color: var(--club); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.hero-title { font-size: clamp(26px, 4vw, 42px); font-weight: 800; letter-spacing: -1.3px; color: #1f2b3d; line-height: 1.03; }
.hero-subtitle { color: #6b7280; margin-top: 10px; font-size: 15px; line-height: 1.5; }
.player-photo {
    width: 180px; height: 180px; border-radius: 26px;
    background: #f4f6fb; border: 3px solid var(--club);
    display: flex; align-items: center; justify-content: center;
    justify-self: end; overflow: hidden;
    font-size: 62px; font-weight: 800; color: var(--club);
}
.player-photo img { width: 100%; height: 100%; object-fit: cover; }

.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}
.stat-card {
    background: #f8f9fc;
    border: 1.5px solid #e8edf5;
    border-radius: 18px;
    padding: 18px;
}
.stat-label { color: #7b8596; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
.stat-value { color: #1f2b3d; font-size: 22px; font-weight: 800; }
.stat-small { color: #6b7280; font-size: 13px; font-weight: 600; }

.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 1.5px solid #ebebeb;
}
.tab {
    padding: 9px 22px;
    border-radius: 10px 10px 0 0;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    color: #666;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1.5px;
}
.tab.active { color: var(--club); border-bottom-color: var(--club); font-weight: 800; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 18px;
}
.info-row {
    background: #f8f9fc;
    border: 1px solid #e8edf5;
    border-radius: 14px;
    padding: 14px 16px;
}
.info-label { font-size: 12px; font-weight: 800; color: var(--club); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
.info-value { font-size: 15px; font-weight: 600; color: #1f2b3d; }
.info-value.empty { color: #a3aab7; font-style: italic; }

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.section-title { font-size: 22px; font-weight: 800; color: #1f2b3d; }
.section-subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

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
    position: relative;
}
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
    top: 12px;
    right: 12px;
    background: var(--club);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    min-width: 26px;
    height: 26px;
    padding: 0 6px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.player-name { font-size: 13px; font-weight: 800; color: #1f2b3d; text-align: center; line-height: 1.2; }
.player-pos { font-size: 11px; color: #6b7280; text-align: center; }
.player-current { outline: 2px solid var(--club); }

.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th { text-align: left; padding: 12px 14px; background: #f5f5f5; color: #333; font-weight: 800; border-bottom: 1px solid #e8e8e8; }
.data-table td { padding: 13px 14px; border-bottom: 1px solid #f0f0f0; color: #444; vertical-align: middle; }
.data-table tr:hover td { background: #fafafa; }
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    background: #eef2ff;
    color: var(--club);
}
.badge.green { background: #dcfce7; color: #15803d; }
.badge.blue { background: #dbeafe; color: #1d4ed8; }
.badge.red { background: #fee2e2; color: #dc2626; }
.badge.yellow { background: #fef9c3; color: #a16207; }
.badge.gray { background: #f3f4f6; color: #6b7280; }

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 18px;
}
.comp-card {
    background: #fff;
    border: 1.5px solid #e0e7f2;
    border-radius: 20px;
    padding: 20px 18px;
    box-shadow: 0 4px 14px rgba(0,0,0,.04);
}
.comp-title { font-size: 16px; font-weight: 800; color: #1f2b3d; margin: 8px 0 5px; }
.comp-meta { font-size: 12px; color: #6b7280; margin-bottom: 12px; }
.comp-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-top: 14px;
}
.comp-stat { background: #f8f9fc; border-radius: 12px; padding: 10px 6px; text-align: center; }
.comp-stat strong { display: block; font-size: 17px; color: #1f2b3d; }
.comp-stat span { font-size: 10px; color: #7b8596; font-weight: 800; text-transform: uppercase; }

.event-list { display: grid; gap: 10px; }
.event-card {
    display: grid;
    grid-template-columns: 110px 1fr auto;
    gap: 14px;
    align-items: center;
    background: #fff;
    border: 1.5px solid #e8edf5;
    border-radius: 16px;
    padding: 14px 16px;
}
.event-date { color: var(--club); font-weight: 800; font-size: 13px; }
.event-main strong { display: block; color: #1f2b3d; margin-bottom: 3px; }
.event-main span { color: #6b7280; font-size: 13px; }

.event-card-link {
    text-decoration: none;
    color: inherit;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.event-card-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
    border-color: var(--club);
}
.event-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 8px 12px;
    background: var(--club);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}
.btn-soft-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 999px;
    padding: 10px 16px;
    background: #eef2f7;
    color: #1f2b3d;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}
.btn-soft-link:hover { background: #e2e8f0; }
.treino-view-header {
    border-bottom: 3px solid #111827;
    padding-bottom: 14px;
    margin-bottom: 20px;
}
.treino-view-title {
    font-size: 24px;
    font-weight: 900;
    color: #0f172a;
    margin-bottom: 6px;
}
.treino-view-meta {
    font-size: 13px;
    color: #64748b;
    font-weight: 700;
}
.treino-info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 22px;
}
.treino-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
}
.treino-info-box span {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 6px;
}
.treino-info-box strong {
    display: block;
    color: #1f2b3d;
    font-size: 15px;
}
.treino-exercicio-view {
    border-top: 3px solid #111827;
    padding-top: 12px;
    margin-top: 24px;
    display: grid;
    grid-template-columns: 48% 52%;
    min-height: 300px;
    break-inside: avoid;
}
.treino-exercicio-visual {
    padding: 14px 18px 14px 0;
    border-right: 1px solid #cbd5e1;
}
.treino-exercicio-desc {
    padding: 14px 0 14px 18px;
}
.treino-print-label {
    font-size: 13px;
    font-weight: 900;
    color: #0f172a;
    padding-bottom: 8px;
    border-bottom: 1px solid #d4d4d8;
    margin-bottom: 10px;
}
.treino-view-canvas {
    width: 100%;
    height: 240px;
    border: 1px solid #e2e8f0;
    background: #fff;
    display: block;
}
.treino-desc-block,
.treino-objetivo-block {
    font-size: 14px;
    line-height: 1.45;
    color: #111827;
    white-space: pre-wrap;
}
.treino-desc-block {
    min-height: 95px;
    padding-bottom: 12px;
}
.treino-objetivo-block { padding-top: 4px; }
@media (max-width: 900px) {
    .treino-info-grid { grid-template-columns: 1fr 1fr; }
    .treino-exercicio-view { grid-template-columns: 1fr; }
    .treino-exercicio-visual { border-right: none; padding-right: 0; }
    .treino-exercicio-desc { padding-left: 0; }
}

.messages-shell {
    display: grid;
    grid-template-columns: 310px 1fr;
    height: calc(100vh - var(--topbar-h) - 56px);
    min-height: 560px;
    background: #fff;
    border: 1px solid #dfe3ee;
    border-radius: 18px;
    box-shadow: 0 8px 22px rgba(23, 42, 88, 0.08);
    overflow: hidden;
}
.messages-sidebar { border-right: 1px solid #e6eaf2; background: #fff; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.messages-sidebar-header { padding: 18px 16px; border-bottom: 1px solid #eef2f7; font-weight: 800; color: #1f2b3d; }
.messages-list { overflow-y: auto; flex: 1; min-height: 0; }
.message-user { display: flex; align-items: center; gap: 10px; padding: 12px 14px; text-decoration: none; color: #1f2b3d; border-bottom: 1px solid #f2f4f9; }
.message-user:hover { background: #f6f8fc; }
.message-user.active { background: #eff4ff; }
.message-user-avatar { width: 44px; height: 44px; border-radius: 50%; background: #eaf0fb; border: 1px solid #d9e0f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--club); overflow: hidden; flex-shrink: 0; }
.message-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.message-user-main { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.message-user-name { font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.message-user-type { font-size: 12px; color: #6b7280; }
.message-user-unread { margin-left: auto; min-width: 20px; height: 20px; border-radius: 999px; background: var(--club); color: #fff; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; }
.messages-chat { display: flex; flex-direction: column; background: #f9fbff; min-height: 0; overflow: hidden; }
.messages-chat-header { min-height: 64px; border-bottom: 1px solid #e5ebf5; background: #fff; display: flex; align-items: center; gap: 10px; padding: 0 16px; font-weight: 800; color: #1f2b3d; }
.messages-thread { flex: 1; padding: 18px 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; min-height: 0; }
.dm-bubble { max-width: min(72%, 520px); border-radius: 18px; padding: 10px 13px 6px; font-size: 14px; line-height: 1.4; word-break: break-word; }
.dm-time { display: block; font-size: 10px; margin-top: 4px; opacity: .65; text-align: right; }
.dm-bubble.out { align-self: flex-end; background: var(--club); color: #fff; border-bottom-right-radius: 6px; }
.dm-bubble.in { align-self: flex-start; background: #fff; color: #1f2b3d; border: 1px solid #e2e8f4; border-bottom-left-radius: 6px; }
.messages-compose { border-top: 1px solid #e5ebf5; background: #fff; padding: 12px; flex-shrink: 0; }
.messages-compose form { display: flex; align-items: center; gap: 8px; }
.messages-compose textarea { flex: 1; min-height: 44px; max-height: 120px; resize: vertical; border: 1px solid #d9e0f0; border-radius: 14px; padding: 11px 12px; font-size: 14px; }
.messages-compose button { border: none; border-radius: 14px; background: var(--club); color: #fff; font-weight: 800; font-size: 14px; padding: 11px 14px; cursor: pointer; }
.messages-empty { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; color: #7b8596; padding: 16px; }

.notifications-list { display: grid; gap: 10px; }
.notification-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    border: 1px solid #e8edf5;
    border-radius: 16px;
    background: #fff;
}
.notification-row.unread { border-color: var(--club); background: #f8fbff; }
.notification-title { font-size: 15px; font-weight: 800; color: #1f2b3d; }
.notification-message { display: block; margin-top: 4px; color: #6b7280; font-size: 13px; }
.notification-check { border: none; background: var(--club); color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-weight: 800; }

.empty-state { padding: 54px 20px; text-align: center; color: #8a94a6; font-size: 14px; }
.empty-state strong { color: #1f2b3d; }

.profile-form {
    display: grid;
    grid-template-columns: 1fr 240px;
    gap: 22px;
    align-items: start;
}
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 7px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: 800; color: #333; }
.form-group input { width: 100%; border: 1px solid #ddd; border-radius: 999px; padding: 13px 16px; font-size: 14px; outline: none; background: #f8f8f8; }
.form-group input:focus { border-color: var(--club); background: #fff; }
.btn-save { border: none; border-radius: 999px; padding: 13px 22px; font-size: 14px; font-weight: 800; cursor: pointer; background: var(--club); color: #fff; }
.profile-avatar-box { background: #f8f9fc; border: 1.5px solid #e8edf5; border-radius: 18px; padding: 18px; text-align: center; }
.profile-avatar { width: 130px; height: 130px; border-radius: 50%; margin: 0 auto 12px; border: 4px solid var(--club); background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 48px; font-weight: 800; color: var(--club); }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
.file-input { border-radius: 16px !important; }

@media (max-width: 900px) {
    .hero, .profile-form { grid-template-columns: 1fr; }
    .player-photo { justify-self: start; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .info-grid { grid-template-columns: 1fr; }
    .messages-shell { grid-template-columns: 1fr; height: auto; }
    .messages-sidebar { max-height: 260px; border-right: none; border-bottom: 1px solid #e6eaf2; }
    .event-card { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .main { padding: 18px; }
    .card, .screen-shell { padding: 22px; }
    .stat-grid { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .tabs { overflow-x: auto; }
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-club-logo">
            <?php if ($logoClube): ?>
                <img src="<?= $logoClube ?>" alt="Logótipo do clube">
            <?php else: ?>
                <span class="topbar-club-logo--placeholder"><?= h($siglaClube) ?></span>
            <?php endif; ?>
        </div>
        <div class="topbar-club-text">
            <span class="topbar-name"><?= h($nomeClube) ?></span>
            <?php if ($siglaClube): ?>
                <span class="topbar-sigla"><?= h($siglaClube) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-right">
        <img src="assets/kroos-logo-branco.png" class="topbar-logo" alt="Kroos">
        <div class="topbar-user-menu-wrap">
            <button class="topbar-menu" type="button" aria-label="Menu" onclick="toggleUserMenu(event)">
                <span></span><span></span><span></span>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <a href="index-jogador.php?view=perfil"><span>Perfil</span></a>
                <a href="index-jogador.php?view=notificacoes"><span>Notificações</span></a>
                <a href="logout.php" class="logout-link"><span>Terminar sessão</span></a>
            </div>
        </div>
    </div>
</div>

<div class="sidebar" id="sidebar">
    <a href="index-jogador.php?view=home" data-view="home" class="<?= $activeSidebarView === 'home' ? 'active' : '' ?>">
        <img src="assets/home.png" alt="">
        <span>Página Principal</span>
    </a>
    <a href="index-jogador.php?view=equipa" data-view="equipa" class="<?= $activeSidebarView === 'equipa' ? 'active' : '' ?>">
        <img src="assets/escaloes.png" alt="">
        <span>Equipa</span>
    </a>
    <a href="index-jogador.php?view=jogos" data-view="jogos" class="<?= $activeSidebarView === 'jogos' ? 'active' : '' ?>">
        <img src="assets/jogos.png" alt="">
        <span>Jogos</span>
    </a>
    <a href="index-jogador.php?view=campeonato" data-view="campeonato" class="<?= $activeSidebarView === 'campeonato' ? 'active' : '' ?>">
        <img src="assets/campeonato.png" alt="">
        <span>Campeonato</span>
    </a>
    <a href="index-jogador.php?view=calendario" data-view="calendario" class="<?= $activeSidebarView === 'calendario' ? 'active' : '' ?>">
        <img src="assets/calendario.png" alt="">
        <span>Calendário</span>
    </a>
    <a href="index-jogador.php?view=mensagens" data-view="mensagens" class="<?= $activeSidebarView === 'mensagens' ? 'active' : '' ?>">
        <img src="assets/mensagens.png" alt="">
        <span>Mensagens</span>
    </a>
</div>

<div class="main">
    <?php if ($erro): ?>
        <div class="alert alert-error" role="alert">
            <span><?= h($erro) ?></span>
            <button class="alert-close" type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success" role="status">
            <span><?= h($sucesso) ?></span>
            <button class="alert-close" type="button" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <?php if (!$jogador): ?>
        <div class="card">
            <div class="empty-state">
                <strong>Conta de jogador ainda não associada.</strong><br><br>
                O teu utilizador existe, mas ainda não está ligado a nenhum registo da tabela <strong>jogadores</strong>.
                O administrador do clube tem de associar esta conta a um jogador.
            </div>
        </div>
    <?php elseif ($viewMode === 'mensagens'): ?>
        <div class="messages-shell">
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
                                if ($nomeU === '') $nomeU = $uMsg['nome_utilizador'];
                                $initialU = strtoupper(substr($nomeU, 0, 1));
                                $isAtivoChat = ($chatSelecionadoId === $uId);
                                $badgeNaoLidas = (int)($uMsg['nao_lidas'] ?? 0);
                            ?>
                            <a class="message-user <?= $isAtivoChat ? 'active' : '' ?>" href="index-jogador.php?view=mensagens&chat=<?= $uId ?>">
                                <div class="message-user-avatar">
                                    <?php if (!empty($uMsg['foto_base64'])): ?>
                                        <img src="<?= $uMsg['foto_base64'] ?>" alt="<?= h($nomeU) ?>">
                                    <?php else: ?>
                                        <span><?= h($initialU ?: 'U') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-user-main">
                                    <span class="message-user-name"><?= h($nomeU) ?></span>
                                    <span class="message-user-type"><?= h($uMsg['tipo_utilizador']) ?></span>
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
                        if ($chatNome === '') $chatNome = $chatSelecionado['nome_utilizador'];
                    ?>
                    <div class="messages-chat-header"><?= h($chatNome) ?></div>
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
                                        $enviadaEm = (date('Y-m-d', $ts) === date('Y-m-d')) ? date('H:i', $ts) : date('d/m/Y H:i', $ts);
                                    }
                                ?>
                                <div class="dm-bubble <?= $isOut ? 'out' : 'in' ?>">
                                    <?= nl2br(h($msg['conteúdo'])) ?>
                                    <?php if ($enviadaEm): ?><span class="dm-time"><?= h($enviadaEm) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="messages-compose">
                        <form method="POST" action="index-jogador.php?view=mensagens&chat=<?= (int)$chatSelecionadoId ?>">
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
    <?php elseif ($viewMode === 'perfil'): ?>
        <div class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Perfil</h2>
                    <p class="section-subtitle">Dados da tua conta de utilizador.</p>
                </div>
            </div>
            <form class="profile-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="editar_perfil">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome de utilizador</label>
                        <input type="text" name="nome_utilizador" value="<?= h($perfilUtilizador['nome_utilizador'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= h($perfilUtilizador['email_utilizador'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Primeiro nome</label>
                        <input type="text" name="primeiro_nome" value="<?= h($perfilUtilizador['primeiro_nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Último nome</label>
                        <input type="text" name="ultimo_nome" value="<?= h($perfilUtilizador['último_nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nº Telemóvel</label>
                        <input type="text" name="telemovel" value="<?= h($perfilUtilizador['telefone_utilizador'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Data de nascimento</label>
                        <input type="date" name="data_nascimento" value="<?= h($perfilUtilizador['data_nascimento'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <button class="btn-save" type="submit">Guardar alterações</button>
                    </div>
                </div>
                <div class="profile-avatar-box">
                    <div class="profile-avatar">
                        <?php if ($fotoPerfilUtilizador): ?>
                            <img src="<?= $fotoPerfilUtilizador ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <?= h(strtoupper(substr($perfilUtilizador['nome_utilizador'] ?? 'J', 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Foto de perfil</label>
                        <input class="file-input" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
            </form>
        </div>
    <?php elseif ($viewMode === 'notificacoes'): ?>
        <div class="screen-shell visible">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Notificações</h2>
                    <p class="section-subtitle">Últimas notificações da tua conta.</p>
                </div>
            </div>
            <div class="notifications-list">
                <?php if (empty($notificacoesUtilizador)): ?>
                    <div class="empty-state">Sem notificações.</div>
                <?php else: ?>
                    <?php foreach ($notificacoesUtilizador as $notificacao): ?>
                        <?php $naoLida = (($notificacao['estado'] ?? 'Nao Lida') === 'Nao Lida'); ?>
                        <div class="notification-row <?= $naoLida ? 'unread' : '' ?>" data-id="<?= (int)$notificacao['id_notificacao'] ?>">
                            <div>
                                <div class="notification-title"><?= h($notificacao['titulo']) ?></div>
                                <small class="notification-message"><?= h($notificacao['mensagem']) ?></small>
                            </div>
                            <?php if ($naoLida): ?>
                                <button class="notification-check" type="button" onclick="markNotificationRead(<?= (int)$notificacao['id_notificacao'] ?>, this)">✓</button>
                            <?php else: ?>
                                <span class="badge gray">Lida</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($viewMode === 'equipa'): ?>
        <div class="screen-shell visible">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Equipa</h2>
                    <p class="section-subtitle"><?= h($jogador['escalão'] . ' ' . $jogador['hierarquia']) ?><?= !empty($jogador['epoca']) ? ' · ' . h($jogador['epoca']) : '' ?></p>
                </div>
            </div>
            <?php if (empty($jogadoresEquipa)): ?>
                <div class="empty-state">Ainda não existem jogadores associados a esta equipa.</div>
            <?php else: ?>
                <div class="players-grid">
                    <?php foreach ($jogadoresEquipa as $colega): ?>
                        <?php $isCurrent = (int)$colega['id_jogador'] === (int)$jogador['id_jogador']; ?>
                        <div class="player-card <?= $isCurrent ? 'player-current' : '' ?>">
                            <?php if (!empty($colega['número_favorito'])): ?>
                                <div class="player-number"><?= h($colega['número_favorito']) ?></div>
                            <?php endif; ?>
                            <div class="player-avatar">
                                <?php if (!empty($colega['foto_base64'])): ?>
                                    <img src="<?= $colega['foto_base64'] ?>" alt="">
                                <?php else: ?>
                                    <?= h(strtoupper(substr($colega['alcunha_jogador'] ?: $colega['nome_completo'], 0, 1))) ?>
                                <?php endif; ?>
                            </div>
                            <div class="player-name"><?= h($colega['alcunha_jogador'] ?: $colega['nome_completo']) ?><?= $isCurrent ? ' · Tu' : '' ?></div>
                            <div class="player-pos"><?= h($colega['posição_principal']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($viewMode === 'jogos'): ?>
        <div class="screen-shell visible">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Jogos</h2>
                    <p class="section-subtitle">Jogos da tua equipa.</p>
                </div>
            </div>
            <?php if (empty($jogosEquipa)): ?>
                <div class="empty-state">Ainda não existem jogos associados à tua equipa.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Competição</th>
                            <th>Adversário</th>
                            <th>Casa/Fora</th>
                            <th>Resultado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogosEquipa as $jogo): ?>
                            <?php
                                $estado = $jogo['estado'];
                                $estadoClass = match ($estado) {
                                    'Realizado' => 'green',
                                    'Cancelado' => 'red',
                                    'Adiado' => 'yellow',
                                    default => 'blue',
                                };
                                $resultado = ($jogo['resultado_nos'] !== null && $jogo['resultado_adv'] !== null)
                                    ? $jogo['resultado_nos'] . ' - ' . $jogo['resultado_adv']
                                    : '—';
                            ?>
                            <tr>
                                <td><?= h(formatDatePt($jogo['data_jogo'])) ?><?= $jogo['hora_jogo'] ? '<br><small>' . h(formatTimePt($jogo['hora_jogo'])) . '</small>' : '' ?></td>
                                <td><?= h($jogo['competicao_nome']) ?></td>
                                <td><?= h($jogo['adversario']) ?><?= $jogo['local_jogo'] ? '<br><small>' . h($jogo['local_jogo']) . '</small>' : '' ?></td>
                                <td><span class="badge gray"><?= ((int)$jogo['casa'] === 1) ? 'Casa' : 'Fora' ?></span></td>
                                <td><strong><?= h($resultado) ?></strong></td>
                                <td><span class="badge <?= $estadoClass ?>"><?= h($estado) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php elseif ($viewMode === 'campeonato'): ?>
        <div class="screen-shell visible">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Campeonato</h2>
                    <p class="section-subtitle">Resumo das competições da tua equipa.</p>
                </div>
            </div>
            <?php if (empty($competicoesEquipa)): ?>
                <div class="empty-state">Ainda não existem competições associadas à tua equipa.</div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($competicoesEquipa as $comp): ?>
                        <?php
                            $idComp = (int)$comp['id_competicao'];
                            $stats = $estatisticasPorCompeticao[$idComp] ?? ['jogos'=>0,'vitorias'=>0,'empates'=>0,'derrotas'=>0,'gm'=>0,'gs'=>0,'pontos'=>0];
                            $estadoCss = match ($comp['estado']) {
                                'Finalizada' => 'gray',
                                'Suspensa' => 'yellow',
                                default => 'green',
                            };
                        ?>
                        <div class="comp-card">
                            <span class="badge <?= $estadoCss ?>"><?= h($comp['estado']) ?></span>
                            <div class="comp-title"><?= h($comp['nome']) ?></div>
                            <div class="comp-meta"><?= h($comp['tipo']) ?><?= $comp['epoca'] ? ' · ' . h($comp['epoca']) : '' ?></div>
                            <div class="comp-stats">
                                <div class="comp-stat"><strong><?= (int)$stats['pontos'] ?></strong><span>Pts</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['jogos'] ?></strong><span>Jogos</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['vitorias'] ?></strong><span>Vit</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['empates'] ?></strong><span>Emp</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['derrotas'] ?></strong><span>Der</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['gm'] ?></strong><span>GM</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['gs'] ?></strong><span>GS</span></div>
                                <div class="comp-stat"><strong><?= (int)$stats['gm'] - (int)$stats['gs'] ?></strong><span>DG</span></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($viewMode === 'treino'): ?>
        <div class="screen-shell visible">
            <?php if (!$treinoSelecionado): ?>
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Treino não encontrado</h2>
                        <p class="section-subtitle">Este treino não existe ou não pertence à tua equipa.</p>
                    </div>
                    <a href="index-jogador.php?view=calendario" class="btn-soft-link">← Voltar ao calendário</a>
                </div>
                <div class="empty-state">Não tens acesso a este plano de treino.</div>
            <?php else: ?>
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Plano de treino</h2>
                        <p class="section-subtitle">Consulta apenas. Só o treinador pode editar.</p>
                    </div>
                    <a href="index-jogador.php?view=calendario" class="btn-soft-link">← Voltar ao calendário</a>
                </div>

                <div class="treino-view-header">
                    <div class="treino-view-title">
                        Treino #<?= h($treinoSelecionado['numero_treino']) ?> — <?= h($treinoSelecionado['conteudo_treino'] ?: 'Plano de treino') ?>
                    </div>
                    <div class="treino-view-meta">
                        <?= h($treinoSelecionado['escalão'] . ' ' . $treinoSelecionado['hierarquia']) ?>
                        <?= $treinoSelecionado['epoca'] ? ' · ' . h($treinoSelecionado['epoca']) : '' ?>
                        · <?= h(formatDatePt($treinoSelecionado['data_treino'])) ?>
                        <?= $treinoSelecionado['hora_treino'] ? ' · ' . h(formatTimePt($treinoSelecionado['hora_treino'])) : '' ?>
                        <?= $treinoSelecionado['dia_da_semana'] ? ' · ' . h($treinoSelecionado['dia_da_semana']) : '' ?>
                    </div>
                </div>

                <div class="treino-info-grid">
                    <div class="treino-info-box"><span>Data</span><strong><?= h(formatDatePt($treinoSelecionado['data_treino'])) ?></strong></div>
                    <div class="treino-info-box"><span>Hora</span><strong><?= h(formatTimePt($treinoSelecionado['hora_treino']) ?: '—') ?></strong></div>
                    <div class="treino-info-box"><span>Equipa</span><strong><?= h($treinoSelecionado['escalão'] . ' ' . $treinoSelecionado['hierarquia']) ?></strong></div>
                    <div class="treino-info-box"><span>Observações</span><strong><?= h($treinoSelecionado['observacoes_treino'] ?: '—') ?></strong></div>
                </div>

                <?php if (empty($exerciciosTreinoSelecionado)): ?>
                    <div class="empty-state">Este treino ainda não tem plano visual associado.</div>
                <?php else: ?>
                    <?php foreach ($exerciciosTreinoSelecionado as $idx => $exercicio): ?>
                        <section class="treino-exercicio-view">
                            <div class="treino-exercicio-visual">
                                <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;font-size:13px;">
                                    <strong>Atividade: <?= h($exercicio['titulo'] ?: ('Exercício ' . ($idx + 1))) ?></strong>
                                    <strong>Duração:</strong>
                                </div>
                                <div class="treino-print-label">Representação visual</div>
                                <canvas class="treino-view-canvas" width="520" height="330" data-treino-exercicio="<?= (int)$idx ?>"></canvas>
                            </div>

                            <div class="treino-exercicio-desc">
                                <div class="treino-print-label">Descrição</div>
                                <div class="treino-desc-block"><?= nl2br(h($exercicio['descricao'] ?: 'Sem descrição definida.')) ?></div>

                                <div class="treino-print-label">Objetivos / Indicações / Regras</div>
                                <div class="treino-objetivo-block"><?= nl2br(h($exercicio['objetivos'] ?: 'Sem objetivos definidos.')) ?></div>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($viewMode === 'calendario'): ?>
        <div class="screen-shell visible">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Calendário</h2>
                    <p class="section-subtitle">Eventos da tua equipa.</p>
                </div>
            </div>
            <?php if (empty($eventosEquipa)): ?>
                <div class="empty-state">Ainda não existem eventos no calendário da tua equipa.</div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach ($eventosEquipa as $evento): ?>
                        <?php
                            $estadoEvClass = match ($evento['estado_evento']) {
                                'Realizado' => 'green',
                                'Cancelado' => 'red',
                                'Adiado' => 'yellow',
                                default => 'blue',
                            };
                        ?>
                        <?php
                            $isTreinoCalendario = ($evento['tipo_evento'] === 'Treino' && !empty($evento['id_treino']));
                            $eventTag = $isTreinoCalendario ? 'a' : 'div';
                            $eventHref = $isTreinoCalendario ? ' href="index-jogador.php?view=treino&id=' . (int)$evento['id_treino'] . '"' : '';
                        ?>
                        <<?= $eventTag ?> class="event-card <?= $isTreinoCalendario ? 'event-card-link' : '' ?>"<?= $eventHref ?>>
                            <div class="event-date">
                                <?= h(formatDatePt($evento['data_evento'])) ?>
                                <?= $evento['hora_evento'] ? '<br>' . h(formatTimePt($evento['hora_evento'])) : '' ?>
                            </div>
                            <div class="event-main">
                                <strong><?= h($evento['tipo_evento']) ?></strong>
                                <span><?= h($evento['descricao_evento'] ?: ($isTreinoCalendario ? ('Treino #' . $evento['numero_treino']) : 'Sem descrição')) ?></span>
                                <?php if ($evento['local_evento']): ?>
                                    <br><span><?= h($evento['local_evento']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isTreinoCalendario): ?>
                                <span class="event-action">Ver treino</span>
                            <?php else: ?>
                                <span class="badge <?= $estadoEvClass ?>"><?= h($evento['estado_evento']) ?></span>
                            <?php endif; ?>
                        </<?= $eventTag ?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="hero">
                <div>
                    <div class="hero-kicker">Painel do Jogador</div>
                    <h1 class="hero-title"><?= h($jogador['alcunha_jogador'] ?: $jogador['nome_completo']) ?></h1>
                    <p class="hero-subtitle">
                        <?= h($jogador['posição_principal']) ?>
                        <?= $jogador['posição_secundária'] ? ' · ' . h($jogador['posição_secundária']) : '' ?>
                        · <?= h($jogador['escalão'] . ' ' . $jogador['hierarquia']) ?>
                        <?= $jogador['epoca'] ? ' · ' . h($jogador['epoca']) : '' ?>
                    </p>
                </div>
                <div class="player-photo">
                    <?php if ($fotoJogador): ?>
                        <img src="<?= $fotoJogador ?>" alt="<?= h($jogador['nome_completo']) ?>">
                    <?php else: ?>
                        <?= h(strtoupper(substr($jogador['alcunha_jogador'] ?: $jogador['nome_completo'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label">Número</div>
                    <div class="stat-value"><?= h($jogador['número_favorito'] ?: '—') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pé</div>
                    <div class="stat-value"><?= h($jogador['pé_preferencial'] ?: '—') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Altura</div>
                    <div class="stat-value"><?= h($jogador['altura'] ?: '—') ?><?= $jogador['altura'] ? '<span class="stat-small"> cm</span>' : '' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Peso</div>
                    <div class="stat-value"><?= h($jogador['peso'] ?: '—') ?><?= $jogador['peso'] ? '<span class="stat-small"> kg</span>' : '' ?></div>
                </div>
            </div>

            <div class="tabs">
                <button class="tab active" type="button" onclick="switchTab(this, 'tab-info')">Info</button>
                <button class="tab" type="button" onclick="switchTab(this, 'tab-equipa')">Equipa</button>
                <button class="tab" type="button" onclick="switchTab(this, 'tab-carreira')">Carreira</button>
                <button class="tab" type="button" onclick="switchTab(this, 'tab-lesoes')">Lesões</button>
            </div>

            <div class="tab-panel active" id="tab-info">
                <div class="info-grid">
                    <div class="info-row"><div class="info-label">Nome completo</div><div class="info-value"><?= h($jogador['nome_completo']) ?></div></div>
                    <div class="info-row"><div class="info-label">Alcunha</div><div class="info-value <?= $jogador['alcunha_jogador'] ? '' : 'empty' ?>"><?= h($jogador['alcunha_jogador'] ?: 'Não definida') ?></div></div>
                    <div class="info-row"><div class="info-label">Data de nascimento</div><div class="info-value"><?= h(formatDatePt($jogador['data_nascimento'])) ?></div></div>
                    <div class="info-row"><div class="info-label">Nacionalidade</div><div class="info-value"><?= h($jogador['nacionalidade']) ?></div></div>
                    <div class="info-row"><div class="info-label">País de nascimento</div><div class="info-value"><?= h($jogador['país_nascimento'] ?: 'Não definido') ?></div></div>
                    <div class="info-row"><div class="info-label">Equipa</div><div class="info-value"><?= h($jogador['escalão'] . ' ' . $jogador['hierarquia']) ?></div></div>
                    <div class="info-row"><div class="info-label">Clube</div><div class="info-value"><?= h($nomeClube) ?></div></div>
                    <div class="info-row"><div class="info-label">Estádio</div><div class="info-value <?= $clube['nome_estádio'] ? '' : 'empty' ?>"><?= h($clube['nome_estádio'] ?: 'Não definido') ?></div></div>
                </div>
            </div>

            <div class="tab-panel" id="tab-equipa">
                <?php if (empty($jogadoresEquipa)): ?>
                    <div class="empty-state">Sem jogadores na tua equipa.</div>
                <?php else: ?>
                    <div class="players-grid">
                        <?php foreach ($jogadoresEquipa as $colega): ?>
                            <?php $isCurrent = (int)$colega['id_jogador'] === (int)$jogador['id_jogador']; ?>
                            <div class="player-card <?= $isCurrent ? 'player-current' : '' ?>">
                                <?php if (!empty($colega['número_favorito'])): ?><div class="player-number"><?= h($colega['número_favorito']) ?></div><?php endif; ?>
                                <div class="player-avatar">
                                    <?php if (!empty($colega['foto_base64'])): ?>
                                        <img src="<?= $colega['foto_base64'] ?>" alt="">
                                    <?php else: ?>
                                        <?= h(strtoupper(substr($colega['alcunha_jogador'] ?: $colega['nome_completo'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="player-name"><?= h($colega['alcunha_jogador'] ?: $colega['nome_completo']) ?><?= $isCurrent ? ' · Tu' : '' ?></div>
                                <div class="player-pos"><?= h($colega['posição_principal']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-panel" id="tab-carreira">
                <?php if (empty($historicoCarreira)): ?>
                    <div class="empty-state">Ainda não existe histórico de carreira registado.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Época</th><th>Clube</th><th>Jogos</th><th>Golos</th><th>Assistências</th></tr></thead>
                        <tbody>
                            <?php foreach ($historicoCarreira as $hist): ?>
                                <tr>
                                    <td><?= h($hist['epoca'] ?: '—') ?></td>
                                    <td><?= h($hist['clube'] ?: '—') ?></td>
                                    <td><?= h($hist['jogos']) ?></td>
                                    <td><?= h($hist['golos_marcados']) ?></td>
                                    <td><?= h($hist['assistências']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="tab-panel" id="tab-lesoes">
                <?php if (empty($lesoesJogador)): ?>
                    <div class="empty-state">Sem lesões registadas.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Lesão</th><th>Tipo</th><th>Recuperação</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php foreach ($lesoesJogador as $lesao): ?>
                                <tr>
                                    <td><?= h($lesao['nome_lesão']) ?></td>
                                    <td><?= h($lesao['tipo_lesão']) ?></td>
                                    <td><?= h($lesao['tempo_recuperação']) ?></td>
                                    <td><?= h($lesao['estado_lesão']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleUserMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.toggle('active');
}

document.addEventListener('click', () => {
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.remove('active');
});

function switchTab(btn, panelId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById(panelId);
    if (panel) panel.classList.add('active');
}

function markNotificationRead(id, btn) {
    const fd = new FormData();
    fd.append('acao', 'marcar_notificacao_lida');
    fd.append('id_notificacao', id);

    fetch('index-jogador.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    }).then(() => {
        const row = btn.closest('.notification-row');
        if (row) row.classList.remove('unread');
        btn.outerHTML = '<span class="badge gray">Lida</span>';
    }).catch(() => {});
}

const thread = document.getElementById('messagesThread');
if (thread) {
    setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 50);
}


/* ── Renderização readonly dos planos visuais no jogador ── */
const treinoPlanoExercicios = <?= json_encode($exerciciosTreinoSelecionado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TREINO_PLANO_BASE_W = 980;
const TREINO_PLANO_BASE_H = 610;

function drawTreinoPlano(canvas, data) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const state = data || {template:'campo_inteiro', objects:[]};
    ctx.save();
    ctx.scale(canvas.width / TREINO_PLANO_BASE_W, canvas.height / TREINO_PLANO_BASE_H);
    drawTreinoCampo(ctx, TREINO_PLANO_BASE_W, TREINO_PLANO_BASE_H, state.template || 'campo_inteiro');
    (state.objects || []).forEach(obj => drawTreinoObjeto(ctx, normalizarTreinoObjeto(obj)));
    ctx.restore();
}

function normalizarTreinoObjeto(o) {
    const n = Object.assign({}, o || {});
    if (n.scale == null) n.scale = 1;
    if (n.type === 'circle' && !n.r) n.r = 42;
    if (n.type === 'rect') { if (!n.w) n.w = 120; if (!n.h) n.h = 72; }
    if (n.type === 'line' && !n.w) n.w = 4;
    if (n.rotation == null) n.rotation = 0;
    return n;
}

function drawTreinoCampo(ctx, w, h, template) {
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.strokeStyle = '#111';
    ctx.lineCap = 'square';
    ctx.lineJoin = 'miter';
    ctx.lineWidth = 4;

    const left = w * .04, right = w * .96, top = h * .08, bottom = h * .92, mid = w / 2, cy = (top + bottom) / 2;

    if (template === 'area') {
        const fieldW = right - left, fieldH = bottom - top;
        ctx.strokeRect(left, top, fieldW, fieldH);
        const boxW = fieldW * .50, boxH = fieldH * .38, boxX = mid - boxW / 2;
        ctx.strokeRect(boxX, top, boxW, boxH);
        ctx.strokeRect(mid - boxW * .22, top, boxW * .44, boxH * .48);
        ctx.beginPath(); ctx.arc(mid, top + boxH, boxW * .16, 0, Math.PI); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid, top + boxH * .58, 4, 0, Math.PI * 2); ctx.fillStyle = '#111'; ctx.fill();
        return;
    }

    if (template === 'futsal') {
        roundRectTreinoPath(ctx, left, top, right - left, bottom - top, 16); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(mid, top); ctx.lineTo(mid, bottom); ctx.stroke();
        ctx.beginPath(); ctx.arc(mid, cy, (bottom - top) * .12, 0, Math.PI * 2); ctx.stroke();
        drawTreinoAreaFutsal(ctx, left, cy, 1, top, bottom);
        drawTreinoAreaFutsal(ctx, right, cy, -1, top, bottom);
        return;
    }

    ctx.strokeRect(left, top, right - left, bottom - top);
    ctx.beginPath(); ctx.moveTo(mid, top); ctx.lineTo(mid, bottom); ctx.stroke();
    ctx.beginPath(); ctx.arc(mid, cy, (bottom - top) * .13, 0, Math.PI * 2); ctx.stroke();

    const bw = (right - left) * .17, bh = (bottom - top) * .48, sw = (right - left) * .06, sh = (bottom - top) * .22;
    const yb = cy - bh / 2, ys = cy - sh / 2;
    ctx.strokeRect(left, yb, bw, bh); ctx.strokeRect(left, ys, sw, sh);
    ctx.beginPath(); ctx.arc(left + bw, cy, bh * .19, -Math.PI / 2, Math.PI / 2); ctx.stroke();
    ctx.beginPath(); ctx.arc(left + bw * .55, cy, 4, 0, Math.PI * 2); ctx.fillStyle = '#111'; ctx.fill();
    ctx.strokeRect(right - bw, yb, bw, bh); ctx.strokeRect(right - sw, ys, sw, sh);
    ctx.beginPath(); ctx.arc(right - bw, cy, bh * .19, Math.PI / 2, Math.PI * 1.5); ctx.stroke();
    ctx.beginPath(); ctx.arc(right - bw * .55, cy, 4, 0, Math.PI * 2); ctx.fillStyle = '#111'; ctx.fill();
}

function roundRectTreinoPath(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x+r,y); ctx.lineTo(x+w-r,y); ctx.quadraticCurveTo(x+w,y,x+w,y+r); ctx.lineTo(x+w,y+h-r); ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h); ctx.lineTo(x+r,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-r); ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y);
}
function drawTreinoAreaFutsal(ctx, x, cy, dir, top, bottom) {
    const h = (bottom - top) * .34;
    ctx.beginPath(); ctx.arc(x, cy, h / 2, dir > 0 ? -Math.PI / 2 : Math.PI / 2, dir > 0 ? Math.PI / 2 : Math.PI * 1.5); ctx.stroke();
    ctx.strokeRect(x + (dir > 0 ? 0 : -42), cy - 35, 42, 70);
}

function drawTreinoObjeto(ctx, o) {
    ctx.save();
    if (o.type !== 'line' && Number(o.rotation || 0)) {
        ctx.translate(o.x || 0, o.y || 0);
        ctx.rotate(Number(o.rotation || 0) * Math.PI / 180);
        o = Object.assign({}, o, {x:0, y:0});
    }

    if (o.type === 'line') drawTreinoLinha(ctx, o);
    else if (o.type === 'player') drawTreinoJogador(ctx, o);
    else if (o.type === 'cone') drawTreinoCone(ctx, o);
    else if (o.type === 'ball') drawTreinoBola(ctx, o);
    else if (o.type === 'barrier') drawTreinoBarreira(ctx, o);
    else if (o.type === 'ladder') drawTreinoEscada(ctx, o);
    else if (o.type === 'goal') drawTreinoBaliza(ctx, o);
    else if (o.type === 'rect') drawTreinoRetangulo(ctx, o);
    else if (o.type === 'circle') drawTreinoCirculo(ctx, o);
    else if (o.type === 'text') drawTreinoTexto(ctx, o);
    ctx.restore();
}

function drawTreinoLinha(ctx, o) {
    const w = Number(o.w || 4);
    ctx.strokeStyle = o.color || '#111';
    ctx.fillStyle = o.color || '#111';
    ctx.lineWidth = w;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    if (o.mode === 'dash') ctx.setLineDash([16, 12]);
    if (o.mode === 'run') {
        drawTreinoLinhaOndulada(ctx, o.x1, o.y1, o.x2, o.y2, w);
        drawTreinoSeta(ctx, o.x1, o.y1, o.x2, o.y2, o.color || '#111', w);
    } else {
        ctx.beginPath(); ctx.moveTo(o.x1, o.y1); ctx.lineTo(o.x2, o.y2); ctx.stroke();
        if (o.mode === 'arrow') drawTreinoSeta(ctx, o.x1, o.y1, o.x2, o.y2, o.color || '#111', w);
    }
    ctx.setLineDash([]);
}
function drawTreinoLinhaOndulada(ctx, x1, y1, x2, y2, w) {
    const dx = x2-x1, dy = y2-y1, len = Math.hypot(dx,dy) || 1;
    const ux = dx/len, uy = dy/len, px = -uy, py = ux, amp = 7;
    ctx.beginPath();
    for (let d=0; d<=len; d+=8) {
        const t=d/len, wave=Math.sin(t*Math.PI*len/28)*amp, x=x1+ux*d+px*wave, y=y1+uy*d+py*wave;
        if (d===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    }
    ctx.stroke();
}
function drawTreinoSeta(ctx, x1, y1, x2, y2, color, w=4) {
    const a=Math.atan2(y2-y1,x2-x1), l=17+w*1.2;
    ctx.save(); ctx.fillStyle=color;
    ctx.beginPath(); ctx.moveTo(x2,y2);
    ctx.lineTo(x2-l*Math.cos(a-Math.PI/6),y2-l*Math.sin(a-Math.PI/6));
    ctx.lineTo(x2-l*Math.cos(a+Math.PI/6),y2-l*Math.sin(a+Math.PI/6));
    ctx.closePath(); ctx.fill(); ctx.restore();
}
function drawTreinoJogador(ctx, o) {
    const s=Number(o.scale||1), r=18*s;
    ctx.beginPath(); ctx.arc(o.x,o.y,r,0,Math.PI*2); ctx.fillStyle=o.color||'#3b82f6'; ctx.fill();
    ctx.lineWidth=2.5*s; ctx.strokeStyle='#fff'; ctx.stroke();
    ctx.fillStyle='#fff'; ctx.font=`900 ${Math.max(9,14*s)}px Inter,Arial`; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(String(o.n ?? ''), o.x, o.y+1*s);
}
function drawTreinoCone(ctx, o) {
    const s=Number(o.scale||1), x=o.x, y=o.y, c=o.color||'#f97316';
    ctx.save(); ctx.fillStyle=c; ctx.strokeStyle='#111827'; ctx.lineWidth=2.4*s;
    if ((o.variant || 'tall') === 'flat') {
        ctx.beginPath(); ctx.arc(x,y,18*s,0,Math.PI*2); ctx.fill(); ctx.stroke();
        ctx.fillStyle='#fff'; ctx.beginPath(); ctx.arc(x,y,7*s,0,Math.PI*2); ctx.fill(); ctx.stroke();
        ctx.strokeStyle='rgba(255,255,255,.7)'; ctx.lineWidth=1.5*s; ctx.beginPath(); ctx.arc(x,y,12*s,0,Math.PI*2); ctx.stroke();
    } else {
        ctx.beginPath(); ctx.moveTo(x,y-27*s); ctx.lineTo(x-16*s,y+13*s); ctx.quadraticCurveTo(x,y+20*s,x+16*s,y+13*s); ctx.closePath(); ctx.fill(); ctx.stroke();
        ctx.strokeStyle='#fff'; ctx.lineWidth=3.2*s; ctx.beginPath(); ctx.moveTo(x-8*s,y-5*s); ctx.lineTo(x+8*s,y-5*s); ctx.moveTo(x-12*s,y+7*s); ctx.lineTo(x+12*s,y+7*s); ctx.stroke();
        ctx.fillStyle='#111827'; ctx.beginPath(); ctx.ellipse(x,y+21*s,23*s,5*s,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle=c; ctx.beginPath(); ctx.ellipse(x,y+19*s,19*s,3*s,0,0,Math.PI*2); ctx.fill();
    }
    ctx.restore();
}
function drawTreinoBola(ctx, o) {
    const s=Number(o.scale||1), x=o.x, y=o.y, r=15*s;
    ctx.save(); ctx.fillStyle='#fff'; ctx.strokeStyle='#111827'; ctx.lineWidth=2.5*s; ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fill(); ctx.stroke();
    ctx.fillStyle='#111827'; ctx.beginPath(); for(let i=0;i<5;i++){const a=-Math.PI/2+i*2*Math.PI/5, px=x+Math.cos(a)*r*.45, py=y+Math.sin(a)*r*.45; i?ctx.lineTo(px,py):ctx.moveTo(px,py);} ctx.closePath(); ctx.fill();
    ctx.strokeStyle='#111827'; ctx.lineWidth=1.4*s; for(let i=0;i<5;i++){const a=-Math.PI/2+i*2*Math.PI/5; ctx.beginPath(); ctx.moveTo(x+Math.cos(a)*r*.48,y+Math.sin(a)*r*.48); ctx.lineTo(x+Math.cos(a)*r*.95,y+Math.sin(a)*r*.95); ctx.stroke();}
    ctx.restore();
}
function drawTreinoBarreira(ctx, o) {
    const s=Number(o.scale||1), x=o.x, y=o.y, c=o.color||'#f97316', sw=Number(o.strokeWidth||4);
    ctx.save(); ctx.strokeStyle=c; ctx.lineWidth=sw*s; ctx.lineCap='round'; ctx.lineJoin='round';
    ctx.beginPath(); ctx.moveTo(x-30*s,y-18*s); ctx.lineTo(x+30*s,y-18*s); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(x-22*s,y-18*s); ctx.lineTo(x-22*s,y+28*s); ctx.moveTo(x+22*s,y-18*s); ctx.lineTo(x+22*s,y+28*s); ctx.stroke();
    ctx.strokeStyle='#111827'; ctx.lineWidth=Math.max(1.8,2.2*s); ctx.beginPath(); ctx.moveTo(x-32*s,y+29*s); ctx.lineTo(x-12*s,y+29*s); ctx.moveTo(x+12*s,y+29*s); ctx.lineTo(x+32*s,y+29*s); ctx.stroke();
    ctx.restore();
}
function drawTreinoEscada(ctx, o) {
    const s=Number(o.scale||1), x=o.x, y=o.y, c=o.color||'#facc15', sw=Number(o.strokeWidth||4), w=46*s, h=110*s;
    const left=x-w/2, right=x+w/2, top=y-h/2, bottom=y+h/2;
    ctx.save(); ctx.strokeStyle=c; ctx.lineWidth=sw*s; ctx.lineCap='round'; ctx.lineJoin='round';
    ctx.beginPath(); ctx.moveTo(left,top); ctx.lineTo(left,bottom); ctx.moveTo(right,top); ctx.lineTo(right,bottom); ctx.stroke();
    for(let i=1;i<6;i++){const yy=top+(h/6)*i; ctx.beginPath(); ctx.moveTo(left,yy); ctx.lineTo(right,yy); ctx.stroke();}
    ctx.restore();
}
function drawTreinoBaliza(ctx, o) {
    const s=Number(o.scale||1), x=o.x, y=o.y, w=72*s, h=28*s;
    ctx.save(); ctx.strokeStyle=o.color||'#111'; ctx.lineWidth=(o.strokeWidth||4)*s; ctx.strokeRect(x-w/2,y-h/2,w,h);
    ctx.strokeStyle='#94a3b8'; ctx.lineWidth=1.2*s; for(let i=1;i<4;i++){ctx.beginPath(); ctx.moveTo(x-w/2+i*w/4,y-h/2); ctx.lineTo(x-w/2+i*w/4,y+h/2); ctx.stroke();}
    ctx.beginPath(); ctx.moveTo(x-w/2,y); ctx.lineTo(x+w/2,y); ctx.stroke(); ctx.restore();
}
function drawTreinoRetangulo(ctx, o) { const s=Number(o.scale||1), w=(o.w||120)*s, h=(o.h||72)*s; ctx.save(); ctx.strokeStyle=o.color||'#111'; ctx.lineWidth=o.strokeWidth||4; ctx.strokeRect(o.x-w/2,o.y-h/2,w,h); ctx.restore(); }
function drawTreinoCirculo(ctx, o) { const s=Number(o.scale||1), r=(o.r||42)*s; ctx.save(); ctx.strokeStyle=o.color||'#111'; ctx.lineWidth=o.strokeWidth||4; ctx.beginPath(); ctx.arc(o.x,o.y,r,0,Math.PI*2); ctx.stroke(); ctx.restore(); }
function drawTreinoTexto(ctx, o) { const s=Number(o.scale||1); ctx.save(); ctx.fillStyle=o.color||'#111'; ctx.font=`900 ${Math.max(10,(o.size||24)*s)}px Inter,Arial`; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(o.text||'Texto',o.x,o.y); ctx.restore(); }

function renderTreinoPlanoJogador() {
    document.querySelectorAll('canvas[data-treino-exercicio]').forEach(canvas => {
        const idx = Number(canvas.dataset.treinoExercicio || 0);
        const ex = treinoPlanoExercicios[idx];
        drawTreinoPlano(canvas, ex && ex.canvas ? ex.canvas : {template:'campo_inteiro', objects:[]});
    });
}

document.addEventListener('DOMContentLoaded', renderTreinoPlanoJogador);
</script>
</body>
</html>
