-- ==============================================================================
-- FelixBus - Esquema Relacional da Base de Dados MySQL
-- Unidade Curricular: Linguagens de Programação para a Internet (LPI / IPCB)
-- Autores: João Resina e Rafael Cruz
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `felixbus` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `felixbus`;

-- --------------------------------------------------------
-- 1. Tabela de Perfis de Acesso (RBAC)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `perfis` (
  `id_perfil` INT AUTO_INCREMENT PRIMARY KEY,
  `designacao` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `perfis` (`id_perfil`, `designacao`) VALUES
(1, 'Visitante'),
(2, 'Cliente'),
(3, 'Funcionário'),
(4, 'Administrador');

-- --------------------------------------------------------
-- 2. Tabela de Utilizadores
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilizadores` (
  `id_utilizador` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nif` VARCHAR(9) NULL,
  `telefone` VARCHAR(15) NULL,
  `id_perfil` INT NOT NULL DEFAULT 2,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_perfil`) REFERENCES `perfis` (`id_perfil`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabela de Carteiras Virtuais dos Clientes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `carteiras` (
  `id_carteira` INT AUTO_INCREMENT PRIMARY KEY,
  `id_utilizador` INT NOT NULL UNIQUE,
  `saldo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `data_atualizacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabela de Transações e Auditoria Financeira
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transacoes` (
  `id_transacao` INT AUTO_INCREMENT PRIMARY KEY,
  `id_carteira` INT NOT NULL,
  `tipo` ENUM('CARREGAMENTO', 'COMPRA', 'REEMBOLSO') NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `data_transacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_carteira`) REFERENCES `carteiras` (`id_carteira`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabela de Autocarros da Frota
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `autocarros` (
  `id_autocarro` INT AUTO_INCREMENT PRIMARY KEY,
  `matricula` VARCHAR(15) NOT NULL UNIQUE,
  `modelo` VARCHAR(100) NOT NULL,
  `lotacao_maxima` INT NOT NULL DEFAULT 50,
  `estado` ENUM('ATIVO', 'MANUTENCAO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabela de Rotas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rotas` (
  `id_rota` INT AUTO_INCREMENT PRIMARY KEY,
  `origem` VARCHAR(100) NOT NULL,
  `destino` VARCHAR(100) NOT NULL,
  `duracao_estimada_min` INT NOT NULL,
  `preco_base` DECIMAL(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Tabela de Horários e Viagens
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horarios` (
  `id_horario` INT AUTO_INCREMENT PRIMARY KEY,
  `id_rota` INT NOT NULL,
  `id_autocarro` INT NOT NULL,
  `data_partida` DATE NOT NULL,
  `hora_partida` TIME NOT NULL,
  `hora_chegada_prevista` TIME NOT NULL,
  `lugares_disponiveis` INT NOT NULL,
  `estado_viagem` ENUM('AGENDADA', 'EM_TRANSITO', 'CONCLUIDA', 'CANCELADA', 'ATRASADA') NOT NULL DEFAULT 'AGENDADA',
  FOREIGN KEY (`id_rota`) REFERENCES `rotas` (`id_rota`) ON DELETE RESTRICT,
  FOREIGN KEY (`id_autocarro`) REFERENCES `autocarros` (`id_autocarro`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Tabela de Bilhetes Emitidos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bilhetes` (
  `id_bilhete` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_bilhete` VARCHAR(32) NOT NULL UNIQUE,
  `id_utilizador` INT NOT NULL,
  `id_horario` INT NOT NULL,
  `numero_lugar` INT NOT NULL,
  `preco_pago` DECIMAL(6,2) NOT NULL,
  `estado` ENUM('EMITIDO', 'VALIDADO', 'CANCELADO') NOT NULL DEFAULT 'EMITIDO',
  `data_compra` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_utilizador`) REFERENCES `utilizadores` (`id_utilizador`) ON DELETE RESTRICT,
  FOREIGN KEY (`id_horario`) REFERENCES `horarios` (`id_horario`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Tabela de Alertas Operacionais
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alertas` (
  `id_alerta` INT AUTO_INCREMENT PRIMARY KEY,
  `id_horario` INT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `mensagem` TEXT NOT NULL,
  `tipo_alerta` ENUM('INFO', 'AVISO', 'ATRASO', 'CANCELAMENTO') NOT NULL DEFAULT 'INFO',
  `data_emissao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_horario`) REFERENCES `horarios` (`id_horario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
