<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Kroos | Registo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonte semelhante à do mockup -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">


    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }


        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }

        /* Estilo dos Passos (1, 2, 3) */
        .steps {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #ccc;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 20px;
        }

        .step.active {
            background-color: black;
        }

        .line {
            width: 40px;
            height: 2px;
            background-color: #ccc;
        }

        /* Grelha do Formulário */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 40px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-weight: 500;
            font-size: 14px;
        }

        input {
            padding: 12px 20px;
            border-radius: 25px;
            border: 1px solid #ccc;
            background-color: #e9e9e9;
            font-size: 14px;
            outline: none;
        }

        input::placeholder {
            color: #999;
        }

        /* Botão Continuar */
        .btn-container {
            grid-column: 2;
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-submit {
            background-color: black;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 30px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-submit:hover {
            opacity: 0.8;
        }

        @media (max-width: 480px) {
            .card {
                width: 90%;
                padding: 32px 24px;
            }
        }

    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="logo">
            <img src="assets/kroos-logo.png" alt="Kroos">
        </div>
        <div class="steps">
            <div class="step active">1</div>
            <div class="line"></div>
            <div class="step">2</div>
            <div class="line"></div>
            <div class="step">3</div>
        </div>
    </header>

    <form method="POST" action="">
        <div class="form-grid">
            <div class="input-group">
                <label>Nome:</label>
                <input type="text" name="nome" placeholder="Introduzir nome" required>
            </div>

            <div class="input-group">
                <label>Password:</label>
                <input type="password" name="password" placeholder="Introduzir password" required>
            </div>

            <div class="input-group">
                <label>Sobrenome:</label>
                <input type="text" name="sobrenome" placeholder="Introduzir sobrenome" required>
            </div>

            <div class="input-group">
                <label>Confirmar password:</label>
                <input type="password" name="confirm_password" placeholder="Introduzir confirmação" required>
            </div>

            <div class="input-group">
                <label>Email:</label>
                <input type="email" name="email" placeholder="Introduzir email" required>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn-submit">Continuar</button>
            </div>
        </div>
    </form>
</div>

</body>
</html>