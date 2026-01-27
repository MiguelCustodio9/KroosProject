<?php
session_start();
require_once __DIR__ . '/basedados.h';

$erro = '';
$redirectTo = ''; // quando estiver preenchido, faz transição e redireciona

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome      = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$nome || !$sobrenome || !$email || !$password || !$confirm) {
        $erro = 'Preenche todos os campos.';
    } elseif ($password !== $confirm) {
        $erro = 'As passwords não coincidem.';
    } else {

        $nome_utilizador = strtolower($nome . '_' . $sobrenome);
        $password_md5 = md5($password);

        $stmt = $conn->prepare("
            INSERT INTO validação_utilizador
            (nome_utilizador, email_utilizador, primeiro_nome, último_nome, password)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $erro = 'Erro na preparação da query.';
        } else {
            $stmt->bind_param(
                'sssss',
                $nome_utilizador,
                $email,
                $nome,
                $sobrenome,
                $password_md5
            );

            if ($stmt->execute()) {
                $_SESSION['id_validacao'] = $stmt->insert_id;
                $redirectTo = 'juntar-criar-clube.php'; // ✅ passo 2
            } else {
                $erro = 'Erro ao guardar utilizador.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Registo</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; font-family: 'Inter', sans-serif; }

body {
    margin: 0;
    min-height: 100vh;
    background: #ffffff;
    animation: pageIn 0.4s ease-out;
    transition: opacity 0.25s ease, transform 0.25s ease; /* ✅ para saída */
}

@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* LOGO */
.header { position: fixed; top: 32px; left: 40px; z-index: 10; }
.header img { height: 80px; }

/* MAIN */
.main {
    min-height: 100vh;
    padding: 120px 80px 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD */
.card {
    position: relative;
    width: 100%;
    max-width: 1200px;
    min-height: 75vh;
    padding: 80px 72px 60px;
    background: #fff;
    border-radius: 32px;
    border: 1px solid #e3e3e3;
    box-shadow:
        0 16px 32px rgba(0,0,0,0.06),
        0 60px 120px rgba(0,0,0,0.10);
}

/* STEPPER */
.steps {
    position: absolute;
    top: 32px;
    right: 48px;
    display: flex;
    align-items: center;
    gap: 18px;
}
.step {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: #000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
.step.inactive { background: #c6c6c6; }

.line { width: 64px; height: 3px; background: #000; }
.line.inactive { background: #d5d5d5; }

/* FORM */
.form-grid {
    margin-top: 64px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
}

input {
    padding: 20px 24px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f2f2f2;
    font-size: 16px;
    outline: none;
}

/* ERRO */
.error {
    grid-column: 1 / -1;
    color: #d00000;
    font-weight: 500;
}

/* BOTÃO */
.btn-container {
    grid-column: 2;
    display: flex;
    justify-content: flex-end;
    margin-top: 32px;
}

.btn-submit {
    width: 100%;
    padding: 18px;
    border-radius: 999px;
    border: none;
    background: #000;
    color: #fff;
    font-size: 17px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}
.btn-submit:hover { opacity: 0.85; }

/* MOBILE */
@media (max-width: 900px) {
    .main { padding: 120px 24px 40px; }
    .card { padding: 96px 32px 40px; min-height: auto; }
    .form-grid { grid-template-columns: 1fr; }
    .btn-container { grid-column: 1; }
    .steps { right: 24px; top: 24px; }
}
</style>
</head>

<body>

<div class="header">
    <img src="assets/kroos-logo.png" alt="Kroos">
</div>

<div class="main">
<div class="card">

    <div class="steps">
        <div class="step">1</div>
        <div class="line"></div>
        <div class="step inactive">2</div>
        <div class="line inactive"></div>
        <div class="step inactive">3</div>
    </div>

    <form method="POST" class="form-grid" id="registerForm">
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <input type="text" name="nome" placeholder="Nome" required>
        <input type="text" name="sobrenome" placeholder="Sobrenome" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirmar password" required>

        <div class="btn-container">
            <button type="submit" class="btn-submit" id="btnContinuar">
                Continuar
            </button>
        </div>
    </form>

</div>
</div>

<?php if ($redirectTo): ?>
<script>
    // ✅ Já gravou na BD. Agora fazemos transição e só depois redirecionamos.
    document.addEventListener('DOMContentLoaded', function () {
        document.body.style.opacity = '0';
        document.body.style.transform = 'translateY(-12px)';

        setTimeout(() => {
            window.location.href = "<?= $redirectTo ?>";
        }, 250);
    });
</script>
<?php endif; ?>

</body>
</html>
