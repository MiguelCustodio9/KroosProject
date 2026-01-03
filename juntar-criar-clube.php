<?php
// juntar-criar-clube.php – Step 2
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Kroos | Clube</title>
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
    transition: opacity 0.25s ease, transform 0.25s ease;
}

@keyframes pageIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* LOGO */
.header {
    position: fixed;
    top: 32px;
    left: 40px;
    z-index: 10;
}

.header img {
    height: 80px;
}

/* MAIN */
.main {
    min-height: 100vh;
    padding: 120px 80px 60px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD GIGANTE */
.card {
    position: relative;
    width: 100%;
    max-width: 1200px;
    max-height: calc(100vh - 200px);
    padding: 72px 72px 56px;
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

.step.inactive { background: #c6c6c6; }

.line {
    width: 64px;
    height: 3px;
    background: #000;
}

.line.inactive { background: #d5d5d5; }

/* OPÇÕES */
.club-options {
    margin-top: 80px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 80px;
}

/* BOX */
.option {
    background: #f1f1f1;
    border-radius: 30px;
    padding: 40px;
    text-align: center;
}

.option h3 {
    margin-bottom: 28px;
    font-weight: 600;
}

/* ICON BOX (IGUAL AO ORIGINAL) */
.icon-box {
    width: 150px;
    height: 150px;
    background: #fff;
    border-radius: 28px;
    margin: 0 auto 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-box img {
    width: 95px;
}

/* JOIN */
.join {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 999px;
    padding: 8px 8px 8px 20px;
}

.join input {
    border: none;
    outline: none;
    font-size: 15px;
    width: 100%;
}

.join button {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: none;
    background: #000;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
}

/* BOTÃO */
.btn {
    width: 100%;
    padding: 18px;
    border-radius: 999px;
    border: none;
    background: #000;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
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


/* MOBILE */
@media (max-width: 900px) {
    .main {
        padding: 120px 24px 40px;
    }

    .card {
        padding: 96px 32px 40px;
    }

    .club-options {
        grid-template-columns: 1fr;
        gap: 48px;
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

    <div class="card">

        <!-- STEPPER -->
        <div class="steps">
            <div class="step">1</div>
            <div class="line"></div>
            <div class="step">2</div>
            <div class="line"></div>
            <div class="step inactive">3</div>
        </div>

        <!-- OPÇÕES -->
        <div class="club-options">

            <!-- JUNTAR -->
            <div class="option">
                <h3>Juntar a clube</h3>

                <div class="icon-box">
                    <img src="assets/join.png" alt="Juntar">
                </div>

                <form>
                    <div class="join">
                        <input type="text" placeholder="Introduzir código">
                        <button type="submit">→</button>
                    </div>
                </form>
            </div>

            <!-- CRIAR -->
            <div class="option">
                <h3>Criar clube</h3>

                <div class="icon-box">
                    <img src="assets/create.png" alt="Criar">
                </div>

                <a href="criar-clube.php" class="btn btn-link" id="goCreateClub">
                     Criar
                </a>

            </div>

        </div>

    </div>

</div>
<script>
document.getElementById('goCreateClub').addEventListener('click', function (e) {
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
