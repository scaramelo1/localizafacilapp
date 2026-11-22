// 22/11 (passo 45): catalogo da ficha no app (Cardapio, Produtos, Ofertas, Pecas...), so Destaque com 1+ item.
// Cada item tem o botao "Pedir", que abre o WhatsApp do comercio ja com o nome e o preco do item.
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Image, Linking, Pressable, StyleSheet, Text, View } from 'react-native';

import { registrarItem, type Catalogo as TipoCatalogo, type CatalogoItem } from '../api/cliente';
import { cores, fonte } from '../tema';

export function Catalogo({ id, catalogo }: { id: number; catalogo: TipoCatalogo }) {
  const pedir = (it: CatalogoItem) => {
    if (!it.pedir) return;
    registrarItem(id, it.id);
    Linking.openURL(it.pedir).catch(() => {});
  };
  return (
    <View style={{ marginTop: 8 }}>
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
                {it.pedir ? (
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
});
