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

input {
    padding: 20px 24px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f2f2f2;
    font-size: 16px;
    outline: none;
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
        <form>
            <div class="form-grid">

                <div class="input-group">
                    <label>Nome</label>
                    <input type="text" placeholder="Introduzir nome">
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" placeholder="Introduzir password">
                </div>

                <div class="input-group">
                    <label>Sobrenome</label>
                    <input type="text" placeholder="Introduzir sobrenome">
                </div>

                <div class="input-group">
                    <label>Confirmar password</label>
                    <input type="password" placeholder="Confirmar password">
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="email" placeholder="Introduzir email">
                </div>

                <div class="btn-container">
                    <button class="btn-submit">Continuar</button>
                </div>

            </div>
        </form>

    </div>

</div>

</body>
</html>
