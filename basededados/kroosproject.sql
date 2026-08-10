-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 29-Jan-2026 às 16:27
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `kroosproject`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `acesso_equipa`
--

CREATE TABLE `acesso_equipa` (
  `id_acesso` int(11) NOT NULL,
  `id_equipa` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `assiduidade`
--

CREATE TABLE `assiduidade` (
  `id_assiduidade` int(11) NOT NULL,
  `id_treino` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `estado` enum('Presente','Não Presente Justificado','Não Presente Injustificado','Lesionado Presente','Lesionado Não Presente') NOT NULL,
  `justificação_ausência` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `clube`
--

CREATE TABLE `clube` (
  `id_clube` int(11) NOT NULL,
  `nome_clube` varchar(100) NOT NULL,
  `sigla` char(5) NOT NULL,
  `logotipo` mediumblob NOT NULL,
  `cor` char(7) NOT NULL,
  `data_fundação` date DEFAULT NULL,
  `sede_morada` varchar(100) DEFAULT NULL,
  `país_clube` varchar(50) DEFAULT NULL,
  `cidade_clube` varchar(50) DEFAULT NULL,
  `telefone_clube` varchar(20) DEFAULT NULL,
  `email_clube` varchar(255) DEFAULT NULL,
  `website_clube` varchar(100) DEFAULT NULL,
  `presidente_clube` varchar(100) DEFAULT NULL,
  `instagram_clube` varchar(100) DEFAULT NULL,
  `facebook_clube` varchar(100) DEFAULT NULL,
  `youtube_clube` varchar(100) DEFAULT NULL,
  `twitter_clube` varchar(100) DEFAULT NULL,
  `tiktok_clube` varchar(100) DEFAULT NULL,
  `código_clube` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `competição`
--

CREATE TABLE `competição` (
  `id_competição` int(11) NOT NULL,
  `id_nome_competição` int(11) NOT NULL,
  `época` int(11) NOT NULL,
  `número_fases` int(11) NOT NULL,
  `id_vencedor` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `competição_default`
--

CREATE TABLE `competição_default` (
  `id_competição_default` int(11) NOT NULL,
  `nome_competição` varchar(100) NOT NULL,
  `tipo_competição` enum('Prova a eliminar','Prova por jornadas') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `convocatória`
--

CREATE TABLE `convocatória` (
  `id_convocatória` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `estado` enum('Convocado','Não Convocado','Lesionado') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `detalhes_jogo`
--

CREATE TABLE `detalhes_jogo` (
  `id_detalhes_jogo` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `tipo_detalhes` enum('11 Inicial','Suplente Não Utilizado','Suplente Utilizado') NOT NULL,
  `minuto_detalhe` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59','60','61','62','63','64','65','66','67','68','69','70','71','72','73','74','75','76','77','78','79','80','81','82','83','84','85','86','87','88','89','90') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `equipa`
--

CREATE TABLE `equipa` (
  `id_equipa` int(11) NOT NULL,
  `escalão` enum('S5','S6','S7','S8','S9','S10','S11','S12','S13','S14','S15','S16','S17','S18','S19','S20','S21','S22','S23','Seniores') NOT NULL,
  `hierarquia` enum('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z') NOT NULL,
  `id_época` int(11) NOT NULL,
  `id_clube` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `estatísticas_jogadores`
--

CREATE TABLE `estatísticas_jogadores` (
  `id_stats` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `minutos_jogados` int(11) DEFAULT NULL,
  `golos` int(11) DEFAULT NULL,
  `remates` int(11) DEFAULT NULL,
  `remates_baliza` int(11) DEFAULT NULL,
  `assistências` int(11) DEFAULT NULL,
  `passes_chave` int(11) DEFAULT NULL,
  `passes` int(11) DEFAULT NULL,
  `passes_certos` int(11) DEFAULT NULL,
  `cruzamentos` int(11) DEFAULT NULL,
  `cruzamentos_certos` int(11) DEFAULT NULL,
  `toques_bola` int(11) DEFAULT NULL,
  `dribles` int(11) DEFAULT NULL,
  `dribles_certos` int(11) DEFAULT NULL,
  `perdas` int(11) DEFAULT NULL,
  `desarmes` int(11) DEFAULT NULL,
  `desarmes_ganhos` int(11) DEFAULT NULL,
  `interceções` int(11) DEFAULT NULL,
  `alívios` int(11) DEFAULT NULL,
  `bloqueios_remate` int(11) DEFAULT NULL,
  `duelos` int(11) DEFAULT NULL,
  `duelos_ganhos` int(11) DEFAULT NULL,
  `faltas_sofridas` int(11) DEFAULT NULL,
  `faltas_feitas` int(11) DEFAULT NULL,
  `amarelos` int(11) DEFAULT NULL,
  `vermelhos` int(11) DEFAULT NULL,
  `defesas` int(11) DEFAULT NULL,
  `remates_baliza_sofridos` int(11) DEFAULT NULL,
  `golos_sofridos` int(11) DEFAULT NULL,
  `clean_sheet` enum('Sim','Não') DEFAULT NULL,
  `saídas` int(11) DEFAULT NULL,
  `saídas_eficazes` int(11) DEFAULT NULL,
  `oportunidades_claras_defendidas` int(11) DEFAULT NULL,
  `class_média` decimal(10,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `estatísticas_jogo`
--

CREATE TABLE `estatísticas_jogo` (
  `id_stats` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `posse_casa` int(11) NOT NULL,
  `posse_fora` int(11) NOT NULL,
  `remates_casa` int(11) NOT NULL,
  `remates_fora` int(11) NOT NULL,
  `remates_baliza_casa` int(11) NOT NULL,
  `remates_baliza_fora` int(11) NOT NULL,
  `grandes_oportunidades_casa` int(11) NOT NULL,
  `grandes_oportunidades_fora` int(11) NOT NULL,
  `cantos_casa` int(11) NOT NULL,
  `cantos_fora` int(11) NOT NULL,
  `passes_casa` int(11) NOT NULL,
  `passes_fora` int(11) NOT NULL,
  `passes_certos_casa` int(11) NOT NULL,
  `passes_certos_fora` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `estádio`
--

CREATE TABLE `estádio` (
  `id_estádio` int(11) NOT NULL,
  `id_clube` int(11) NOT NULL,
  `nome_estádio` varchar(100) NOT NULL,
  `capacidade` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `eventos_clube`
--

CREATE TABLE `eventos_clube` (
  `id_evento` int(11) NOT NULL,
  `id_equipa` int(11) NOT NULL,
  `tipo_evento` enum('Treino','Jogo','Reunião Técnico-Tática','Sessão de Recuperação','Convívio de Equipa','Outro') NOT NULL,
  `descrição_evento` text DEFAULT NULL,
  `estado_evento` enum('Por realizar','Realizado','Cancelado','Adiado') NOT NULL,
  `data_evento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `eventos_jogo`
--

CREATE TABLE `eventos_jogo` (
  `id_evento` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `tipo_evento` enum('Golos','Amarelos','Vermelhos','Substituição') NOT NULL,
  `jogador_entrada` int(11) DEFAULT NULL,
  `jogador_saída` int(11) DEFAULT NULL,
  `jogador_assistência` int(11) DEFAULT NULL,
  `tipo_golo` enum('Construção Organizada','Transição','Canto Indireto','Canto Direto','Lançamento Lateral','Livre Indireto','Livre Direto','Grande Penalidade') DEFAULT NULL,
  `zona_golo` enum('Dentro da Área','Fora da Área') DEFAULT NULL,
  `zona_corpo_utilizado_golo` enum('Pé Esquerdo','Pé Direito','Cabeça','Outro') DEFAULT NULL,
  `minuto_evento` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59','60','61','62','63','64','65','66','67','68','69','70','71','72','73','74','75','76','77','78','79','80','81','82','83','84','85','86','87','88','89','90') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `exercícios`
--

CREATE TABLE `exercícios` (
  `id_exercício` int(11) NOT NULL,
  `esquema` mediumblob NOT NULL,
  `estrutura` varchar(50) DEFAULT NULL,
  `descrição_exercício` text NOT NULL,
  `variantes` text DEFAULT NULL,
  `fundamentos_ofensivos` text DEFAULT NULL,
  `fundamentos_defensivos` text DEFAULT NULL,
  `ações_ofensivas` text DEFAULT NULL,
  `ações_defensivas` text DEFAULT NULL,
  `duração` time NOT NULL,
  `repetições` int(11) DEFAULT NULL,
  `séries` int(11) DEFAULT NULL,
  `pausa_entre_repetições` int(11) DEFAULT NULL,
  `pausa_entre_séries` int(11) DEFAULT NULL,
  `volume_exercício` time NOT NULL,
  `recuperação_para_próximo` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `fase`
--

CREATE TABLE `fase` (
  `id_fase` int(11) NOT NULL,
  `fase` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `fase`
--

INSERT INTO `fase` (`id_fase`, `fase`) VALUES
(1, '1ª Jornada'),
(2, '2ª Jornada'),
(3, '3ª Jornada'),
(4, '4ª Jornada'),
(5, '5ª Jornada'),
(6, '6ª Jornada'),
(7, '7ª Jornada'),
(8, '8ª Jornada'),
(9, '9ª Jornada'),
(10, '10ª Jornada'),
(11, '11ª Jornada'),
(12, '12ª Jornada'),
(13, '13ª Jornada'),
(14, '14ª Jornada'),
(15, '15ª Jornada'),
(16, '16ª Jornada'),
(17, '17ª Jornada'),
(18, '18ª Jornada'),
(19, '19ª Jornada'),
(20, '20ª Jornada'),
(21, '21ª Jornada'),
(22, '22ª Jornada'),
(23, '23ª Jornada'),
(24, '24ª Jornada'),
(25, '25ª Jornada'),
(26, '26ª Jornada'),
(27, '27ª Jornada'),
(28, '28ª Jornada'),
(29, '29ª Jornada'),
(30, '30ª Jornada'),
(31, '31ª Jornada'),
(32, '32ª Jornada'),
(33, '33ª Jornada'),
(34, '34ª Jornada'),
(35, '35ª Jornada'),
(36, '36ª Jornada'),
(37, '37ª Jornada'),
(38, '38ª Jornada'),
(39, '39ª Jornada'),
(40, '40ª Jornada'),
(41, '1ª Eliminatória'),
(42, '2ª Eliminatória'),
(43, '3ª Eliminatória'),
(44, '4ª Eliminatória'),
(45, '5ª Eliminatória'),
(46, '32 Avos de Final'),
(47, '16 Avos de Final'),
(48, 'Oitavos de Final'),
(49, 'Quartos de Final'),
(50, 'Meias Finais'),
(51, 'Final'),
(52, '3º e 4º Lugar');

-- --------------------------------------------------------

--
-- Estrutura da tabela `histórico_carreira`
--

CREATE TABLE `histórico_carreira` (
  `id_carreira` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `id_época` int(11) NOT NULL,
  `id_clube` int(11) NOT NULL,
  `jogos` int(11) NOT NULL DEFAULT 0,
  `golos_marcados` int(11) NOT NULL DEFAULT 0,
  `golos_sofridos` int(11) DEFAULT NULL,
  `assistências` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `histórico_transferência`
--

CREATE TABLE `histórico_transferência` (
  `id_transferência` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `id_clube_origem` int(11) NOT NULL,
  `id_clube_destino` int(11) NOT NULL,
  `valor` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `jogadores`
--

CREATE TABLE `jogadores` (
  `id_jogador` int(11) NOT NULL,
  `foto_jogador` mediumblob NOT NULL,
  `nome_completo` varchar(100) NOT NULL,
  `alcunha_jogador` varchar(100) NOT NULL,
  `número_favorito` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59','60','61','62','63','64','65','66','67','68','69','70','71','72','73','74','75','76','77','78','79','80','81','81','82','83','84','85','86','87','88','89','90','91','92','93','94','95','96','97','98','99') DEFAULT NULL,
  `posição_principal` enum('Guarda-Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança') NOT NULL,
  `posição_secundária` enum('Guarda Redes','Defesa Central','Defesa Esquerdo','Defesa Direito','Ala Esquerdo','Ala Direito','Médio Defensivo','Médio Centro','Médio Esquerdo','Médio Direito','Médio Ofensivo','Extremo Esquerdo','Extremo Direito','Segundo Avançado','Ponta de Lança') DEFAULT NULL,
  `data_nascimento` date NOT NULL,
  `local_nascimento` varchar(100) DEFAULT NULL,
  `nacionalidade` varchar(100) NOT NULL,
  `país_nascimento` varchar(100) NOT NULL,
  `pé_preferencial` enum('Direito','Esquerdo','Ambos') DEFAULT NULL,
  `altura` varchar(3) DEFAULT NULL,
  `peso` varchar(3) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `id_equipa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `jogos`
--

CREATE TABLE `jogos` (
  `id_jogo` int(11) NOT NULL,
  `id_competição` int(11) NOT NULL,
  `id_fase` int(11) NOT NULL,
  `clube_casa` int(11) NOT NULL,
  `clube_visitante` int(11) NOT NULL,
  `data_jogo` date NOT NULL,
  `hora_jogo` time NOT NULL,
  `local_jogo` int(11) NOT NULL,
  `resultado_casa` int(11) DEFAULT NULL,
  `resultado_fora` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `lesões`
--

CREATE TABLE `lesões` (
  `id_lesão` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `nome_lesão` varchar(100) NOT NULL,
  `descrição_lesão` varchar(100) NOT NULL,
  `tipo_lesão` enum('Óssea','Muscular','Ligamentar/Articular','Neurológica','Cutânea') NOT NULL,
  `tempo_recuperação` enum('5 dias - 1 semana','1 - 2 semanas','2 - 3 semanas','3 - 1 mês','1 mês','1 - 2 meses','2 meses','2 - 3 meses','3 meses','4 meses','5 meses','6 meses','6 - 8 meses','8 - 10 meses','10 meses - 1 ano','1 ano','+1 ano') NOT NULL,
  `estado_lesão` enum('Lesionado','Em recuperação','Em retorno progressivo','Recuperado') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `mensagens`
--

CREATE TABLE `mensagens` (
  `id_mensagem` int(11) NOT NULL,
  `origem` int(11) NOT NULL,
  `destino` int(11) NOT NULL,
  `conteúdo` text NOT NULL,
  `estado` enum('Lida','Não Lida') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacao`
--

CREATE TABLE `notificacao` (
  `id_notificacao` int(11) NOT NULL,
  `id_utilizador` int(11) NOT NULL,
  `id_clube` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('info','sucesso','aviso','erro') NOT NULL DEFAULT 'info',
  `estado` enum('Nao Lida','Lida') NOT NULL DEFAULT 'Nao Lida',
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `lida_em` datetime DEFAULT NULL,
  `link_acao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `plano_treino`
--

CREATE TABLE `plano_treino` (
  `id_plano_treino` int(11) NOT NULL,
  `exercício_1` int(11) NOT NULL,
  `exercício_2` int(11) NOT NULL,
  `exercício_3` int(11) NOT NULL,
  `exercício_4` int(11) NOT NULL,
  `exercício_5` int(11) NOT NULL,
  `exercício_6` int(11) NOT NULL,
  `exercício_7` int(11) NOT NULL,
  `exercício_8` int(11) NOT NULL,
  `exercício_9` int(11) NOT NULL,
  `exercício_10` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `treino`
--

CREATE TABLE `treino` (
  `id_treino` int(11) NOT NULL,
  `número_treino` int(11) NOT NULL,
  `data` date NOT NULL,
  `hora` time NOT NULL,
  `conteúdo` text NOT NULL,
  `id_plano` int(11) DEFAULT NULL,
  `observações` text DEFAULT NULL,
  `dia_da_semana` enum('Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizador`
--

CREATE TABLE `utilizador` (
  `id_utilizador` int(11) NOT NULL,
  `nome_utilizador` varchar(255) NOT NULL,
  `foto_perfil` mediumblob DEFAULT NULL,
  `email_utilizador` varchar(255) NOT NULL,
  `telefone_utilizador` varchar(20) DEFAULT NULL,
  `primeiro_nome` varchar(50) NOT NULL,
  `último_nome` varchar(50) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_utilizador` enum('admin','treinador','jogador','admin_clube') NOT NULL,
  `tipo_treinador` enum('Treinador Principal','Treinador Adjunto','Treinador Estagiário','Treinador de Guarda Redes','Preparador Físico','Cientista Desportivo','Analista') DEFAULT NULL,
  `id_clube` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `utilizador`
--
DELIMITER $$
CREATE TRIGGER `tg_encrypt_password_insert` BEFORE INSERT ON `utilizador` FOR EACH ROW BEGIN
    SET NEW.password = MD5(NEW.password);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tg_encrypt_password_update` BEFORE UPDATE ON `utilizador` FOR EACH ROW BEGIN
    -- Só encripta se a senha enviada for diferente da senha atual
    IF NEW.password <> OLD.password THEN
        SET NEW.password = MD5(NEW.password);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `validação_transferência`
--

CREATE TABLE `validação_transferência` (
  `id_validação_transferência` int(11) NOT NULL,
  `id_jogador` int(11) NOT NULL,
  `id_clube_origem` int(11) NOT NULL,
  `id_clube_destino` int(11) NOT NULL,
  `valor` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `validação_utilizador`
--

CREATE TABLE `validação_utilizador` (
  `id_validação` int(11) NOT NULL,
  `nome_utilizador` varchar(255) NOT NULL,
  `foto_perfil` mediumblob DEFAULT NULL,
  `email_utilizador` varchar(255) NOT NULL,
  `telefone_utilizador` varchar(20) DEFAULT NULL,
  `primeiro_nome` varchar(50) NOT NULL,
  `último_nome` varchar(50) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_utilizador` enum('admin_clube','treinador','jogador') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `época`
--

CREATE TABLE `época` (
  `id_época` int(11) NOT NULL,
  `época` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `época`
--

INSERT INTO `época` (`id_época`, `época`) VALUES
(1, '2025/2026'),
(2, '2026/2027');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `acesso_equipa`
--
ALTER TABLE `acesso_equipa`
  ADD PRIMARY KEY (`id_acesso`),
  ADD KEY `fk_id_equipa_acesso` (`id_equipa`),
  ADD KEY `fk_id_utilizador` (`id_utilizador`);

--
-- Índices para tabela `assiduidade`
--
ALTER TABLE `assiduidade`
  ADD PRIMARY KEY (`id_assiduidade`),
  ADD KEY `fk_id_treino` (`id_treino`),
  ADD KEY `fk_id_jogador` (`id_jogador`);

--
-- Índices para tabela `clube`
--
ALTER TABLE `clube`
  ADD PRIMARY KEY (`id_clube`),
  ADD UNIQUE KEY `codigo_unico` (`código_clube`);

--
-- Índices para tabela `competição`
--
ALTER TABLE `competição`
  ADD PRIMARY KEY (`id_competição`),
  ADD KEY `fk_id_nome_competição` (`id_nome_competição`),
  ADD KEY `fk_id_vencedor` (`id_vencedor`);

--
-- Índices para tabela `competição_default`
--
ALTER TABLE `competição_default`
  ADD PRIMARY KEY (`id_competição_default`);

--
-- Índices para tabela `convocatória`
--
ALTER TABLE `convocatória`
  ADD PRIMARY KEY (`id_convocatória`),
  ADD KEY `fk_id_jogador_conv` (`id_jogador`),
  ADD KEY `fk_id_jogo_conv` (`id_jogo`);

--
-- Índices para tabela `detalhes_jogo`
--
ALTER TABLE `detalhes_jogo`
  ADD PRIMARY KEY (`id_detalhes_jogo`),
  ADD KEY `fk_id_jogador1` (`id_jogador`),
  ADD KEY `fk_id_jogo` (`id_jogo`);

--
-- Índices para tabela `equipa`
--
ALTER TABLE `equipa`
  ADD PRIMARY KEY (`id_equipa`),
  ADD KEY `fk_id_época` (`id_época`),
  ADD KEY `fk_id_clube_equipa` (`id_clube`);

--
-- Índices para tabela `estatísticas_jogadores`
--
ALTER TABLE `estatísticas_jogadores`
  ADD PRIMARY KEY (`id_stats`),
  ADD KEY `fk_id_jogador2` (`id_jogador`),
  ADD KEY `fk_id_jogador3` (`id_jogo`);

--
-- Índices para tabela `estatísticas_jogo`
--
ALTER TABLE `estatísticas_jogo`
  ADD PRIMARY KEY (`id_stats`),
  ADD KEY `fk_id_jogo_stats` (`id_jogo`);

--
-- Índices para tabela `estádio`
--
ALTER TABLE `estádio`
  ADD PRIMARY KEY (`id_estádio`),
  ADD KEY `fk_id_clube` (`id_clube`);

--
-- Índices para tabela `eventos_clube`
--
ALTER TABLE `eventos_clube`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `fk_id_equipa_evento` (`id_equipa`);

--
-- Índices para tabela `eventos_jogo`
--
ALTER TABLE `eventos_jogo`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `fk_id_jogos` (`id_jogo`),
  ADD KEY `fk_id_jogador3` (`id_jogador`),
  ADD KEY `fk_id_jogador4` (`jogador_entrada`),
  ADD KEY `fk_id_jogador5` (`jogador_saída`),
  ADD KEY `fk_id_jogador6` (`jogador_assistência`);

--
-- Índices para tabela `exercícios`
--
ALTER TABLE `exercícios`
  ADD PRIMARY KEY (`id_exercício`);

--
-- Índices para tabela `fase`
--
ALTER TABLE `fase`
  ADD PRIMARY KEY (`id_fase`);

--
-- Índices para tabela `histórico_carreira`
--
ALTER TABLE `histórico_carreira`
  ADD PRIMARY KEY (`id_carreira`),
  ADD KEY `fk_id_jogador_carreira` (`id_jogador`),
  ADD KEY `fk_id_época_carreira` (`id_época`),
  ADD KEY `fk_id_clube_carreira` (`id_clube`);

--
-- Índices para tabela `histórico_transferência`
--
ALTER TABLE `histórico_transferência`
  ADD PRIMARY KEY (`id_transferência`),
  ADD KEY `fk_id_jogador_transferência` (`id_jogador`),
  ADD KEY `fk_id_clube_origem` (`id_clube_origem`),
  ADD KEY `fk_id_clube_destino` (`id_clube_destino`);

--
-- Índices para tabela `jogadores`
--
ALTER TABLE `jogadores`
  ADD PRIMARY KEY (`id_jogador`),
  ADD KEY `fk_id_equipa` (`id_equipa`);

--
-- Índices para tabela `jogos`
--
ALTER TABLE `jogos`
  ADD PRIMARY KEY (`id_jogo`),
  ADD KEY `fk_id_competição` (`id_competição`);

--
-- Índices para tabela `lesões`
--
ALTER TABLE `lesões`
  ADD PRIMARY KEY (`id_lesão`),
  ADD KEY `fk_id_jogador` (`id_jogador`);

--
-- Índices para tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`id_mensagem`),
  ADD KEY `fk_origem` (`origem`);

ALTER TABLE `notificacao`
  ADD PRIMARY KEY (`id_notificacao`),
  ADD KEY `fk_notificacao_utilizador` (`id_utilizador`),
  ADD KEY `fk_notificacao_clube` (`id_clube`),
  ADD KEY `idx_notificacao_estado` (`estado`),
  ADD KEY `idx_notificacao_criada_em` (`criada_em`);

--
-- Índices para tabela `plano_treino`
--
ALTER TABLE `plano_treino`
  ADD PRIMARY KEY (`id_plano_treino`);

--
-- Índices para tabela `treino`
--
ALTER TABLE `treino`
  ADD PRIMARY KEY (`id_treino`),
  ADD KEY `fk_id_plano` (`id_plano`);

--
-- Índices para tabela `utilizador`
--
ALTER TABLE `utilizador`
  ADD PRIMARY KEY (`id_utilizador`),
  ADD KEY `idx_utilizador_clube` (`id_clube`);

--
-- Índices para tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  ADD PRIMARY KEY (`id_validação_transferência`),
  ADD KEY `fk_id_jogador1` (`id_jogador`),
  ADD KEY `fk_clube_origem` (`id_clube_origem`),
  ADD KEY `fk_clube_origem_destino` (`id_clube_destino`);

--
-- Índices para tabela `validação_utilizador`
--
ALTER TABLE `validação_utilizador`
  ADD PRIMARY KEY (`id_validação`);

--
-- Índices para tabela `época`
--
ALTER TABLE `época`
  ADD PRIMARY KEY (`id_época`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `acesso_equipa`
--
ALTER TABLE `acesso_equipa`
  MODIFY `id_acesso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assiduidade`
--
ALTER TABLE `assiduidade`
  MODIFY `id_assiduidade` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `clube`
--
ALTER TABLE `clube`
  MODIFY `id_clube` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `competição`
--
ALTER TABLE `competição`
  MODIFY `id_competição` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `competição_default`
--
ALTER TABLE `competição_default`
  MODIFY `id_competição_default` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `convocatória`
--
ALTER TABLE `convocatória`
  MODIFY `id_convocatória` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `detalhes_jogo`
--
ALTER TABLE `detalhes_jogo`
  MODIFY `id_detalhes_jogo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `equipa`
--
ALTER TABLE `equipa`
  MODIFY `id_equipa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estatísticas_jogadores`
--
ALTER TABLE `estatísticas_jogadores`
  MODIFY `id_stats` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estatísticas_jogo`
--
ALTER TABLE `estatísticas_jogo`
  MODIFY `id_stats` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estádio`
--
ALTER TABLE `estádio`
  MODIFY `id_estádio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `eventos_clube`
--
ALTER TABLE `eventos_clube`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `eventos_jogo`
--
ALTER TABLE `eventos_jogo`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `exercícios`
--
ALTER TABLE `exercícios`
  MODIFY `id_exercício` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fase`
--
ALTER TABLE `fase`
  MODIFY `id_fase` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de tabela `histórico_carreira`
--
ALTER TABLE `histórico_carreira`
  MODIFY `id_carreira` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `histórico_transferência`
--
ALTER TABLE `histórico_transferência`
  MODIFY `id_transferência` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogadores`
--
ALTER TABLE `jogadores`
  MODIFY `id_jogador` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `jogos`
--
ALTER TABLE `jogos`
  MODIFY `id_jogo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lesões`
--
ALTER TABLE `lesões`
  MODIFY `id_lesão` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `id_mensagem` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacao`
--
ALTER TABLE `notificacao`
  MODIFY `id_notificacao` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `plano_treino`
--
ALTER TABLE `plano_treino`
  MODIFY `id_plano_treino` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `treino`
--
ALTER TABLE `treino`
  MODIFY `id_treino` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `utilizador`
--
ALTER TABLE `utilizador`
  MODIFY `id_utilizador` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  MODIFY `id_validação_transferência` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `validação_utilizador`
--
ALTER TABLE `validação_utilizador`
  MODIFY `id_validação` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `época`
--
ALTER TABLE `época`
  MODIFY `id_época` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `estatísticas_jogadores`
--
ALTER TABLE `estatísticas_jogadores`
  ADD CONSTRAINT `fk_id_jogador2` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador3` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `estatísticas_jogo`
--
ALTER TABLE `estatísticas_jogo`
  ADD CONSTRAINT `fk_id_jogo_stats` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `eventos_clube`
--
ALTER TABLE `eventos_clube`
  ADD CONSTRAINT `fk_id_equipa_evento` FOREIGN KEY (`id_equipa`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `jogadores`
--
ALTER TABLE `jogadores`
  ADD CONSTRAINT `fk_id_equipa` FOREIGN KEY (`id_equipa`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `jogos`
--
ALTER TABLE `jogos`
  ADD CONSTRAINT `fk_id_competição` FOREIGN KEY (`id_competição`) REFERENCES `competição` (`id_competição`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `lesões`
--
ALTER TABLE `lesões`
  ADD CONSTRAINT `fk_id_jogador` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD CONSTRAINT `fk_origem` FOREIGN KEY (`origem`) REFERENCES `utilizador` (`id_utilizador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `treino`
--
ALTER TABLE `treino`
  ADD CONSTRAINT `fk_id_plano` FOREIGN KEY (`id_plano`) REFERENCES `plano_treino` (`id_plano_treino`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  ADD CONSTRAINT `fk_clube_origem` FOREIGN KEY (`id_clube_origem`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_clube_origem_destino` FOREIGN KEY (`id_clube_destino`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador1` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE;

ALTER TABLE `utilizador`
  ADD CONSTRAINT `fk_utilizador_clube`
  FOREIGN KEY (`id_clube`)
  REFERENCES `clube` (`id_clube`)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

-- ========================================================
-- FASE 1: SEED BASE (estrutura principal da aplicação)
-- ========================================================
-- Objetivo:
-- - Garantir um clube de referência
-- - Criar utilizadores base (admin_clube, treinador, jogador)
-- - Ligar equipa e jogador para validação inicial

INSERT INTO `clube` (`nome_clube`, `sigla`, `logotipo`, `cor`, `data_fundação`, `sede_morada`, `país_clube`, `cidade_clube`, `telefone_clube`, `email_clube`, `website_clube`, `presidente_clube`, `instagram_clube`, `facebook_clube`, `youtube_clube`, `twitter_clube`, `tiktok_clube`, `código_clube`)
VALUES (
  'Clube de Teste',
  'CTS',
  0x89504E470D0A1A0A,
  '#123456',
  '2020-01-15',
  'Rua de Teste, 123',
  'Portugal',
  'Porto',
  '+351 912 345 678',
  'teste@clube.pt',
  'https://www.clubedeteste.pt',
  'Presidente Teste',
  '@clubedeteste',
  '@clubedeteste',
  '@clubedeteste',
  '@clubedeteste',
  '@clubedeteste',
  'TEST01'
) ON DUPLICATE KEY UPDATE `nome_clube` = VALUES(`nome_clube`);

SET @id_clube := (SELECT `id_clube` FROM `clube` WHERE `código_clube` = 'TEST01' LIMIT 1);

INSERT INTO `equipa` (`escalão`, `hierarquia`, `id_época`, `id_clube`)
VALUES ('S11', 'A', 1, @id_clube)
ON DUPLICATE KEY UPDATE `hierarquia` = VALUES(`hierarquia`);

SET @id_equipa := (SELECT `id_equipa` FROM `equipa` WHERE `id_clube` = @id_clube AND `escalão` = 'S11' AND `hierarquia` = 'A' LIMIT 1);

INSERT INTO `utilizador` (`nome_utilizador`, `foto_perfil`, `email_utilizador`, `telefone_utilizador`, `primeiro_nome`, `último_nome`, `data_nascimento`, `password`, `tipo_utilizador`, `id_clube`)
VALUES
  ('admin_sistema', NULL, 'admin@test.local', '+351 900 000 001', 'Admin', 'Sistema', '1990-05-10', '123456', 'admin', NULL),
  ('admin_clube_teste', NULL, 'adminclube@test.local', '+351 900 000 002', 'Ana', 'Silva', '1992-03-19', '123456', 'admin_clube', @id_clube),
  ('treinador_teste', NULL, 'treinador@test.local', '+351 900 000 003', 'Rui', 'Costa', '1988-11-04', '123456', 'treinador', @id_clube),
  ('jogador_teste', NULL, 'jogador@test.local', '+351 900 000 004', 'João', 'Pereira', '2004-02-22', '123456', 'jogador', @id_clube)
ON DUPLICATE KEY UPDATE `email_utilizador` = VALUES(`email_utilizador`);

SET @id_admin_clube := (SELECT `id_utilizador` FROM `utilizador` WHERE `email_utilizador` = 'adminclube@test.local' LIMIT 1);
SET @id_treinador := (SELECT `id_utilizador` FROM `utilizador` WHERE `email_utilizador` = 'treinador@test.local' LIMIT 1);
SET @id_jogador_utilizador := (SELECT `id_utilizador` FROM `utilizador` WHERE `email_utilizador` = 'jogador@test.local' LIMIT 1);

INSERT INTO `acesso_equipa` (`id_equipa`, `id_utilizador`)
VALUES (@id_equipa, @id_admin_clube), (@id_equipa, @id_treinador)
ON DUPLICATE KEY UPDATE `id_utilizador` = VALUES(`id_utilizador`);

INSERT INTO `jogadores` (`foto_jogador`, `nome_completo`, `alcunha_jogador`, `número_favorito`, `posição_principal`, `posição_secundária`, `data_nascimento`, `local_nascimento`, `nacionalidade`, `país_nascimento`, `pé_preferencial`, `altura`, `peso`, `instagram`, `facebook`, `twitter`, `id_equipa`)
VALUES (
  0x89504E470D0A1A0A,
  'João Pereira',
  'JP',
  '10',
  'Médio Ofensivo',
  'Extremo Direito',
  '2004-02-22',
  'Porto',
  'Portuguesa',
  'Portugal',
  'Direito',
  '178',
  '70',
  '@jppereira',
  '@jppereira',
  '@jppereira',
  @id_equipa
)
ON DUPLICATE KEY UPDATE `nome_completo` = VALUES(`nome_completo`);

-- --------------------------------------------------------
-- 1.1 Notificações de teste (seed base)
-- --------------------------------------------------------

DELETE FROM `notificacao`
WHERE `titulo` LIKE '[TESTE] %'
  AND `id_utilizador` IN (@id_admin_clube, @id_treinador, @id_jogador_utilizador);

INSERT INTO `notificacao` (`id_utilizador`, `id_clube`, `titulo`, `mensagem`, `tipo`, `estado`, `criada_em`, `lida_em`, `link_acao`)
VALUES
  (@id_admin_clube, @id_clube, '[TESTE] Bem-vindo ao clube', 'O clube foi criado com sucesso e esta notificação serve para validar a interface.', 'sucesso', 'Lida', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, NULL),
  (@id_admin_clube, @id_clube, '[TESTE] Treino agendado', 'Foi agendado treino para terça-feira às 19:00.', 'info', 'Nao Lida', NOW() - INTERVAL 1 DAY, NULL, NULL),
  (@id_admin_clube, @id_clube, '[TESTE] Documento em falta', 'Confirma os dados do estádio para completar o perfil do clube.', 'aviso', 'Nao Lida', NOW() - INTERVAL 6 HOUR, NULL, NULL),
  (@id_treinador, @id_clube, '[TESTE] Convocatória disponível', 'Já podes consultar a convocatória para o próximo jogo.', 'info', 'Nao Lida', NOW() - INTERVAL 5 HOUR, NULL, NULL),
  (@id_treinador, @id_clube, '[TESTE] Assiduidade atualizada', 'A assiduidade do último treino foi registada.', 'sucesso', 'Lida', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 2 HOUR, NULL),
  (@id_jogador_utilizador, @id_clube, '[TESTE] Observação técnica', 'O treinador deixou uma observação no teu plano individual.', 'info', 'Nao Lida', NOW() - INTERVAL 90 MINUTE, NULL, NULL);

-- ========================================================
-- FASE 2: SEED FUNCIONAL (dados para dashboards e fluxos)
-- ========================================================
-- Objetivo:
-- - Alimentar ecrãs de Escalões, Competições, Calendário e Mensagens
-- - Manter idempotência com INSERT IGNORE

-- --------------------------------------------------------
-- 2.1 Compatibilidade de schema para funcionalidades novas
-- --------------------------------------------------------

ALTER TABLE `eventos_clube`
  ADD COLUMN IF NOT EXISTS `hora_evento` TIME DEFAULT NULL AFTER `data_evento`,
  ADD COLUMN IF NOT EXISTS `local_evento` VARCHAR(200) DEFAULT NULL AFTER `hora_evento`;

ALTER TABLE `jogadores`
  ADD COLUMN IF NOT EXISTS `id_utilizador` INT DEFAULT NULL AFTER `id_equipa`;

ALTER TABLE `mensagens`
  ADD COLUMN IF NOT EXISTS `enviada_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `estado`;

CREATE TABLE IF NOT EXISTS `competicoes_clube` (
  `id_competicao` INT AUTO_INCREMENT PRIMARY KEY,
  `id_clube` INT NOT NULL,
  `id_equipa` INT NOT NULL,
  `nome` VARCHAR(200) NOT NULL,
  `tipo` ENUM('Liga','Taça','Torneio','Campeonato','Amigável','Outro') NOT NULL DEFAULT 'Liga',
  `epoca` VARCHAR(20) DEFAULT NULL,
  `estado` ENUM('A decorrer','Finalizada','Suspensa') NOT NULL DEFAULT 'A decorrer',
  `descricao` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jogos_clube` (
  `id_jogo` INT AUTO_INCREMENT PRIMARY KEY,
  `id_competicao` INT NOT NULL,
  `adversario` VARCHAR(200) NOT NULL,
  `data_jogo` DATE NOT NULL,
  `hora_jogo` TIME DEFAULT NULL,
  `casa` TINYINT(1) NOT NULL DEFAULT 1,
  `local_jogo` VARCHAR(200) DEFAULT NULL,
  `resultado_nos` INT DEFAULT NULL,
  `resultado_adv` INT DEFAULT NULL,
  `estado` ENUM('Agendado','Realizado','Cancelado','Adiado') NOT NULL DEFAULT 'Agendado',
  `id_evento_clube` INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- 2.2 Épocas e equipas
-- --------------------------------------------------------

INSERT IGNORE INTO `época` (`id_época`,`época`) VALUES
(1,'2022/2023'),(2,'2023/2024'),(3,'2024/2025'),(4,'2025/2026');

INSERT IGNORE INTO `equipa` (`id_equipa`,`escalão`,`hierarquia`,`id_época`,`id_clube`) VALUES
(1,'S11','A',3,1),(2,'S13','A',3,1),(3,'S15','A',3,1),(4,'Seniores','A',4,1);

-- --------------------------------------------------------
-- 2.3 Utilizadores e jogadores
-- --------------------------------------------------------

INSERT IGNORE INTO `utilizador`
    (`id_utilizador`,`nome_utilizador`,`email_utilizador`,`primeiro_nome`,`último_nome`,`password`,`tipo_utilizador`,`id_clube`)
VALUES
(10,'joao.silva','joao.silva@clube.pt','João','Silva','12345','jogador',1),
(11,'mario.costa','mario.costa@clube.pt','Mário','Costa','12345','jogador',1),
(12,'pedro.lopes','pedro.lopes@clube.pt','Pedro','Lopes','12345','jogador',1),
(13,'rui.ferreira','rui.ferreira@clube.pt','Rui','Ferreira','12345','jogador',1),
(14,'tiago.martins','tiago.martins@clube.pt','Tiago','Martins','12345','jogador',1),
(15,'carlos.santos','carlos.santos@clube.pt','Carlos','Santos','12345','jogador',1),
(16,'andre.alves','andre.alves@clube.pt','André','Alves','12345','jogador',1),
(17,'nuno.pereira','nuno.pereira@clube.pt','Nuno','Pereira','12345','jogador',1),
(18,'luis.oliveira','luis.oliveira@clube.pt','Luís','Oliveira','12345','jogador',1),
(19,'goncalo.rodrigues','goncalo.rodrigues@clube.pt','Gonçalo','Rodrigues','12345','jogador',1),
(20,'treinador1','treinador1@clube.pt','Miguel','Faria','12345','treinador',1),
(21,'treinador2','treinador2@clube.pt','Sérgio','Neves','12345','treinador',1);

INSERT IGNORE INTO `jogadores`
    (`id_jogador`,`foto_jogador`,`nome_completo`,`alcunha_jogador`,`número_favorito`,`posição_principal`,`data_nascimento`,`nacionalidade`,`país_nascimento`,`id_equipa`,`id_utilizador`)
VALUES
(1,'','João Silva','Jojo','1','Guarda-Redes','2013-03-10','Portuguesa','Portugal',1,10),
(2,'','Mário Costa','Mário','5','Defesa Central','2013-07-22','Portuguesa','Portugal',1,11),
(3,'','Pedro Lopes','PL','3','Defesa Esquerdo','2013-09-01','Portuguesa','Portugal',1,12),
(4,'','Rui Ferreira','Ruizinho','8','Médio Centro','2013-11-15','Portuguesa','Portugal',1,13),
(5,'','Tiago Martins','Tiagão','10','Médio Ofensivo','2014-02-28','Portuguesa','Portugal',1,14),
(6,'','Carlos Santos','Carlitos','1','Guarda-Redes','2011-04-05','Portuguesa','Portugal',2,15),
(7,'','André Alves','André','4','Defesa Central','2011-06-18','Portuguesa','Portugal',2,16),
(8,'','Nuno Pereira','Nuno','7','Médio Defensivo','2012-01-30','Portuguesa','Portugal',2,17),
(9,'','Luís Oliveira','Luizão','9','Ponta de Lança','2011-08-12','Portuguesa','Portugal',2,18),
(10,'','Gonçalo Rodrigues','Gonças','11','Extremo Esquerdo','2012-05-25','Portuguesa','Portugal',2,19);

INSERT IGNORE INTO `acesso_equipa` (`id_acesso`,`id_equipa`,`id_utilizador`) VALUES
(1,1,20),(2,2,21);

-- --------------------------------------------------------
-- 2.4 Histórico e lesões
-- --------------------------------------------------------

INSERT IGNORE INTO `histórico_carreira`
    (`id_carreira`,`id_jogador`,`id_época`,`id_clube`,`jogos`,`golos_marcados`,`assistências`)
VALUES
(1,1,2,1,18,0,2),(2,1,3,1,22,1,3),
(3,4,2,1,16,3,5),(4,4,3,1,20,7,8),
(5,9,2,1,15,10,4),(6,9,3,1,19,14,6);

INSERT IGNORE INTO `lesões`
    (`id_lesão`,`id_jogador`,`nome_lesão`,`descrição_lesão`,`tipo_lesão`,`tempo_recuperação`,`estado_lesão`)
VALUES
(1,4,'Entorse tornozelo direito','Ligeira entorse no tornozelo','Ligamentar/Articular','1 - 2 semanas','Recuperado'),
(2,9,'Rotura fibrilar gémeo','Rotura parcial no gémeo esquerdo','Muscular','3 - 1 mês','Em recuperação'),
(3,1,'Fractura dedo mão','Fractura no 3º dedo da mão direita','Óssea','5 dias - 1 semana','Recuperado');

-- --------------------------------------------------------
-- 2.5 Competições, jogos e eventos de calendário
-- --------------------------------------------------------

INSERT IGNORE INTO `competicoes_clube`
    (`id_competicao`,`id_clube`,`id_equipa`,`nome`,`tipo`,`epoca`,`estado`,`descricao`)
VALUES
(1,1,1,'Campeonato Distrital Sub-11','Campeonato','2024/2025','A decorrer','Campeonato da Associação de Futebol de Lisboa'),
(2,1,1,'Taça Sub-11','Taça','2024/2025','A decorrer','Taça distrital Sub-11'),
(3,1,2,'Campeonato Distrital Sub-13','Campeonato','2024/2025','A decorrer','Liga regional Sub-13'),
(4,1,4,'Liga Amadora Senior','Liga','2025/2026','A decorrer','Campeonato da liga local sénior'),
(5,1,3,'Torneio de Verão Sub-15','Torneio','2024/2025','Finalizada','Torneio de pré-época');

INSERT IGNORE INTO `jogos_clube`
    (`id_jogo`,`id_competicao`,`adversario`,`data_jogo`,`hora_jogo`,`casa`,`local_jogo`,`resultado_nos`,`resultado_adv`,`estado`)
VALUES
(1,1,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -30 DAY),'10:00',1,'Campo Principal',3,1,'Realizado'),
(2,1,'Benfica',DATE_ADD(CURDATE(), INTERVAL -20 DAY),'11:00',0,'Estádio da Luz',2,2,'Realizado'),
(3,1,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -10 DAY),'10:30',1,'Campo Principal',1,0,'Realizado'),
(4,1,'Vitória FC',DATE_ADD(CURDATE(), INTERVAL 7 DAY),'10:00',0,'Campo da Vitória',NULL,NULL,'Agendado'),
(5,1,'Belenenses',DATE_ADD(CURDATE(), INTERVAL 14 DAY),'11:00',1,'Campo Principal',NULL,NULL,'Agendado'),
(6,2,'FC Porto',DATE_ADD(CURDATE(), INTERVAL -45 DAY),'14:00',1,'Campo Principal',4,0,'Realizado'),
(7,2,'GD Estoril',DATE_ADD(CURDATE(), INTERVAL 21 DAY),'14:00',0,NULL,NULL,NULL,'Agendado'),
(8,3,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -25 DAY),'09:30',1,'Campo Principal',2,3,'Realizado'),
(9,3,'Benfica',DATE_ADD(CURDATE(), INTERVAL -12 DAY),'10:00',0,'Caixa Futebol Campus',1,1,'Realizado'),
(10,3,'Casa Pia',DATE_ADD(CURDATE(), INTERVAL 5 DAY),'10:00',1,'Campo Principal',NULL,NULL,'Agendado'),
(11,4,'Atlético Queluz',DATE_ADD(CURDATE(), INTERVAL -35 DAY),'15:30',1,'Campo Principal',2,1,'Realizado'),
(12,4,'Leões de Lisboa',DATE_ADD(CURDATE(), INTERVAL -18 DAY),'16:00',0,'Estádio Municipal',0,2,'Realizado'),
(13,4,'FC Loures',DATE_ADD(CURDATE(), INTERVAL 3 DAY),'15:00',1,'Campo Principal',NULL,NULL,'Agendado'),
(14,4,'GD Vendas Novas',DATE_ADD(CURDATE(), INTERVAL 10 DAY),'15:30',0,NULL,NULL,NULL,'Agendado'),
(15,5,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -90 DAY),'09:00',0,'Campo Neutro',1,2,'Realizado'),
(16,5,'FC Porto',DATE_ADD(CURDATE(), INTERVAL -89 DAY),'11:00',0,'Campo Neutro',3,1,'Realizado'),
(17,5,'Benfica',DATE_ADD(CURDATE(), INTERVAL -89 DAY),'15:00',0,'Campo Neutro',2,2,'Realizado');

INSERT IGNORE INTO `eventos_clube`
    (`id_evento`,`id_equipa`,`tipo_evento`,`descrição_evento`,`estado_evento`,`data_evento`,`hora_evento`)
SELECT
    jc.id_jogo + 1000,
    cc.id_equipa,
    'Jogo',
    CONCAT('Jogo vs ', jc.adversario),
    CASE jc.estado WHEN 'Realizado' THEN 'Realizado' WHEN 'Cancelado' THEN 'Cancelado' ELSE 'Por realizar' END,
    jc.data_jogo,
    jc.hora_jogo
FROM jogos_clube jc
JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao;

-- --------------------------------------------------------
-- 2.6 Mensagens de demonstração
-- --------------------------------------------------------

INSERT IGNORE INTO `mensagens`
    (`id_mensagem`,`origem`,`destino`,`conteúdo`,`estado`,`enviada_em`)
VALUES
(1,1,20,'Olá Miguel! Amanhã o treino é às 18h, confirmas?','Lida',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(2,20,1,'Confirmado, estarei lá às 17h45 para preparar.','Lida',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(3,1,20,'Ótimo! Traz os coletes e os cones.','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(4,20,1,'Já tratei disso. Podemos falar sobre a táctica para o próximo jogo?','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(5,1,20,'Claro, vemo-nos amanhã antes do treino.','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(6,21,1,'Bom dia! Tenho uma dúvida sobre a convocatória do Gonçalo.','Não Lida',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(7,1,21,'Bom dia Sérgio! O Gonçalo está lesionado, não vai jogar.','Lida',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(8,21,1,'Ah entendo, obrigado! Quem convoco então?','Não Lida',DATE_SUB(NOW(),INTERVAL 23 HOUR)),
(9,1,21,'O André Alves está disponível e em boa forma.','Lida',DATE_SUB(NOW(),INTERVAL 22 HOUR)),
(10,20,1,'Treinador: os pais do João Silva ligaram sobre os equipamentos.','Não Lida',DATE_SUB(NOW(),INTERVAL 10 HOUR)),
(11,1,20,'Vou tratar disso. Obrigado pelo aviso!','Lida',DATE_SUB(NOW(),INTERVAL 9 HOUR)),
(12,20,21,'Sérgio, tens o plano de treino para esta semana?','Lida',DATE_SUB(NOW(),INTERVAL 6 HOUR)),
(13,21,20,'Sim, partilhei no grupo. Viste?','Não Lida',DATE_SUB(NOW(),INTERVAL 5 HOUR)),
(14,20,21,'Encontrei! Muito bom plano, obrigado.','Não Lida',DATE_SUB(NOW(),INTERVAL 4 HOUR)),
(15,21,1,'Presidente, quando é a próxima reunião de dirigentes?','Não Lida',DATE_SUB(NOW(),INTERVAL 2 HOUR));

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
