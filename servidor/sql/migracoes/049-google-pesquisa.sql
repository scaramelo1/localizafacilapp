-- 25/11 (passo 87, pedido do usuário): vezes que o site apareceu na pesquisa do Google (impressões do Search Console),
-- por dia e por página (com a ficha, quando a página é de uma ficha), e o que as pessoas pesquisaram.
-- Preenchido por servidor/google-pesquisa.php (cron), lido em /admin/numeros.php e /admin/numeros-ficha.php.
CREATE TABLE IF NOT EXISTS google_paginas (
  dia DATE NOT NULL,
  pagina VARCHAR(255) NOT NULL,
  profissional_id INT NULL,
  cidade VARCHAR(40) NULL,
  impressoes INT NOT NULL DEFAULT 0,
  cliques INT NOT NULL DEFAULT 0,
  posicao DECIMAL(6,1) NOT NULL DEFAULT 0,
  PRIMARY KEY (dia, pagina),
  KEY profissional (profissional_id, dia),
  KEY cidade (cidade, dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS google_consultas (
  dia DATE NOT NULL,
  consulta VARCHAR(190) NOT NULL,
  impressoes INT NOT NULL DEFAULT 0,
  cliques INT NOT NULL DEFAULT 0,
  posicao DECIMAL(6,1) NOT NULL DEFAULT 0,
  PRIMARY KEY (dia, consulta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
