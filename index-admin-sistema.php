<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kroos - Painel do Administrador de Sistema</title>
    <!-- Ícones e Fonte Arial (padrão do projeto) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================================================
           RESET & ESTILOS BASE (Baseado no design do Kroos Project)
           ========================================================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f5f7;
            color: #172b4d;
            display: flex;
            min-height: 100vh;
        }

        /* ==========================================================================
           MENU LATERAL (SIDEBAR - Ecrã 4.11.8)
           ========================================================================== */
        .sidebar {
            width: 260px;
            background-color: #1e1e1e;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #333;
        }

        .sidebar-header i {
            font-size: 24px;
            color: #ffffff;
        }

        .sidebar-header h2 {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .sidebar-badge {
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            flex-grow: 1;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 24px;
            color: #aaaaaa;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .sidebar-menu li a:hover,
        .sidebar-menu li.active a {
            color: #ffffff;
            background-color: #2a2a2a;
            border-left: 4px solid #ffffff;
        }

        .sidebar-menu li a i {
            width: 20px;
            text-align: center;
        }

        /* ==========================================================================
           ÁREA PRINCIPAL (MAIN CONTENT)
           ========================================================================== */
        .main-wrapper {
            margin-left: 260px;
            width: calc(100% - 260px);
            display: flex;
            flex-direction: column;
        }

        /* CABEÇALHO SUPERIOR (TOPBAR) */
        .topbar {
            background-color: #ffffff;
            height: 70px;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-title h1 {
            font-size: 20px;
            font-weight: bold;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            position: relative;
        }

        .topbar-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ccc;
        }

        .user-info {
            text-align: right;
        }

        .user-info .name {
            font-weight: bold;
            font-size: 14px;
            display: block;
        }

        .user-info .role {
            font-size: 12px;
            color: #777;
        }

        /* DROPDOWN MENU (Ecrã 4.11.7) */
        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            width: 180px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            overflow: hidden;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-dropdown a {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .profile-dropdown a:hover {
            background-color: #f8f9fa;
        }

        .profile-dropdown a.logout {
            color: #d9534f;
            font-weight: bold;
        }

        /* CONTEÚDO PRINCIPAL */
        .content {
            padding: 30px;
        }

        /* CARTÕES DE ESTATÍSTICAS / RESUMO */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card .info h3 {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .info p {
            font-size: 13px;
            color: #666;
        }

        .stat-card .icon {
            font-size: 30px;
            color: #333;
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
        }

        /* PAINEL DE TABELA & ACÇÕES */
        .panel {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 25px;
            margin-bottom: 30px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .panel-title {
            font-size: 18px;
            font-weight: bold;
        }

        /* BOTOES */
        .btn {
            padding: 9px 16px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .btn-dark {
            background-color: #000000;
            color: #ffffff;
        }

        .btn-success {
            background-color: #28a745;
            color: #ffffff;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000000;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #ffffff;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* TABELAS (Estilo Index de Atletas/Treinadores) */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        th {
            background-color: #fafafa;
            font-weight: bold;
            color: #555;
        }

        tr:hover {
            background-color: #f9f9f9;
        }

        .user-photo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 10px;
        }

        /* ESTADOS / BADGES (Semáforo do relatório) */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-active { background-color: #d4edda; color: #155724; } /* Verde */
        .status-pending { background-color: #fff3cd; color: #856404; } /* Amarelo */
        .status-blocked { background-color: #f8d7da; color: #721c24; } /* Vermelho */

        /* NAVEGAÇÃO INTERNA / SEPOSITORES DE GESTÃO */
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .tab-item {
            padding: 8px 16px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .tab-item.active {
            background-color: #000000;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- MENU LATERAL DA APLICAÇÃO -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fa-solid fa-brain"></i>
            <h2>Kroos</h2>
            <span class="sidebar-badge">SYS ADMIN</span>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="#"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="#"><i class="fa-solid fa-users"></i> Utilizadores</a></li>
            <li><a href="#"><i class="fa-solid fa-shield-halved"></i> Clubes</a></li>
            <li><a href="#"><i class="fa-solid fa-trophy"></i> Competições</a></li>
            <li><a href="#"><i class="fa-solid fa-building-flag"></i> Estádios</a></li>
            <li><a href="#"><i class="fa-solid fa-dumbbell"></i> Exercícios/Treinos</a></li>
            <li><a href="#"><i class="fa-solid fa-arrow-right-arrow-left"></i> Transferências</a></li>
            <li><a href="#"><i class="fa-solid fa-gear"></i> Definições do Sistema</a></li>
        </ul>
    </aside>

    <!-- ÁREA DE CONTEÚDO PRINCIPAL -->
    <div class="main-wrapper">
        
        <!-- CABEÇALHO SUPERIOR -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Painel de Administração Global do Sistema</h1>
            </div>

            <!-- MENU DO PERFIL -->
            <div class="topbar-user" onclick="toggleDropdown()">
                <div class="user-info">
                    <span class="name">Administrador Global</span>
                    <span class="role">Admin do Sistema</span>
                </div>
                <img src="https://via.placeholder.com/40" alt="Foto Perfil Admin">
                <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #777;"></i>

                <!-- DROPDOWN (Ecrã 4.11.7) -->
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="#"><i class="fa-solid fa-user"></i> Perfil Admin</a>
                    <a href="#"><i class="fa-solid fa-sliders"></i> Sistema</a>
                    <a href="#" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Terminar Sessão</a>
                </div>
            </div>
        </header>

        <!-- CONTEÚDO DO DASHBOARD -->
        <main class="content">
            
            <!-- CARTÕES DE VISÃO GERAL -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="info">
                        <h3>1,240</h3>
                        <p>Total de Utilizadores</p>
                    </div>
                    <div class="icon"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h3>48</h3>
                        <p>Clubes Registados</p>
                    </div>
                    <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h3>12</h3>
                        <p>Pedidos Pendentes</p>
                    </div>
                    <div class="icon" style="color: #d9534f;"><i class="fa-solid fa-user-clock"></i></div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h3>156</h3>
                        <p>Jogos Registados</p>
                    </div>
                    <div class="icon"><i class="fa-solid fa-futbol"></i></div>
                </div>
            </div>

            <!-- PAINEL CENTRAL DE GESTÃO DO SISTEMA -->
            <div class="panel">
                
                <!-- NAVEGAÇÃO DE SECÇÕES ADMINISTRATIVAS -->
                <div class="tab-nav">
                    <div class="tab-item active"><i class="fa-solid fa-user-shield"></i> Validação / Utilizadores</div>
                    <div class="tab-item"><i class="fa-solid fa-building"></i> Gestão de Clubes</div>
                    <div class="tab-item"><i class="fa-solid fa-sitemap"></i> Equipas & Escalões</div>
                    <div class="tab-item"><i class="fa-solid fa-database"></i> Registos Globais</div>
                </div>

                <div class="panel-header">
                    <div class="panel-title">Gestão e Controlo de Utilizadores</div>
                    <div>
                        <button class="btn btn-dark"><i class="fa-solid fa-plus"></i> Adicionar Utilizador</button>
                    </div>
                </div>

                <!-- TABELA DE UTILIZADORES (Permite Adicionar, Editar e Eliminar) -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilizador</th>
                                <th>Email</th>
                                <th>Tipo (Função)</th>
                                <th>Data Registo</th>
                                <th>Estado</th>
                                <th style="text-align: right;">Ações de Controlo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Exemplo 1: Utilizador Pendente de Aprovação -->
                            <tr>
                                <td>#1042</td>
                                <td>
                                    <img src="https://via.placeholder.com/36" class="user-photo" alt="User">
                                    <strong>Carlos Ramos</strong>
                                </td>
                                <td>carlos.ramos@gmail.com</td>
                                <td><span class="badge">admin_clube</span></td>
                                <td>24/08/2026</td>
                                <td><span class="status-badge status-pending">Pendente</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-success btn-sm" title="Aprovar/Validar"><i class="fa-solid fa-check"></i> Aprovar</button>
                                    <button class="btn btn-warning btn-sm" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <!-- Exemplo 2: Treinador Ativo -->
                            <tr>
                                <td>#1041</td>
                                <td>
                                    <img src="https://via.placeholder.com/36" class="user-photo" alt="User">
                                    <strong>Miguel Custódio</strong>
                                </td>
                                <td>miguel.custodio@ipcb.pt</td>
                                <td><span class="badge">treinador</span></td>
                                <td>20/08/2026</td>
                                <td><span class="status-badge status-active">Ativo</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-warning btn-sm" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                    <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </td>
                            </tr>
                            <!-- Exemplo 3: Jogador Ativo -->
                            <tr>
                                <td>#1040</td>
                                <td>
                                    <img src="https://via.placeholder.com/36" class="user-photo" alt="User">
                                    <strong>Simão Major</strong>
                                </td>
                                <td>simao.major@ipcb.pt</td>
                                <td><span class="badge">jogador</span></td>
                                <td>18/08/2026</td>
                                <td><span class="status-badge status-active">Ativo</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-warning btn-sm" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                    <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </td>
                            </tr>
                            <!-- Exemplo 4: Utilizador Bloqueado -->
                            <tr>
                                <td>#1039</td>
                                <td>
                                    <img src="https://via.placeholder.com/36" class="user-photo" alt="User">
                                    <strong>João Silva</strong>
                                </td>
                                <td>joao.silva@teste.com</td>
                                <td><span class="badge">treinador</span></td>
                                <td>10/08/2026</td>
                                <td><span class="status-badge status-blocked">Bloqueado</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-warning btn-sm" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                    <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <!-- SCRIPT SIMPLES PARA INTERATIVIDADE -->
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        // Fechar dropdown se clicar fora dele
        window.onclick = function(event) {
            if (!event.target.closest('.topbar-user')) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown && dropdown.classList.contains('active')) {
                    dropdown.classList.remove('active');
                }
            }
        }
    </script>
</body>
</html>