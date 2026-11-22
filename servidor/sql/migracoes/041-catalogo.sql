-- 22/11 (passo 45): catálogo da ficha (só Destaque)
-- catalogo_busca: nomes dos itens (para a busca achar "X-Tudo"); catalogo_itens: quantos itens no ar; catalogo_em: última mudança
ALTER TABLE profissionais ADD COLUMN catalogo_busca TEXT NULL AFTER cardapio, ADD COLUMN catalogo_itens INT NOT NULL DEFAULT 0 AFTER catalogo_busca,
    ADD COLUMN catalogo_em DATE NULL AFTER catalogo_itens;
-- "Pedir este item" (WhatsApp com o item escrito): quantas vezes por item e por dia
CREATE TABLE IF NOT EXISTS catalogo_pedidos (
    profissional_id INT NOT NULL,
    item_id VARCHAR(16) NOT NULL,
    item_nome VARCHAR(120) NOT NULL,
    dia DATE NOT NULL,
    origem VARCHAR(12) NOT NULL DEFAULT 'app',
    total INT NOT NULL DEFAULT 0,
    PRIMARY KEY (profissional_id, item_id, dia, origem),
    KEY dia (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
