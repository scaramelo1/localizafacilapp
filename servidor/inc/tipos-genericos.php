<?php
// 23/11 (passo 78, avaliação: qualidade dos dados): fichas com tipo genérico ("Outros", "Escritório da empresa", "Loja",
// "Empresa" ou sem tipo) não aparecem nos filtros nem nas páginas de tipo. Sugere um tipo pelo NOME, só entre os tipos que
// o site já usa (com a área mais comum desse tipo). Quem decide é o admin em /admin/tipos.php. Também aponta as "fichas"
// que nem são comércio (o nome é um endereço ou um bairro).
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/limpeza.php';

const TIPOS_GENERICOS = ['', 'Outros', 'Escritório da empresa', 'Empresa', 'Loja'];
// [expressão no nome (sem acento, minúsculo), tipo] — a primeira que bate vale (as mais específicas primeiro)
const TIPOS_REGRAS = [
    ['/\b(pousada|hotel|hostel|chale|chales)\b/', 'Hotel'],
    ['/\bpizz/', 'Pizzaria'],
    ['/\b(burger|hamburg)/', 'Hamburgueria'],
    ['/\b(trattoria|restaurante|cantina|marmit|churrascaria|comida caseira)/', 'Restaurante'],
    ['/\b(lanche|lanchonete|lanches|pastel|pastelaria|espetinho)/', 'Lanchonete'],
    ['/\b(padaria|panific|paes|pao de)\b/', 'Padaria'],
    ['/\b(confeitaria|patisser|bolos?|cake)\b/', 'Confeitaria'],
    ['/\b(doces?|doceria|brigadeir|chocolat)/', 'Doceria'],
    ['/\b(cafeteria|coffee|cafe)\b/', 'Cafeteria'],
    ['/\b(sorvet|acai|gelat|picole)/', 'Sorveteria'],
    ['/\b(acougue|carnes|boutique de carne)\b/', 'Açougue'],
    ['/\b(supermercado|supermercados)\b/', 'Supermercado'],
    ['/\b(hortifruti|sacolao|frutas|verduras)\b/', 'Mercado de frutas e vegetais'],
    ['/\b(mercearia|mercadinho|mercado|minimercado)\b/', 'Mercado'],
    ['/\b(adega|bebidas)\b/', 'Distribuidora de bebidas'],
    ['/\b(choperia|boteco|botequim|pub|bar)\b/', 'Bar'],
    ['/\b(barber|barbearia|barbeiro)/', 'Barbearia'],
    ['/\b(sobrancelh|cilios|lash|brow)/', 'Salão de sobrancelhas'],
    ['/\b(manicure|pedicure|unhas?|nail)/', 'Manicure'],
    ['/\b(tattoo|tatuag|piercing)/', 'Estúdio de tatuagem'],
    ['/\b(estetica automotiva|lava ?rapido|lava ?jato|lavagem)/', 'Lava-rápido'],
    ['/\b(estetica|depilac)/', 'Estética'],
    ['/\b(cabeleir|hair|salao|beauty|beleza|cabelos?)\b/', 'Salão de Beleza'],
    ['/\b(perfum)/', 'Perfumaria'],
    ['/\b(cosmetic)/', 'Loja de cosmético'],
    ['/\b(semijoias?|joias?|joalheria|relojoaria)\b/', 'Joalheria'],
    ['/\b(calcados?|sapatos?|sapataria)\b/', 'Loja de calçado'],
    ['/\b(lingerie|moda intima)\b/', 'Loja de lingerie'],
    ['/\b(uniformes?)\b/', 'Loja de uniformes'],
    ['/\b(tecidos)\b/', 'Loja de tecidos'],
    ['/\b(modas?|roupas?|boutique|confecc|vestuario|brecho|store)\b/', 'Loja de Roupa'],
    ['/\b(otica|oticas|oculos)\b/', 'Ótica'],
    ['/\b(farmacia|drogaria|drogas)\b/', 'Farmácia'],
    ['/\b(odonto|dentista|dental|ortodont)/', 'Dentista'],
    ['/\b(psicolog)/', 'Psicólogo'],
    ['/\b(fisioter)/', 'Clínica de Fisioterapia'],
    ['/\b(nutricion)/', 'Nutricionista'],
    ['/\b(veterinar|vet)\b/', 'Veterinário'],
    ['/\b(racao|racoes|agropecuaria|agro)\b/', 'Loja de ração'],
    ['/\b(pet|petshop|banho e tosa)\b/', 'Pet Shop'],
    ['/\b(pilates)\b/', 'Estúdio de pilates'],
    ['/\b(academia|fitness|crossfit|gym|musculacao)\b/', 'Academia'],
    ['/\b(advocacia|advogad|juridic)/', 'Advogado'],
    ['/\b(contabil|contabilidade|contador)/', 'Escritório de contabilidade'],
    ['/\b(imobiliaria|imoveis|imovel)\b/', 'Imobiliária'],
    ['/\b(seguros|corretora de seguro)/', 'Corretor de seguros'],
    ['/\b(construtora|construcoes|empreiteira)\b/', 'Construtora'],
    ['/\b(materiais (para |de )?construc|material de construc|deposito|pimacol|cimento)/', 'Loja de materiais de construção'],
    ['/\b(materiais eletricos|material eletrico|eletrica|eletricos)\b/', 'Loja de materiais elétricos'],
    ['/\b(eletricista|instalacoes eletricas)\b/', 'Eletricista'],
    ['/\b(serralher)/', 'Serralharia'],
    ['/\b(vidracaria|vidros|box)\b/', 'Vidraçaria'],
    ['/\b(marcenaria|marceneiro|planejados)\b/', 'Loja de marcenaria'],
    ['/\b(colchao|colchoes)\b/', 'Loja de colchões'],
    ['/\b(moveis|movel)\b/', 'Loja de móveis'],
    ['/\b(tintas)\b/', 'Loja de tintas'],
    ['/\b(ferragens|ferramentas)\b/', 'Loja de ferramentas'],
    ['/\b(gas)\b/', 'Companhia de gás'],
    ['/\b(energia solar|solar)\b/', 'Fornecedor de equipamentos a energia solar'],
    ['/\b(auto ?pecas|autopecas|pecas)\b/', 'Loja de autopeças'],
    ['/\b(auto ?eletrica|autoeletric)/', 'Autoelétrico'],
    ['/\b(funilaria|pintura automotiva)\b/', 'Funilaria'],
    ['/\b(pneus?|borracharia)\b/', 'Comércio de Pneu'],
    ['/\b(auto ?escola|cfc)\b/', 'Autoescola'],
    ['/\b(despachante)\b/', 'Despachante'],
    ['/\b(motos?|motocicletas)\b/', 'Oficina mecânica de motos'],
    ['/\b(mecanica|mecanico|oficina|auto center|retifica)\b/', 'Oficina mecânica'],
    ['/\b(veiculos|automoveis|multimarcas|seminovos)\b/', 'Revendedora de carros usados'],
    ['/\b(capoeira|jiu|karate|judo|muay|lutas?)\b/', 'Academia'],
    ['/\b(escola|colegio)\b/', 'Escola de educação geral'],
    ['/\b(idiomas|ingles|english)\b/', 'Escola de idiomas'],
    ['/\b(igreja|paroquia|assembleia de deus|congregacao)\b/', 'Igreja'],
    ['/\b(grafica|impressos|impressao|comunicacao visual)\b/', 'Gráfica'],
    ['/\b(assistencia|conserto).*\b(celular|cel|cell|iphone)\b|\b(celular|cell)\b.*\bassistencia/', 'Oficina de reparos de celulares'],
    ['/\b(celular|celulares|cell|iphone|smartphone)\b/', 'Loja de celulares'],
    ['/\b(informatica|computador|notebook)\b/', 'Loja de Informática'],
    ['/\b(papelaria)\b/', 'Papelaria'],
    ['/\b(floricultura|flores)\b/', 'Floricultura'],
    ['/\b(brinquedos?)\b/', 'Loja de Brinquedos'],
    ['/\b(presentes|canecas|personalizados)\b/', 'Loja de presentes'],
    ['/\b(utilidades|variedades|bazar|armarinho|1 ?99)\b/', 'Loja de variedades'],
    ['/\b(chaveiro)\b/', 'Chaveiro(a)'],
    ['/\b(fotografia|fotografo|foto)\b/', 'Fotógrafo'],
    ['/\b(transportes?|mudancas|fretes?|logistica|carretos)\b/', 'Serviço de transporte'],
    ['/\b(turismo|viagens)\b/', 'Agência de turismo'],
    ['/\b(marketing|publicidade|agencia digital)\b/', 'Agência de Marketing'],
    ['/\b(embalagens?)\b/', 'Empresa de embalagens'],
    ['/\b(produtos de limpeza|limpeza)\b/', 'Fornecedor de produtos de limpeza'],
    ['/\b(engenharia|engenheiro)\b/', 'Empresa de engenharia civil'],
    ['/\b(arquitet)/', 'Arquiteto'],
    ['/\b(lavanderia)\b/', 'Lavanderia'],
    ['/\b(tabacaria|headshop)\b/', 'Tabacaria'],
    ['/\b(livraria)\b/', 'Livraria'],
    ['/\b(costura|costureira)\b/', 'Costureira'],
    ['/\b(monitoramento|alarmes?|cftv|seguranca eletronica)\b/', 'Fornecedor de sistema de segurança'],
    ['/\b(buffet|festas|salao de festas)\b/', 'Salão de festas'],
];

