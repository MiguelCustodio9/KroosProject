-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 21-Jan-2026 às 17:29
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
-- Estrutura da tabela `clube`
--

CREATE TABLE `clube` (
  `id_clube` int(11) NOT NULL,
  `nome_clube` varchar(100) NOT NULL,
  `sigla` char(3) NOT NULL,
  `logotipo` mediumblob NOT NULL,
  `cor` char(7) NOT NULL,
  `data_fundação` date NOT NULL,
  `sede_morada` varchar(100) NOT NULL,
  `país_clube` varchar(50) NOT NULL,
  `cidade_clube` varchar(50) NOT NULL,
  `telefone_clube` varchar(20) NOT NULL,
  `email_clube` varchar(255) NOT NULL,
  `website_clube` varchar(100) DEFAULT NULL,
  `presidente_clube` varchar(100) NOT NULL,
  `instagram_clube` varchar(100) DEFAULT NULL,
  `facebook_clube` varchar(100) NOT NULL,
  `youtube_clube` varchar(100) NOT NULL,
  `twitter_clube` varchar(100) NOT NULL,
  `tiktok_clube` varchar(100) NOT NULL,
  `código_clube` varchar(100) NOT NULL
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
  `tipo_utilizador` enum('admin_clube','treinador','jogador','') NOT NULL
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
-- Índices para tabela `clube`
--
ALTER TABLE `clube`
  ADD PRIMARY KEY (`id_clube`),
  ADD UNIQUE KEY `nome_clube` (`nome_clube`,`telefone_clube`,`email_clube`,`website_clube`,`presidente_clube`,`código_clube`),
  ADD UNIQUE KEY `instagram_clube` (`instagram_clube`,`facebook_clube`,`youtube_clube`,`twitter_clube`,`tiktok_clube`);

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
-- AUTO_INCREMENT de tabela `clube`
--
ALTER TABLE `clube`
  MODIFY `id_clube` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT de tabela `utilizador`
--
ALTER TABLE `utilizador`
  MODIFY `id_utilizador` int(11) NOT NULL AUTO_INCREMENT;

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
-- Limitadores para a tabela `acesso_equipa`
--
ALTER TABLE `acesso_equipa`
  ADD CONSTRAINT `fk_id_equipa_acesso` FOREIGN KEY (`id_equipa`) REFERENCES `equipa` (`id_equipa`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_id_utilizador` FOREIGN KEY (`id_utilizador`) REFERENCES `utilizador` (`id_utilizador`) ON UPDATE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
