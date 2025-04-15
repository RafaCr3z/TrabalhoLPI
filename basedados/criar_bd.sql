-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 17-Jun-2024 às 00:48
-- Versão do servidor: 10.4.28-MariaDB
-- versão do PHP: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `criar_bd`
--
CREATE DATABASE IF NOT EXISTS FelixBus;
USE FelixBus;

-- -----------------------------------------
--              Tabela perfis
-- Descrição: Armazena os diferentes tipos de perfis de utilizadores
-- -----------------------------------------
CREATE TABLE perfis (
    id INT PRIMARY KEY,
    descricao VARCHAR(20) NOT NULL -- Nome do perfil (admin, funcionário, cliente, visitante)
);

-- -----------------------------------------
--              Tabela utilizadores
-- Descrição: Guarda informações dos utilizadores
-- -----------------------------------------
CREATE TABLE utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(20) UNIQUE NOT NULL, -- Nome de utilizador único
    pwd VARCHAR(255) NOT NULL, -- Senha armazenada em texto simples
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telemovel VARCHAR(20) NOT NULL,
    morada TEXT NOT NULL,
    tipo_perfil INT NOT NULL DEFAULT 4, -- Ligação com a tabela perfis
    FOREIGN KEY (tipo_perfil) REFERENCES perfis(id)
);

-- -----------------------------------------
--              Tabela rotas
-- Descrição: Regista as rotas disponíveis
-- -----------------------------------------
CREATE TABLE rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origem VARCHAR(100) NOT NULL,
    destino VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    capacidade INT NOT NULL, -- Número total de lugares no autocarro
    disponivel INT NOT NULL DEFAULT 1 -- Se a rota está ativa (1 = Sim, 0 = Não)
);

-- -----------------------------------------
--              Tabela horários
-- Descrição: Relaciona horários com rotas
-- -----------------------------------------
CREATE TABLE horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_rota INT NOT NULL,
    horario_partida TIME NOT NULL, -- Hora específica da viagem
    data_viagem DATE NOT NULL, -- Data da viagem
    lugares_disponiveis INT NOT NULL, -- Número de lugares disponíveis
    disponivel TINYINT(1) NOT NULL DEFAULT 1, -- Se o horário está disponível
    FOREIGN KEY (id_rota) REFERENCES rotas(id)
);

-- --------------------------------------------------------
--          Tabela bilhetes
-- Descrição: Regista as compras de bilhetes feitas pelos clientes
-- --------------------------------------------------------
CREATE TABLE bilhetes (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()), -- ID único gerado automaticamente
    id_cliente INT NOT NULL,
    id_rota INT NOT NULL,
    data_compra DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data da compra do bilhete
    data_viagem DATE NOT NULL, -- Data da viagem
    hora_viagem TIME NOT NULL, -- Hora da viagem
    FOREIGN KEY (id_cliente) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (id_rota) REFERENCES rotas(id)
);

-- -----------------------------------------
--              Tabela carteiras
-- Descrição: Guarda o saldo dos clientes
-- -----------------------------------------
CREATE TABLE carteiras (
    id_cliente INT PRIMARY KEY,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (id_cliente) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- -----------------------------------------
--           Tabela carteira_felixbus
-- Descrição: Guarda o saldo total da empresa
-- -----------------------------------------
CREATE TABLE carteira_felixbus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    saldo DECIMAL(10,2) DEFAULT 0.00
);

-- -----------------------------------------
--           Tabela transacoes
-- Descrição: Registra todas as transações financeiras
-- -----------------------------------------
CREATE TABLE transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_carteira_felixbus INT,
    valor DECIMAL(10,2) NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- Ex: "compra", "reembolso"
    data_transacao DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data da transação
    descricao TEXT, -- Descrição opcional da transação
    FOREIGN KEY (id_cliente) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (id_carteira_felixbus) REFERENCES carteira_felixbus(id)
);

-- -----------------------------------------
--           Tabela alertas
-- Descrição: Armazena mensagens de alerta e promoções
-- -----------------------------------------
CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem TEXT NOT NULL, -- Texto do alerta
    data_inicio DATETIME NOT NULL, -- Quando começa a ser exibido
    data_fim DATETIME NOT NULL -- Quando deixa de ser exibido
);

-- Inserção de dados iniciais
-- Criando perfis de utilizadores
INSERT INTO perfis (id, descricao) VALUES
(1, 'admin'),
(2, 'funcionario'),
(3, 'cliente'),
(4, 'visitante');

-- Criando utilizadores padrão
INSERT INTO utilizadores (user, pwd, nome, email, telemovel, morada, tipo_perfil) VALUES
('cliente', 'cliente', 'Cliente Exemplo', 'cliente@felixbus.com', '961111111', 'Lisboa', 3),
('funcionario', 'funcionario', 'Funcionário Exemplo', 'funcionario@felixbus.com', '962222222', 'Porto', 2),
('admin', 'admin', 'Administrador', 'admin@felixbus.com', '963333333', 'Coimbra', 1);

-- Criando rotas
INSERT INTO rotas (origem, destino, preco, capacidade) VALUES
('Lisboa', 'Porto', 25.00, 50),
('Porto', 'Coimbra', 15.00, 40),
('Coimbra', 'Faro', 35.00, 30),
('Lisboa', 'Faro', 30.00, 45),
('Porto', 'Braga', 10.00, 35),
('Braga', 'Guimarães', 8.00, 30),
('Faro', 'Portimão', 12.00, 35),
('Lisboa', 'Coimbra', 20.00, 45),
('Porto', 'Lisboa', 25.00, 50),
('Coimbra', 'Porto', 15.00, 40),
('Faro', 'Lisboa', 30.00, 45),
('Braga', 'Porto', 10.00, 35),
('Guimarães', 'Braga', 8.00, 30),
('Portimão', 'Faro', 12.00, 35),
('Coimbra', 'Lisboa', 20.00, 45);

-- Criando horários das viagens
INSERT INTO horarios (id_rota, horario_partida) VALUES
(1, '08:00:00'),
(1, '14:00:00'),
(2, '12:30:00'),
(4, '07:30:00'),
(4, '15:30:00'),
(5, '09:00:00'),
(5, '17:00:00'),
(6, '08:30:00'),
(6, '16:30:00'),
(7, '10:00:00'),
(7, '18:00:00'),
(8, '07:00:00'),
(8, '13:00:00'),
(8, '19:00:00'),
(9, '06:30:00'),
(9, '12:00:00'),
(9, '18:30:00'),
(10, '07:30:00'),
(10, '13:30:00'),
(11, '08:00:00'),
(11, '16:00:00'),
(12, '09:30:00'),
(12, '17:30:00'),
(13, '08:15:00'),
(13, '16:15:00'),
(14, '10:30:00'),
(14, '18:30:00'),
(15, '07:45:00'),
(15, '13:45:00'),
(15, '19:45:00');

-- Criando carteira da empresa
INSERT INTO carteira_felixbus (saldo) VALUES (0.00);

-- Criando carteira de um cliente
INSERT INTO carteiras (id_cliente, saldo) VALUES
((SELECT id FROM utilizadores WHERE user = 'cliente'), 100.00);

--Criando alertas
INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES
('Promoção: 20% de desconto em todas as viagens!', '2025-04-01 05:00', '2025-04-30 23:55'),
('Aviso: Alteração nos horários de Lisboa para Porto.', '2025-04-05 10:00', '2025-04-15 21:59');

COMMIT;
