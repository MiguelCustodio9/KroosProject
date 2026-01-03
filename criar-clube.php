<?php
// criar-clube.php – Step 3 (Criar Clube)
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

/* CARD */
.card {
    position: relative;
    width: 100%;
    max-width: 1200px;
    max-height: calc(100vh - 200px);
    padding: 72px;
    background: #fff;
    border-radius: 32px;
    border: 1px solid #e3e3e3;

    box-shadow:
        0 16px 32px rgba(0,0,0,0.06),
        0 60px 120px rgba(0,0,0,0.10);

    overflow-y: auto;
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

.line {
    width: 64px;
    height: 3px;
    background: #000;
}

/* GRID */
.form-grid {
    margin-top: 40px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
}

/* INPUTS */
.input-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

label {
    font-size: 14px;
    font-weight: 500;
}

input, select {
    padding: 16px 22px;
    border-radius: 999px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    font-size: 15px;
    outline: none;
}

/* LOGO UPLOAD */
.logo-box {
    width: 220px;
    height: 220px;
    max-width: 100%;
    max-height: 100%;
    background: #e6e6e6;
    border-radius: 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-direction: column;
    gap: 6px;
    position: relative;
    overflow: hidden;
}

.gallery-icon {
    width: 64px;
    height: auto;
    opacity: 0.85;
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
    margin: auto;
    z-index: 2;
    object-fit: contain;
}

.logo-box span {
    text-align: center;
    color: #9a9a9a;
    font-size: 14px;
}

.logo-box img#logoPreview {
    max-width: 100%;
    max-height: 100%;
    border-radius: 24px;
}

/* COLOR PICKER */
.color-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-picker input[type="color"] {
    width: 48px;
    height: 48px;
    border: 1px solid #999;
    border-radius: 6px;
    padding: 0;
    cursor: pointer;
}

.color-picker input[type="text"] {
    width: 120px;
    padding: 14px 18px;
    border-radius: 6px;
    border: 1px solid #999;
    font-family: monospace;
    font-size: 14px;
}

/* BOTÃO */
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

/* MOBILE */
@media (max-width: 900px) {
    .main {
        padding: 120px 24px 40px;
    }

    .card {
        padding: 48px 32px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .btn-container {
        justify-content: center;
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
            <div class="step">3</div>
        </div>

        <form>

            <div class="form-grid">

                <!-- ESQUERDA -->
                <div>
                    <div class="input-group">
                        <label>Nome do clube</label>
                        <input type="text" placeholder="Introduzir nome">
                    </div>

                    <div class="input-group">
                        <label>Sigla / Acrónimo</label>
                        <input type="text" placeholder="Introduzir sigla/acrónimo">
                    </div>

                    <div class="input-group">
                        <label>Associação</label>
                        <select>
                            <option>Selecione a associação</option>
                        </select>
                    </div>
                </div>

                <!-- DIREITA -->
                <div>
                    <label>Logótipo</label>
                    <div class="logo-box" id="logoBox">
                        <img src="assets/image-gallery.png" alt="Galeria" class="gallery-icon" id="galleryIcon">
                        <span id="logoText">Carregar<br>ficheiro</span>
                        <img id="logoPreview" style="display:none;">
                    </div>

                    <input type="file" id="logoInput" accept="image/*" style="display:none;">


                    <label>Escolher cor principal</label>
                    <div class="color-picker">
                        <input type="color" id="colorPicker" value="#FFFFFF">
                        <input type="text" id="colorHex" value="#FFFFFF">
                    </div>

                </div>

            </div>

            <div class="btn-container">
                <button class="btn">Continuar</button>
            </div>

        </form>

    </div>
</div>
<script>
const logoBox = document.getElementById('logoBox');
const logoInput = document.getElementById('logoInput');
const logoPreview = document.getElementById('logoPreview');
const logoText = document.getElementById('logoText');
const galleryIcon = document.getElementById('galleryIcon');

logoBox.addEventListener('click', () => {
    logoInput.click();
});

logoInput.addEventListener('change', () => {
    const file = logoInput.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        // mostrar preview
        logoPreview.src = e.target.result;
        logoPreview.style.display = 'block';

        // ESCONDER completamente os placeholders
        galleryIcon.style.display = 'none';
        logoText.style.display = 'none';
    };
    reader.readAsDataURL(file);
});

const colorPicker = document.getElementById('colorPicker');
const colorHex = document.getElementById('colorHex');

// Quando escolhes no painel
colorPicker.addEventListener('input', () => {
    colorHex.value = colorPicker.value.toUpperCase();
});

// Quando escreves à mão
colorHex.addEventListener('input', () => {
    const value = colorHex.value;
    if (/^#([0-9A-Fa-f]{6})$/.test(value)) {
        colorPicker.value = value;
    }
});
</script>

</body>
</html>
