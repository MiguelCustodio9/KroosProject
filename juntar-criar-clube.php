<?php
// step1.php – Interface UI (Step 1)
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
            background: #fff;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 30px 60px;
        }

        .logo img {
            height: 42px;
        }

        /* Stepper */
        .stepper {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .step {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
        }

        .step.active {
            background: #000;
        }

        .step.inactive {
            background: #bfbfbf;
        }

        .line {
            width: 50px;
            height: 3px;
            background: #000;
        }

        .line.inactive {
            background: #cfcfcf;
        }

        /* Main content */
        .container {
            display: flex;
            justify-content: center;
            gap: 80px;
            margin-top: 60px;
        }

        .card {
            width: 320px;
            background: #e6e6e6;
            border-radius: 30px;
            padding: 30px;
            text-align: center;
        }

        .card h3 {
            margin-bottom: 24px;
            font-weight: 600;
        }

        .icon-box {
            width: 120px;
            height: 120px;
            background: #fff;
            border-radius: 24px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-box img {
            width: 70px;
        }

        /* Join input */
        .join {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-radius: 999px;
            padding: 6px 6px 6px 16px;
        }

        .join input {
            border: none;
            outline: none;
            font-size: 14px;
            width: 100%;
        }

        .join button {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: none;
            background: #000;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
        }

        /* Create button */
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 999px;
            border: none;
            background: #000;
            color: #fff;
            font-size: 15px;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: center;
                gap: 40px;
            }

            .header {
                flex-direction: column;
                gap: 30px;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div class="logo">
            <img src="assets/kroos-logo.png" alt="Kroos">
        </div>

        <div class="stepper">
            <div class="step active">1</div>
            <div class="line"></div>
            <div class="step active">2</div>
            <div class="line inactive"></div>
            <div class="step inactive">3</div>
        </div>
    </div>

    <!-- Cards -->
    <div class="container">

        <!-- Juntar a clube -->
        <div class="card">
            <h3>Juntar a clube:</h3>

            <div class="icon-box">
                <img src="assets/join.png" alt="Juntar">
            </div>

            <form method="post" action="#">
                <div class="join">
                    <input type="text" name="codigo" placeholder="Introduzir código">
                    <button type="submit">→</button>
                </div>
            </form>
        </div>

        <!-- Criar clube -->
        <div class="card">
            <h3>Criar clube:</h3>

            <div class="icon-box">
                <img src="assets/create.png" alt="Criar">
            </div>

            <form method="post" action="#">
                <button class="btn" type="submit">
                    Criar
                </button>
            </form>
        </div>

    </div>

</body>
</html>