/** O nome é um endereço ("R. Getúlio Vargas, 507 - Lj 4") ou um bairro ("Jardim Moneda")? Então não é um comércio. */
function tipo_nao_e_comercio(string $nome): bool {
    $t = chave_texto($nome);
    if (preg_match('/^(r|rua|av|avenida|al|alameda|praca|pca|rod|rodovia|estrada|travessa|tv)\b[ .]*[a-z].*\d/', $t)) return true;
    return (bool)preg_match('/^(jardim|jd|vila|parque|residencial|bairro|conjunto|loteamento|condominio|recanto) [a-z ]{3,30}$/', trim($t));
}

/** Tipos que o site já usa => id da área mais comum de cada um. */
function tipos_existentes(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = [];
    foreach (linhas("SELECT p.tipo, p.categoria_id, COUNT(*) AS n FROM profissionais p WHERE p.status = 'ativo' AND COALESCE(p.tipo, '') <> ''
        GROUP BY p.tipo, p.categoria_id ORDER BY n DESC") as $l) $r[$l['tipo']] ??= (int)$l['categoria_id'];
    return $r;
}

/**
 * Sugestões: ['mudar' => [[ficha, tipo novo, categoria_id nova], ...], 'lixo' => [ficha, ...], 'sem' => número sem sugestão].
 */
function tipos_propor(): array {
    $existe = tipos_existentes();
    $r = ['mudar' => [], 'lixo' => [], 'sem' => 0];
    $in = implode(',', array_fill(0, count(TIPOS_GENERICOS), '?'));
    foreach (linhas("SELECT p.id, p.nome, p.tipo, p.cidade, p.categoria_id, c.nome AS categoria FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE p.status = 'ativo' AND COALESCE(p.tipo, '') IN ($in) ORDER BY p.cidade, p.nome", TIPOS_GENERICOS) as $p) {
        if (tipo_nao_e_comercio((string)$p['nome'])) { $r['lixo'][] = $p; continue; }
        $t = ' ' . chave_texto((string)$p['nome']) . ' ';
        $achou = null;
        foreach (TIPOS_REGRAS as [$re, $tipo]) if (preg_match($re, $t) && isset($existe[$tipo])) { $achou = $tipo; break; }
        if ($achou) $r['mudar'][] = [$p, $achou, $existe[$achou]];
        else $r['sem']++;
    }
    return $r;
}
