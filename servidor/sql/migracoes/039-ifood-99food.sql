-- 22/11 (passo 41): link da página do comércio no iFood e no 99Food (só áreas de comida: Alimentação e Mercados e bebidas)
ALTER TABLE profissionais ADD COLUMN ifood VARCHAR(300) NULL AFTER site, ADD COLUMN food99 VARCHAR(300) NULL AFTER ifood;
ALTER TABLE contatos MODIFY tipo ENUM('perfil','whatsapp','ligar','instagram','mapa','site','compartilhar','email','ifood','food99') NOT NULL;
