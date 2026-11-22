-- 22/11 (passo 42): cardápio da ficha (teste): JSON {"secoes":[{"nome":"Lanches","itens":[{"nome","desc","preco","destaque"}]}]}
ALTER TABLE profissionais ADD COLUMN cardapio LONGTEXT NULL AFTER food99;
