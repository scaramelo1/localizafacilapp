-- 25/11/2026 (passo 85): Amarilis, a assistente do Localiza Fácil no WhatsApp (inc/amarilis.php).
-- Uma conversa por número (ou por admin, na tela de teste); cada mensagem recebida e cada resposta; recados aos donos.
CREATE TABLE IF NOT EXISTS amarilis_conversas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  canal VARCHAR(12) NOT NULL,                 -- whatsapp | teste
  contato VARCHAR(40) NOT NULL,               -- número do WhatsApp (wa_id) ou admin-<id>
  nome VARCHAR(120) NULL,                     -- nome do perfil do WhatsApp
  cidade VARCHAR(60) NULL,                    -- slug da última cidade procurada
  bloqueada TINYINT(1) NOT NULL DEFAULT 0,    -- admin bloqueou (abuso): não responde mais
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (canal, contato),
  KEY (atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amarilis_mensagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversa_id INT NOT NULL,
  de VARCHAR(10) NOT NULL,                    -- pessoa | amarilis
  texto TEXT NOT NULL,
  wa_id VARCHAR(120) NULL,                    -- id da mensagem no WhatsApp (não processa a mesma 2x)
  status VARCHAR(12) NOT NULL DEFAULT 'ok',   -- pessoa: nova | respondendo | ok | erro; amarilis: ok | erro
  fichas VARCHAR(400) NULL,                   -- fichas citadas na resposta (#id Nome; ...), para a próxima pergunta
  ferramentas TEXT NULL,                      -- o que a IA consultou (JSON), para conferir no admin
  chamadas_ia TINYINT NOT NULL DEFAULT 0,
  modelo VARCHAR(40) NULL,
  ms INT NULL,
  erro VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (wa_id),
  KEY (conversa_id, id),
  KEY (status),
  KEY (criado_em),
  CONSTRAINT fk_amarilis_msg_conv FOREIGN KEY (conversa_id) REFERENCES amarilis_conversas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amarilis_recados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversa_id INT NOT NULL,
  profissional_id INT NOT NULL,
  nome VARCHAR(120) NULL,                     -- nome que o cliente deu
  texto TEXT NOT NULL,
  com_numero TINYINT(1) NOT NULL DEFAULT 0,   -- o cliente deixou passar o número dele ao dono
  para_email VARCHAR(160) NULL,
  email_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (profissional_id),
  KEY (conversa_id),
  CONSTRAINT fk_amarilis_rec_conv FOREIGN KEY (conversa_id) REFERENCES amarilis_conversas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
