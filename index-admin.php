<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ── Protecção da página ── */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador']) ||
    $_SESSION['tipo_utilizador'] !== 'admin_clube' ||
    !isset($_SESSION['id_clube'])
) {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];
$id_clube      = $_SESSION['id_clube'];

$erro = '';
$sucesso = '';
$activeTab = 'tab-info';

$listaEscaloesDisponiveis = [
    'S5','S6','S7','S8','S9','S10','S11','S12','S13','S14','S15',
    'S16','S17','S18','S19','S20','S21','S22','S23','Seniores'
];

$listaHierarquiasDisponiveis = range('A', 'Z');

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

        if ($nomeUtilizador === '' || $emailUtilizador === '' || $primeiroNome === '' || $ultimoNome === '') {
            $erro = 'Preenche todos os campos obrigatórios do perfil.';
        } elseif (!filter_var($emailUtilizador, FILTER_VALIDATE_EMAIL)) {
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

            if ($perfilEmailExiste) {
                $erro = 'Já existe outro utilizador com este email.';
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

                if (!$stmtUpdatePerfil->execute()) {
                    $erro = 'Erro ao guardar as alterações do perfil.';
                } else {
                    $sucesso = 'Perfil atualizado com sucesso.';
                }
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

    /* ── Editar informações do clube ── */
    if ($acao === 'editar_clube') {

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

    /* ── Criar escalão ── */
    if ($acao === 'criar_escalao') {

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

    /* ── Editar escalão ── */
    if ($acao === 'editar_escalao') {

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

    /* ── Criar treinador ── */
    if ($acao === 'criar_treinador') {

        $activeTab = 'tab-treinadores';

        $primeiroNome       = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome         = trim($_POST['ultimo_nome'] ?? '');
        $emailTreinador     = trim($_POST['email_treinador'] ?? '');
        $passwordTreinador  = $_POST['password_treinador'] ?? '';
        $idEquipaTreinador  = (int)($_POST['id_equipa'] ?? 0);

        if ($primeiroNome === '' || $ultimoNome === '' || $emailTreinador === '' || $passwordTreinador === '') {
            $erro = 'Preenche todos os campos obrigatórios do treinador.';
        } elseif (!filter_var($emailTreinador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Email do treinador inválido.';
        } else {

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
            } else {

                $nomeUtilizadorTreinador = strtolower(
                    preg_replace('/\s+/', '_', $primeiroNome . '_' . $ultimoNome . '_' . substr(md5($emailTreinador), 0, 6))
                );

                /*
                    A password vai em texto normal.
                    O trigger da tabela utilizador faz MD5 automaticamente.
                */
                $stmtCreateTreinador = $conn->prepare("
                    INSERT INTO utilizador
                    (nome_utilizador, email_utilizador, primeiro_nome, `último_nome`,
                     password, tipo_utilizador, id_clube)
                    VALUES (?, ?, ?, ?, ?, 'treinador', ?)
                ");

                if (!$stmtCreateTreinador) {
                    $erro = 'Erro na preparação da criação do treinador.';
                } else {
                    $stmtCreateTreinador->bind_param(
                        "sssssi",
                        $nomeUtilizadorTreinador,
                        $emailTreinador,
                        $primeiroNome,
                        $ultimoNome,
                        $passwordTreinador,
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

    /* ── Editar treinador ── */
    if ($acao === 'editar_treinador') {

        $activeTab = 'tab-treinadores';

        $idTreinador       = (int)($_POST['id_treinador'] ?? 0);
        $primeiroNome      = trim($_POST['primeiro_nome'] ?? '');
        $ultimoNome        = trim($_POST['ultimo_nome'] ?? '');
        $emailTreinador    = trim($_POST['email_treinador'] ?? '');
        $novaPassword      = $_POST['nova_password_treinador'] ?? '';
        $idEquipaTreinador = (int)($_POST['id_equipa'] ?? 0);

        if ($idTreinador <= 0) {
            $erro = 'Treinador inválido.';
        } elseif ($primeiroNome === '' || $ultimoNome === '' || $emailTreinador === '') {
            $erro = 'Preenche os dados obrigatórios do treinador.';
        } elseif (!filter_var($emailTreinador, FILTER_VALIDATE_EMAIL)) {
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
                } else {

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
                                SET primeiro_nome = ?,
                                    `último_nome` = ?,
                                    email_utilizador = ?,
                                    password = ?
                                WHERE id_utilizador = ?
                                  AND id_clube = ?
                                  AND tipo_utilizador = 'treinador'
                            ");

                            $stmtUpdateTreinador->bind_param(
                                "ssssii",
                                $primeiroNome,
                                $ultimoNome,
                                $emailTreinador,
                                $novaPassword,
                                $idTreinador,
                                $id_clube
                            );
                        } else {
                            $stmtUpdateTreinador = $conn->prepare("
                                UPDATE utilizador
                                SET primeiro_nome = ?,
                                    `último_nome` = ?,
                                    email_utilizador = ?
                                WHERE id_utilizador = ?
                                  AND id_clube = ?
                                  AND tipo_utilizador = 'treinador'
                            ");

                            $stmtUpdateTreinador->bind_param(
                                "sssii",
                                $primeiroNome,
                                $ultimoNome,
                                $emailTreinador,
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
        u.primeiro_nome,
        u.`último_nome`,
        u.email_utilizador,
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
    GROUP BY u.id_utilizador, u.primeiro_nome, u.`último_nome`, u.email_utilizador
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
    SELECT nome_utilizador, email_utilizador, telefone_utilizador,
           primeiro_nome, `último_nome`, data_nascimento
    FROM utilizador
    WHERE id_utilizador = ?
      AND id_clube = ?
    LIMIT 1
");
$stmtPerfil->bind_param("ii", $id_utilizador, $id_clube);
$stmtPerfil->execute();
$perfilUtilizador = $stmtPerfil->get_result()->fetch_assoc() ?: [];

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
    background: #f4f6fb;
    border-bottom: 1px solid #dfe3ee;
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px 0 18px;
    color: #1c2d4f;
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
    color: #4b5b7c;
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
    border-color: rgba(48, 71, 172, 0.9);
    box-shadow: 0 0 0 4px rgba(48, 71, 172, 0.12);
}

.profile-avatar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 220px;
    background: rgba(255,255,255,0.12);
    border: 3px solid rgba(42, 62, 154, 0.9);
    border-radius: 28px;
    padding: 18px 10px;
    box-shadow: inset 0 0 0 1px rgba(42, 62, 154, 0.2);
}

.profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 4px solid rgba(42, 62, 154, 0.9);
    background: #eaf0ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(42, 62, 154, 0.95);
    font-size: 56px;
    font-weight: 800;
    margin-bottom: 12px;
}

.profile-avatar-button {
    width: 100%;
    max-width: 220px;
    border: none;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(48, 71, 172, 0.95), rgba(42, 62, 154, 0.95));
    color: #fff;
    padding: 14px 18px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.profile-save-button {
    border: none;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(48, 71, 172, 0.95), rgba(42, 62, 154, 0.95));
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
        <span class="topbar-name"><?= htmlspecialchars($nomeClube) ?></span>
        <?php if ($siglaClube): ?>
            <span class="topbar-sigla"><?= htmlspecialchars($siglaClube) ?></span>
        <?php endif; ?>
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
                <form id="profileForm" method="post" action="">
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
                            <div class="profile-avatar">👤</div>
                            <button class="profile-avatar-button" type="button">Editar Foto de Perfil</button>
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
                        <img src="<?= $logoClube ?>" alt="Logótipo de <?= htmlspecialchars($nomeClube) ?>">
                    <?php else: ?>
                        <span class="club-logo-placeholder"><?= htmlspecialchars($siglaClube) ?></span>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ── Painel Escalões ── -->
        <div class="tab-panel <?= $activeTab === 'tab-escaloes' ? 'active' : '' ?>" id="tab-escaloes">

            <div class="tab-action-row">
                <h3>Escalões</h3>
                <button class="btn-create" type="button" onclick="openModal('modalCriarEscalao')">
                    + Criar Escalão
                </button>
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
                            <th class="actions-col">Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escaloesClube as $esc): ?>
                            <tr>
                                <td><?= htmlspecialchars($esc['escalão']) ?></td>
                                <td><?= htmlspecialchars($esc['hierarquia']) ?></td>
                                <td><?= htmlspecialchars($esc['época'] ?? 'Não definida') ?></td>
                                <td class="actions-cell">
                                    <button
                                        class="btn-row-edit"
                                        type="button"
                                        title="Editar escalão"
                                        onclick="openModal('modalEditarEscalao<?= (int)$esc['id_equipa'] ?>')"
                                    >
                                        ✎
                                    </button>
                                </td>
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
                <button class="btn-create" type="button" onclick="openModal('modalCriarTreinador')">
                    + Criar Treinador
                </button>
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
                            <th>Email</th>
                            <th>Equipas associadas</th>
                            <th class="actions-col">Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treinadoresClube as $treinador): ?>
                            <tr>
                                <td><?= htmlspecialchars($treinador['primeiro_nome'] . ' ' . $treinador['último_nome']) ?></td>
                                <td><?= htmlspecialchars($treinador['email_utilizador']) ?></td>
                                <td>
                                    <?= $treinador['equipas']
                                        ? htmlspecialchars($treinador['equipas'])
                                        : '<span class="muted">Sem equipa</span>' ?>
                                </td>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- ══ MODAL EDITAR CLUBE ══ -->
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

<!-- ══ MODAL CRIAR ESCALÃO ══ -->
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

<!-- ══ MODAL CRIAR TREINADOR ══ -->
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
                    <label>Email</label>
                    <input type="email" name="email_treinador" required>
                </div>

                <div class="edit-group">
                    <label>Password inicial</label>
                    <input type="password" name="password_treinador" required>
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

<!-- ══ MODAIS EDITAR ESCALÕES ══ -->
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

<!-- ══ MODAIS EDITAR TREINADORES ══ -->
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
                    <label>Email</label>
                    <input
                        type="email"
                        name="email_treinador"
                        value="<?= htmlspecialchars($treinador['email_utilizador']) ?>"
                        required
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

function markNotificationAsRead(idNotificacao, row) {
    if (!idNotificacao || !row) return;

    const state = row.dataset.state || 'Nao Lida';
    if (state === 'Lida') return;

    const formData = new URLSearchParams();
    formData.append('acao', 'marcar_notificacao_lida');
    formData.append('id_notificacao', String(idNotificacao));

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: formData.toString()
    }).then(() => {
        row.dataset.state = 'Lida';
        row.classList.remove('unread');
        row.classList.add('read');
    }).catch(() => {
        row.classList.add('read');
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
            markNotificationAsRead(id, row);
        }
    });

    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const id = row.dataset.id;
            if (id) {
                markNotificationAsRead(id, row);
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