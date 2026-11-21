-- 21/11 (pedido do usuário: "sistema de enviar e-mail pelo site"): fila de e-mails enviados pelo Gmail (inc/email.php,
-- enviar-emails.php no cron de minuto em minuto), lotes escritos no admin (/admin/emails.php), formulário de contato
-- (/contato.php) e quem pediu para não receber mais os e-mails da equipe (/descadastrar.php).
CREATE TABLE IF NOT EXISTS emails_lotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assunto VARCHAR(200) NOT NULL,
  texto TEXT NOT NULL,
  publico VARCHAR(120) NOT NULL,         -- descrição de para quem foi (ex.: 'Donos · Poços de Caldas')
  total INT NOT NULL DEFAULT 0,
  admin_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  para VARCHAR(160) NOT NULL,
  nome VARCHAR(120) NULL,
  assunto VARCHAR(200) NOT NULL,
  texto TEXT NOT NULL,                   -- texto simples; o HTML é montado na hora de enviar (inc/email.php)
  tipo VARCHAR(20) NOT NULL,             -- 'equipe' (admin), 'aviso' (automático), 'contato' (formulário), 'teste'
  lote_id INT NULL,
  responder_para VARCHAR(160) NULL,      -- Reply-To (formulário de contato: o e-mail de quem escreveu)
  status VARCHAR(12) NOT NULL DEFAULT 'fila',   -- 'fila', 'enviado', 'erro' (desistiu depois de 3 tentativas), 'pulado' (descadastrado)
  tentativas INT NOT NULL DEFAULT 0,
  proxima_em DATETIME NULL,              -- depois de uma falha, só tenta de novo a partir daqui
  erro VARCHAR(255) NULL,
  admin_id INT NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_em DATETIME NULL,
  KEY fila (status, proxima_em, id),
  KEY lote (lote_id),
  KEY enviados (enviado_em),
  KEY contato (tipo, ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS emails_descadastro (
  email VARCHAR(160) PRIMARY KEY,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
