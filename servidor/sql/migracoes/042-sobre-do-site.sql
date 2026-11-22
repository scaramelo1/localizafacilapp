-- 22/11 (passo 55, E): "Sobre" tirado do site do próprio comércio (descrição que o comércio escreveu), sempre aprovado
-- por um admin antes de aparecer. sobre_site / sobre_site_url = o texto aprovado e de onde veio (a ficha mostra com o link).
ALTER TABLE profissionais ADD COLUMN sobre_site TEXT NULL AFTER descricao, ADD COLUMN sobre_site_url VARCHAR(300) NULL AFTER sobre_site;
CREATE TABLE IF NOT EXISTS sobre_site (
    profissional_id INT NOT NULL PRIMARY KEY,
    url VARCHAR(300) NOT NULL,
    titulo VARCHAR(300) NULL,
    texto TEXT NULL,
    situacao ENUM('pendente', 'aprovado', 'recusado', 'sem_texto', 'erro') NOT NULL DEFAULT 'pendente',
    motivo VARCHAR(200) NULL,
    lido_em DATETIME NOT NULL,
    decidido_em DATETIME NULL,
    decidido_por INT NULL,
    KEY situacao (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
