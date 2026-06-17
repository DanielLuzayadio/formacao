CREATE TABLE IF NOT EXISTS `mnr_config` (
  `chave` VARCHAR(50) NOT NULL PRIMARY KEY,
  `valor` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mnr_config` (`chave`, `valor`) VALUES
('nome', 'Centro de Formação'),
('icone', '🎓'),
('morada', ''),
('activado', 'true')
ON DUPLICATE KEY UPDATE `chave`=`chave`;

CREATE TABLE IF NOT EXISTS `mnr_utilizadores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` ENUM('admin','recepcao') NOT NULL DEFAULT 'recepcao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mnr_utilizadores` (`nome`, `senha`, `perfil`) VALUES
('admin', 'admin123', 'admin'),
('recepcao', '1234', 'recepcao')
ON DUPLICATE KEY UPDATE `nome`=`nome`;

CREATE TABLE IF NOT EXISTS `mnr_cursos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `duracao` VARCHAR(100) DEFAULT '',
  `valor` DECIMAL(12,2) DEFAULT 0,
  `desc` TEXT DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_turmas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `cursoId` INT NOT NULL,
  `horario` VARCHAR(100) DEFAULT '',
  `vagas` INT DEFAULT 20,
  `inicio` DATE DEFAULT NULL,
  `fim` DATE DEFAULT NULL,
  `formador` VARCHAR(150) DEFAULT '',
  `estado` ENUM('Em curso','Agendada','Concluída') DEFAULT 'Agendada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_alunos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `tel` VARCHAR(30) DEFAULT '',
  `nasc` DATE DEFAULT NULL,
  `bi` VARCHAR(50) DEFAULT '',
  `sexo` ENUM('M','F') DEFAULT 'M',
  `cursoId` INT DEFAULT NULL,
  `turmaId` INT DEFAULT NULL,
  `propina` DECIMAL(12,2) DEFAULT 0,
  `inscricao` DATE DEFAULT NULL,
  `estado` ENUM('Activo','Concluído','Desistiu') DEFAULT 'Activo',
  `tshirt_num` VARCHAR(10) DEFAULT '',
  `tshirt_tam` VARCHAR(5) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_pagamentos` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `alunoId` INT NOT NULL,
  `mes` VARCHAR(7) NOT NULL,
  `valor` DECIMAL(12,2) DEFAULT 0,
  `desconto` DECIMAL(12,2) DEFAULT 0,
  `metodo` VARCHAR(50) DEFAULT 'Dinheiro',
  `data` DATE DEFAULT NULL,
  `obs` TEXT DEFAULT '',
  `utilizador` VARCHAR(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_presencas` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `turmaId` INT NOT NULL,
  `alunoId` INT NOT NULL,
  `data` DATE NOT NULL,
  `estado` ENUM('P','F','J') DEFAULT 'P'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_certificados` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `alunoId` INT NOT NULL,
  `cursoId` INT DEFAULT NULL,
  `emitidoEm` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `utilizador` VARCHAR(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mnr_senhas_rotativas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dataAtivacao` DATE DEFAULT NULL,
  `totalMeses` INT DEFAULT 10,
  `senhas` TEXT DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mnr_senhas_rotativas` (`dataAtivacao`, `totalMeses`, `senhas`) VALUES
(NULL, 10, '[]');
