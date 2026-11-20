-- 20/11 (Plano D, passos 10 e 11): link de confirmação mandado ao dono pelo WhatsApp e fila "Confirmar por telefone".
CREATE TABLE IF NOT EXISTS confirmacao_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profissional_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  admin_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aberto_em DATETIME NULL,
  usado_em DATETIME NULL,
  resultado VARCHAR(12) NULL,            -- 'ok' (está tudo certo) ou 'corrigiu' (mandou correção)
  UNIQUE KEY token (token_hash),
  KEY ficha (profissional_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS confirmar_fila (
  profissional_id INT PRIMARY KEY,
  situacao VARCHAR(12) NOT NULL,         -- 'nao_atende', 'link', 'fechou', 'pulou'
  tentativas INT NOT NULL DEFAULT 0,
  proxima_em DATETIME NULL,              -- antes disso a ficha não volta para a fila
  admin_id INT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY situacao (situacao, proxima_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
