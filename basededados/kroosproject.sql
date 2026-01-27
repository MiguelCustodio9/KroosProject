-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 26-Jan-2026 às 18:50
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
  `id_clube` int(11) NOT NULL AUTO_INCREMENT,
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
  `código_clube` varchar(100) NOT NULL,
  PRIMARY KEY (`id_clube`),
  UNIQUE KEY `codigo_unico` (`código_clube`)
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
  `foto_perfil` mediumblob NOT NULL,
  `email_utilizador` varchar(255) NOT NULL,
  `telefone_utilizador` varchar(20) NOT NULL,
  `primeiro_nome` varchar(50) NOT NULL,
  `último_nome` varchar(50) NOT NULL,
  `data_nascimento` date NOT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_utilizador` enum('admin','treinador','jogador','admin_clube') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `utilizador`
--

INSERT INTO `utilizador` (`id_utilizador`, `nome_utilizador`, `foto_perfil`, `email_utilizador`, `telefone_utilizador`, `primeiro_nome`, `último_nome`, `data_nascimento`, `password`, `tipo_utilizador`) VALUES
(1, 'admin', '', 'admin@gmail.com', '966666666', 'admin', 'admin', '2026-01-22', '21232f297a57a5a743894a0e4a801fc3', 'admin');

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
  `foto_perfil` mediumblob NOT NULL,
  `email_utilizador` varchar(255) NOT NULL,
  `telefone_utilizador` varchar(20) NOT NULL,
  `primeiro_nome` varchar(50) NOT NULL,
  `último_nome` varchar(50) NOT NULL,
  `data_nascimento` date NOT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_utilizador` enum('admin_clube','treinador','jogador','') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `validação_utilizador`
--

INSERT INTO `validação_utilizador` (`id_validação`, `nome_utilizador`, `foto_perfil`, `email_utilizador`, `telefone_utilizador`, `primeiro_nome`, `último_nome`, `data_nascimento`, `password`, `tipo_utilizador`) VALUES
(1, 'miguel_custodio_', '', 'migacusta9@gmail.com', '963353681', 'miguel', 'custódio', '2005-12-13', 'Mfcusta9_', 'treinador');

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
  ADD UNIQUE KEY `nome_clube` (`nome_clube`,`telefone_clube`,`email_clube`,`website_clube`,`presidente_clube`,`código_clube`),
  ADD UNIQUE KEY `instagram_clube` (`instagram_clube`,`facebook_clube`,`youtube_clube`,`twitter_clube`,`tiktok_clube`);

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
-- Índices para tabela `estádio`
--
ALTER TABLE `estádio`
  ADD PRIMARY KEY (`id_estádio`),
  ADD UNIQUE KEY `nome_estádio` (`nome_estádio`),
  ADD KEY `fk_id_clube` (`id_clube`);

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
  ADD KEY `fk_id_equipa_jogadores` (`id_equipa`);

--
-- Índices para tabela `jogos`
--
ALTER TABLE `jogos`
  ADD PRIMARY KEY (`id_jogo`),
  ADD KEY `fk_id_competição1` (`id_competição`),
  ADD KEY `fk_id_fase` (`id_fase`),
  ADD KEY `fk_clube_casa` (`clube_casa`),
  ADD KEY `fk_clube_visitante` (`clube_visitante`),
  ADD KEY `fk_local_jogo` (`local_jogo`);

--
-- Índices para tabela `lesões`
--
ALTER TABLE `lesões`
  ADD PRIMARY KEY (`id_lesão`),
  ADD KEY `fk_id_jogador_lesão` (`id_jogador`);

--
-- Índices para tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`id_mensagem`),
  ADD KEY `fk_origem` (`origem`),
  ADD KEY `fk_destino` (`destino`);

--
-- Índices para tabela `plano_treino`
--
ALTER TABLE `plano_treino`
  ADD PRIMARY KEY (`id_plano_treino`),
  ADD KEY `fk_exercício_1` (`exercício_1`),
  ADD KEY `fk_exercício_2` (`exercício_2`),
  ADD KEY `fk_exercício_3` (`exercício_3`),
  ADD KEY `fk_exercício_4` (`exercício_4`),
  ADD KEY `fk_exercício_5` (`exercício_5`),
  ADD KEY `fk_exercício_6` (`exercício_6`),
  ADD KEY `fk_exercício_7` (`exercício_7`),
  ADD KEY `fk_exercício_8` (`exercício_8`),
  ADD KEY `fk_exercício_9` (`exercício_9`),
  ADD KEY `fk_exercício_10` (`exercício_10`);

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
  ADD UNIQUE KEY `nome_utilizador` (`nome_utilizador`),
  ADD UNIQUE KEY `email_utilizador` (`email_utilizador`),
  ADD UNIQUE KEY `telefone_utilizador` (`telefone_utilizador`),
  ADD UNIQUE KEY `password` (`password`),
  ADD UNIQUE KEY `foto_perfil` (`foto_perfil`) USING HASH;

--
-- Índices para tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  ADD PRIMARY KEY (`id_validação_transferência`),
  ADD KEY `fk_id_origem_vd` (`id_clube_origem`),
  ADD KEY `fk_id_destino_vd` (`id_clube_destino`),
  ADD KEY `fk_id_jogador_vd` (`id_jogador`);

--
-- Índices para tabela `validação_utilizador`
--
ALTER TABLE `validação_utilizador`
  ADD PRIMARY KEY (`id_validação`),
  ADD UNIQUE KEY `nome_utilizador` (`nome_utilizador`),
  ADD UNIQUE KEY `email_utilizador` (`email_utilizador`),
  ADD UNIQUE KEY `telefone_utilizador` (`telefone_utilizador`),
  ADD UNIQUE KEY `password` (`password`);

