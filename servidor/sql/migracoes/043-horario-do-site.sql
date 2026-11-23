-- 23/11 (passo 61): horário lido do site próprio do comércio (horario-do-site.php). Fica pendente até um admin aprovar
-- (corrigindo se precisar) em /admin/sobre-site.php?aba=horario; aprovado vai para profissionais.horarios.
CREATE TABLE IF NOT EXISTS horario_site (
    profissional_id INT NOT NULL PRIMARY KEY,
    url VARCHAR(300) NOT NULL,
    trecho VARCHAR(400) NULL,
    horarios TEXT NULL,
    situacao ENUM('pendente', 'aprovado', 'recusado', 'nao_achou', 'erro') NOT NULL DEFAULT 'pendente',
    motivo VARCHAR(200) NULL,
    lido_em DATETIME NOT NULL,
    decidido_em DATETIME NULL,
    decidido_por INT NULL,
    KEY situacao (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
