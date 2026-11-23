-- 23/11 (passo 70): robô "Dados pelo site" (dados-do-site.php). Lê o site próprio do comércio uma vez e acha WhatsApp,
-- telefone, Instagram, Facebook, e-mail de contato, o que faz ou vende e endereço. Cada dado fica PENDENTE em dados_site
-- até um admin aprovar em /admin/dados-site.php. O Sobre e o horário que ele achar vão para sobre_site / horario_site.
--
-- E-mail de contato separado: profissionais.email é o LOGIN do dono no painel (único); o do site vai em email_contato.
ALTER TABLE profissionais
    ADD COLUMN email_contato VARCHAR(160) NULL AFTER email,
    ADD COLUMN facebook VARCHAR(300) NULL AFTER instagram;

ALTER TABLE contatos MODIFY tipo ENUM('perfil','whatsapp','ligar','instagram','mapa','site','compartilhar','email','ifood','food99','facebook') NOT NULL;

-- um por ficha: quando o robô leu o site e o que deu
CREATE TABLE IF NOT EXISTS dados_site_lidos (
    profissional_id INT NOT NULL PRIMARY KEY,
    url VARCHAR(300) NOT NULL,
    paginas TINYINT NOT NULL DEFAULT 0,
    achados TINYINT NOT NULL DEFAULT 0,
    situacao ENUM('lido', 'nada', 'erro') NOT NULL,
    motivo VARCHAR(200) NULL,
    lido_em DATETIME NOT NULL,
    KEY situacao (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- um por dado achado (ficha + campo). tipo: preencher = a ficha está sem; diferente = a ficha tem outro valor
CREATE TABLE IF NOT EXISTS dados_site (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    campo ENUM('whatsapp', 'telefone', 'instagram', 'facebook', 'email', 'servicos', 'endereco') NOT NULL,
    valor VARCHAR(500) NOT NULL,
    valor_ficha VARCHAR(500) NULL,
    tipo ENUM('preencher', 'diferente') NOT NULL,
    trecho VARCHAR(400) NULL,
    url VARCHAR(300) NOT NULL,
    situacao ENUM('pendente', 'aprovado', 'recusado') NOT NULL DEFAULT 'pendente',
    valor_antes VARCHAR(500) NULL,
    lido_em DATETIME NOT NULL,
    decidido_em DATETIME NULL,
    decidido_por INT NULL,
    UNIQUE KEY ficha_campo (profissional_id, campo),
    KEY situacao (situacao, campo),
    KEY valor (campo, valor(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