--
-- Índices para tabela `época`
--
ALTER TABLE `época`
  ADD PRIMARY KEY (`id_época`),
  ADD UNIQUE KEY `época` (`época`);

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
-- AUTO_INCREMENT de tabela `estádio`
--
ALTER TABLE `estádio`
  MODIFY `id_estádio` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_utilizador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  MODIFY `id_validação_transferência` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `validação_utilizador`
--
ALTER TABLE `validação_utilizador`
  MODIFY `id_validação` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `época`
--
ALTER TABLE `época`
  MODIFY `id_época` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `acesso_equipa`
--
ALTER TABLE `acesso_equipa`
  ADD CONSTRAINT `fk_id_equipa_acesso` FOREIGN KEY (`id_equipa`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_utilizador` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizador` (`id_utilizador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `assiduidade`
--
ALTER TABLE `assiduidade`
  ADD CONSTRAINT `fk_id_jogador` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_treino` FOREIGN KEY (`id_treino`) REFERENCES `treino` (`id_treino`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `competição`
--
ALTER TABLE `competição`
  ADD CONSTRAINT `fk_id_competição` FOREIGN KEY (`id_competição`) REFERENCES `competição_default` (`id_competição_default`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_nome_competição` FOREIGN KEY (`id_nome_competição`) REFERENCES `competição_default` (`id_competição_default`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_vencedor` FOREIGN KEY (`id_vencedor`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `convocatória`
--
ALTER TABLE `convocatória`
  ADD CONSTRAINT `fk_id_jogador_conv` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogo_conv` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `detalhes_jogo`
--
ALTER TABLE `detalhes_jogo`
  ADD CONSTRAINT `fk_id_jogador1` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `equipa`
--
ALTER TABLE `equipa`
  ADD CONSTRAINT `fk_id_clube_equipa` FOREIGN KEY (`id_clube`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_época` FOREIGN KEY (`id_época`) REFERENCES `época` (`id_época`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `estádio`
--
ALTER TABLE `estádio`
  ADD CONSTRAINT `fk_id_clube` FOREIGN KEY (`id_clube`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `eventos_jogo`
--
ALTER TABLE `eventos_jogo`
  ADD CONSTRAINT `fk_id_jogador3` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador4` FOREIGN KEY (`jogador_entrada`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador5` FOREIGN KEY (`jogador_saída`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador6` FOREIGN KEY (`jogador_assistência`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogos` FOREIGN KEY (`id_jogo`) REFERENCES `jogos` (`id_jogo`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `histórico_carreira`
--
ALTER TABLE `histórico_carreira`
  ADD CONSTRAINT `fk_id_clube_carreira` FOREIGN KEY (`id_clube`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador_carreira` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_época_carreira` FOREIGN KEY (`id_época`) REFERENCES `época` (`id_época`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `histórico_transferência`
--
ALTER TABLE `histórico_transferência`
  ADD CONSTRAINT `fk_id_clube_destino` FOREIGN KEY (`id_clube_destino`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_clube_origem` FOREIGN KEY (`id_clube_origem`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador_transferência` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `jogadores`
--
ALTER TABLE `jogadores`
  ADD CONSTRAINT `fk_id_equipa_jogadores` FOREIGN KEY (`id_equipa`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `jogos`
--
ALTER TABLE `jogos`
  ADD CONSTRAINT `fk_clube_casa` FOREIGN KEY (`clube_casa`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_clube_visitante` FOREIGN KEY (`clube_visitante`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_competição1` FOREIGN KEY (`id_competição`) REFERENCES `competição` (`id_competição`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_fase` FOREIGN KEY (`id_fase`) REFERENCES `fase` (`id_fase`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_local_jogo` FOREIGN KEY (`local_jogo`) REFERENCES `estádio` (`id_estádio`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `lesões`
--
ALTER TABLE `lesões`
  ADD CONSTRAINT `fk_id_jogador_lesão` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD CONSTRAINT `fk_destino` FOREIGN KEY (`destino`) REFERENCES `utilizador` (`id_utilizador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_origem` FOREIGN KEY (`origem`) REFERENCES `utilizador` (`id_utilizador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `plano_treino`
--
ALTER TABLE `plano_treino`
  ADD CONSTRAINT `fk_exercício_1` FOREIGN KEY (`exercício_1`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_10` FOREIGN KEY (`exercício_10`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_2` FOREIGN KEY (`exercício_2`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_3` FOREIGN KEY (`exercício_3`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_4` FOREIGN KEY (`exercício_4`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_5` FOREIGN KEY (`exercício_5`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_6` FOREIGN KEY (`exercício_6`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_7` FOREIGN KEY (`exercício_7`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_8` FOREIGN KEY (`exercício_8`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exercício_9` FOREIGN KEY (`exercício_9`) REFERENCES `exercícios` (`id_exercício`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `treino`
--
ALTER TABLE `treino`
  ADD CONSTRAINT `fk_id_plano` FOREIGN KEY (`id_plano`) REFERENCES `plano_treino` (`id_plano_treino`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `validação_transferência`
--
ALTER TABLE `validação_transferência`
  ADD CONSTRAINT `fk_id_destino_vd` FOREIGN KEY (`id_clube_destino`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_jogador_vd` FOREIGN KEY (`id_jogador`) REFERENCES `jogadores` (`id_jogador`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_origem_vd` FOREIGN KEY (`id_clube_origem`) REFERENCES `clube` (`id_clube`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
