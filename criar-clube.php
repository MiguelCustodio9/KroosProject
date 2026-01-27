<?php
session_start();
require_once __DIR__ . '/basedados.h';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome  = trim($_POST['nome_clube'] ?? '');
    $sigla = strtoupper(trim($_POST['sigla'] ?? ''));
    $cor   = strtoupper(trim($_POST['cor'] ?? ''));
    $data_fundacao = $_POST['data_fundacao'] ?? '';

    if (
        $nome === '' ||
        $sigla === '' ||
        $cor === '' ||
        $data_fundacao === '' ||
        empty($_FILES['logotipo']['tmp_name'])
    ) {
        $erro = 'Preenche todos os campos obrigatórios.';
    } else {

        $codigo_clube = strtoupper(bin2hex(random_bytes(4)));
        $logotipo = file_get_contents($_FILES['logotipo']['tmp_name']);

        $stmt = $conn->prepare("
            INSERT INTO clube
            (nome_clube, sigla, logotipo, cor, data_fundação, código_clube)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssss",
            $nome,
            $sigla,
            $logotipo,
            $cor,
            $data_fundacao,
            $codigo_clube
        );

        if ($stmt->execute()) {

            $id_clube = $stmt->insert_id;
            $_SESSION['id_clube'] = $id_clube;

            $id_validacao = $_SESSION['id_validacao'] ?? null;
            if (!$id_validacao) {
                die('Utilizador em validação não encontrado.');
            }

            $stmtUser = $conn->prepare("
                SELECT nome_utilizador, foto_perfil, email_utilizador,
                       telefone_utilizador, primeiro_nome, último_nome,
                       data_nascimento, password
                FROM validação_utilizador
                WHERE id_validação = ?
            ");
            $stmtUser->bind_param("i", $id_validacao);
            $stmtUser->execute();
            $user = $stmtUser->get_result()->fetch_assoc();

            if (!$user) {
                die('Dados do utilizador inválidos.');
            }

            $stmtInsert = $conn->prepare("
                INSERT INTO utilizador
                (nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador,
                 primeiro_nome, último_nome, data_nascimento, password,
                 tipo_utilizador, id_clube)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin_clube', ?)
            ");

            $stmtInsert->bind_param(
                "ssssssssi",
                $user['nome_utilizador'],
                $user['foto_perfil'],
                $user['email_utilizador'],
                $user['telefone_utilizador'],
                $user['primeiro_nome'],
                $user['último_nome'],
                $user['data_nascimento'],
                $user['password'],
                $id_clube
            );

            $stmtInsert->execute();

            $_SESSION['id_utilizador'] = $stmtInsert->insert_id;
            $_SESSION['tipo_utilizador'] = 'admin_clube';

            $conn->query("DELETE FROM validação_utilizador WHERE id_validação = $id_validacao");

            header('Location: index-admin.php');
            exit;

        } else {
            $erro = 'Erro ao criar clube.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Criar Clube</title>
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
    background: #fff;
    animation: pageIn 0.4s ease-out;
}

@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.header {
    position: fixed;
    top: 32px;
    left: 40px;
}

.header img {
    height: 80px;
}

.main {
    min-height: 100vh;
    padding: 120px 80px 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.card {
    width: 100%;
    max-width: 1200px;
    padding: 72px;
    border-radius: 32px;
    border: 1px solid #e3e3e3;
    box-shadow: 0 16px 32px rgba(0,0,0,.06), 0 60px 120px rgba(0,0,0,.1);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    margin-top: 40px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

input {
    padding: 16px 22px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    font-size: 15px;
}

.logo-box {
    width: 220px;
    height: 220px;
    background: #e6e6e6;
    border-radius: 24px;
    margin-bottom: 24px;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;
    flex-direction: column;
    gap: 6px;

    position: relative; /* 🔥 FUNDAMENTAL */
    overflow: hidden;
}

.gallery-icon {
    width: 64px;
    margin-bottom: 12px;
}

.gallery-icon,
#logoText {
    position: relative;
    z-index: 1;
}

#logoPreview {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: none;
    z-index: 2;
}

.color-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-picker input[type="color"] {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    border: 1px solid #999;
    padding: 0;
    cursor: pointer;
}

.color-picker input[type="color"]::-webkit-color-swatch {
    border: none;
}

.color-picker input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}

#colorHex {
    width: 120px;
    padding: 14px 18px;
    border-radius: 6px;
    border: 1px solid #999;
    font-family: monospace;
}

.btn-container {
    margin-top: 60px;
    display: flex;
    justify-content: flex-end;
}

.btn {
    width: 260px;
    padding: 18px;
    border-radius: 999px;
    border: none;
    background: #000;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

/* ===== STEPPER ===== */
/* ===== STEPPER DENTRO DO CARD ===== */
.stepper-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 40px;
}

.steps {
    display: flex;
    align-items: center;
    gap: 18px;
}

.step {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.line {
    width: 64px;
    height: 3px;
    background: #000;
}


</style>
</head>

<body>

<div class="header">
    <img src="assets/kroos-logo.png">
</div>

<div class="main">
<div class="card">
   <div class="stepper-wrapper">
        <div class="steps">
            <div class="step">1</div>
            <div class="line"></div>
            <div class="step">2</div>
            <div class="line"></div>
            <div class="step">3</div>
        </div>
    </div>


<?php if ($erro): ?>
<div style="color:red;margin-bottom:20px;"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div class="form-grid">

<div>
    <div class="input-group">
        <label>Nome do clube</label>
        <input type="text" name="nome_clube" required>
    </div>

    <div class="input-group">
        <label>Sigla / Acrónimo</label>
        <input type="text" name="sigla" maxlength="5" required>
    </div>

    <div class="input-group">
        <label>Ano de fundação</label>
        <input type="date" name="data_fundacao" required>
    </div>
</div>

<div>
    <label>Logótipo</label>
    <div class="logo-box" id="logoBox">
        <img src="assets/image-gallery.png" class="gallery-icon" id="galleryIcon">
        <span id="logoText">Carregar<br>ficheiro</span>
        <img id="logoPreview">
    </div>

    <input type="file" name="logotipo" id="logoInput" accept="image/*" hidden required>

    <label>Escolher cor principal</label>
    <div class="color-picker">
        <input type="color" id="colorInput" value="#FFFFFF">
        <input type="text" id="colorHex" name="cor" value="#FFFFFF">
    </div>
</div>

</div>

<div class="btn-container">
    <button class="btn" type="submit">Continuar</button>
</div>

</form>
</div>
</div>

<script>
const logoBox = document.getElementById('logoBox');
const logoInput = document.getElementById('logoInput');
const logoPreview = document.getElementById('logoPreview');
const galleryIcon = document.getElementById('galleryIcon');
const logoText = document.getElementById('logoText');

logoBox.addEventListener('click', () => logoInput.click());

logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        logoPreview.src = e.target.result;
        logoPreview.style.display = 'block';
        galleryIcon.style.display = 'none';
        logoText.style.display = 'none';
    };
    reader.readAsDataURL(file);
});

const colorInput = document.getElementById('colorInput');
const colorHex = document.getElementById('colorHex');

colorInput.addEventListener('input', () => {
    colorHex.value = colorInput.value.toUpperCase();
});

colorHex.addEventListener('input', () => {
    if (/^#([0-9A-Fa-f]{6})$/.test(colorHex.value)) {
        colorInput.value = colorHex.value;
    }
});
</script>

</body>
</html>
