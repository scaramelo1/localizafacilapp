-- 20/11 (Plano D, passo 6): ficha "a confirmar" x "confirmada". As fichas copiadas do Acha Fácil (lista do Google Maps)
-- entram a confirmar; viram confirmadas quando o dono cadastra/assume (SQL_TEM_DONO, automático) ou quando a equipe
-- confere os dados por telefone/WhatsApp (confirmado_por = 'equipe'). Só confirmada vai para o Google (passo 7).
ALTER TABLE profissionais
  ADD COLUMN IF NOT EXISTS confirmado_em DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS confirmado_por VARCHAR(12) NULL DEFAULT NULL;
CREATE INDEX IF NOT EXISTS confirmado ON profissionais (cidade, confirmado_em);
