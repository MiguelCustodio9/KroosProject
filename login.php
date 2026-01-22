<?php
session_start();
include 'basedados.h';

// Verificar se já está autenticado
if (isset($_SESSION['id_utilizador'])) {
    header('Location: index.php');
    exit();
}

$erro = '';
$sucesso = '';

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obter dados do formulário
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validação básica
    if (empty($email) || empty($password)) {
        $erro = 'Por favor, preencha todos os campos!';
    } else {
        // Preparar query (usar prepared statements para segurança)
        $sql = "SELECT id_utilizador, nome_utilizador, email_utilizador, tipo_utilizador, password 
                FROM utilizador 
                WHERE email_utilizador = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $erro = 'Erro na base de dados: ' . $conn->error;
        } else {
            // Vincular parâmetros
            $stmt->bind_param("s", $email);
            
            // Executar query
            $stmt->execute();
            
            // Obter resultado
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $usuario = $result->fetch_assoc();
                
                // A password vem já encriptada (MD5) da base de dados via trigger
                // Comparar a password encriptada com MD5 da password introduzida
                $password_md5 = md5($password);
                
                if ($password_md5 === $usuario['password']) {
                    // Login bem-sucedido
                    $_SESSION['id_utilizador'] = $usuario['id_utilizador'];
                    $_SESSION['nome_utilizador'] = $usuario['nome_utilizador'];
                    $_SESSION['email_utilizador'] = $usuario['email_utilizador'];
                    $_SESSION['tipo_utilizador'] = $usuario['tipo_utilizador'];
                    
                    // Redirecionar para página inicial ou dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    // Password incorreta
                    $erro = 'Email ou password incorretos!';
                }
            } else {
                // Utilizador não encontrado
                $erro = 'Email ou password incorretos!';
            }
            
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Kroos | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonte semelhante à do mockup -->
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
            transition:opacity 0.25s ease, transform 0.25s ease;
        }

        /* Cartão principal */
        .card {
            width: 520px;
            padding: 56px 48px;
            border: 1px solid #e0e0e0;
            border-radius: 24px;
            background: #fff;

            box-shadow:
                0 12px 24px rgba(0, 0, 0, 0.06),
                0 40px 80px rgba(0, 0, 0, 0.08);
                
            transition:transform 0.25s ease, box-shadow 0.25s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow:
                0 16px 32px rgba(0, 0, 0, 0.12),
                0 48px 96px rgba(0, 0, 0, 0.16);
        }

        /* Logo */
        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }

        .logo img {
            height: 72px;
        }

        /* Inputs */
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

        .field input::placeholder {
            color: #555;
        }

        /* Esqueceu password */
        .forgot {
            text-align: right;
            font-size: 13px;
            margin-top: -14px;
            margin-bottom: 24px;
        }

        .forgot a {
            color: #333;
            text-decoration: none;
        }

        /* Botões */
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 999px;
            border: none;
            font-size: 15px;
            cursor: pointer;
        }

        .btn-link {
            display: flex;
        align-items: center;
        justify-content: center;

        text-decoration: none;
        color: #fff;

        cursor: pointer;
    }


        .btn-primary {
            background: #000;
            color: #fff;
            margin-bottom: 18px;
        }

        .btn-google {
            background: #fff;
            border: 1.5px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .btn-google img {
            height: 18px;
        }

        /* Separador */
        .divider {
            height: 1px;
            background: #ddd;
            margin: 10px 0 18px;
        }

        /* Criar conta */
        .signup-text {
            text-align: center;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .btn-secondary {
            background: #000;
            color: #fff;
        }

        /* Mensagens de erro/sucesso */
        .alert {
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

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
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

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="field">
                <input type="email" name="email" placeholder="Email" required>
            </div>

            <div class="field">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="forgot">
                <a href="#">Esqueceu-se da password?</a>
            </div>

            <button class="btn btn-primary" type="submit">
                Login
            </button>

            <button class="btn btn-google" type="button">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
                Continuar com Google
            </button>

            <div class="divider"></div>

            <div class="signup-text">
                Ainda não tem conta?
            </div>

            <a href="criar-utilizador.php" class="btn btn-secondary btn-link" id="goRegister">
                Criar Conta
            </a>
        </form>

    </div>
<script>
document.getElementById('goRegister').addEventListener('click', function (e) {
    e.preventDefault();
    document.body.style.opacity = '0';
    document.body.style.transform = 'translateY(-12px)';

    setTimeout(() => {
        window.location.href = this.href;
    }, 250);
});
</script>

</body>
</html>
