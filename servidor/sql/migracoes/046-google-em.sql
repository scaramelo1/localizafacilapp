-- 23/11 (passo 77): quando a ficha entrou no Google. Quem entrou fica (não sai quando a ordem das ondas muda);
-- as próximas ondas escolhem entre as que ainda não entraram (horário, WhatsApp e e-mail primeiro).
ALTER TABLE profissionais ADD COLUMN google_em DATETIME NULL AFTER confirmado_por, ADD KEY google_em (google_em);
