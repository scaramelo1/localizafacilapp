// 22/11 (passo 49): barra "Ver pedido" e janela do pedido (igual ao site): itens, total, retirar ou entregar,
// pagamento, nome e observacao; "Enviar pedido pelo WhatsApp" abre o WhatsApp do comercio com tudo escrito.
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Linking, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { registrarPedido } from '../api/cliente';
import { contas, dadosCliente, guardarCliente, limparPedido, mensagemPedido, mudarItem, reais, usePedido } from '../carrinho';
import { cores, fonte } from '../tema';

const PAGAMENTOS = ['Pix', 'Dinheiro', 'Cartão (na entrega ou no local)'];

/** Barra fixa embaixo da tela da ficha (so com itens desta loja no pedido). */
export function BarraPedido({ pid }: { pid: number }) {
  const pedido = usePedido();
  const [aberta, setAberta] = useState(false);
  if (!pedido || pedido.pid !== pid) return null;
  const c = contas(pedido);
  return (
    <>
      <View style={estilos.barra}>
        <Pressable accessibilityRole="button" onPress={() => setAberta(true)} style={({ pressed }) => [estilos.barraBotao, pressed && { opacity: 0.9 }]}>
          <MaterialCommunityIcons name="cart-outline" size={24} color="#fff" />
          <Text style={estilos.barraTexto}>
            Ver pedido - {c.q} {c.q === 1 ? 'item' : 'itens'}
            {c.total > 0 ? ` - ${reais(c.total)}${c.semPreco ? '+' : ''}` : ''}
          </Text>
        </Pressable>
      </View>
      <JanelaPedido visivel={aberta} aoFechar={() => setAberta(false)} />
    </>
  );
}

