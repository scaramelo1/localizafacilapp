-- 23/11 (avaliação do site, item 3): fichas repetidas. Admin > Comércios > "Fichas repetidas" (/admin/duplicadas.php).
-- 'juntada': a ficha sai_id foi pausada e juntada na fica_id (favoritos e avaliações passam; o endereço antigo da ficha
-- que saiu redireciona (301) para a que ficou). 'diferentes': o admin disse que não são a mesma (o par não volta à lista).
-- antes: JSON com o que mudou, para Desfazer.
CREATE TABLE IF NOT EXISTS fichas_iguais (
    a_id INT NOT NULL,
    b_id INT NOT NULL,
    situacao ENUM('juntada', 'diferentes') NOT NULL,
    fica_id INT NULL,
    sai_id INT NULL,
    antes TEXT NULL,
    decidido_em DATETIME NOT NULL,
    decidido_por INT NULL,
    PRIMARY KEY (a_id, b_id),
    KEY sai (sai_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
