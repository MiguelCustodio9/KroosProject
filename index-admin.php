<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ── Protecção da página ── */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador']) ||
    !in_array($_SESSION['tipo_utilizador'], ['admin_clube', 'treinador'], true) ||
    !isset($_SESSION['id_clube'])
) {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];
$id_clube      = $_SESSION['id_clube'];
$tipo_utilizador_sessao = $_SESSION['tipo_utilizador'];
$isAdminClube = ($tipo_utilizador_sessao === 'admin_clube');

$erro = '';
$sucesso = '';
$activeTab = 'tab-info';

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

/* ══════════════════════════════════
   AÇÕES POST
══════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'editar_perfil') {
        $nomeUtilizador = trim($_POST['nome_utilizador'] ?? '');
        $emailUtilizador = trim($_POST['email'] ?? '');
        $primeiroNome = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome = trim($_POST['ultimo_nome'] ?? '');
        $telefoneUtilizador = trim($_POST['telemovel'] ?? '');
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');
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
            if (!empty($_FILES['foto_perfil']['tmp_name'])) {
                if (!isset($_FILES['foto_perfil']) || (int)($_FILES['foto_perfil']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
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

body { background: #f0f2f7; }

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
    transition: margin-left .22s cubic-bezier(.4,0,.2,1);
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

.card-header-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 12px;
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
    gap: 18px;
    padding: 16px 20px 0;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    background: #fff;
}

.notification-tab {
    border: none;
    background: transparent;
    padding: 12px 12px 10px;
    font-size: 14px;
    font-weight: 600;
    color: #6a7280;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}

.notification-tab.active {
    color: var(--club);
    border-bottom-color: var(--club);
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
        gap: 10px;
        overflow-x: auto;
    }

    .notification-label {
        font-size: 14px;
    }
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
    width: 80px;
    text-align: right;
}

.actions-cell {
    text-align: right;
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

/* ── Alerts ── */
.alert {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 18px;
    transition: opacity .28s ease, transform .28s ease;
}

.alert.fade-out {
    opacity: 0;
    transform: translateY(-4px);
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
    margin-bottom: 28px;
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
}
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
    <a href="#" class="active" onclick="event.preventDefault(); showDashboard();">
        <img src="assets/clube.png" alt="">
        <span>Clube</span>
    </a>
    <a href="#">
        <img src="assets/escaloes.png" alt="">
        <span>Escalões</span>
    </a>
    <a href="#">
        <img src="assets/eventos.png" alt="">
        <span>Eventos</span>
    </a>
    <a href="#">
        <img src="assets/calendario.png" alt="">
        <span>Calendário</span>
    </a>
    <a href="#">
        <img src="assets/mensagens.png" alt="">
        <span>Mensagens</span>
    </a>
    <a href="#">
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
                            <button class="profile-avatar-button" id="btnEditarFotoPerfil" type="button">Editar Foto de Perfil</button>
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

        <?php if ($erro): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

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

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab <?= $activeTab === 'tab-info' ? 'active' : '' ?>" onclick="switchTab(this,'tab-info')">Info</button>
            <button class="tab <?= $activeTab === 'tab-escaloes' ? 'active' : '' ?>" onclick="switchTab(this,'tab-escaloes')">Escalões</button>
            <button class="tab <?= $activeTab === 'tab-treinadores' ? 'active' : '' ?>" onclick="switchTab(this,'tab-treinadores')">Treinadores</button>
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
                            <th class="actions-col">Editar</th>
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
                                        <button class="btn-row-edit" type="submit" title="Remover escalão" style="margin-left:8px; border-color:#f2b4b4; color:#b42318;">×</button>
                                    </form>
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
                            <th class="actions-col">Editar</th>
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
                                    <button
                                        class="btn-row-edit"
                                        type="button"
                                        title="Editar treinador"
                                        onclick="openModal('modalEditarTreinador<?= (int)$treinador['id_utilizador'] ?>')"
                                    >
                                        ✎
                                    </button>
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

        <form method="POST" onsubmit="return confirm('Tens a certeza que queres remover este treinador?');" style="padding: 0 22px 20px;">
            <input type="hidden" name="acao" value="remover_treinador">
            <input type="hidden" name="id_treinador" value="<?= (int)$treinador['id_utilizador'] ?>">
            <button class="btn-remove" type="submit">Remover treinador</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
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
            el.style.display = 'none';
        }
    });
}

function showDashboardContent() {
    document.querySelectorAll('.card-header-actions, .tabs, .tab-panel, .alert').forEach(el => {
        if (el) {
            el.style.display = '';
        }
    });
}

function showProfileScreen() {
    const dashboard = document.getElementById('dashboardCard');
    const profile = document.getElementById('profileScreen');
    const notifications = document.getElementById('notificationsScreen');

    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();

    if (profile) {
        profile.style.display = 'block';
        profile.classList.add('visible');
    }
    if (notifications) {
        notifications.style.display = 'none';
        notifications.classList.remove('visible');
    }
}

function showNotificationsScreen() {
    const dashboard = document.getElementById('dashboardCard');
    const profile = document.getElementById('profileScreen');
    const notifications = document.getElementById('notificationsScreen');

    if (dashboard) dashboard.style.display = 'block';
    hideDashboardContent();

    if (profile) {
        profile.style.display = 'none';
        profile.classList.remove('visible');
    }
    if (notifications) {
        notifications.style.display = 'block';
        notifications.classList.add('visible');
    }
}

function showDashboard() {
    const dashboard = document.getElementById('dashboardCard');
    const profile = document.getElementById('profileScreen');
    const notifications = document.getElementById('notificationsScreen');

    if (dashboard) dashboard.style.display = 'block';
    showDashboardContent();

    if (profile) {
        profile.style.display = 'none';
        profile.classList.remove('visible');
    }
    if (notifications) {
        notifications.style.display = 'none';
        notifications.classList.remove('visible');
    }
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

setTimeout(() => {
    document.querySelectorAll('.alert').forEach((alertEl) => {
        alertEl.classList.add('fade-out');
        setTimeout(() => {
            alertEl.style.display = 'none';
        }, 320);
    });
}, 5000);

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
</script>

</body>
</html>