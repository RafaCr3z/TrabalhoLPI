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
    descricao VARCHAR(20) NOT NULL -- Nome do perfil (admin, funcionário, cliente)
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
    id INT AUTO_INCREMENT PRIMARY KEY, -- ID sequencial automático
    id_cliente INT NOT NULL,
    id_rota INT NOT NULL,
    data_compra DATETIME DEFAULT CURRENT_TIMESTAMP, -- Data da compra do bilhete
    data_viagem DATE NOT NULL, -- Data da viagem
    hora_viagem TIME NOT NULL, -- Hora da viagem
    numero_lugar INT, -- Número do lugar no ônibus
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
(3, 'cliente');

-- Criando utilizadores padrão
INSERT INTO utilizadores (id, user, pwd, nome, email, telemovel, morada, tipo_perfil) VALUES
(1,'admin', 'admin', 'Administrador', 'admin@felixbus.com', '963333333', 'Coimbra', 1),
(2,'funcionario', 'funcionario', 'Funcionário', 'funcionario@felixbus.com', '962222222', 'Porto', 2),
(3,'cliente', 'cliente', 'Cliente', 'cliente@felixbus.com', '961111111', 'Lisboa', 3);

-- Criando rotas
INSERT INTO rotas (id, origem, destino, preco, capacidade) VALUES
(1,'Lisboa', 'Porto', 25.00, 50),
(2,'Porto', 'Coimbra', 15.00, 40),
(3,'Coimbra', 'Faro', 35.00, 30),
(4,'Lisboa', 'Faro', 30.00, 45),
(5,'Porto', 'Braga', 10.00, 35),
(6,'Braga', 'Guimarães', 8.00, 30),
(7,'Faro', 'Portimão', 12.00, 35),
(8,'Lisboa', 'Coimbra', 20.00, 45),
(9,'Porto', 'Lisboa', 25.00, 50),
(10,'Coimbra', 'Porto', 15.00, 40),
(11,'Faro', 'Lisboa', 30.00, 45),
(12,'Braga', 'Porto', 10.00, 35),
(13,'Guimarães', 'Braga', 8.00, 30),
(14,'Portimão', 'Faro', 12.00, 35),
(15,'Coimbra', 'Lisboa', 20.00, 45);

-- Criando horários das viagens com datas diferentes
INSERT INTO horarios (id_rota, horario_partida, data_viagem, lugares_disponiveis) VALUES
(1, '08:00:00', '2024-06-20', 50),
(1, '14:00:00', '2024-06-21', 50),
(1, '08:00:00', '2024-06-22', 50),
(1, '14:00:00', '2024-06-23', 50),
(1, '08:00:00', '2024-06-24', 50),
(1, '14:00:00', '2024-06-25', 50),
(2, '09:30:00', '2024-06-26', 40),
(2, '15:30:00', '2024-06-27', 40),
(2, '09:30:00', '2024-06-28', 40),
(2, '15:30:00', '2024-06-29', 40),
(3, '07:00:00', '2024-06-30', 30),
(3, '13:00:00', '2024-07-01', 30),
(3, '07:00:00', '2024-07-02', 30),
(3, '13:00:00', '2024-07-03', 30),
(4, '07:30:00', '2024-07-04', 45),
(4, '15:30:00', '2024-07-05', 45),
(4, '07:30:00', '2024-07-06', 45),
(4, '15:30:00', '2024-07-07', 45),
(5, '09:00:00', '2024-07-08', 35),
(5, '17:00:00', '2024-07-09', 35),
(5, '09:00:00', '2024-07-10', 35),
(5, '17:00:00', '2024-07-11', 35),
(6, '08:30:00', '2024-07-12', 30),
(6, '16:30:00', '2024-07-13', 30),
(6, '08:30:00', '2024-07-14', 30),
(6, '16:30:00', '2024-07-15', 30),
(7, '10:00:00', '2024-07-16', 35),
(7, '18:00:00', '2024-07-17', 35),
(7, '10:00:00', '2024-07-18', 35),
(7, '18:00:00', '2024-07-19', 35),
(8, '07:00:00', '2024-07-20', 45),
(8, '13:00:00', '2024-07-21', 45),
(8, '19:00:00', '2024-07-22', 45),
(8, '07:00:00', '2024-07-23', 45),
(8, '13:00:00', '2024-07-24', 45),
(8, '19:00:00', '2024-07-25', 45),
(9, '06:30:00', '2024-07-26', 50),
(9, '12:00:00', '2024-07-27', 50),
(9, '18:30:00', '2024-07-28', 50),
(9, '06:30:00', '2024-07-29', 50),
(9, '12:00:00', '2024-07-30', 50),
(9, '18:30:00', '2024-07-31', 50),
(10, '07:30:00', '2024-08-01', 40),
(10, '13:30:00', '2024-08-02', 40),
(10, '07:30:00', '2024-08-03', 40),
(10, '13:30:00', '2024-08-04', 40),
(11, '08:00:00', '2024-08-05', 45),
(11, '16:00:00', '2024-08-06', 45),
(11, '08:00:00', '2024-08-07', 45),
(11, '16:00:00', '2024-08-08', 45),
(12, '09:30:00', '2024-08-09', 35),
(12, '17:30:00', '2024-08-10', 35),
(12, '09:30:00', '2024-08-11', 35),
(12, '17:30:00', '2024-08-12', 35),
(13, '08:15:00', '2024-08-13', 30),
(13, '16:15:00', '2024-08-14', 30),
(13, '08:15:00', '2024-08-15', 30),
(13, '16:15:00', '2024-08-16', 30),
(14, '10:30:00', '2024-08-17', 35),
(14, '18:30:00', '2024-08-18', 35),
(14, '10:30:00', '2024-08-19', 35),
(14, '18:30:00', '2024-08-20', 35),
(15, '07:45:00', '2024-08-21', 45),
(15, '13:45:00', '2024-08-22', 45),
(15, '19:45:00', '2024-08-23', 45),
(15, '07:45:00', '2024-08-24', 45),
(15, '13:45:00', '2024-08-25', 45),
(15, '19:45:00', '2024-08-26', 45);

-- Criando carteira da empresa
INSERT INTO carteira_felixbus (saldo) VALUES (0.00);

-- Criando carteira de um cliente
INSERT INTO carteiras (id_cliente, saldo) VALUES
((SELECT id FROM utilizadores WHERE user = 'cliente'), 0.00);

--Criando alertas
INSERT INTO alertas (id, mensagem, data_inicio, data_fim) VALUES
(1,'Promoção: 20% de desconto em todas as viagens!', '2024-07-01 05:00', '2024-07-31 23:55'),
(2,'Aviso: Alteração nos horários de Lisboa para Porto.', '2024-08-05 10:00', '2024-08-15 21:59'),
(3,'Novos destinos disponíveis a partir de setembro!', '2024-09-01 00:00', '2024-09-30 23:59'),
(4,'Manutenção programada: alguns horários serão cancelados.', '2024-10-10 08:00', '2024-10-20 18:00');

COMMIT;

ALTER TABLE utilizadores
ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1;