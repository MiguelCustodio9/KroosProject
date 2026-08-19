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
    $stmtCkJ = $conn->prepare("SELECT j.id_jogador FROM jogadores j JOIN equipa eq ON eq.id_equipa=j.id_equipa WHERE j.id_jogador=? AND eq.id_clube=? LIMIT 1");
    $stmtCkJ->bind_param("ii", $idJ, $idC);
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

if ($_SESSION['tipo_utilizador'] === 'treinador') {
    header('Location: index-treinador.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] !== 'admin_clube') {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];
$id_clube      = $_SESSION['id_clube'];
$tipo_utilizador_sessao = $_SESSION['tipo_utilizador'];
$isAdminClube = true;

$erro = '';
$sucesso = '';
$activeTab = 'tab-info';
$viewMode = $_GET['view'] ?? 'dashboard';
$mostrarMensagens = ($viewMode === 'mensagens');
$activeSidebarView = match ($viewMode) {
    'mensagens' => 'mensagens',
    'calendario' => 'calendario',
    'escaloes' => 'escaloes',
    'competicoes' => 'competicoes',
    'home' => 'home',
    default => 'clube',
};
$chatSelecionadoId = (int)($_GET['chat'] ?? 0);

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

/* ══════════════════════════════════
   AÇÕES POST
══════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

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
                    header("Location: index-admin.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
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
                    header("Location: index-admin.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
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
                header("Location: index-admin.php?view=calendario&cal_month=$calMonth&cal_year=$calYear$calDayParam");
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

/* ── Buscar eventos do calendário (todos os escalões do clube) ── */
$eventosCalendario = [];
$stmtEventosCalendario = $conn->prepare("
    SELECT ec.id_evento, ec.tipo_evento, ec.`descrição_evento` AS descricao_evento,
           ec.estado_evento, ec.data_evento, ec.hora_evento, ec.local_evento,
           eq.id_equipa, eq.`escalão`, eq.hierarquia
    FROM eventos_clube ec
    JOIN equipa eq ON eq.id_equipa = ec.id_equipa
    WHERE eq.id_clube = ?
    ORDER BY ec.data_evento ASC, ec.hora_evento ASC
");
$stmtEventosCalendario->bind_param("i", $id_clube);
$stmtEventosCalendario->execute();
$resEventosCalendario = $stmtEventosCalendario->get_result();
while ($row = $resEventosCalendario->fetch_assoc()) {
    $eventosCalendario[] = $row;
}

/* ── Buscar jogadores por equipa (para o ecrã de escalões) ── */
$jogadoresPorEquipa = [];
$stmtJogadores = $conn->prepare("
    SELECT j.id_jogador, j.nome_completo, j.alcunha_jogador, j.`posição_principal`,
           j.`posição_secundária`, j.`número_favorito`, j.`pé_preferencial`,
           j.data_nascimento, j.nacionalidade, j.altura, j.peso,
           j.id_equipa, j.id_utilizador, j.foto_jogador
    FROM jogadores j
    JOIN equipa eq ON eq.id_equipa = j.id_equipa
    WHERE eq.id_clube = ?
    ORDER BY j.id_equipa, j.`número_favorito` ASC, j.nome_completo ASC
");
$stmtJogadores->bind_param("i", $id_clube);
$stmtJogadores->execute();
$resJogadores = $stmtJogadores->get_result();
while ($row = $resJogadores->fetch_assoc()) {
    $row['tem_foto'] = !empty($row['foto_jogador']) && strlen($row['foto_jogador']) > 10;
    $row['foto_base64'] = $row['tem_foto'] ? 'data:image/png;base64,' . base64_encode($row['foto_jogador']) : null;
    unset($row['foto_jogador']);
    $jogadoresPorEquipa[$row['id_equipa']][] = $row;
}

/* ── Buscar competições do clube ── */
$competicoesClube = [];
$stmtCompetiu = $conn->prepare("
    SELECT cc.id_competicao, cc.id_equipa, cc.nome, cc.tipo, cc.epoca, cc.estado, cc.descricao,
           eq.`escalão`, eq.hierarquia
    FROM competicoes_clube cc
    JOIN equipa eq ON eq.id_equipa = cc.id_equipa
    WHERE cc.id_clube = ?
    ORDER BY cc.id_competicao DESC
");
$stmtCompetiu->bind_param("i", $id_clube);
$stmtCompetiu->execute();
$resComp = $stmtCompetiu->get_result();
while ($row = $resComp->fetch_assoc()) {
    $competicoesClube[] = $row;
}

/* ── Buscar jogos de todas as competições do clube ── */
$jogosPorCompeticao = [];
$stmtJogosClub = $conn->prepare("
    SELECT jc.id_jogo, jc.id_competicao, jc.adversario, jc.data_jogo, jc.hora_jogo,
           jc.casa, jc.local_jogo, jc.resultado_nos, jc.resultado_adv, jc.estado
    FROM jogos_clube jc
    JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao
    WHERE cc.id_clube = ?
    ORDER BY jc.data_jogo ASC, jc.hora_jogo ASC
");
$stmtJogosClub->bind_param("i", $id_clube);
$stmtJogosClub->execute();
$resJogosC = $stmtJogosClub->get_result();
while ($row = $resJogosC->fetch_assoc()) {
    $jogosPorCompeticao[$row['id_competicao']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | <?= htmlspecialchars($nomeClube) ?></title>
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

/* ══════════════════════════════════
   MAIN CONTENT
══════════════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 28px 28px 40px;
    min-height: calc(100vh - var(--topbar-h));
    box-sizing: border-box;
    overflow-y: auto;
    transition: margin-left .22s cubic-bezier(.4,0,.2,1);
}

body.layout-locked .main {
    height: calc(100vh - var(--topbar-h));
    min-height: 0;
    overflow-y: auto;
    box-sizing: border-box;
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
    color: #777;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: 0.5px;
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
</style>
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
    <a href="#" data-view="clube" class="<?= $activeSidebarView === 'clube' ? 'active' : '' ?>" onclick="event.preventDefault(); showDashboard();">
        <img src="assets/clube.png" alt="">
        <span>Clube</span>
    </a>
    <a href="#" data-view="escaloes" class="<?= $activeSidebarView === 'escaloes' ? 'active' : '' ?>" onclick="event.preventDefault(); showEscaloesScreen();">
        <img src="assets/escaloes.png" alt="">
        <span>Escalões</span>
    </a>
    <a href="#" data-view="competicoes" class="<?= $activeSidebarView === 'competicoes' ? 'active' : '' ?>" onclick="event.preventDefault(); showCompeticoesScreen();">
        <img src="assets/eventos.png" alt="">
        <span>Competições</span>
    </a>
    <a href="#" data-view="calendario" class="<?= $activeSidebarView === 'calendario' ? 'active' : '' ?>" onclick="event.preventDefault(); showCalendarScreen();">
        <img src="assets/calendario.png" alt="">
        <span>Calendário</span>
    </a>
    <a href="#" data-view="mensagens" class="<?= $activeSidebarView === 'mensagens' ? 'active' : '' ?>" onclick="event.preventDefault(); showMessagesScreen();">
        <img src="assets/mensagens.png" alt="">
        <span>Mensagens</span>
    </a>
    <a href="#" data-view="home" class="<?= $activeSidebarView === 'home' ? 'active' : '' ?>" onclick="event.preventDefault(); showMainMenu();">
        <img src="assets/home.png" alt="">
        <span>Menu Principal</span>
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
                            <a class="message-user <?= $isAtivoChat ? 'active' : '' ?>" href="index-admin.php?view=mensagens&chat=<?= $uId ?>">
                                <div class="message-user-avatar">
                                    <?php if (!empty($uMsg['foto_base64'])): ?>
                                        <img src="<?= $uMsg['foto_base64'] ?>" alt="<?= htmlspecialchars($nomeU) ?>">
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($initialU ?: 'U') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-user-main">
                                    <span class="message-user-name"><?= htmlspecialchars($nomeU) ?></span>
                                    <span class="message-user-type"><?= htmlspecialchars($uMsg['tipo_utilizador']) ?></span>
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
                        <form method="POST" action="index-admin.php?view=mensagens&chat=<?= (int)$chatSelecionadoId ?>">
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
                        <img id="clubLogoImage"
                            src="<?= $logoClube ?>"
                            alt="Logótipo de <?= htmlspecialchars($nomeClube) ?>"
                            onerror="showClubLogoStatus();">

                        <div id="clubLogoStatus" class="club-logo-status" style="display:none;">
                            SEM LOGÓTIPO
                        </div>
                    <?php else: ?>
                        <div class="club-logo-status">
                            SEM LOGÓTIPO
                        </div>
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
        <form method="POST" action="index-admin.php?view=calendario" id="formCriarEvento" onsubmit="return validarFormEvento(this)">
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
        <form method="POST" action="index-admin.php?view=calendario" id="formEditarEvento" onsubmit="return validarFormEvento(this)">
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
    ['profileScreen','notificationsScreen','messagesScreen','calendarScreen','escaloesScreen','competicoesScreen'].forEach(id => {
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

function showClubLogoStatus() {
    const logo = document.getElementById('clubLogoImage');
    const status = document.getElementById('clubLogoStatus');

    if (logo) {
        logo.style.display = 'none';
    }

    if (status) {
        status.textContent = 'SEM LOGÓTIPO';
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
                <form method="POST" action="index-admin.php?view=calendario" style="display:inline;"
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
    <?php elseif (($_GET['view'] ?? '') === 'home'): ?>
    showMainMenu();
    <?php else: ?>
    setLayoutLock(false);
    setActiveSidebar('clube');
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
    fetch('index-admin.php?ajax=jogador_detalhe&id=' + idJogador)
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
</script>

</body>
</html>