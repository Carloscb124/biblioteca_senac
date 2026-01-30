-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30/01/2026 às 15:16
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `biblioteca_senac`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `emprestimos`
--

CREATE TABLE `emprestimos` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_livro` int(11) NOT NULL,
  `data_emprestimo` date DEFAULT curdate(),
  `data_prevista` date DEFAULT NULL,
  `data_devolucao` date DEFAULT NULL,
  `devolvido` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `emprestimos`
--

INSERT INTO `emprestimos` (`id`, `id_usuario`, `id_livro`, `data_emprestimo`, `data_prevista`, `data_devolucao`, `devolvido`) VALUES
(6, 5, 7, '2026-01-01', '2026-01-13', NULL, 0),
(7, 5, 1, '2026-01-28', '2026-01-29', '2026-01-30', 1),
(9, 1, 13, '2026-01-30', '2026-02-06', NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `livros`
--

CREATE TABLE `livros` (
  `id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `ano_publicacao` int(11) DEFAULT NULL,
  `disponivel` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ISBN` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `livros`
--

INSERT INTO `livros` (`id`, `titulo`, `autor`, `ano_publicacao`, `disponivel`, `criado_em`, `ISBN`) VALUES
(1, 'A invenção de Hugo Cabret', 'Brian Selznick', 2007, 1, '2026-01-28 11:39:27', '978-85-7675-203-5'),
(2, 'Origem', 'Dan Brown', 2017, 1, '2026-01-28 11:39:27', '978-85-8041-766-1'),
(3, 'O magico de Oz', 'L. Frank Baum', 2013, 1, '2026-01-28 11:39:27', '978-85-378-0966-2'),
(4, 'O lado bom da vida', 'Matthew Quick', 2012, 1, '2026-01-28 11:39:27', '978-85-8057-277-3'),
(5, 'Principios de Administração Financeira', 'Lawrence J. Gitman', 2010, 1, '2026-01-28 11:39:27', '978-85-7605-332-3'),
(6, 'Os Últimos dias de Krypton', 'Kevin J. Anderson', 2012, 1, '2026-01-28 11:39:27', '978-85-441-0334-0'),
(7, 'Cadê você, Bernadete?', 'Maria Semple', 2012, 0, '2026-01-28 11:39:27', '978-85-359-2293-6'),
(8, 'Hardware 2, o guia definivito', 'Carlos Eduardo Morimoto', 2010, 1, '2026-01-28 11:39:27', '978-85-99593-16-5'),
(9, 'HTML e CSS', 'Paulo Henrique Santo Pedro', 2024, 1, '2026-01-28 11:39:27', '978-85-396-4854-2'),
(10, 'O andar do bêbado', 'Leonard Mlodinow', 2008, 1, '2026-01-28 11:39:27', '978-85-378-0155-0'),
(11, 'Empreendedorismo: Uma visão do processo', 'Robert A. Baron', 2015, 1, '2026-01-28 11:39:27', '978-85-221-0533-5'),
(12, 'Planejamento Estratégico: Conceitos, metodologia e práticas', 'Djalma de Pinho Rebouças de Oliveira', 2015, 1, '2026-01-28 11:39:27', '978-85-97-00069-6'),
(13, 'Empreendedorismo: Dando asas ao espirito empreendedor', 'Editora Manole', 2012, 0, '2026-01-28 11:39:27', '978-85-204-3829-9'),
(14, 'Diario de um banana: Bons tempos', 'Jeff Kinney', 2015, 1, '2026-01-28 11:39:27', '978-85-7683-942-2'),
(15, 'Gestão de Vendas os 21 Segredos do Sucesso', 'Marcos Cobra', 2007, 1, '2026-01-28 11:39:27', '978-85-02-06435-5'),
(16, 'O grande', 'Golias', 1547, 1, '2026-01-29 12:26:15', '1565489878964'),
(17, 'O pequeno', 'davi', 1548, 1, '2026-01-29 12:27:26', '1565489878965'),
(18, 'O medio', 'Rei Saul', 1546, 1, '2026-01-29 12:36:28', '145-456-589-456-4');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `perfil` enum('admin','leitor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `telefone`, `criado_em`, `perfil`) VALUES
(1, 'Carlos Eduardo', 'teste@gmail.com', '$2y$10$V8.gWFVa40sbci2zIlkzjepCVZWp8FONrsVSuDlAQ//FMC3.0WPwK', NULL, '2026-01-29 13:38:22', 'admin'),
(5, 'Carlos', 'teste3@gmail.com', '$2y$10$q3xsP38hHq69XRsMb184xOXaF797l.qrfNtjlH0CDKgi/4EtUhHdO', NULL, '2026-01-29 13:43:45', 'leitor');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_livro` (`id_livro`);

--
-- Índices de tabela `livros`
--
ALTER TABLE `livros`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `livros`
--
ALTER TABLE `livros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD CONSTRAINT `emprestimos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `emprestimos_ibfk_2` FOREIGN KEY (`id_livro`) REFERENCES `livros` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