function JanelaPedido({ visivel, aoFechar }: { visivel: boolean; aoFechar: () => void }) {
  const pedido = usePedido();
  const [cli] = useState(dadosCliente);
  const [entrega, setEntrega] = useState(false);
  const [end, setEnd] = useState(cli.end);
  const [pagamento, setPagamento] = useState(PAGAMENTOS[0]);
  const [troco, setTroco] = useState('');
  const [nome, setNome] = useState(cli.nome);
  const [obs, setObs] = useState('');
  if (!pedido) return null;
  const c = contas(pedido);
  const loja = { pid: pedido.pid, nome: pedido.nome, whatsapp: pedido.whatsapp };

  const enviar = () => {
    if (entrega && !end.trim()) {
      Alert.alert('Falta o endereço', 'Coloque o endereço de entrega.');
      return;
    }
    const texto = mensagemPedido(pedido, { entrega, end: end.trim(), pagamento, troco: troco.trim(), nome: nome.trim(), obs: obs.trim() });
    guardarCliente(nome.trim(), end.trim());
    registrarPedido(pedido.pid, Object.keys(pedido.itens));
    Linking.openURL(`${pedido.whatsapp}?text=${encodeURIComponent(texto)}`).catch(() =>
      Alert.alert('Não foi possível abrir', 'Confira se o WhatsApp está instalado.'),
    );
  };

  return (
    <Modal visible={visivel} animationType="slide" transparent onRequestClose={aoFechar} statusBarTranslucent>
      <KeyboardAvoidingView style={estilos.fundo} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <Pressable style={{ flex: 1 }} onPress={aoFechar} accessibilityLabel="Fechar" />
        <View style={estilos.caixa}>
          <View style={estilos.topo}>
            <View style={{ flex: 1 }}>
              <Text style={estilos.titulo}>Seu pedido</Text>
              <Text style={estilos.sub}>{pedido.nome}</Text>
            </View>
            <Pressable accessibilityRole="button" accessibilityLabel="Fechar" onPress={aoFechar} style={estilos.fechar}>
              <MaterialCommunityIcons name="close" size={26} color={cores.texto} />
            </Pressable>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: 24 }}>
            {Object.entries(pedido.itens).map(([id, it]) => (
              <View key={id} style={estilos.item}>
                <Text style={estilos.itemN}>{it.n}x</Text>
                <Text style={estilos.itemNome}>{it.nome}</Text>
                <Text style={estilos.itemPreco}>{it.preco === null ? 'a confirmar' : reais(it.n * it.preco)}</Text>
                <View style={{ flexDirection: 'row', gap: 4 }}>
                  <BotaoRedondo icone="minus" rotulo="Tirar um" onPress={() => mudarItem(loja, id, it, -1)} />
                  <BotaoRedondo icone="plus" rotulo="Pôr mais um" onPress={() => mudarItem(loja, id, it, 1)} />
                </View>
              </View>
            ))}
            <Text style={estilos.total}>
              Total: <Text style={{ color: cores.verde }}>{reais(c.total)}</Text>
              {c.semPreco ? ' + itens a confirmar' : ''}
            </Text>

            <Text style={estilos.rotulo}>Como você quer receber?</Text>
            <Opcao texto="Vou buscar no local" marcada={!entrega} onPress={() => setEntrega(false)} />
            <Opcao texto="Entregar no meu endereço" marcada={entrega} onPress={() => setEntrega(true)} />
            {entrega && <Campo rotulo="Endereço de entrega" valor={end} mudar={setEnd} dica="Rua, número, bairro" />}

            <Text style={estilos.rotulo}>Forma de pagamento</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {PAGAMENTOS.map((pg) => (
                <Pressable key={pg} accessibilityRole="radio" accessibilityState={{ selected: pagamento === pg }} onPress={() => setPagamento(pg)} style={[estilos.chip, pagamento === pg && estilos.chipAtivo]}>
                  <Text style={[estilos.chipTexto, pagamento === pg && { color: '#fff' }]}>{pg}</Text>
                </Pressable>
              ))}
            </View>
            {pagamento === 'Dinheiro' && <Campo rotulo="Troco para quanto? (opcional)" valor={troco} mudar={setTroco} dica="R$ 50,00" teclado="decimal-pad" />}
            <Campo rotulo="Seu nome" valor={nome} mudar={setNome} />
            <Campo rotulo="Observação (opcional)" valor={obs} mudar={setObs} dica="Ex.: sem cebola" linhas />

            <Pressable accessibilityRole="button" onPress={enviar} style={({ pressed }) => [estilos.enviar, pressed && { opacity: 0.9 }]}>
              <MaterialCommunityIcons name="whatsapp" size={26} color="#fff" />
              <Text style={estilos.enviarTexto}>Enviar pedido pelo WhatsApp</Text>
            </Pressable>
            <Text style={estilos.aviso}>O comércio confirma o valor, a entrega e o tempo pelo WhatsApp.</Text>
            <Pressable
              accessibilityRole="button"
              onPress={() =>
                Alert.alert('Esvaziar o pedido?', '', [
                  { text: 'Cancelar', style: 'cancel' },
                  { text: 'Esvaziar', style: 'destructive', onPress: () => { limparPedido(); aoFechar(); } },
                ])
              }
            >
              <Text style={estilos.limpar}>Esvaziar o pedido</Text>
            </Pressable>
          </ScrollView>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function BotaoRedondo({ icone, rotulo, onPress }: { icone: 'plus' | 'minus'; rotulo: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={rotulo} onPress={onPress} hitSlop={6} style={estilos.redondo}>
      <MaterialCommunityIcons name={icone} size={20} color={cores.verde} />
    </Pressable>
  );
}
function Opcao({ texto, marcada, onPress }: { texto: string; marcada: boolean; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="radio" accessibilityState={{ selected: marcada }} onPress={onPress} style={estilos.opcao}>
      <MaterialCommunityIcons name={marcada ? 'radiobox-marked' : 'radiobox-blank'} size={26} color={cores.verde} />
      <Text style={{ fontSize: fonte.normal, color: cores.texto, fontWeight: '700' }}>{texto}</Text>
    </Pressable>
  );
}
function Campo({ rotulo, valor, mudar, dica, linhas, teclado }: { rotulo: string; valor: string; mudar: (v: string) => void; dica?: string; linhas?: boolean; teclado?: 'decimal-pad' }) {
  return (
    <View style={{ marginTop: 12 }}>
      <Text style={estilos.rotuloCampo}>{rotulo}</Text>
      <TextInput value={valor} onChangeText={mudar} placeholder={dica} multiline={linhas} keyboardType={teclado} maxLength={linhas ? 300 : 200}
        style={[estilos.entrada, linhas && { minHeight: 70, textAlignVertical: 'top' }]} placeholderTextColor={cores.textoSuave} />
    </View>
  );
}

