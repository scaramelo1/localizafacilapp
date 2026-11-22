// 22/11 (passo 45): catalogo da ficha no app (Cardapio, Produtos, Ofertas, Pecas...), so Destaque com 1+ item.
// Cada item tem o botao "Pedir", que abre o WhatsApp do comercio ja com o nome e o preco do item.
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Alert, Image, Linking, Pressable, StyleSheet, Text, View } from 'react-native';

import { registrarItem, type Catalogo as TipoCatalogo, type CatalogoItem } from '../api/cliente';
import { limparPedido, mudarItem, pedidoDeOutraLoja, usePedido } from '../carrinho';
import { cores, fonte } from '../tema';

export function Catalogo({ id, loja, catalogo }: { id: number; loja: string; catalogo: TipoCatalogo }) {
  const router = useRouter();
  const pedido = usePedido();
  // 22/11 (passo 49): pedido de varios itens (comida, mercado, bebidas com WhatsApp); um pedido por loja
  const comPedido = !!catalogo.carrinho && !!catalogo.whatsapp;
  const outra = pedidoDeOutraLoja(id);
  const qtd = (itemId: string) => (pedido && pedido.pid === id ? pedido.itens[itemId]?.n ?? 0 : 0);
  const mexer = (it: CatalogoItem, d: number) => {
    const dados = { pid: id, nome: loja, whatsapp: catalogo.whatsapp! };
    const fazer = () => mudarItem(dados, it.id, { nome: it.nome, preco: it.precoNum ?? null }, d);
    const aberto = d > 0 ? pedidoDeOutraLoja(id) : null;
    if (!aberto) return fazer();
    Alert.alert('Limpar o pedido?', `Seu pedido tem itens de ${aberto.nome}. Para adicionar itens de ${loja}, é preciso limpar o pedido anterior.`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: `Voltar ao pedido`, onPress: () => router.push(`/profissional/${aberto.pid}?origem=link`) },
      { text: 'Limpar e adicionar', style: 'destructive', onPress: () => { limparPedido(); fazer(); } },
    ]);
  };
  const pedir = (it: CatalogoItem) => {
    if (!it.pedir) return;
    registrarItem(id, it.id);
    Linking.openURL(it.pedir).catch(() => {});
  };
  return (
    <View style={{ marginTop: 8, paddingBottom: comPedido ? 80 : 0 }}>
      {comPedido && outra ? (
        <Pressable onPress={() => router.push(`/profissional/${outra.pid}?origem=link`)} style={estilos.outra}>
          <Text style={estilos.outraTexto}>
            Você tem um pedido aberto em <Text style={{ fontWeight: '900', color: cores.verde }}>{outra.nome}</Text>. Só dá para pedir de uma loja por vez.
          </Text>
        </Pressable>
      ) : null}
      {catalogo.validade ? (
        <View style={estilos.validade}>
          <MaterialCommunityIcons name="calendar-clock" size={18} color={cores.verde} />
          <Text style={estilos.validadeTexto}>{catalogo.validade}</Text>
        </View>
      ) : null}
      {catalogo.atualizado ? <Text style={estilos.atualizado}>Atualizado em {catalogo.atualizado}</Text> : null}
      {catalogo.secoes.map((s) => (
        <View key={s.nome} style={{ marginTop: 14 }}>
          <Text style={estilos.secao}>{s.nome}</Text>
          {s.itens.map((it) => (
            <View key={it.id} style={[estilos.item, it.esgotado && { opacity: 0.55 }]}>
              {it.foto ? <Image source={{ uri: it.foto }} style={estilos.foto} /> : null}
              <View style={{ flex: 1 }}>
                <View style={estilos.linha}>
                  <Text style={estilos.nome}>{it.nome}</Text>
                  <View style={{ alignItems: 'flex-end' }}>
                    {it.precoDe ? <Text style={estilos.precoDe}>{it.precoDe}</Text> : null}
                    {it.preco ? (
                      <Text style={[estilos.preco, it.esgotado && { textDecorationLine: 'line-through' }]}>
                        {it.aPartir ? <Text style={estilos.pequeno}>a partir de </Text> : null}
                        {it.preco}
                        {it.unidade ? <Text style={estilos.pequeno}>/{it.unidade}</Text> : null}
                      </Text>
                    ) : null}
                  </View>
                </View>
                {it.destaque || it.esgotado ? (
                  <View style={{ flexDirection: 'row', gap: 6, marginTop: 2 }}>
                    {it.destaque && !it.esgotado ? <Text style={estilos.selo}>Mais pedido</Text> : null}
                    {it.esgotado ? <Text style={[estilos.selo, { backgroundColor: '#eee', color: '#666' }]}>Esgotado</Text> : null}
                  </View>
                ) : null}
                {it.desc ? <Text style={estilos.desc}>{it.desc}</Text> : null}
                {it.etiquetas.length > 0 ? (
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 5, marginTop: 6 }}>
                    {it.etiquetas.map((e) => (
                      <Text key={e} style={estilos.etiqueta}>
                        {e}
                      </Text>
                    ))}
                  </View>
                ) : null}
                {comPedido && !it.esgotado ? (
                  qtd(it.id) === 0 ? (
                    <Pressable accessibilityRole="button" onPress={() => mexer(it, 1)} style={({ pressed }) => [estilos.adicionar, pressed && { opacity: 0.8 }]}>
                      <MaterialCommunityIcons name="plus" size={18} color={cores.verde} />
                      <Text style={estilos.adicionarTexto}>Adicionar</Text>
                    </Pressable>
                  ) : (
                    <View style={estilos.qtd}>
                      <Pressable accessibilityRole="button" accessibilityLabel="Tirar um" onPress={() => mexer(it, -1)} style={estilos.qtdBotao} hitSlop={6}>
                        <MaterialCommunityIcons name="minus" size={20} color={cores.verde} />
                      </Pressable>
                      <Text style={estilos.qtdN}>{qtd(it.id)}</Text>
                      <Pressable accessibilityRole="button" accessibilityLabel="Pôr mais um" onPress={() => mexer(it, 1)} style={estilos.qtdBotao} hitSlop={6}>
                        <MaterialCommunityIcons name="plus" size={20} color={cores.verde} />
                      </Pressable>
                    </View>
                  )
                ) : it.pedir ? (
                  <Pressable accessibilityRole="button" onPress={() => pedir(it)} style={({ pressed }) => [estilos.pedir, pressed && { opacity: 0.8 }]}>
                    <MaterialCommunityIcons name="whatsapp" size={18} color={cores.whatsapp} />
                    <Text style={estilos.pedirTexto}>Pedir</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ))}
        </View>
      ))}
      <Text style={estilos.aviso}>{catalogo.aviso}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  validade: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', backgroundColor: cores.amarelo, borderRadius: 999, paddingHorizontal: 12, paddingVertical: 5, marginTop: 6 },
  validadeTexto: { fontWeight: '800', color: cores.verde, fontSize: fonte.pequena },
  atualizado: { color: cores.textoSuave, fontSize: fonte.pequena, marginTop: 6 },
  secao: { fontSize: fonte.grande, fontWeight: '900', color: cores.verde, marginBottom: 4 },
  item: { flexDirection: 'row', gap: 12, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: cores.borda, borderStyle: 'dashed' },
  foto: { width: 68, height: 68, borderRadius: 12, backgroundColor: '#fffbea' },
  linha: { flexDirection: 'row', justifyContent: 'space-between', gap: 10 },
  nome: { flex: 1, fontWeight: '800', fontSize: fonte.normal, color: cores.texto },
  preco: { fontWeight: '900', fontSize: fonte.normal, color: cores.verde },
  precoDe: { color: '#9a9a9a', textDecorationLine: 'line-through', fontSize: fonte.pequena, fontWeight: '700' },
  pequeno: { fontSize: fonte.pequena, fontWeight: '700', color: cores.textoSuave },
  selo: { fontSize: 12, fontWeight: '800', backgroundColor: cores.fundoSuave, color: '#7a5b00', borderRadius: 999, paddingHorizontal: 8, paddingVertical: 1, overflow: 'hidden' },
  desc: { color: cores.textoSuave, fontSize: fonte.pequena, marginTop: 3, lineHeight: 20 },
  etiqueta: { fontSize: 12, fontWeight: '800', backgroundColor: cores.verdeClaro, color: cores.verde, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 2, overflow: 'hidden' },
  pedir: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', borderWidth: 2, borderColor: cores.whatsapp, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 5, marginTop: 8 },
  pedirTexto: { fontWeight: '800', color: cores.whatsapp, fontSize: fonte.pequena },
  aviso: { color: cores.textoSuave, fontSize: fonte.pequena, marginTop: 14, lineHeight: 20 },
  outra: { backgroundColor: cores.fundoSuave, borderWidth: 2, borderColor: cores.amarelo, borderRadius: 14, padding: 12, marginTop: 8 },
  outraTexto: { fontSize: fonte.pequena, color: cores.texto, lineHeight: 21 },
  adicionar: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', borderWidth: 2, borderColor: cores.verde, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 6, marginTop: 8 },
  adicionarTexto: { fontWeight: '900', color: cores.verde, fontSize: fonte.pequena },
  qtd: { flexDirection: 'row', alignItems: 'center', gap: 14, alignSelf: 'flex-start', backgroundColor: cores.verde, borderRadius: 999, padding: 4, marginTop: 8 },
  qtdBotao: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  qtdN: { color: '#fff', fontWeight: '900', fontSize: fonte.normal, minWidth: 18, textAlign: 'center' },
});
