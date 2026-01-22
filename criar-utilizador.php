<?php
session_start();
include 'basedados.h';

// Verificar se está conectado à BD
if (!$conn || $conn->connect_error) {
    die("Erro: Base de dados não está configurada. Importe o ficheiro SQL.");
}

$erros = [];
$primeiro_nome = '';
$ultimo_nome = '';
$nome_utilizador = '';
$email = '';
$telefone = '';
$data_nascimento = '';
$tipo_utilizador = '';

// Processar formulário de registo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obter dados do formulário
    $nome_utilizador = isset($_POST['nome_utilizador']) ? trim($_POST['nome_utilizador']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
    $primeiro_nome = isset($_POST['primeiro_nome']) ? trim($_POST['primeiro_nome']) : '';
    $ultimo_nome = isset($_POST['ultimo_nome']) ? trim($_POST['ultimo_nome']) : '';
    $data_nascimento = isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    $tipo_utilizador = isset($_POST['tipo_utilizador']) ? $_POST['tipo_utilizador'] : '';
    
    // Validação dos dados
    if (empty($primeiro_nome)) {
        $erros[] = 'Primeiro nome é obrigatório!';
    }
    
    if (empty($ultimo_nome)) {
        $erros[] = 'Último nome é obrigatório!';
    }
    
    if (empty($nome_utilizador)) {
        $erros[] = 'Nome de utilizador é obrigatório!';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido!';
    }
    
    if (empty($data_nascimento)) {
        $erros[] = 'Data de nascimento é obrigatória!';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $erros[] = 'Password deve ter pelo menos 6 caracteres!';
    }
    
    if ($password !== $password_confirm) {
        $erros[] = 'As passwords não coincidem!';
    }
    
    if (empty($tipo_utilizador)) {
        $erros[] = 'Tipo de utilizador é obrigatório!';
    }
    
    if (empty($erros)) {
        // Verificar se o email já existe
        $sql_check = "SELECT id_validação FROM validação_utilizador WHERE email_utilizador = ? OR nome_utilizador = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $email, $nome_utilizador);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $erros[] = 'Este email ou nome de utilizador já está registado!';
        } else {
            // Preparar inserção na tabela de validação
            $sql = "INSERT INTO validação_utilizador 
                    (nome_utilizador, email_utilizador, telefone_utilizador, primeiro_nome, último_nome, 
                     data_nascimento, password, tipo_utilizador, foto_perfil) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $erros[] = 'Erro na base de dados: ' . $conn->error;
            } else {
                // Usar foto padrão
                $foto_padrao = '';
                
                // Vincular parâmetros
                $stmt->bind_param("sssssssss", 
                    $nome_utilizador, 
                    $email, 
                    $telefone, 
                    $primeiro_nome, 
                    $ultimo_nome, 
                    $data_nascimento, 
                    $password, 
                    $tipo_utilizador,
                    $foto_padrao
                );
                
                // Executar inserção
                if ($stmt->execute()) {
                    // Sucesso - redirecionar com mensagem
                    $_SESSION['sucesso'] = 'Registo realizado com sucesso! Aguarde aprovação do administrador.';
                    header('Location: login.php');
                    exit();
                } else {
                    $erros[] = 'Erro ao registar utilizador: ' . $stmt->error;
                }
                
                $stmt->close();
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
* {
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: #ffffff;
    animation: pageIn 0.4s ease-out;
}

/* LOGO TOPO ESQUERDO */
.header {
    position: fixed;
    top: 32px;
    left: 40px;
    z-index: 10;
}

.header img {
    height: 80px; /* LOGO MAIOR */
}

/* CONTEÚDO CENTRAL */
.main {
    min-height: 100vh;
    padding: 120px 80px 60px; /* espaço p/ logo e respiro */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD GIGANTE */
.card {
    position: relative;
    width: 100%;
    max-width: 1200px;
    min-height: 75vh; /* QUASE FULLSCREEN */
    padding: 80px 72px 60px;
    background: #fff;
    border-radius: 32px;
    border: 1px solid #e3e3e3;

    box-shadow:
        0 16px 32px rgba(0,0,0,0.06),
        0 60px 120px rgba(0,0,0,0.10);
}

/* STEPPER NO TOPO DIREITO DO CARD */
.steps {
    position: absolute;
    top: 32px;
    right: 48px;
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

.step.inactive {
    background: #c6c6c6;
}

.line {
    width: 64px;
    height: 3px;
    background: #000;
}

.line.inactive {
    background: #d5d5d5;
}

/* FORM */
.form-grid {
    margin-top: 64px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    row-gap: 56px;
    column-gap: 48px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

label {
    font-size: 14px;
    font-weight: 500;
}

input,
select {
    padding: 20px 24px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f2f2f2;
    font-size: 16px;
    outline: none;
}

input:focus,
select:focus {
    border-color: #000;
    background: #fafafa;
}

select {
    cursor: pointer;
}

/* Mensagens de erro */
.alert {
    grid-column: 1 / -1;
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    animation: slideDown 0.3s ease-out;
}

.alert-error {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

.alert li {
    margin: 5px 0;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

.btn-submit:hover {
    opacity: 0.85;
}

.btn-link {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #fff;
    cursor: pointer;
}


@keyframes pageIn {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* MOBILE */
@media (max-width: 900px) {
    .main {
        padding: 120px 24px 40px;
    }

    .card {
        min-height: auto;
        padding: 96px 32px 40px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .btn-container {
        grid-column: 1;
    }

    .steps {
        right: 24px;
        top: 24px;
    }
}
</style>
</head>

<body>

<!-- LOGO -->
<div class="header">
    <img src="assets/kroos-logo.png" alt="Kroos">
</div>

<div class="main">

    <!-- CARD -->
    <div class="card">

        <!-- STEPPER -->
        <div class="steps">
            <div class="step">1</div>
            <div class="line"></div>
            <div class="step inactive">2</div>
            <div class="line inactive"></div>
            <div class="step inactive">3</div>
        </div>

        <!-- FORM -->
        <form method="post" action="">
            <div class="form-grid">

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-error">
                        <strong>Erros encontrados:</strong>
                        <ul>
                            <?php foreach ($erros as $erro_msg): ?>
                                <li><?php echo htmlspecialchars($erro_msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="input-group">
                    <label>Primeiro Nome</label>
                    <input type="text" name="primeiro_nome" placeholder="Introduzir primeiro nome" value="<?php echo htmlspecialchars($primeiro_nome); ?>" required>
                </div>

                <div class="input-group">
                    <label>Último Nome</label>
                    <input type="text" name="ultimo_nome" placeholder="Introduzir último nome" value="<?php echo htmlspecialchars($ultimo_nome); ?>" required>
                </div>

                <div class="input-group">
                    <label>Nome de Utilizador</label>
                    <input type="text" name="nome_utilizador" placeholder="Introduzir nome de utilizador" value="<?php echo htmlspecialchars($nome_utilizador); ?>" required>
                </div>

                <div class="input-group">
                    <label>Telefone</label>
                    <input type="tel" name="telefone" placeholder="Introduzir telefone" value="<?php echo htmlspecialchars($telefone); ?>">
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Introduzir email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="input-group">
                    <label>Data de Nascimento</label>
                    <input type="date" name="data_nascimento" value="<?php echo htmlspecialchars($data_nascimento); ?>" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Introduzir password (mín. 6 caracteres)" required>
                </div>

                <div class="input-group">
                    <label>Confirmar Password</label>
                    <input type="password" name="password_confirm" placeholder="Confirmar password" required>
                </div>

                <div class="input-group">
                    <label>Tipo de Utilizador</label>
                    <select name="tipo_utilizador" required>
                        <option value="">Selecione um tipo...</option>
                        <option value="jogador" <?php echo ($tipo_utilizador === 'jogador') ? 'selected' : ''; ?>>Jogador</option>
                        <option value="treinador" <?php echo ($tipo_utilizador === 'treinador') ? 'selected' : ''; ?>>Treinador</option>
                        <option value="admin_clube" <?php echo ($tipo_utilizador === 'admin_clube') ? 'selected' : ''; ?>>Admin Clube</option>
                    </select>
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn-submit">
                        Registar
                    </button>
                </div>

            </div>
        </form>

    </div>

</div>

</body>
</html>