const estilos = StyleSheet.create({
  barra: { position: 'absolute', left: 16, right: 16, bottom: 20 },
  barraBotao: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: cores.whatsapp, borderRadius: 999, minHeight: 58, paddingHorizontal: 18, elevation: 6, shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 10, shadowOffset: { width: 0, height: 4 } },
  barraTexto: { color: '#fff', fontWeight: '900', fontSize: fonte.normal },
  fundo: { flex: 1, backgroundColor: 'rgba(0,0,0,0.45)' },
  caixa: { backgroundColor: cores.cartao, borderTopLeftRadius: 24, borderTopRightRadius: 24, paddingHorizontal: 18, paddingTop: 16, maxHeight: '92%' },
  topo: { flexDirection: 'row', alignItems: 'center', marginBottom: 6 },
  titulo: { fontSize: fonte.grande, fontWeight: '900', color: cores.texto },
  sub: { fontSize: fonte.pequena, color: cores.textoSuave },
  fechar: { width: 44, height: 44, borderRadius: 22, backgroundColor: cores.fundo, alignItems: 'center', justifyContent: 'center' },
  item: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: cores.borda },
  itemN: { fontWeight: '900', color: cores.verde, minWidth: 30, fontSize: fonte.normal },
  itemNome: { flex: 1, fontWeight: '700', fontSize: fonte.pequena, color: cores.texto },
  itemPreco: { fontWeight: '800', fontSize: fonte.pequena, color: cores.texto },
  redondo: { width: 34, height: 34, borderRadius: 17, borderWidth: 2, borderColor: cores.borda, alignItems: 'center', justifyContent: 'center', backgroundColor: cores.fundo },
  total: { fontSize: fonte.media, fontWeight: '900', textAlign: 'right', marginTop: 12, color: cores.texto },
  rotulo: { fontSize: fonte.normal, fontWeight: '900', color: cores.texto, marginTop: 18, marginBottom: 4 },
  opcao: { flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 44 },
  chip: { borderWidth: 2, borderColor: cores.borda, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: cores.fundo },
  chipAtivo: { backgroundColor: cores.verde, borderColor: cores.verde },
  chipTexto: { fontWeight: '800', color: cores.texto, fontSize: fonte.pequena },
  rotuloCampo: { fontSize: fonte.pequena, fontWeight: '800', color: cores.texto, marginBottom: 4 },
  entrada: { borderWidth: 2, borderColor: cores.borda, borderRadius: 14, paddingHorizontal: 12, paddingVertical: 10, fontSize: fonte.normal, color: cores.texto, backgroundColor: '#fff' },
  enviar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: cores.whatsapp, borderRadius: 999, minHeight: 58, marginTop: 20 },
  enviarTexto: { color: '#fff', fontWeight: '900', fontSize: fonte.normal },
  aviso: { color: cores.textoSuave, fontSize: fonte.pequena, textAlign: 'center', marginTop: 10 },
  limpar: { color: cores.erro, fontWeight: '800', textAlign: 'center', marginTop: 14, fontSize: fonte.pequena },
});
