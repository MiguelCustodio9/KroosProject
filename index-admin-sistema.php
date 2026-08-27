<?php
session_start();
require_once __DIR__ . '/basedados.h';

/* ══════════════════════════════════
   Protecção da página
══════════════════════════════════ */
if (
    !isset($_SESSION['id_utilizador']) ||
    !isset($_SESSION['tipo_utilizador'])
) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['tipo_utilizador'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id_utilizador = $_SESSION['id_utilizador'];

/* ── View activa (menu escolhido na sidebar) ──
   Predefinido: Estatísticas, ao entrar sem parâmetro na URL ── */
$viewsValidas = [
    'utilizadores',
    'competicoes',
    'jogadores',
    'clubes',
    'notificacoes_gestao',
    'definicoes',
    'estatisticas'
];
$viewMode = $_GET['view'] ?? 'estatisticas';
if (!in_array($viewMode, $viewsValidas, true)) {
    $viewMode = 'estatisticas';
}

/* ── Formatação de datas para DD/MM/AAAA ── */
function formatarData($data, $comHora = false) {
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($data);
    if ($ts === false) return '-';
    return $comHora ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

/* ── Buscar perfil do utilizador (admin de sistema) ── */
$perfilUtilizador = [];
$stmtPerfil = $conn->prepare("
    SELECT nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador,
           primeiro_nome, `último_nome`, data_nascimento
    FROM utilizador
    WHERE id_utilizador = ?
    LIMIT 1
");
$stmtPerfil->bind_param("i", $id_utilizador);
$stmtPerfil->execute();
$perfilUtilizador = $stmtPerfil->get_result()->fetch_assoc() ?: [];

$fotoPerfilUtilizador = !empty($perfilUtilizador['foto_perfil'])
    ? 'data:image/png;base64,' . base64_encode($perfilUtilizador['foto_perfil'])
    : null;

$erro = '';
$sucesso = '';
if (isset($_SESSION['flash_sucesso'])) {
    $sucesso = $_SESSION['flash_sucesso'];
    unset($_SESSION['flash_sucesso']);
}
if (isset($_SESSION['flash_erro'])) {
    $erro = $_SESSION['flash_erro'];
    unset($_SESSION['flash_erro']);
}

/* ══════════════════════════════════
   AÇÕES POST (perfil / notificações / CRUD)
══════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    /* ══════════════════════════════════
       GESTÃO DE UTILIZADORES — todos os campos
    ══════════════════════════════════ */
    if ($acao === 'criar_utilizador') {
        $nome = trim($_POST['nome_utilizador'] ?? '');
        $email = trim($_POST['email_utilizador'] ?? '');
        $tipo = $_POST['tipo_utilizador'] ?? 'jogador';
        $pNome = trim($_POST['primeiro_nome'] ?? '');
        $uNome = trim($_POST['ultimo_nome'] ?? '');
        $telefone = trim($_POST['telefone_utilizador'] ?? '') ?: null;
        $dataNasc = trim($_POST['data_nascimento'] ?? '') ?: null;
        $tipoTreinador = trim($_POST['tipo_treinador'] ?? '') ?: null;
        $idClube = !empty($_POST['id_clube']) ? (int)$_POST['id_clube'] : null;
        $foto = (!empty($_FILES['foto_perfil']['tmp_name'])) ? file_get_contents($_FILES['foto_perfil']['tmp_name']) : null;

        $stmt = $conn->prepare("INSERT INTO utilizador (nome_utilizador, email_utilizador, tipo_utilizador, primeiro_nome, `último_nome`, telefone_utilizador, data_nascimento, tipo_treinador, id_clube, foto_perfil, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, MD5('123456'))");
        $stmt->bind_param("ssssssssis", $nome, $email, $tipo, $pNome, $uNome, $telefone, $dataNasc, $tipoTreinador, $idClube, $foto);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Utilizador criado com sucesso (Pass: 123456)!";
        else $_SESSION['flash_erro'] = "Erro ao criar utilizador: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=utilizadores"); exit;
    }

    if ($acao === 'editar_utilizador') {
        $id = (int)($_POST['id_utilizador'] ?? 0);
        $nome = trim($_POST['nome_utilizador'] ?? '');
        $email = trim($_POST['email_utilizador'] ?? '');
        $tipo = $_POST['tipo_utilizador'] ?? 'jogador';
        $pNome = trim($_POST['primeiro_nome'] ?? '');
        $uNome = trim($_POST['ultimo_nome'] ?? '');
        $telefone = trim($_POST['telefone_utilizador'] ?? '') ?: null;
        $dataNasc = trim($_POST['data_nascimento'] ?? '') ?: null;
        $tipoTreinador = trim($_POST['tipo_treinador'] ?? '') ?: null;
        $idClube = !empty($_POST['id_clube']) ? (int)$_POST['id_clube'] : null;

        if (!empty($_FILES['foto_perfil']['tmp_name'])) {
            $foto = file_get_contents($_FILES['foto_perfil']['tmp_name']);
            $stmt = $conn->prepare("UPDATE utilizador SET nome_utilizador=?, email_utilizador=?, tipo_utilizador=?, primeiro_nome=?, `último_nome`=?, telefone_utilizador=?, data_nascimento=?, tipo_treinador=?, id_clube=?, foto_perfil=? WHERE id_utilizador=?");
            $stmt->bind_param("ssssssssisi", $nome, $email, $tipo, $pNome, $uNome, $telefone, $dataNasc, $tipoTreinador, $idClube, $foto, $id);
        } else {
            $stmt = $conn->prepare("UPDATE utilizador SET nome_utilizador=?, email_utilizador=?, tipo_utilizador=?, primeiro_nome=?, `último_nome`=?, telefone_utilizador=?, data_nascimento=?, tipo_treinador=?, id_clube=? WHERE id_utilizador=?");
            $stmt->bind_param("ssssssssii", $nome, $email, $tipo, $pNome, $uNome, $telefone, $dataNasc, $tipoTreinador, $idClube, $id);
        }
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Utilizador atualizado!";
        else $_SESSION['flash_erro'] = "Erro ao editar utilizador: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=utilizadores"); exit;
    }

    if ($acao === 'eliminar_utilizador') {
        $id = (int)($_POST['id_utilizador'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM utilizador WHERE id_utilizador = ? AND id_utilizador <> ?");
        $stmt->bind_param("ii", $id, $id_utilizador);
        $stmt->execute();
        $_SESSION['flash_sucesso'] = "Utilizador eliminado!";
        header("Location: index-admin-sistema.php?view=utilizadores"); exit;
    }

    /* ══════════════════════════════════
       GESTÃO DE COMPETIÇÕES — todos os campos
    ══════════════════════════════════ */
    if ($acao === 'criar_competicao') {
        $idClube = (int)($_POST['id_clube'] ?? 0);
        $idEquipa = (int)($_POST['id_equipa'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $epoca = (int)($_POST['epoca'] ?? date('Y'));
        $estado = trim($_POST['estado'] ?? 'Agendada');
        $descricao = trim($_POST['descricao'] ?? '');

        $stmt = $conn->prepare("INSERT INTO competicoes_clube (id_clube, id_equipa, nome, tipo, epoca, estado, descricao) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iississ", $idClube, $idEquipa, $nome, $tipo, $epoca, $estado, $descricao);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Competição adicionada com sucesso!";
        else $_SESSION['flash_erro'] = "Erro ao adicionar competição: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=competicoes"); exit;
    }

    if ($acao === 'editar_competicao') {
        $id = (int)($_POST['competicao_id'] ?? 0);
        $idClube = (int)($_POST['id_clube'] ?? 0);
        $idEquipa = (int)($_POST['id_equipa'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $epoca = (int)($_POST['epoca'] ?? date('Y'));
        $estado = trim($_POST['estado'] ?? 'Agendada');
        $descricao = trim($_POST['descricao'] ?? '');

        $stmt = $conn->prepare("UPDATE competicoes_clube SET id_clube=?, id_equipa=?, nome=?, tipo=?, epoca=?, estado=?, descricao=? WHERE id_competicao=?");
        $stmt->bind_param("iississi", $idClube, $idEquipa, $nome, $tipo, $epoca, $estado, $descricao, $id);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Competição atualizada!";
        else $_SESSION['flash_erro'] = "Erro ao editar competição: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=competicoes"); exit;
    }

    if ($acao === 'eliminar_competicao') {
        $id = (int)($_POST['competicao_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM competicoes_clube WHERE id_competicao = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_sucesso'] = "Competição eliminada!";
        header("Location: index-admin-sistema.php?view=competicoes"); exit;
    }

    /* ══════════════════════════════════
       GESTÃO DE JOGADORES — todos os campos
    ══════════════════════════════════ */
    if ($acao === 'criar_jogador') {
        $nomeCompleto = trim($_POST['nome_completo'] ?? '');
        $alcunha = trim($_POST['alcunha_jogador'] ?? '') ?: null;
        $posPrincipal = $_POST['posicao_principal'] ?? 'Médio';
        $numFavorito = (int)($_POST['numero_favorito'] ?? 0);
        $posSecundaria = trim($_POST['posicao_secundaria'] ?? '') ?: null;
        $dataNasc = trim($_POST['data_nascimento'] ?? '') ?: null;
        $localNasc = trim($_POST['local_nascimento'] ?? '') ?: null;
        $nacionalidade = trim($_POST['nacionalidade'] ?? '') ?: null;
        $pePreferencial = trim($_POST['pe_preferencial'] ?? '') ?: null;
        $altura = !empty($_POST['altura']) ? (float)$_POST['altura'] : null;
        $peso = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
        $instagram = trim($_POST['instagram'] ?? '') ?: null;
        $facebook = trim($_POST['facebook'] ?? '') ?: null;
        $twitter = trim($_POST['twitter'] ?? '') ?: null;
        $idEquipa = (int)($_POST['equipa_id'] ?? 1);
        $foto = (!empty($_FILES['foto_jogador']['tmp_name'])) ? file_get_contents($_FILES['foto_jogador']['tmp_name']) : null;

        $stmt = $conn->prepare("INSERT INTO jogadores (nome_completo, alcunha_jogador, posição_principal, número_favorito, posição_secundária, data_nascimento, local_nascimento, nacionalidade, pé_preferencial, altura, peso, instagram, facebook, twitter, id_equipa, foto_jogador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssssddsssis", $nomeCompleto, $alcunha, $posPrincipal, $numFavorito, $posSecundaria, $dataNasc, $localNasc, $nacionalidade, $pePreferencial, $altura, $peso, $instagram, $facebook, $twitter, $idEquipa, $foto);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Jogador registado com sucesso!";
        else $_SESSION['flash_erro'] = "Erro ao registar jogador: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=jogadores"); exit;
    }

    if ($acao === 'editar_jogador') {
        $id = (int)($_POST['jogador_id'] ?? 0);
        $nomeCompleto = trim($_POST['nome_completo'] ?? '');
        $alcunha = trim($_POST['alcunha_jogador'] ?? '') ?: null;
        $posPrincipal = $_POST['posicao_principal'] ?? 'Médio';
        $numFavorito = (int)($_POST['numero_favorito'] ?? 0);
        $posSecundaria = trim($_POST['posicao_secundaria'] ?? '') ?: null;
        $dataNasc = trim($_POST['data_nascimento'] ?? '') ?: null;
        $localNasc = trim($_POST['local_nascimento'] ?? '') ?: null;
        $nacionalidade = trim($_POST['nacionalidade'] ?? '') ?: null;
        $pePreferencial = trim($_POST['pe_preferencial'] ?? '') ?: null;
        $altura = !empty($_POST['altura']) ? (float)$_POST['altura'] : null;
        $peso = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
        $instagram = trim($_POST['instagram'] ?? '') ?: null;
        $facebook = trim($_POST['facebook'] ?? '') ?: null;
        $twitter = trim($_POST['twitter'] ?? '') ?: null;
        $idEquipa = (int)($_POST['equipa_id'] ?? 1);

        if (!empty($_FILES['foto_jogador']['tmp_name'])) {
            $foto = file_get_contents($_FILES['foto_jogador']['tmp_name']);
            $stmt = $conn->prepare("UPDATE jogadores SET nome_completo=?, alcunha_jogador=?, posição_principal=?, número_favorito=?, posição_secundária=?, data_nascimento=?, local_nascimento=?, nacionalidade=?, pé_preferencial=?, altura=?, peso=?, instagram=?, facebook=?, twitter=?, id_equipa=?, foto_jogador=? WHERE id_jogador=?");
            $stmt->bind_param("sssisssssddsssisi", $nomeCompleto, $alcunha, $posPrincipal, $numFavorito, $posSecundaria, $dataNasc, $localNasc, $nacionalidade, $pePreferencial, $altura, $peso, $instagram, $facebook, $twitter, $idEquipa, $foto, $id);
        } else {
            $stmt = $conn->prepare("UPDATE jogadores SET nome_completo=?, alcunha_jogador=?, posição_principal=?, número_favorito=?, posição_secundária=?, data_nascimento=?, local_nascimento=?, nacionalidade=?, pé_preferencial=?, altura=?, peso=?, instagram=?, facebook=?, twitter=?, id_equipa=? WHERE id_jogador=?");
            $stmt->bind_param("sssisssssddsssii", $nomeCompleto, $alcunha, $posPrincipal, $numFavorito, $posSecundaria, $dataNasc, $localNasc, $nacionalidade, $pePreferencial, $altura, $peso, $instagram, $facebook, $twitter, $idEquipa, $id);
        }
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Jogador atualizado!";
        else $_SESSION['flash_erro'] = "Erro ao editar jogador: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=jogadores"); exit;
    }

    if ($acao === 'eliminar_jogador') {
        $id = (int)($_POST['jogador_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM jogadores WHERE id_jogador = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_sucesso'] = "Jogador removido!";
        header("Location: index-admin-sistema.php?view=jogadores"); exit;
    }

    /* ══════════════════════════════════
       GESTÃO DE CLUBES — todos os campos
    ══════════════════════════════════ */
    if ($acao === 'criar_clube') {
        $nome = trim($_POST['nome_clube'] ?? '');
        $sigla = trim($_POST['sigla'] ?? '');
        $dataFundacao = trim($_POST['data_fundacao'] ?? '') ?: null;
        $sedeMorada = trim($_POST['sede_morada'] ?? '') ?: null;
        $paisClube = trim($_POST['pais_clube'] ?? '') ?: null;
        $cidadeClube = trim($_POST['cidade_clube'] ?? '') ?: null;
        $telefoneClube = trim($_POST['telefone_clube'] ?? '') ?: null;
        $emailClube = trim($_POST['email_clube'] ?? '') ?: null;
        $websiteClube = trim($_POST['website_clube'] ?? '') ?: null;
        $presidenteClube = trim($_POST['presidente_clube'] ?? '') ?: null;
        $instagramClube = trim($_POST['instagram_clube'] ?? '') ?: null;
        $facebookClube = trim($_POST['facebook_clube'] ?? '') ?: null;
        $youtubeClube = trim($_POST['youtube_clube'] ?? '') ?: null;
        $twitterClube = trim($_POST['twitter_clube'] ?? '') ?: null;
        $tiktokClube = trim($_POST['tiktok_clube'] ?? '') ?: null;
        $codigoClube = trim($_POST['codigo_clube'] ?? '') ?: null;
        $cor = $_POST['cor'] ?? '#000000';
        $logotipo = (!empty($_FILES['logotipo']['tmp_name'])) ? file_get_contents($_FILES['logotipo']['tmp_name']) : null;

        $stmt = $conn->prepare("INSERT INTO clube (nome_clube, sigla, `data_fundação`, sede_morada, país_clube, cidade_clube, telefone_clube, email_clube, website_clube, presidente_clube, instagram_clube, facebook_clube, youtube_clube, twitter_clube, tiktok_clube, `código_clube`, cor, logotipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssssssss", $nome, $sigla, $dataFundacao, $sedeMorada, $paisClube, $cidadeClube, $telefoneClube, $emailClube, $websiteClube, $presidenteClube, $instagramClube, $facebookClube, $youtubeClube, $twitterClube, $tiktokClube, $codigoClube, $cor, $logotipo);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Clube criado com sucesso!";
        else $_SESSION['flash_erro'] = "Erro ao criar clube: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=clubes"); exit;
    }

    if ($acao === 'editar_clube') {
        $id = (int)($_POST['clube_id'] ?? 0);
        $nome = trim($_POST['nome_clube'] ?? '');
        $sigla = trim($_POST['sigla'] ?? '');
        $dataFundacao = trim($_POST['data_fundacao'] ?? '') ?: null;
        $sedeMorada = trim($_POST['sede_morada'] ?? '') ?: null;
        $paisClube = trim($_POST['pais_clube'] ?? '') ?: null;
        $cidadeClube = trim($_POST['cidade_clube'] ?? '') ?: null;
        $telefoneClube = trim($_POST['telefone_clube'] ?? '') ?: null;
        $emailClube = trim($_POST['email_clube'] ?? '') ?: null;
        $websiteClube = trim($_POST['website_clube'] ?? '') ?: null;
        $presidenteClube = trim($_POST['presidente_clube'] ?? '') ?: null;
        $instagramClube = trim($_POST['instagram_clube'] ?? '') ?: null;
        $facebookClube = trim($_POST['facebook_clube'] ?? '') ?: null;
        $youtubeClube = trim($_POST['youtube_clube'] ?? '') ?: null;
        $twitterClube = trim($_POST['twitter_clube'] ?? '') ?: null;
        $tiktokClube = trim($_POST['tiktok_clube'] ?? '') ?: null;
        $codigoClube = trim($_POST['codigo_clube'] ?? '') ?: null;
        $cor = $_POST['cor'] ?? '#000000';

        if (!empty($_FILES['logotipo']['tmp_name'])) {
            $logotipo = file_get_contents($_FILES['logotipo']['tmp_name']);
            $stmt = $conn->prepare("UPDATE clube SET nome_clube=?, sigla=?, `data_fundação`=?, sede_morada=?, país_clube=?, cidade_clube=?, telefone_clube=?, email_clube=?, website_clube=?, presidente_clube=?, instagram_clube=?, facebook_clube=?, youtube_clube=?, twitter_clube=?, tiktok_clube=?, `código_clube`=?, cor=?, logotipo=? WHERE id_clube=?");
            $stmt->bind_param("ssssssssssssssssssi", $nome, $sigla, $dataFundacao, $sedeMorada, $paisClube, $cidadeClube, $telefoneClube, $emailClube, $websiteClube, $presidenteClube, $instagramClube, $facebookClube, $youtubeClube, $twitterClube, $tiktokClube, $codigoClube, $cor, $logotipo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE clube SET nome_clube=?, sigla=?, `data_fundação`=?, sede_morada=?, país_clube=?, cidade_clube=?, telefone_clube=?, email_clube=?, website_clube=?, presidente_clube=?, instagram_clube=?, facebook_clube=?, youtube_clube=?, twitter_clube=?, tiktok_clube=?, `código_clube`=?, cor=? WHERE id_clube=?");
            $stmt->bind_param("sssssssssssssssssi", $nome, $sigla, $dataFundacao, $sedeMorada, $paisClube, $cidadeClube, $telefoneClube, $emailClube, $websiteClube, $presidenteClube, $instagramClube, $facebookClube, $youtubeClube, $twitterClube, $tiktokClube, $codigoClube, $cor, $id);
        }
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Clube atualizado!";
        else $_SESSION['flash_erro'] = "Erro ao editar clube: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=clubes"); exit;
    }

    if ($acao === 'eliminar_clube') {
        $id = (int)($_POST['clube_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM clube WHERE id_clube = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_sucesso'] = "Clube eliminado!";
        header("Location: index-admin-sistema.php?view=clubes"); exit;
    }

    /* ══════════════════════════════════
       GESTÃO DE NOTIFICAÇÕES — todos os campos
    ══════════════════════════════════ */
    if ($acao === 'criar_notificacao_sistema') {
        $dest = (int)($_POST['id_utilizador'] ?? 0);
        $idClube = !empty($_POST['id_clube']) ? (int)$_POST['id_clube'] : null;
        $tit = trim($_POST['titulo'] ?? '');
        $msg = trim($_POST['mensagem'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'sistema');

        $stmt = $conn->prepare("INSERT INTO notificacao (id_utilizador, id_clube, titulo, mensagem, tipo, estado) VALUES (?, ?, ?, ?, ?, 'Nao Lida')");
        $stmt->bind_param("iisss", $dest, $idClube, $tit, $msg, $tipo);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Notificação enviada!";
        else $_SESSION['flash_erro'] = "Erro ao enviar notificação: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=notificacoes_gestao"); exit;
    }

    if ($acao === 'editar_notificacao') {
        $id = (int)($_POST['id_notificacao'] ?? 0);
        $dest = (int)($_POST['id_utilizador'] ?? 0);
        $idClube = !empty($_POST['id_clube']) ? (int)$_POST['id_clube'] : null;
        $tit = trim($_POST['titulo'] ?? '');
        $msg = trim($_POST['mensagem'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'sistema');
        $estado = trim($_POST['estado'] ?? 'Nao Lida');

        $stmt = $conn->prepare("UPDATE notificacao SET id_utilizador=?, id_clube=?, titulo=?, mensagem=?, tipo=?, estado=? WHERE id_notificacao=?");
        $stmt->bind_param("iisssssi", $dest, $idClube, $tit, $msg, $tipo, $estado, $id);
        if ($stmt->execute()) $_SESSION['flash_sucesso'] = "Notificação atualizada!";
        else $_SESSION['flash_erro'] = "Erro ao editar notificação: " . $stmt->error;
        header("Location: index-admin-sistema.php?view=notificacoes_gestao"); exit;
    }

    if ($acao === 'eliminar_notificacao') {
        $id = (int)($_POST['id_notificacao'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM notificacao WHERE id_notificacao = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_sucesso'] = "Notificação eliminada!";
        header("Location: index-admin-sistema.php?view=notificacoes_gestao"); exit;
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

        if ($nomeUtilizador === '' || $primeiroNome === '' || $ultimoNome === '' || $emailUtilizador === '') {
            $erro = 'Preenche todos os campos obrigatórios do perfil.';
        } elseif (!filter_var($emailUtilizador, FILTER_VALIDATE_EMAIL)) {
            $erro = 'O email do perfil não é válido.';
        } else {
            $stmtCheckPerfilEmail = $conn->prepare("
                SELECT id_utilizador FROM utilizador
                WHERE email_utilizador = ? AND id_utilizador <> ?
                LIMIT 1
            ");
            $stmtCheckPerfilEmail->bind_param("si", $emailUtilizador, $id_utilizador);
            $stmtCheckPerfilEmail->execute();
            if ($stmtCheckPerfilEmail->get_result()->fetch_assoc()) {
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

            if (!$erro) {
                if ($novaFotoPerfil !== null) {
                    $stmtUpdatePerfil = $conn->prepare("
                        UPDATE utilizador
                        SET nome_utilizador = ?, foto_perfil = ?, email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''), primeiro_nome = ?,
                            `último_nome` = ?, data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                    ");
                    $stmtUpdatePerfil->bind_param(
                        "sssssssi",
                        $nomeUtilizador, $novaFotoPerfil, $emailUtilizador, $telefoneUtilizador,
                        $primeiroNome, $ultimoNome, $dataNascimento, $id_utilizador
                    );
                } else {
                    $stmtUpdatePerfil = $conn->prepare("
                        UPDATE utilizador
                        SET nome_utilizador = ?, email_utilizador = ?,
                            telefone_utilizador = NULLIF(?, ''), primeiro_nome = ?,
                            `último_nome` = ?, data_nascimento = NULLIF(?, '')
                        WHERE id_utilizador = ?
                    ");
                    $stmtUpdatePerfil->bind_param(
                        "ssssssi",
                        $nomeUtilizador, $emailUtilizador, $telefoneUtilizador,
                        $primeiroNome, $ultimoNome, $dataNascimento, $id_utilizador
                    );
                }

                if (!$stmtUpdatePerfil->execute()) {
                    $erro = 'Erro ao guardar as alterações do perfil.';
                } else {
                    $sucesso = 'Perfil atualizado com sucesso.';
                }
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
                SET estado = ?, lida_em = CASE WHEN ? = 'Lida' THEN NOW() ELSE NULL END
                WHERE id_notificacao = ? AND id_utilizador = ?
            ");
            $stmtMarcaLida->bind_param("ssii", $estadoNotificacao, $estadoNotificacao, $idNotificacao, $id_utilizador);
            $stmtMarcaLida->execute();
        }
        exit;
    }
}

/* ── Buscar notificações do utilizador ── */
$notificacoesUtilizador = [];
$stmtNotificacoes = $conn->prepare("
    SELECT id_notificacao, titulo, mensagem, tipo, estado, criada_em, lida_em, link_acao
    FROM notificacao
    WHERE id_utilizador = ?
    ORDER BY criada_em ASC
    LIMIT 20
");
$stmtNotificacoes->bind_param("i", $id_utilizador);
$stmtNotificacoes->execute();
$resNotificacoes = $stmtNotificacoes->get_result();
while ($row = $resNotificacoes->fetch_assoc()) {
    $notificacoesUtilizador[] = $row;
}

/* ── Menus de gestão (sidebar) ── */
$menusGestao = [
    'utilizadores'          => ['label' => 'Gestão de Utilizadores', 'icon' => 'assets/user.png'],
    'competicoes'           => ['label' => 'Gestão de Competições',  'icon' => 'assets/campeonato.png'],
    'jogadores'              => ['label' => 'Gestão de Jogadores',    'icon' => 'assets/soccer-player.png'],
    'clubes'                 => ['label' => 'Gestão de Clubes',       'icon' => 'assets/clube.png'],
    'notificacoes_gestao'    => ['label' => 'Gestão de Notificações', 'icon' => 'assets/mensagens.png'],
    'definicoes'             => ['label' => 'Definições Gerais',      'icon' => 'assets/settings.png'],
];

/* ── Consultas para os Ecrãs da Plataforma Kroos ── */

// 0. Lista de Equipas (para selects e para mostrar nome em vez de ID)
$listaEquipas = $conn->query("
    SELECT id_equipa, escalão
    FROM equipa
    ORDER BY escalão ASC
")->fetch_all(MYSQLI_ASSOC);

// 1. Gestão de Utilizadores
$listaUtilizadores = $conn->query("
    SELECT 
        u.id_utilizador,
        u.nome_utilizador, 
        u.foto_perfil,
        u.email_utilizador,
        u.telefone_utilizador,
        u.primeiro_nome, 
        u.último_nome,
        u.data_nascimento,
        u.tipo_utilizador,
        u.tipo_treinador,
        u.id_clube
    FROM utilizador u
    ORDER BY u.id_utilizador ASC
")->fetch_all(MYSQLI_ASSOC);

// 2. Lista de Competições — com o nome do clube
$listaCompeticoes = $conn->query("
    SELECT 
        c.id_competicao, 
        c.id_clube, 
        c.id_equipa, 
        c.nome,
        c.tipo,
        c.epoca,
        c.estado,
        c.descricao,
        cl.nome_clube
    FROM competicoes_clube c 
    LEFT JOIN clube cl ON c.id_clube = cl.id_clube
    ORDER BY c.id_competicao ASC
")->fetch_all(MYSQLI_ASSOC);

// 3. Lista de Jogadores e Escalão da Equipa
$listaJogadores = $conn->query("
    SELECT 
        j.id_jogador, 
        j.nome_completo, 
        j.alcunha_jogador,
        j.posição_principal, 
        j.número_favorito, 
        j.posição_secundária,
        j.data_nascimento,
        j.local_nascimento,
        j.nacionalidade,
        j.foto_jogador,
        j.pé_preferencial,
        j.altura,
        j.peso,
        j.instagram,
        j.facebook,
        j.twitter,
        j.id_equipa,
        e.escalão 
    FROM jogadores j 
    LEFT JOIN equipa e ON j.id_equipa = e.id_equipa 
    ORDER BY j.id_jogador ASC
")->fetch_all(MYSQLI_ASSOC);

// 4. Lista de Clubes
$listaClubes = $conn->query("
    SELECT 
        c.id_clube, 
        c.nome_clube, 
        c.sigla, 
        c.logotipo,
        c.data_fundação,
        c.sede_morada,
        c.país_clube,
        c.cidade_clube,
        c.telefone_clube,
        c.email_clube,
        c.website_clube,
        c.presidente_clube,
        c.instagram_clube,
        c.facebook_clube,
        c.youtube_clube,
        c.twitter_clube,
        c.tiktok_clube,
        c.código_clube,
        c.cor 
    FROM clube c
    ORDER BY c.id_clube ASC
")->fetch_all(MYSQLI_ASSOC);

// 5. Notificações de Gestão / Sistema — com nome do destinatário e do clube
$listaNotifGestao = $conn->query("
    SELECT 
        n.id_notificacao, 
        n.id_clube,
        cl.nome_clube,
        u.id_utilizador, 
        u.nome_utilizador,
        n.titulo, 
        n.mensagem, 
        n.tipo,
        n.estado, 
        n.criada_em,
        n.lida_em
    FROM notificacao n 
    INNER JOIN utilizador u ON n.id_utilizador = u.id_utilizador 
    LEFT JOIN clube cl ON n.id_clube = cl.id_clube
    ORDER BY n.criada_em ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos Admin de Sistema</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

.kroos-table-wrap { width: 100%; overflow-x: auto; margin-top: 15px; }
.kroos-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
.kroos-table th, .kroos-table td { padding: 12px 16px; border-bottom: 1px solid #eaeaea; }
.kroos-table th { background: #fafafa; font-weight: 700; text-transform: uppercase; font-size: 11px; }
.btn-delete { background: #b00020; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
.btn-edit { background: #228B22; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-right: 6px; }
.kroos-form-inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; background: #fafafa; padding: 16px; border-radius: 12px; border: 1px solid #eaeaea; }
.kroos-form-inline input, .kroos-form-inline select, .kroos-form-inline textarea { padding: 8px 12px; border-radius: 8px; border: 1px solid #ccc; font-size: 14px; font-family: 'Inter', sans-serif; }
.kroos-form-inline textarea { resize: vertical; min-height: 40px; grid-column: 1 / -1; }


:root {
    --club: #000000;
    --sidebar-w: 68px;
    --topbar-h: 64px;
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow-y: auto;
}

body { background: #ffffff; color: #000000; }

body.layout-locked { overflow: hidden; }


.td-acoes {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}
.td-acoes .btn-edit,
.td-acoes .btn-delete {
    margin-right: 0;
    width: 100%;
    text-align: center;
}
.td-acoes form {
    width: 100%;
}

/* ══════════════════════════════════
   TOP BAR
══════════════════════════════════ */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    background: #ffffff;
    border-bottom: 1px solid #eaeaea;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 calc(var(--sidebar-w) + 20px);
    z-index: 100;
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
    background: #f3f3f3;
    border: 1px solid #e2e2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.topbar-club-logo--placeholder {
    color: #000;
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
    color: #000;
    line-height: 1.2;
}

.topbar-sigla {
    font-size: 12px;
    font-weight: 500;
    color: #6b7280;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-logo {
    height: 26px;
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
    background: #000;
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
    background: #ffffff;
    border: 1px solid #eaeaea;
    display: none;
    flex-direction: column;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(0,0,0,.12);
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
    color: #000;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}

.user-dropdown a:hover {
    background: #f5f5f5;
}

.user-dropdown a.logout-link {
    color: #b00020;
    font-weight: 600;
}

.user-dropdown a.logout-link:hover {
    background: #fff1f1;
}

/* ══════════════════════════════════
   SIDEBAR (tamanho e estilo inalterados)
══════════════════════════════════ */
.sidebar {
    position: fixed;
    top: var(--topbar-h);
    left: 0;
    width: var(--sidebar-w);
    height: calc(100vh - var(--topbar-h));
    background: #ffffff;
    border-right: 1px solid #eaeaea;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    padding: 16px 0;
    z-index: 99;
    transition: width .22s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}

.sidebar:hover { width: 230px; }

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 10px 0;
    color: #6b7280;
    text-decoration: none;
    white-space: nowrap;
    font-size: 13.5px;
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
    background: #f5f5f5;
    color: #000;
    border-left-color: #000;
    font-weight: 700;
}

.sidebar a .side-icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.sidebar a .side-icon img {
    filter: brightness(0) saturate(100%);
    opacity: .55;
    transition: opacity .15s;
}

.sidebar a:hover .side-icon img,
.sidebar a.active .side-icon img {
    opacity: 1;
}

/* Rótulo da sidebar — mantém a mesma largura/estilo; texto cortado
   ganha um efeito de deslize horizontal em vez de ficar cortado */
.sidebar a span.side-label {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: opacity .18s, width .22s;
    display: inline-block;
    white-space: nowrap;
    vertical-align: bottom;
}

.sidebar:hover a span.side-label {
    opacity: 1;
    width: auto;
    max-width: 150px;
}

.sidebar a span.side-label.marquee-active {
    animation: sidebarLabelMarquee 3.2s ease-in-out infinite;
}

@keyframes sidebarLabelMarquee {
    0%, 15%   { transform: translateX(0); }
    45%, 60%  { transform: translateX(var(--marquee-distance, 0)); }
    90%, 100% { transform: translateX(0); }
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

body.layout-locked .main {
    height: calc(100vh - var(--topbar-h));
    min-height: 0;
    overflow: hidden;
}

.sidebar:hover ~ .main { margin-left: 230px; }

/* ══════════════════════════════════
   CARD
══════════════════════════════════ */
.card {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 20px;
    padding: 28px 32px 36px;
    position: relative;
}

/* ── Painel de perfil ── */
.profile-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #eaeaea;
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.profile-shell.visible { display: block; }

.profile-header {
    background: #000;
    border-bottom: 1px solid #000;
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

.profile-panel {
    background: #fafafa;
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
    color: #000;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.profile-field .profile-input {
    width: 100%;
    min-height: 56px;
    padding: 12px 16px;
    border-radius: 16px;
    border: 1px solid #dedede;
    background: #fff;
    color: #1f2b3d;
    font-weight: 600;
    font-size: 15px;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.profile-field .profile-input:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 4px rgba(0,0,0,0.06);
}

.profile-avatar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 220px;
    background: #fafafa;
    border: 3px solid #000;
    border-radius: 28px;
    padding: 18px 10px;
}

.profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 4px solid #000;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
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
    background: #000;
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
    accent-color: #000;
}

.profile-save-button {
    border: none;
    border-radius: 16px;
    background: #000;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    padding: 14px 22px;
    cursor: pointer;
    transition: opacity .15s ease;
}

.profile-save-button:hover { opacity: .85; }

.profile-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
}

@media (max-width: 820px) {
    .profile-content { grid-template-columns: 1fr; }
    .profile-grid { grid-template-columns: 1fr; }
}

/* ── Painel de notificações ── */
.notifications-shell {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #eaeaea;
    overflow: hidden;
    margin-bottom: 28px;
    display: none;
}

.notifications-shell.visible { display: block; }

.notifications-card { background: #fafafa; }

.notifications-tabs {
    display: flex;
    gap: 0;
    padding: 0;
    border-bottom: 1px solid #000;
    background: #000;
}

.notification-tab {
    border: none;
    border-left: 3px solid transparent;
    background: transparent;
    padding: 14px 22px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(255,255,255,.7);
    cursor: pointer;
    transition: background .15s, color .15s, border-color .15s, padding-left .15s;
}

.notification-tab:hover,
.notification-tab.active {
    background: rgba(255,255,255,.12);
    color: #fff;
    border-left-color: #fff;
    padding-left: 26px;
}

.notifications-list { background: #fafafa; padding: 0 0 8px; }

.notification-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #eee;
    background: transparent;
}

.notification-row:last-child { border-bottom: none; }

.notification-row.unread { background: #fff; }

.notification-row.read {
    background: #f0f0f0;
    border-left: 3px solid #000;
}

.notification-label { font-size: 16px; font-weight: 500; color: #1f2a37; }

.notification-row.read .notification-label { color: #7b8596; font-weight: 400; }

.notification-check {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #000;
    background: #fff;
    color: #000;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.notification-row.read .notification-check { background: #000; border-color: #000; color: #fff; }
.notification-row.unread .notification-check { background: #fff; border-color: #000; color: #000; }

/* ══════════════════════════════════
   ECRÃS DE GESTÃO
══════════════════════════════════ */
.screen-shell {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 20px;
    padding: 28px 32px 36px;
    display: none;
}
.screen-shell.visible { display: block; }

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
    color: #000;
    margin-bottom: 5px;
}

.trainer-page-subtitle { font-size: 13px; color: #6b7280; }

.btn-create {
    border: none;
    background: #000;
    color: #fff;
    padding: 11px 18px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
}

.btn-create:hover { opacity: .85; transform: translateY(-1px); }

.empty-state {
    padding: 70px 20px;
    text-align: center;
    color: #9aa0ae;
    font-size: 14px;
    border: 1.5px dashed #e2e2e2;
    border-radius: 18px;
}

.empty-state svg { width: 48px; height: 48px; margin-bottom: 14px; opacity: .35; }

.empty-state strong { display: block; color: #444; font-size: 15px; margin-bottom: 6px; }

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

.alert-error { background: #fff1f1; color: #b00020; border: 1px solid #ffd0d0; }
.alert-success { background: #f2f2f2; color: #000; border: 1px solid #ddd; }

@media (max-width: 760px) {
    .messages-shell.visible { grid-template-columns: 1fr; }
}

/* ══════════════════════════════════
   ESTATÍSTICAS
══════════════════════════════════ */
.stats-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.stats-title {
    font-size: 22px;
    font-weight: 800;
    color: #000;
    margin-bottom: 5px;
}

.stats-subtitle {
    font-size: 13px;
    color: #6b7280;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 26px;
}

.stat-card {
    background: #fafafa;
    border: 1px solid #eaeaea;
    border-radius: 18px;
    padding: 20px 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color .15s ease, transform .15s ease, background .15s ease;
}

.stat-card:hover {
    border-color: #000;
    background: #fff;
    transform: translateY(-2px);
}

.stat-card .stat-value {
    font-size: 30px;
    font-weight: 800;
    color: #000;
    line-height: 1;
}

.stat-card .stat-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.stats-section-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 8px 0 16px;
}

.stats-section-divider span {
    font-size: 12px;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    letter-spacing: .05em;
    white-space: nowrap;
}

.stats-section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #eaeaea;
}

.stat-card.stat-card--dark {
    background: #000;
    border-color: #000;
}

.stat-card.stat-card--dark .stat-value { color: #fff; }
.stat-card.stat-card--dark .stat-label { color: rgba(255,255,255,.65); }

@media (max-width: 480px) {
    .stat-card .stat-value { font-size: 24px; }
}

/* ══════════════════════════════════
   MODAIS DE EDIÇÃO
══════════════════════════════════ */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    display: none; align-items: flex-start; justify-content: center; z-index: 1000; padding: 40px 20px;
    overflow-y: auto;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #fff; border-radius: 18px; padding: 26px; width: 100%; max-width: 640px;
}
.modal-box h3 { margin-bottom: 16px; font-size: 18px; font-weight: 800; }
.modal-box form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.modal-box input, .modal-box select, .modal-box textarea { padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; font-size: 14px; width: 100%; font-family: 'Inter', sans-serif; }
.modal-box textarea { grid-column: 1 / -1; resize: vertical; min-height: 60px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; grid-column: 1 / -1; }
.btn-cancel { background: #eee; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; }
.btn-save { background: #000; color: #fff; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; }
.modal-field-full { grid-column: 1 / -1; }

/* ══════════════════════════════════
   PESQUISA E ORDENAÇÃO DE TABELAS
══════════════════════════════════ */
.table-search {
    padding: 9px 14px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
    width: 260px;
    margin-bottom: 12px;
}
.kroos-table th {
    cursor: pointer;
    user-select: none;
    position: relative;
}
.kroos-table th.th-asc::after { content: ' ▲'; font-size: 10px; }
.kroos-table th.th-desc::after { content: ' ▼'; font-size: 10px; }

</style>
</head>
<body>

<!-- ══ TOP BAR ══ -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-club-logo">
            <span class="topbar-club-logo--placeholder">SYS</span>
        </div>

        <div class="topbar-club-text">
            <span class="topbar-name">Kroos</span>
            <span class="topbar-sigla">Admin de Sistema</span>
        </div>
    </div>

    <div class="topbar-right">
        <img src="assets/kroos-logo.png" class="topbar-logo" alt="Kroos">

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
    <?php foreach ($menusGestao as $viewKey => $menu): ?>
    <a href="#" data-view="<?= $viewKey ?>" class="<?= $viewMode === $viewKey ? 'active' : '' ?>" onclick="event.preventDefault(); showScreen('<?= $viewKey ?>');">
        <span class="side-icon">
            <img src="<?= htmlspecialchars($menu['icon']) ?>" alt="" style="width:20px;height:20px;object-fit:contain;" onerror="this.style.display='none';">
        </span>
        <span class="side-label"><?= htmlspecialchars($menu['label']) ?></span>
    </a>
    <?php endforeach; ?>
    <a href="#" data-view="estatisticas" class="<?= $viewMode === 'estatisticas' ? 'active' : '' ?>" onclick="event.preventDefault(); showScreen('estatisticas');">
        <span class="side-icon">
            <img src="assets/graph.png" alt="" style="width:20px;height:20px;object-fit:contain;" onerror="this.style.display='none';">
        </span>
        <span class="side-label">Estatísticas</span>
    </a>
</div>

<!-- ══ MAIN ══ -->
<div class="main">
    <div class="card" id="dashboardCard">

        <!-- ══ PERFIL ══ -->
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

        <!-- ══ NOTIFICAÇÕES ══ -->
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

        <!-- ══ ESTATÍSTICAS ══ -->
        <div class="screen-shell" id="screen-estatisticas">
            <?php

            $query = "
                SELECT 
                    (SELECT COUNT(*) FROM competicoes_clube) as total_competicoes,
                    (SELECT COUNT(*) FROM jogadores) as total_jogadores,
                    (SELECT COUNT(*) FROM clube) as total_clubes,
                    (SELECT COUNT(*) FROM equipa) as total_equipas,
                    (SELECT COUNT(*) FROM jogos_clube) as total_jogos,
                    (SELECT COUNT(*) FROM estádio) as total_estadios,
                    (SELECT COUNT(*) FROM treino) as total_treinos,
                    (SELECT COUNT(*) FROM utilizador) as total_utilizadores,
                    (SELECT COUNT(*) FROM utilizador WHERE tipo_utilizador = 'treinador') as total_treinadores,
                    (SELECT COUNT(*) FROM utilizador WHERE tipo_utilizador = 'admin') as total_admin_sistema,
                    (SELECT COUNT(*) FROM utilizador WHERE tipo_utilizador = 'admin_clube') as total_admin_clubes
            ";

            $result = mysqli_query($conn, $query);

            if ($result) {
                $estatisticas = mysqli_fetch_assoc($result);
            } else {
                echo "Erro ao buscar estatísticas: " . mysqli_error($conn);
                $estatisticas = [];
            }
            ?>

            <div class="estatisticas-container">
                <div class="stats-header">
                    <div>
                        <h2 class="stats-title">Estatísticas Globais do Sistema</h2>
                        <p class="stats-subtitle">Vista geral dos números registados em toda a plataforma Kroos.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_clubes'] ?? 0 ?></span>
                        <span class="stat-label">Clubes</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_equipas'] ?? 0 ?></span>
                        <span class="stat-label">Equipas</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_jogadores'] ?? 0 ?></span>
                        <span class="stat-label">Jogadores</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_treinadores'] ?? 0 ?></span>
                        <span class="stat-label">Treinadores</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_competicoes'] ?? 0 ?></span>
                        <span class="stat-label">Competições</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_jogos'] ?? 0 ?></span>
                        <span class="stat-label">Jogos</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_treinos'] ?? 0 ?></span>
                        <span class="stat-label">Treinos</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_estadios'] ?? 0 ?></span>
                        <span class="stat-label">Estádios</span>
                    </div>
                </div>

                <div class="stats-section-divider"><span>Utilizadores</span></div>

                <div class="stats-grid">
                    <div class="stat-card stat-card--dark">
                        <span class="stat-value"><?= $estatisticas['total_utilizadores'] ?? 0 ?></span>
                        <span class="stat-label">Total Registados</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_admin_clubes'] ?? 0 ?></span>
                        <span class="stat-label">Admins de Clube</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?= $estatisticas['total_admin_sistema'] ?? 0 ?></span>
                        <span class="stat-label">Admins de Sistema</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ GESTÃO DE UTILIZADORES ══ -->
        <div class="screen-shell" id="screen-utilizadores">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Gestão de Utilizadores</h2>
                    <p class="trainer-page-subtitle">Visualiza e gere os utilizadores registados na plataforma Kroos.</p>
                </div>
            </div>

            <form method="post" class="kroos-form-inline" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="criar_utilizador">
                <input type="text" name="nome_utilizador" placeholder="Nome de Utilizador" required>
                <input type="email" name="email_utilizador" placeholder="Email" required>
                <input type="text" name="primeiro_nome" placeholder="Primeiro Nome" required>
                <input type="text" name="ultimo_nome" placeholder="Último Nome" required>
                <input type="tel" name="telefone_utilizador" placeholder="Telefone">
                <input type="date" name="data_nascimento">
                <select name="tipo_utilizador">
                    <option value="jogador">Jogador</option>
                    <option value="treinador">Treinador</option>
                    <option value="admin_clube">Admin Clube</option>
                    <option value="admin">Admin Sistema</option>
                </select>
                <input type="text" name="tipo_treinador" placeholder="Tipo de Treinador (opcional)">
                <select name="id_clube">
                    <option value="">Sem Clube</option>
                    <?php foreach ($listaClubes as $cl): ?>
                        <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="foto_perfil" accept="image/*">
                <button type="submit" class="btn-create">+ Adicionar Utilizador</button>
            </form>

            <input type="search" class="table-search" placeholder="Pesquisar utilizadores..." oninput="filtrarTabela('tabela-utilizadores', this.value)">

            <div class="kroos-table-wrap">
                <table class="kroos-table" id="tabela-utilizadores">
                    <thead>
                        <tr>
                            <th onclick="ordenarTabela('tabela-utilizadores',0)">ID</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',1)">Utilizador</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',2)">Nome Completo</th>
                            <th>Foto de Perfil</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',4)">Telefone</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',5)">Data de Nascimento</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',6)">Email</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',7)">Tipo de Utilizador</th>
                            <th onclick="ordenarTabela('tabela-utilizadores',8)">Tipo de Treinador</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaUtilizadores as $u): ?>
                        <tr>
                            <td><?= $u['id_utilizador'] ?></td>
                            <td><strong><?= htmlspecialchars($u['nome_utilizador']) ?></strong></td>
                            <td><?= htmlspecialchars(($u['primeiro_nome'] ?? '').' '.($u['último_nome'] ?? '')) ?></td>
                            <td>
                                <img src="caminho/para/pasta/<?= htmlspecialchars($u['foto_perfil'] ?? '') ?>" alt="Foto de Perfil" style="width: 40px; height: 40px; object-fit: cover;">
                            </td>
                            <td><?= htmlspecialchars($u['telefone_utilizador'] ?? '') ?></td>
                            <td><?= formatarData($u['data_nascimento']) ?></td>
                            <td><?= htmlspecialchars($u['email_utilizador']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($u['tipo_utilizador']) ?></span></td>
                            <td><span class="badge"><?= htmlspecialchars($u['tipo_treinador'] ?? '') ?></span></td>
                            <td class="td-acoes">
                                <button class="btn-edit" type="button" onclick='editarUtilizador(<?= json_encode([
                                    "id" => $u['id_utilizador'],
                                    "nome" => $u['nome_utilizador'],
                                    "email" => $u['email_utilizador'],
                                    "pnome" => $u['primeiro_nome'],
                                    "unome" => $u['último_nome'],
                                    "tipo" => $u['tipo_utilizador'],
                                    "tipoTreinador" => $u['tipo_treinador'],
                                    "telefone" => $u['telefone_utilizador'],
                                    "data" => $u['data_nascimento'],
                                    "idClube" => $u['id_clube']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar utilizador?');">
                                    <input type="hidden" name="acao" value="eliminar_utilizador">
                                    <input type="hidden" name="id_utilizador" value="<?= $u['id_utilizador'] ?>">
                                    <button class="btn-delete" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ GESTÃO DE COMPETIÇÕES ══ -->
        <div class="screen-shell" id="screen-competicoes">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Gestão de Competições</h2>
                    <p class="trainer-page-subtitle">Configuração e listagem das competições desportivas.</p>
                </div>
            </div>

            <form method="post" class="kroos-form-inline">
                <input type="hidden" name="acao" value="criar_competicao">
                <select name="id_clube" required>
                    <?php foreach ($listaClubes as $cl): ?>
                        <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="id_equipa" required>
                    <?php foreach ($listaEquipas as $eq): ?>
                        <option value="<?= $eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="nome" placeholder="Nome da Competição" required>
                <input type="text" name="tipo" placeholder="Tipo (ex: Liga, Torneio)">
                <input type="number" name="epoca" placeholder="Época (Ano)" value="<?= date('Y') ?>" required>
                <select name="estado">
                    <option value="Agendada">Agendada</option>
                    <option value="Em Curso">Em Curso</option>
                    <option value="Terminada">Terminada</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
                <textarea name="descricao" placeholder="Descrição"></textarea>
                <button type="submit" class="btn-create">+ Adicionar Competição</button>
            </form>

            <input type="search" class="table-search" placeholder="Pesquisar competições..." oninput="filtrarTabela('tabela-competicoes', this.value)">

            <div class="kroos-table-wrap">
                <table class="kroos-table" id="tabela-competicoes">
                    <thead>
                        <tr>
                            <th onclick="ordenarTabela('tabela-competicoes',0)">ID</th>
                            <th onclick="ordenarTabela('tabela-competicoes',1)">Competição</th>
                            <th onclick="ordenarTabela('tabela-competicoes',2)">Época</th>
                            <th onclick="ordenarTabela('tabela-competicoes',3)">Clube</th>
                            <th onclick="ordenarTabela('tabela-competicoes',4)">Tipo</th>
                            <th onclick="ordenarTabela('tabela-competicoes',5)">Estado</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaCompeticoes as $c): ?>
                        <tr>
                            <td><?= $c['id_competicao'] ?></td>
                            <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                            <td><?= htmlspecialchars($c['epoca']) ?></td>
                            <td><?= htmlspecialchars($c['nome_clube'] ?? $c['id_clube']) ?></td>
                            <td><?= htmlspecialchars($c['tipo']) ?></td>
                            <td><?= htmlspecialchars($c['estado']) ?></td>
                            <td><?= htmlspecialchars($c['descricao']) ?></td>
                            <td class="td-acoes">
                                <button class="btn-edit" type="button" onclick='editarCompeticao(<?= json_encode([
                                    "id" => $c['id_competicao'],
                                    "clube" => $c['id_clube'],
                                    "equipa" => $c['id_equipa'],
                                    "nome" => $c['nome'],
                                    "tipo" => $c['tipo'],
                                    "epoca" => $c['epoca'],
                                    "estado" => $c['estado'],
                                    "descricao" => $c['descricao']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar competição?');">
                                    <input type="hidden" name="acao" value="eliminar_competicao">
                                    <input type="hidden" name="competicao_id" value="<?= $c['id_competicao'] ?>">
                                    <button class="btn-delete" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ GESTÃO DE JOGADORES ══ -->
        <div class="screen-shell" id="screen-jogadores">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Gestão de Jogadores</h2>
                    <p class="trainer-page-subtitle">Registo dos atletas dos planteis do Kroos.</p>
                </div>
            </div>

            <form method="post" class="kroos-form-inline" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="criar_jogador">
                <input type="text" name="nome_completo" placeholder="Nome Completo do Atleta" required>
                <input type="text" name="alcunha_jogador" placeholder="Alcunha">
                <input type="number" name="numero_favorito" placeholder="Dorsal" required>
                <select name="posicao_principal">
                    <option value="Guarda-Redes">Guarda-Redes</option>
                    <option value="Defesa">Defesa</option>
                    <option value="Médio">Médio</option>
                    <option value="Avançado">Avançado</option>
                </select>
                <select name="posicao_secundaria">
                    <option value="">Sem Posição Secundária</option>
                    <option value="Guarda-Redes">Guarda-Redes</option>
                    <option value="Defesa">Defesa</option>
                    <option value="Médio">Médio</option>
                    <option value="Avançado">Avançado</option>
                </select>
                <input type="date" name="data_nascimento">
                <input type="text" name="local_nascimento" placeholder="Local de Nascimento">
                <input type="text" name="nacionalidade" placeholder="Nacionalidade">
                <select name="pe_preferencial">
                    <option value="">Pé Preferencial</option>
                    <option value="Direito">Direito</option>
                    <option value="Esquerdo">Esquerdo</option>
                    <option value="Ambidestro">Ambidestro</option>
                </select>
                <input type="number" step="0.01" name="altura" placeholder="Altura (m)">
                <input type="number" step="0.1" name="peso" placeholder="Peso (kg)">
                <input type="text" name="instagram" placeholder="Instagram">
                <input type="text" name="facebook" placeholder="Facebook">
                <input type="text" name="twitter" placeholder="Twitter">
                <select name="equipa_id" required>
                    <?php foreach ($listaEquipas as $eq): ?>
                        <option value="<?= $eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="foto_jogador" accept="image/*">
                <button type="submit" class="btn-create">+ Adicionar Jogador</button>
            </form>

            <input type="search" class="table-search" placeholder="Pesquisar jogadores..." oninput="filtrarTabela('tabela-jogadores', this.value)">

            <div class="kroos-table-wrap">
                <table class="kroos-table" id="tabela-jogadores">
                    <thead>
                        <tr>
                            <th onclick="ordenarTabela('tabela-jogadores',0)">ID</th>
                            <th onclick="ordenarTabela('tabela-jogadores',1)">Nome Completo</th>
                            <th onclick="ordenarTabela('tabela-jogadores',2)">Alcunha</th>
                            <th>Foto de Jogador</th>
                            <th onclick="ordenarTabela('tabela-jogadores',4)">Dorsal</th>
                            <th onclick="ordenarTabela('tabela-jogadores',5)">Posição Principal</th>
                            <th onclick="ordenarTabela('tabela-jogadores',6)">Posição Secundária</th>
                            <th onclick="ordenarTabela('tabela-jogadores',7)">Data de Nascimento</th>
                            <th onclick="ordenarTabela('tabela-jogadores',8)">Local de Nascimento</th>
                            <th onclick="ordenarTabela('tabela-jogadores',9)">Nacionalidade</th>
                            <th onclick="ordenarTabela('tabela-jogadores',10)">Pé Preferencial</th>
                            <th onclick="ordenarTabela('tabela-jogadores',11)">Altura</th>
                            <th onclick="ordenarTabela('tabela-jogadores',12)">Peso</th>
                            <th>Instagram</th>
                            <th>Facebook</th>
                            <th>Twitter</th>
                            <th onclick="ordenarTabela('tabela-jogadores',16)">Equipa</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaJogadores as $j): ?>
                        <tr>
                            <td><?= $j['id_jogador'] ?></td>
                            <td><strong><?= htmlspecialchars($j['nome_completo']) ?></strong></td>
                            <td><?= htmlspecialchars($j['alcunha_jogador']) ?></td>
                            <td>
                                <img src="caminho/para/pasta/<?= htmlspecialchars($j['foto_jogador']) ?>" alt="Foto de Perfil" style="width: 40px; height: 40px; object-fit: cover;">
                            </td>
                            <td>#<?= htmlspecialchars($j['número_favorito']) ?></td>
                            <td><?= htmlspecialchars($j['posição_principal']) ?></td>
                            <td><?= htmlspecialchars($j['posição_secundária']) ?></td>
                            <td><?= formatarData($j['data_nascimento']) ?></td>
                            <td><?= htmlspecialchars($j['local_nascimento']) ?></td>
                            <td><?= htmlspecialchars($j['nacionalidade']) ?></td>
                            <td><?= htmlspecialchars($j['pé_preferencial']) ?></td>
                            <td><?= htmlspecialchars($j['altura']) ?> m</td>
                            <td><?= htmlspecialchars($j['peso']) ?> kg</td>
                            <td><?= htmlspecialchars($j['instagram']) ?></td>
                            <td><?= htmlspecialchars($j['facebook']) ?></td>
                            <td><?= htmlspecialchars($j['twitter']) ?></td>
                            <td><?= htmlspecialchars($j['escalão'] ?? $j['id_equipa']) ?></td>
                            <td class="td-acoes">
                                <button class="btn-edit" type="button" onclick='editarJogador(<?= json_encode([
                                    "id" => $j['id_jogador'],
                                    "nome" => $j['nome_completo'],
                                    "alcunha" => $j['alcunha_jogador'],
                                    "num" => $j['número_favorito'],
                                    "pos" => $j['posição_principal'],
                                    "posSec" => $j['posição_secundária'],
                                    "data" => $j['data_nascimento'],
                                    "local" => $j['local_nascimento'],
                                    "nacionalidade" => $j['nacionalidade'],
                                    "pe" => $j['pé_preferencial'],
                                    "altura" => $j['altura'],
                                    "peso" => $j['peso'],
                                    "instagram" => $j['instagram'],
                                    "facebook" => $j['facebook'],
                                    "twitter" => $j['twitter'],
                                    "equipa" => $j['id_equipa']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar jogador?');">
                                    <input type="hidden" name="acao" value="eliminar_jogador">
                                    <input type="hidden" name="jogador_id" value="<?= $j['id_jogador'] ?>">
                                    <button class="btn-delete" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ GESTÃO DE CLUBES ══ -->
        <div class="screen-shell" id="screen-clubes">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Gestão de Clubes</h2>
                    <p class="trainer-page-subtitle">Administração dos clubes associados.</p>
                </div>
            </div>

            <form method="post" class="kroos-form-inline" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="criar_clube">
                <input type="text" name="nome_clube" placeholder="Nome do Clube" required>
                <input type="text" name="sigla" placeholder="Sigla (ex: IPCB)" required>
                <input type="date" name="data_fundacao" placeholder="Data de Fundação">
                <input type="text" name="sede_morada" placeholder="Sede / Morada">
                <input type="text" name="pais_clube" placeholder="País">
                <input type="text" name="cidade_clube" placeholder="Cidade">
                <input type="tel" name="telefone_clube" placeholder="Telefone">
                <input type="email" name="email_clube" placeholder="Email">
                <input type="text" name="website_clube" placeholder="Website">
                <input type="text" name="presidente_clube" placeholder="Presidente">
                <input type="text" name="instagram_clube" placeholder="Instagram">
                <input type="text" name="facebook_clube" placeholder="Facebook">
                <input type="text" name="youtube_clube" placeholder="Youtube">
                <input type="text" name="twitter_clube" placeholder="Twitter">
                <input type="text" name="tiktok_clube" placeholder="Tiktok">
                <input type="text" name="codigo_clube" placeholder="Código do Clube">
                <input type="color" name="cor" value="#000000">
                <input type="file" name="logotipo" accept="image/*">
                <button type="submit" class="btn-create">+ Adicionar Clube</button>
            </form>

            <input type="search" class="table-search" placeholder="Pesquisar clubes..." oninput="filtrarTabela('tabela-clubes', this.value)">

            <div class="kroos-table-wrap">
                <table class="kroos-table" id="tabela-clubes">
                    <thead>
                        <tr>
                            <th onclick="ordenarTabela('tabela-clubes',0)">ID</th>
                            <th onclick="ordenarTabela('tabela-clubes',1)">Nome do Clube</th>
                            <th onclick="ordenarTabela('tabela-clubes',2)">Sigla</th>
                            <th>Logótipo</th>
                            <th>Cor</th>
                            <th onclick="ordenarTabela('tabela-clubes',5)">Data de Fundação</th>
                            <th onclick="ordenarTabela('tabela-clubes',6)">Sede</th>
                            <th onclick="ordenarTabela('tabela-clubes',7)">País</th>
                            <th onclick="ordenarTabela('tabela-clubes',8)">Cidade</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Website</th>
                            <th>Presidente</th>
                            <th>Instagram</th>
                            <th>Facebook</th>
                            <th>Youtube</th>
                            <th>Twitter</th>
                            <th>Tiktok</th>
                            <th>Código</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaClubes as $cl): ?>
                        <tr>
                            <td><?= $cl['id_clube'] ?></td>
                            <td><strong><?= htmlspecialchars($cl['nome_clube']) ?></strong></td>
                            <td><?= htmlspecialchars($cl['sigla']) ?></td>
                            <td>
                                <img src="caminho/para/pasta/<?= htmlspecialchars($cl['logotipo']) ?>" alt="Logótipo" style="width: 40px; height: 40px; object-fit: cover;">
                            </td>
                            <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($cl['cor']) ?>"></span></td>
                            <td><?= formatarData($cl['data_fundação']) ?></td>
                            <td><?= htmlspecialchars($cl['sede_morada']) ?></td>
                            <td><?= htmlspecialchars($cl['país_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['cidade_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['telefone_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['email_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['website_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['presidente_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['instagram_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['facebook_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['youtube_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['twitter_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['tiktok_clube']) ?></td>
                            <td><?= htmlspecialchars($cl['código_clube']) ?></td>
                            <td class="td-acoes">
                                <button class="btn-edit" type="button" onclick='editarClube(<?= json_encode([
                                    "id" => $cl['id_clube'],
                                    "nome" => $cl['nome_clube'],
                                    "sigla" => $cl['sigla'],
                                    "dataFundacao" => $cl['data_fundação'],
                                    "sede" => $cl['sede_morada'],
                                    "pais" => $cl['país_clube'],
                                    "cidade" => $cl['cidade_clube'],
                                    "telefone" => $cl['telefone_clube'],
                                    "email" => $cl['email_clube'],
                                    "website" => $cl['website_clube'],
                                    "presidente" => $cl['presidente_clube'],
                                    "instagram" => $cl['instagram_clube'],
                                    "facebook" => $cl['facebook_clube'],
                                    "youtube" => $cl['youtube_clube'],
                                    "twitter" => $cl['twitter_clube'],
                                    "tiktok" => $cl['tiktok_clube'],
                                    "codigo" => $cl['código_clube'],
                                    "cor" => $cl['cor']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar clube?');">
                                    <input type="hidden" name="acao" value="eliminar_clube">
                                    <input type="hidden" name="clube_id" value="<?= $cl['id_clube'] ?>">
                                    <button class="btn-delete" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ GESTÃO DE NOTIFICAÇÕES ══ -->
        <div class="screen-shell" id="screen-notificacoes_gestao">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Gestão de Notificações do Sistema</h2>
                    <p class="trainer-page-subtitle">Envia avisos e notificações aos utilizadores do sistema.</p>
                </div>
            </div>

            <form method="post" class="kroos-form-inline">
                <input type="hidden" name="acao" value="criar_notificacao_sistema">
                <select name="id_utilizador" required>
                    <?php foreach ($listaUtilizadores as $u): ?>
                        <option value="<?= $u['id_utilizador'] ?>"><?= htmlspecialchars($u['nome_utilizador']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="id_clube">
                    <option value="">Sem Clube</option>
                    <?php foreach ($listaClubes as $cl): ?>
                        <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="tipo" placeholder="Tipo (ex: sistema, aviso)" value="sistema">
                <input type="text" name="titulo" placeholder="Título do aviso" required>
                <textarea name="mensagem" placeholder="Mensagem completa..." required></textarea>
                <button type="submit" class="btn-create">Enviar Notificação</button>
            </form>

            <input type="search" class="table-search" placeholder="Pesquisar notificações..." oninput="filtrarTabela('tabela-notificacoes', this.value)">

            <div class="kroos-table-wrap">
                <table class="kroos-table" id="tabela-notificacoes">
                    <thead>
                        <tr>
                            <th onclick="ordenarTabela('tabela-notificacoes',0)">ID</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',1)">Destinatário</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',2)">Clube do Destinatário</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',3)">Tipo</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',4)">Título</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',5)">Estado</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',6)">Data de Criação</th>
                            <th onclick="ordenarTabela('tabela-notificacoes',7)">Data de Leitura</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaNotifGestao as $ng): ?>
                        <tr>
                            <td><?= $ng['id_notificacao'] ?></td>
                            <td><strong><?= htmlspecialchars($ng['nome_utilizador']) ?></strong></td>
                            <td><?= htmlspecialchars($ng['nome_clube'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($ng['tipo']) ?></td>
                            <td><?= htmlspecialchars($ng['titulo']) ?></td>
                            <td><?= htmlspecialchars($ng['estado']) ?></td>
                            <td><?= formatarData($ng['criada_em'], true) ?></td>
                            <td><?= formatarData($ng['lida_em'], true) ?></td>
                            <td class="td-acoes">
                                <button class="btn-edit" type="button" onclick='editarNotificacao(<?= json_encode([
                                    "id" => $ng['id_notificacao'],
                                    "destinatario" => $ng['id_utilizador'],
                                    "clube" => $ng['id_clube'],
                                    "titulo" => $ng['titulo'],
                                    "mensagem" => $ng['mensagem'],
                                    "tipo" => $ng['tipo'],
                                    "estado" => $ng['estado']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                                <form method="post" onsubmit="return confirm('Eliminar notificação?');">
                                    <input type="hidden" name="acao" value="eliminar_notificacao">
                                    <input type="hidden" name="id_notificacao" value="<?= $ng['id_notificacao'] ?>">
                                    <button class="btn-delete" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ DEFINIÇÕES GERAIS ══ -->
        <div class="screen-shell" id="screen-definicoes">
            <div class="trainer-page-header">
                <div>
                    <h2 class="trainer-page-title">Definições Gerais</h2>
                    <p class="trainer-page-subtitle">Definições do sistema Kroos Project.</p>
                </div>
            </div>
            <div class="empty-state">
                <strong>Plataforma de Apoio à Gestão Desportiva - Kroos</strong>
                Versão 1.0 (Janeiro 2026)
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════
     MODAIS DE EDIÇÃO — todos os campos
══════════════════════════════════ -->

<!-- MODAL EDITAR UTILIZADOR -->
<div class="modal-overlay" id="modalEditarUtilizador">
    <div class="modal-box">
        <h3>Editar Utilizador</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar_utilizador">
            <input type="hidden" id="edit_u_id" name="id_utilizador">
            <input type="text" id="edit_u_nome" name="nome_utilizador" placeholder="Nome de Utilizador" required>
            <input type="email" id="edit_u_email" name="email_utilizador" placeholder="Email" required>
            <input type="text" id="edit_u_pnome" name="primeiro_nome" placeholder="Primeiro Nome" required>
            <input type="text" id="edit_u_unome" name="ultimo_nome" placeholder="Último Nome" required>
            <input type="tel" id="edit_u_telefone" name="telefone_utilizador" placeholder="Telefone">
            <input type="date" id="edit_u_data" name="data_nascimento">
            <select id="edit_u_tipo" name="tipo_utilizador">
                <option value="jogador">Jogador</option>
                <option value="treinador">Treinador</option>
                <option value="admin_clube">Admin Clube</option>
                <option value="admin">Admin Sistema</option>
            </select>
            <input type="text" id="edit_u_tipo_treinador" name="tipo_treinador" placeholder="Tipo de Treinador">
            <select id="edit_u_clube" name="id_clube">
                <option value="">Sem Clube</option>
                <?php foreach ($listaClubes as $cl): ?>
                    <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="file" name="foto_perfil" accept="image/*">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditarUtilizador')">Cancelar</button>
                <button type="submit" class="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR COMPETIÇÃO -->
<div class="modal-overlay" id="modalEditarCompeticao">
    <div class="modal-box">
        <h3>Editar Competição</h3>
        <form method="post">
            <input type="hidden" name="acao" value="editar_competicao">
            <input type="hidden" id="edit_c_id" name="competicao_id">
            <select id="edit_c_clube" name="id_clube" required>
                <?php foreach ($listaClubes as $cl): ?>
                    <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="edit_c_equipa" name="id_equipa" required>
                <?php foreach ($listaEquipas as $eq): ?>
                    <option value="<?= $eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="edit_c_nome" name="nome" placeholder="Nome da Competição" required>
            <input type="text" id="edit_c_tipo" name="tipo" placeholder="Tipo">
            <input type="number" id="edit_c_epoca" name="epoca" placeholder="Época">
            <select id="edit_c_estado" name="estado">
                <option value="Agendada">Agendada</option>
                <option value="Em Curso">Em Curso</option>
                <option value="Terminada">Terminada</option>
                <option value="Cancelada">Cancelada</option>
            </select>
            <textarea id="edit_c_descricao" name="descricao" placeholder="Descrição"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditarCompeticao')">Cancelar</button>
                <button type="submit" class="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR JOGADOR -->
<div class="modal-overlay" id="modalEditarJogador">
    <div class="modal-box">
        <h3>Editar Jogador</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar_jogador">
            <input type="hidden" id="edit_j_id" name="jogador_id">
            <input type="text" id="edit_j_nome" name="nome_completo" placeholder="Nome Completo" required>
            <input type="text" id="edit_j_alcunha" name="alcunha_jogador" placeholder="Alcunha">
            <input type="number" id="edit_j_num" name="numero_favorito" placeholder="Dorsal">
            <select id="edit_j_pos" name="posicao_principal">
                <option value="Guarda-Redes">Guarda-Redes</option>
                <option value="Defesa">Defesa</option>
                <option value="Médio">Médio</option>
                <option value="Avançado">Avançado</option>
            </select>
            <select id="edit_j_pos_sec" name="posicao_secundaria">
                <option value="">Sem Posição Secundária</option>
                <option value="Guarda-Redes">Guarda-Redes</option>
                <option value="Defesa">Defesa</option>
                <option value="Médio">Médio</option>
                <option value="Avançado">Avançado</option>
            </select>
            <input type="date" id="edit_j_data" name="data_nascimento">
            <input type="text" id="edit_j_local" name="local_nascimento" placeholder="Local de Nascimento">
            <input type="text" id="edit_j_nacionalidade" name="nacionalidade" placeholder="Nacionalidade">
            <select id="edit_j_pe" name="pe_preferencial">
                <option value="">Pé Preferencial</option>
                <option value="Direito">Direito</option>
                <option value="Esquerdo">Esquerdo</option>
                <option value="Ambidestro">Ambidestro</option>
            </select>
            <input type="number" step="0.01" id="edit_j_altura" name="altura" placeholder="Altura (m)">
            <input type="number" step="0.1" id="edit_j_peso" name="peso" placeholder="Peso (kg)">
            <input type="text" id="edit_j_instagram" name="instagram" placeholder="Instagram">
            <input type="text" id="edit_j_facebook" name="facebook" placeholder="Facebook">
            <input type="text" id="edit_j_twitter" name="twitter" placeholder="Twitter">
            <select id="edit_j_equipa" name="equipa_id" required>
                <?php foreach ($listaEquipas as $eq): ?>
                    <option value="<?= $eq['id_equipa'] ?>"><?= htmlspecialchars($eq['escalão']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="file" name="foto_jogador" accept="image/*">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditarJogador')">Cancelar</button>
                <button type="submit" class="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR CLUBE -->
<div class="modal-overlay" id="modalEditarClube">
    <div class="modal-box">
        <h3>Editar Clube</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar_clube">
            <input type="hidden" id="edit_cl_id" name="clube_id">
            <input type="text" id="edit_cl_nome" name="nome_clube" placeholder="Nome do Clube" required>
            <input type="text" id="edit_cl_sigla" name="sigla" placeholder="Sigla">
            <input type="date" id="edit_cl_data_fundacao" name="data_fundacao">
            <input type="text" id="edit_cl_sede" name="sede_morada" placeholder="Sede / Morada">
            <input type="text" id="edit_cl_pais" name="pais_clube" placeholder="País">
            <input type="text" id="edit_cl_cidade" name="cidade_clube" placeholder="Cidade">
            <input type="tel" id="edit_cl_telefone" name="telefone_clube" placeholder="Telefone">
            <input type="email" id="edit_cl_email" name="email_clube" placeholder="Email">
            <input type="text" id="edit_cl_website" name="website_clube" placeholder="Website">
            <input type="text" id="edit_cl_presidente" name="presidente_clube" placeholder="Presidente">
            <input type="text" id="edit_cl_instagram" name="instagram_clube" placeholder="Instagram">
            <input type="text" id="edit_cl_facebook" name="facebook_clube" placeholder="Facebook">
            <input type="text" id="edit_cl_youtube" name="youtube_clube" placeholder="Youtube">
            <input type="text" id="edit_cl_twitter" name="twitter_clube" placeholder="Twitter">
            <input type="text" id="edit_cl_tiktok" name="tiktok_clube" placeholder="Tiktok">
            <input type="text" id="edit_cl_codigo" name="codigo_clube" placeholder="Código do Clube">
            <input type="color" id="edit_cl_cor" name="cor">
            <input type="file" name="logotipo" accept="image/*">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditarClube')">Cancelar</button>
                <button type="submit" class="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR NOTIFICAÇÃO -->
<div class="modal-overlay" id="modalEditarNotificacao">
    <div class="modal-box">
        <h3>Editar Notificação</h3>
        <form method="post">
            <input type="hidden" name="acao" value="editar_notificacao">
            <input type="hidden" id="edit_n_id" name="id_notificacao">
            <select id="edit_n_destinatario" name="id_utilizador" required>
                <?php foreach ($listaUtilizadores as $u): ?>
                    <option value="<?= $u['id_utilizador'] ?>"><?= htmlspecialchars($u['nome_utilizador']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="edit_n_clube" name="id_clube">
                <option value="">Sem Clube</option>
                <?php foreach ($listaClubes as $cl): ?>
                    <option value="<?= $cl['id_clube'] ?>"><?= htmlspecialchars($cl['nome_clube']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="edit_n_tipo" name="tipo" placeholder="Tipo">
            <select id="edit_n_estado" name="estado">
                <option value="Nao Lida">Não Lida</option>
                <option value="Lida">Lida</option>
            </select>
            <input type="text" id="edit_n_titulo" name="titulo" placeholder="Título" required>
            <textarea id="edit_n_mensagem" name="mensagem" placeholder="Mensagem" required></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalEditarNotificacao')">Cancelar</button>
                <button type="submit" class="btn-save">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
/* Menu superior direito */
function toggleUserMenu(event) {
    event.stopPropagation();
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.toggle('active');
}

document.addEventListener('click', function () {
    const menu = document.getElementById('userDropdown');
    if (menu) menu.classList.remove('active');
});

const userDropdown = document.getElementById('userDropdown');
if (userDropdown) {
    userDropdown.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}

function closeAlert(buttonEl) {
    if (!buttonEl) return;
    const alertEl = buttonEl.closest('.alert');
    if (alertEl) {
        alertEl.dataset.dismissed = '1';
        alertEl.style.display = 'none';
    }
}

/* Gestão de ecrãs */
const TODOS_OS_ECRAS = ['profileScreen', 'notificationsScreen', 'screen-home', 'screen-utilizadores', 'screen-competicoes', 'screen-jogadores', 'screen-clubes', 'screen-notificacoes_gestao', 'screen-definicoes', 'screen-estatisticas'];

function hideAllScreens() {
    TODOS_OS_ECRAS.forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'none'; el.classList.remove('visible'); }
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

function showScreen(view) {
    hideAllScreens();
    setLayoutLock(false);
    setActiveSidebar(view);

    const id = view === 'home' ? 'screen-home' : 'screen-' + view;
    const el = document.getElementById(id);
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }

    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.history.replaceState({}, '', url);
}

function showProfileScreen() {
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('profileScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

function showNotificationsScreen() {
    hideAllScreens();
    setLayoutLock(false);
    const el = document.getElementById('notificationsScreen');
    if (el) { el.style.display = 'block'; el.classList.add('visible'); }
}

/* Perfil — guardar via fetch */
function saveProfileChanges() {
    const form = document.getElementById('profileForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitProfileBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'A guardar...';
    }

    const formData = new FormData(form);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) throw new Error('Erro ao guardar perfil');
            if (submitBtn) submitBtn.textContent = 'Guardado';
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

const profileForm = document.getElementById('profileForm');
if (profileForm) {
    profileForm.addEventListener('submit', function (event) {
        event.preventDefault();
        exportAdjustedProfileImage();
        saveProfileChanges();
    });
}

/* Notificações — filtros e leitura */
function filterNotifications(filter = 'all') {
    document.querySelectorAll('.notification-row').forEach(row => {
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
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: formData.toString()
    }).then(() => applyNotificationState(row, nextState))
      .catch(() => applyNotificationState(row, nextState));
}

const notificationTabs = document.querySelectorAll('.notification-tab');
notificationTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        notificationTabs.forEach(btn => btn.classList.remove('active'));
        tab.classList.add('active');
        const text = tab.textContent.trim();
        if (text === 'Lidas') filterNotifications('read');
        else if (text === 'Por ler') filterNotifications('unread');
        else filterNotifications('all');
    });
});

document.querySelectorAll('.notification-row').forEach(row => {
    row.addEventListener('click', () => {
        const id = row.dataset.id;
        if (id) toggleNotificationState(id, row);
    });
    row.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const id = row.dataset.id;
            if (id) toggleNotificationState(id, row);
        }
    });
});

/* Foto de perfil — upload + ajuste (zoom / posição) */
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
    if (!msg) { fotoPerfilErro.style.display = 'none'; fotoPerfilErro.textContent = ''; return; }
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
    if (!ctx) { fotoPerfilAjustadaInput.value = ''; return; }

    const iw = imagemOriginalParaAjuste.naturalWidth || imagemOriginalParaAjuste.width;
    const ih = imagemOriginalParaAjuste.naturalHeight || imagemOriginalParaAjuste.height;
    if (!iw || !ih) { fotoPerfilAjustadaInput.value = ''; return; }

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
    btnEditarFotoPerfil.addEventListener('click', () => fotoPerfilInput.click());

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
                if (profileAdjustTools) profileAdjustTools.style.display = 'grid';
                if (fotoPerfilZoom) fotoPerfilZoom.value = '1';
                if (fotoPerfilPosX) fotoPerfilPosX.value = '50';
                if (fotoPerfilPosY) fotoPerfilPosY.value = '50';
                applyPreviewTransform();
                exportAdjustedProfileImage();
            };
            tempImage.src = event.target.result;
            if (preview) { preview.src = event.target.result; preview.style.display = 'block'; }
            if (initial) initial.style.display = 'none';
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

/* ══════════════════════════════════
   MODAIS DE EDIÇÃO
══════════════════════════════════ */
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) overlay.classList.remove('active');
    });
});

function setValue(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = (val === null || val === undefined) ? '' : val;
}

function editarUtilizador(d) {
    setValue('edit_u_id', d.id);
    setValue('edit_u_nome', d.nome);
    setValue('edit_u_email', d.email);
    setValue('edit_u_pnome', d.pnome);
    setValue('edit_u_unome', d.unome);
    setValue('edit_u_tipo', d.tipo);
    setValue('edit_u_tipo_treinador', d.tipoTreinador);
    setValue('edit_u_telefone', d.telefone);
    setValue('edit_u_data', d.data ? String(d.data).substring(0, 10) : '');
    setValue('edit_u_clube', d.idClube);
    openModal('modalEditarUtilizador');
}

function editarCompeticao(d) {
    setValue('edit_c_id', d.id);
    setValue('edit_c_clube', d.clube);
    setValue('edit_c_equipa', d.equipa);
    setValue('edit_c_nome', d.nome);
    setValue('edit_c_tipo', d.tipo);
    setValue('edit_c_epoca', d.epoca);
    setValue('edit_c_estado', d.estado);
    setValue('edit_c_descricao', d.descricao);
    openModal('modalEditarCompeticao');
}

function editarJogador(d) {
    setValue('edit_j_id', d.id);
    setValue('edit_j_nome', d.nome);
    setValue('edit_j_alcunha', d.alcunha);
    setValue('edit_j_num', d.num);
    setValue('edit_j_pos', d.pos);
    setValue('edit_j_pos_sec', d.posSec);
    setValue('edit_j_data', d.data ? String(d.data).substring(0, 10) : '');
    setValue('edit_j_local', d.local);
    setValue('edit_j_nacionalidade', d.nacionalidade);
    setValue('edit_j_pe', d.pe);
    setValue('edit_j_altura', d.altura);
    setValue('edit_j_peso', d.peso);
    setValue('edit_j_instagram', d.instagram);
    setValue('edit_j_facebook', d.facebook);
    setValue('edit_j_twitter', d.twitter);
    setValue('edit_j_equipa', d.equipa);
    openModal('modalEditarJogador');
}

function editarClube(d) {
    setValue('edit_cl_id', d.id);
    setValue('edit_cl_nome', d.nome);
    setValue('edit_cl_sigla', d.sigla);
    setValue('edit_cl_data_fundacao', d.dataFundacao ? String(d.dataFundacao).substring(0, 10) : '');
    setValue('edit_cl_sede', d.sede);
    setValue('edit_cl_pais', d.pais);
    setValue('edit_cl_cidade', d.cidade);
    setValue('edit_cl_telefone', d.telefone);
    setValue('edit_cl_email', d.email);
    setValue('edit_cl_website', d.website);
    setValue('edit_cl_presidente', d.presidente);
    setValue('edit_cl_instagram', d.instagram);
    setValue('edit_cl_facebook', d.facebook);
    setValue('edit_cl_youtube', d.youtube);
    setValue('edit_cl_twitter', d.twitter);
    setValue('edit_cl_tiktok', d.tiktok);
    setValue('edit_cl_codigo', d.codigo);
    setValue('edit_cl_cor', d.cor || '#000000');
    openModal('modalEditarClube');
}

function editarNotificacao(d) {
    setValue('edit_n_id', d.id);
    setValue('edit_n_destinatario', d.destinatario);
    setValue('edit_n_clube', d.clube);
    setValue('edit_n_tipo', d.tipo);
    setValue('edit_n_estado', d.estado);
    setValue('edit_n_titulo', d.titulo);
    setValue('edit_n_mensagem', d.mensagem);
    openModal('modalEditarNotificacao');
}

/* ══════════════════════════════════
   PESQUISA E ORDENAÇÃO DE TABELAS
══════════════════════════════════ */
function filtrarTabela(tableId, termo) {
    const tabela = document.getElementById(tableId);
    if (!tabela) return;
    const t = termo.trim().toLowerCase();
    tabela.querySelectorAll('tbody tr').forEach(linha => {
        linha.style.display = linha.textContent.toLowerCase().includes(t) ? '' : 'none';
    });
}

const ordenacaoEstado = {};
function ordenarTabela(tableId, colIndex) {
    const tabela = document.getElementById(tableId);
    if (!tabela) return;
    const tbody = tabela.querySelector('tbody');
    const linhas = Array.from(tbody.querySelectorAll('tr'));
    const chave = tableId + '-' + colIndex;
    const asc = ordenacaoEstado[chave] !== 'asc';
    ordenacaoEstado[chave] = asc ? 'asc' : 'desc';

    linhas.sort((a, b) => {
        const aTxt = (a.children[colIndex]?.textContent || '').trim();
        const bTxt = (b.children[colIndex]?.textContent || '').trim();
        const aNum = parseFloat(aTxt.replace(',', '.'));
        const bNum = parseFloat(bTxt.replace(',', '.'));
        const r = (!isNaN(aNum) && !isNaN(bNum) && aTxt !== '' && bTxt !== '')
            ? aNum - bNum
            : aTxt.localeCompare(bTxt, 'pt');
        return asc ? r : -r;
    });

    linhas.forEach(l => tbody.appendChild(l));
    tabela.querySelectorAll('th').forEach(th => th.classList.remove('th-asc', 'th-desc'));
    tabela.querySelectorAll('th')[colIndex]?.classList.add(asc ? 'th-asc' : 'th-desc');
}

/* ══════════════════════════════════
   SIDEBAR — efeito de deslize para títulos cortados
   (não altera o tamanho nem o estilo da sidebar em si)
══════════════════════════════════ */
document.querySelectorAll('.sidebar a').forEach(a => {
    const label = a.querySelector('.side-label');
    if (!label) return;

    label.addEventListener('transitionend', (event) => {
        if (event.propertyName !== 'width') return;
        const overflow = label.scrollWidth - label.clientWidth;
        if (overflow > 2) {
            label.style.setProperty('--marquee-distance', (-overflow - 4) + 'px');
            label.classList.add('marquee-active');
        } else {
            label.classList.remove('marquee-active');
            label.style.removeProperty('--marquee-distance');
        }
    });

    a.addEventListener('mouseleave', () => {
        label.classList.remove('marquee-active');
    });
});

/* Estado inicial conforme parâmetro ?view= (predefinido: estatísticas) */
document.addEventListener('DOMContentLoaded', function () {
    const initialView = <?= json_encode($viewMode) ?>;
    showScreen(initialView);
});
</script>

</body>
</html>