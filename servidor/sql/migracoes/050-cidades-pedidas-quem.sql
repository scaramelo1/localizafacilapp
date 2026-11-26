-- 25/11 (pedido do usuário): página própria do "Quero minha Cidade!" no admin (/admin/cidades-pedidas.php).
-- Guarda quem pediu (conta do app ou do site, quando a pessoa entrou), de onde (app ou site) e se a equipe já viu.
ALTER TABLE cidades_pedidas
  ADD COLUMN usuario_id INT NULL AFTER uf,
  ADD COLUMN origem VARCHAR(10) NULL AFTER usuario_id,
  ADD COLUMN visto TINYINT(1) NOT NULL DEFAULT 0 AFTER ip,
  ADD KEY usuario (usuario_id),
  ADD KEY visto (visto);
-- os pedidos que já existiam chegaram pela notificação do celular: ficam como não vistos para aparecer no contador
