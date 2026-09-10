<?php
session_start();

require_once __DIR__ . '/basedados.h';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfigPath = __DIR__ . '/mail-config.php';
$mailConfig = file_exists($mailConfigPath) ? require $mailConfigPath : null;

$erro = '';
$sucesso = '';
$mostrarFormularioCodigo = false;

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function enviarEmailCodigo(string $email, string $nome, string $codigo): array
{
    global $mailConfig;

    if (!is_array($mailConfig)) {
        return [
            'ok' => false,
            'erro' => 'O ficheiro mail-config.php não foi encontrado ou não devolve uma configuração válida.'
        ];
    }

    $camposObrigatorios = ['host', 'port', 'smtp_secure', 'username', 'password', 'from_email', 'from_name'];
    foreach ($camposObrigatorios as $campo) {
        if (!isset($mailConfig[$campo]) || trim((string)$mailConfig[$campo]) === '') {
            return [
                'ok' => false,
                'erro' => 'Falta configurar o campo ' . $campo . ' no mail-config.php.'
            ];
        }
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $mailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->Port = (int)$mailConfig['port'];

        if (($mailConfig['smtp_secure'] ?? 'tls') === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($email, $nome);

        $mail->isHTML(false);
        $mail->Subject = 'Código de recuperação de password - Kroos';
        $mail->Body =
            "Olá " . $nome . ",\n\n" .
            "Recebemos um pedido para recuperar a tua password no Kroos.\n\n" .
            "O teu código de recuperação é: " . $codigo . "\n\n" .
            "Este código expira em 15 minutos.\n\n" .
            "Se não foste tu a pedir isto, ignora este email.\n\n" .
            "Kroos";

        $mail->send();

        return [
            'ok' => true,
            'erro' => ''
        ];
    } catch (Exception $e) {
        return [
            'ok' => false,
            'erro' => $mail->ErrorInfo ?: $e->getMessage()
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'erro' => $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'enviar_codigo') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $erro = 'Indica o teu email.';
        } else {
            $stmtUser = $conn->prepare(" 
                SELECT id_utilizador, email_utilizador, primeiro_nome, último_nome
                FROM utilizador
                WHERE email_utilizador = ?
                LIMIT 1
            ");
            $stmtUser->bind_param("s", $email);
            $stmtUser->execute();
            $user = $stmtUser->get_result()->fetch_assoc();

            if (!$user) {
                $erro = 'Email inválido! Tente novamente.';
                $mostrarFormularioCodigo = false;
            } else {
                $idUtilizador = (int)$user['id_utilizador'];
                $codigo = (string)random_int(100000, 999999);
                $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);
                $expiraEm = date('Y-m-d H:i:s', time() + 15 * 60);

                $stmtLimpar = $conn->prepare(" 
                    UPDATE recuperacao_password
                    SET usado = 1, usado_em = NOW()
                    WHERE id_utilizador = ?
                      AND usado = 0
                ");
                $stmtLimpar->bind_param("i", $idUtilizador);
                $stmtLimpar->execute();

                $stmtInsert = $conn->prepare(" 
                    INSERT INTO recuperacao_password
                        (id_utilizador, codigo_hash, expira_em)
                    VALUES (?, ?, ?)
                ");
                $stmtInsert->bind_param("iss", $idUtilizador, $codigoHash, $expiraEm);

                if (!$stmtInsert->execute()) {
                    $erro = 'Erro ao criar código de recuperação.';
                } else {
                    $nomeCompleto = trim(($user['primeiro_nome'] ?? '') . ' ' . ($user['último_nome'] ?? ''));

                    $resultadoEmail = enviarEmailCodigo(
                        $user['email_utilizador'],
                        $nomeCompleto ?: 'utilizador',
                        $codigo
                    );

                    if ($resultadoEmail['ok']) {
                        $_SESSION['reset_id_utilizador'] = $idUtilizador;
                        $_SESSION['reset_email'] = $user['email_utilizador'];

                        $mostrarFormularioCodigo = true;
                        $sucesso = 'Enviámos um código para o teu email.';
                    } else {
                        $mostrarFormularioCodigo = false;
                        $erro = 'Não foi possível enviar o email de recuperação: ' . $resultadoEmail['erro'];
                    }
                }
            }
        }
    }

    if ($acao === 'alterar_password') {
        $idUtilizador = (int)($_SESSION['reset_id_utilizador'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $novaPassword = $_POST['nova_password'] ?? '';
        $confirmarPassword = $_POST['confirmar_password'] ?? '';

        $mostrarFormularioCodigo = true;

        if ($idUtilizador <= 0) {
            $erro = 'Sessão expirada. Pede um novo código.';
            $mostrarFormularioCodigo = false;
        } elseif ($codigo === '' || $novaPassword === '' || $confirmarPassword === '') {
            $erro = 'Preenche todos os campos.';
        } elseif ($novaPassword !== $confirmarPassword) {
            $erro = 'As passwords não coincidem.';
        } elseif (strlen($novaPassword) < 6) {
            $erro = 'A password deve ter pelo menos 6 caracteres.';
        } else {
            $stmtCodigo = $conn->prepare(" 
                SELECT id_recuperacao, codigo_hash, expira_em
                FROM recuperacao_password
                WHERE id_utilizador = ?
                  AND usado = 0
                ORDER BY id_recuperacao DESC
                LIMIT 1
            ");
            $stmtCodigo->bind_param("i", $idUtilizador);
            $stmtCodigo->execute();
            $reset = $stmtCodigo->get_result()->fetch_assoc();

            if (!$reset) {
                $erro = 'Código inválido ou expirado.';
            } elseif (strtotime($reset['expira_em']) < time()) {
                $erro = 'O código expirou. Pede um novo.';
            } elseif (!password_verify($codigo, $reset['codigo_hash'])) {
                $erro = 'Código incorreto.';
            } else {
                // Não uses MD5 aqui. A trigger da tabela utilizador faz o hash no UPDATE.
                $stmtUpdate = $conn->prepare(" 
                    UPDATE utilizador
                    SET password = ?
                    WHERE id_utilizador = ?
                ");
                $stmtUpdate->bind_param("si", $novaPassword, $idUtilizador);

                if (!$stmtUpdate->execute()) {
                    $erro = 'Erro ao alterar password.';
                } else {
                    $idRecuperacao = (int)$reset['id_recuperacao'];

                    $stmtUsado = $conn->prepare(" 
                        UPDATE recuperacao_password
                        SET usado = 1, usado_em = NOW()
                        WHERE id_recuperacao = ?
                    ");
                    $stmtUsado->bind_param("i", $idRecuperacao);
                    $stmtUsado->execute();

                    unset($_SESSION['reset_id_utilizador'], $_SESSION['reset_email']);

                    $_SESSION['password_alterada'] = true;
                    header('Location: login.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Recuperar Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pageIn 0.4s ease-out;
}

@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.card {
    width: 520px;
    padding: 56px 48px;
    border: 1px solid #e0e0e0;
    border-radius: 24px;
    background: #fff;
    box-shadow:
        0 12px 24px rgba(0, 0, 0, 0.06),
        0 40px 80px rgba(0, 0, 0, 0.08);
}

.logo {
    display: flex;
    justify-content: center;
    margin-bottom: 32px;
}

.logo img {
    height: 72px;
}

.title {
    text-align: center;
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    font-size: 14px;
    color: #555;
    line-height: 1.5;
    margin-bottom: 28px;
}

.error {
    background: #fff1f1;
    color: #b00020;
    border: 1px solid #ffd0d0;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 22px;
    text-align: center;
}

.success {
    background: #f0fff4;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 22px;
    text-align: center;
}

.field {
    margin-bottom: 24px;
}

.field input {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid #999;
    padding: 10px 4px;
    font-size: 15px;
    outline: none;
}

.btn {
    width: 100%;
    padding: 14px;
    border-radius: 999px;
    border: none;
    font-size: 15px;
    cursor: pointer;
    background: #000;
    color: #fff;
    margin-top: 6px;
}

.back {
    display: block;
    text-align: center;
    margin-top: 22px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
}

@media (max-width: 600px) {
    .card {
        width: 92%;
        padding: 36px 28px;
    }

    .logo img {
        height: 60px;
    }
}
</style>
</head>

<body>

<div class="card">

    <div class="logo">
        <img src="assets/kroos-logo.png" alt="Kroos">
    </div>

    <div class="title">Recuperar password</div>

    <?php if ($erro): ?>
        <div class="error"><?= h($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="success"><?= h($sucesso) ?></div>
    <?php endif; ?>

    <?php if (!$mostrarFormularioCodigo): ?>

        <div class="subtitle">
            Indica o email associado à tua conta. Vais receber um código para alterar a password.
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="enviar_codigo">

            <div class="field">
                <input type="email" name="email" placeholder="Email da conta" required>
            </div>

            <button class="btn" type="submit">Enviar código</button>
        </form>

    <?php else: ?>

        <div class="subtitle">
            Introduz o código recebido por email e define a nova password.
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="alterar_password">

            <div class="field">
                <input type="text" name="codigo" placeholder="Código recebido por email" maxlength="6" required>
            </div>

            <div class="field">
                <input type="password" name="nova_password" placeholder="Nova password" required>
            </div>

            <div class="field">
                <input type="password" name="confirmar_password" placeholder="Confirmar nova password" required>
            </div>

            <button class="btn" type="submit">Alterar password</button>
        </form>

    <?php endif; ?>

    <a class="back" href="login.php">← Voltar ao login</a>

</div>

</body>
</html>
