import { useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, ScrollView, Text, View } from 'react-native';

import { chamar, type ProfissionalResumo, type TipoNegocio } from '../../api/cliente';
import { useCidade } from '../../cidade';
import { Cabecalho } from '../../componentes/Cabecalho';
import { AVISO_ABERTO, CartaoProfissional, Carregando, Falha, Pilula, Vazio } from '../../componentes/ui';
import { cores } from '../../tema';

interface Resposta {
  categoria: { id: number; nome: string; icone: string; cor: string };
  tipos?: TipoNegocio[];
  totalArea?: number;
  profissionais: ProfissionalResumo[];
  total: number;
  temMais: boolean;
}

const POR_PAGINA = 50;

// Uma area (ex.: Alimentacao): cabecalho com o nome e quantos lugares, busca amarela dentro da area e filtros pelo tipo.
// 23/11 (1.17.0): a lista vem de 50 em 50 (antes vinha inteira: Saude em Pocos eram 1.102 lugares, 540 KB de uma vez).
// Os filtros (tipo, busca e aberto agora) sao feitos no servidor; a busca espera a pessoa parar de digitar.
export default function TelaCategoria() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { cidade } = useCidade();
  const [tipo, setTipo] = useState<string | null>(null);
  const [termo, setTermo] = useState('');
  const [termoBusca, setTermoBusca] = useState('');
  const [aberto, setAberto] = useState(false);

  const [cabeca, setCabeca] = useState<{ categoria: Resposta['categoria']; tipos: TipoNegocio[]; totalArea: number } | null>(null);
  const [lista, setLista] = useState<ProfissionalResumo[]>([]);
  const [total, setTotal] = useState(0);
  const [pagina, setPagina] = useState(1);
  const [temMais, setTemMais] = useState(false);
  const [carregando, setCarregando] = useState(true);
  const [carregandoMais, setCarregandoMais] = useState(false);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const pedido = useRef(0);

  // busca: espera 400 ms sem digitar
  useEffect(() => {
    const t = setTimeout(() => setTermoBusca(termo.trim()), 400);
    return () => clearTimeout(t);
  }, [termo]);

  const caminho = useCallback(
    (p: number) => {
      let c = `categoria.php?id=${id}&pagina=${p}&por=${POR_PAGINA}`;
      if (tipo) c += `&tipo=${encodeURIComponent(tipo)}`;
      if (termoBusca) c += `&q=${encodeURIComponent(termoBusca)}`;
      if (aberto) c += '&aberto=1';
      return c;
    },
    [id, tipo, termoBusca, aberto],
  );

  // primeira pagina (de novo a cada filtro ou cidade)
  const carregar = useCallback(
    (puxou = false) => {
      const meu = ++pedido.current;
      if (puxou) setAtualizando(true);
      else setCarregando(true);
      setErro(null);
      chamar<Resposta>(caminho(1))
        .then((d) => {
          if (meu !== pedido.current) return;
          setLista(d.profissionais);
          setTotal(d.total);
          setTemMais(d.temMais);
          setPagina(1);
          if (d.tipos) setCabeca({ categoria: d.categoria, tipos: d.tipos, totalArea: d.totalArea ?? d.total });
        })
        .catch((e: unknown) => {
          if (meu === pedido.current) setErro(e instanceof Error ? e.message : 'Não foi possível carregar.');
        })
        .finally(() => {
          if (meu === pedido.current) {
            setCarregando(false);
            setAtualizando(false);
          }
        });
    },
    [caminho],
  );

  useEffect(() => {
    carregar();
  }, [carregar, cidade.slug]);

  // proxima pagina ao chegar perto do fim
  const carregarMais = () => {
    if (!temMais || carregandoMais || carregando) return;
    const meu = pedido.current;
    const proxima = pagina + 1;
    setCarregandoMais(true);
    chamar<Resposta>(caminho(proxima))
      .then((d) => {
        if (meu !== pedido.current) return;
        setLista((atual) => {
          const ja = new Set(atual.map((x) => x.id));
          return [...atual, ...d.profissionais.filter((x) => !ja.has(x.id))];
        });
        setTemMais(d.temMais);
        setPagina(proxima);
      })
      .catch(() => {})
      .finally(() => setCarregandoMais(false));
  };

  const c = cabeca?.categoria;
  // so mostra filtros que valem a pena (tipos com pelo menos 2 lugares)
  const tipos = (cabeca?.tipos ?? []).filter((x) => x.total >= 2);
  const totalArea = cabeca?.totalArea ?? 0;
  const filtrando = !!(tipo || termoBusca || aberto);

  return (
    <FlatList
      data={lista}
      keyExtractor={(p) => String(p.id)}
      contentContainerStyle={{ paddingBottom: 40, flexGrow: 1 }}
      keyboardShouldPersistTaps="handled"
      refreshing={atualizando}
      onRefresh={() => carregar(true)}
      onEndReached={carregarMais}
      onEndReachedThreshold={0.6}
      initialNumToRender={12}
      windowSize={7}
      ListHeaderComponent={
        <View style={{ marginBottom: 14 }}>
          <Cabecalho
            voltar
            titulo={c?.nome ?? ' '}
            subtitulo={cabeca ? `${totalArea.toLocaleString('pt-BR')} ${totalArea === 1 ? 'lugar' : 'lugares'} em ${cidade.curto}` : undefined}
            busca={{ texto: 'Buscar comércios ou serviços', valor: termo, aoMudar: setTermo }}
          />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 16, gap: 8 }}>
            <Pilula texto="Aberto agora" ativo={aberto} onPress={() => setAberto(!aberto)} />
            {tipos.length > 1 && <Pilula texto="Todos" ativo={tipo === null} onPress={() => setTipo(null)} />}
            {tipos.length > 1 &&
              tipos.map((x) => <Pilula key={x.nome} texto={x.nome} ativo={tipo === x.nome} onPress={() => setTipo(tipo === x.nome ? null : x.nome)} />)}
          </ScrollView>
          {aberto && <Text style={{ paddingHorizontal: 16, paddingTop: 10, fontSize: 15, color: cores.textoSuave }}>{AVISO_ABERTO}</Text>}
          {filtrando && !carregando && lista.length > 0 && (
            <Text style={{ paddingHorizontal: 16, paddingTop: 10, fontSize: 15, color: cores.textoSuave }}>
              {total.toLocaleString('pt-BR')} {total === 1 ? 'lugar encontrado' : 'lugares encontrados'}
            </Text>
          )}
        </View>
      }
      renderItem={({ item }) => (
        <View style={{ paddingHorizontal: 16 }}>
          <CartaoProfissional p={item} origem="categoria" />
        </View>
      )}
      ListFooterComponent={carregandoMais ? <ActivityIndicator style={{ marginVertical: 20 }} color={cores.textoSuave} /> : null}
      ListEmptyComponent={
        carregando ? (
          <Carregando />
        ) : erro ? (
          <Falha mensagem={erro} tentar={() => carregar()} />
        ) : (
          <Vazio
            icone="store-search-outline"
            texto={aberto ? 'Nenhum lugar com horário informado está aberto agora.' : filtrando ? 'Nada encontrado com esse filtro.' : 'Ainda não há lugares nesta área. Volte em breve!'}
          />
        )
      }
    />
  );
}
