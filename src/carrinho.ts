// 22/11 (passo 49): pedido de varios itens do catalogo, enviado inteiro pelo WhatsApp do comercio (igual ao site).
// Um pedido por vez, de uma loja so: guardado no celular (Armazenamento, chave 'carrinho') com a loja.
import { useSyncExternalStore } from 'react';

import { Armazenamento } from './armazenamento';

export interface ItemPedido {
  nome: string;
  /** null = sem preco fixo ("a partir de"): vai como "a confirmar" */
  preco: number | null;
  n: number;
}
export interface Pedido {
  pid: number;
  nome: string;
  whatsapp: string;
  itens: Record<string, ItemPedido>;
}

const CHAVE = 'carrinho';
let atual: Pedido | null = ler();
const ouvintes = new Set<() => void>();

function ler(): Pedido | null {
  try {
    const p = JSON.parse(Armazenamento.ler(CHAVE) ?? 'null') as Pedido | null;
    return p && p.itens && Object.keys(p.itens).length ? p : null;
  } catch {
    return null;
  }
}
function mudar(p: Pedido | null) {
  atual = p && Object.keys(p.itens).length ? p : null;
  if (atual) Armazenamento.gravar(CHAVE, JSON.stringify(atual));
  else Armazenamento.apagar(CHAVE);
  ouvintes.forEach((f) => f());
}

export function usePedido(): Pedido | null {
  return useSyncExternalStore(
    (f) => {
      ouvintes.add(f);
      return () => ouvintes.delete(f);
    },
    () => atual,
  );
}

/** Ha pedido aberto em OUTRA loja? (para perguntar se limpa) */
export function pedidoDeOutraLoja(pid: number): Pedido | null {
  return atual && atual.pid !== pid ? atual : null;
}

/** Soma ou tira 1 (d = +1 / -1). Comecar em outra loja limpa o pedido anterior (perguntar antes!). */
export function mudarItem(loja: { pid: number; nome: string; whatsapp: string }, id: string, item: { nome: string; preco: number | null }, d: number) {
  const base: Pedido = atual && atual.pid === loja.pid ? { ...atual, itens: { ...atual.itens } } : { ...loja, itens: {} };
  const it = base.itens[id] ?? { nome: item.nome, preco: item.preco, n: 0 };
  const n = Math.max(0, Math.min(99, it.n + d));
  if (n === 0) delete base.itens[id];
  else base.itens[id] = { ...it, n };
  mudar(base);
}

export function limparPedido() {
  mudar(null);
}

export function contas(p: Pedido | null) {
  let q = 0;
  let total = 0;
  let semPreco = false;
  for (const it of Object.values(p?.itens ?? {})) {
    q += it.n;
    if (it.preco === null) semPreco = true;
    else total += it.n * it.preco;
  }
  return { q, total, semPreco };
}

export function reais(v: number) {
  return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Nome e endereco do cliente, lembrados para o proximo pedido (so neste celular). */
export function dadosCliente(): { nome: string; end: string } {
  try {
    return { nome: '', end: '', ...(JSON.parse(Armazenamento.ler('pedido_cliente') ?? '{}') as object) };
  } catch {
    return { nome: '', end: '' };
  }
}
export function guardarCliente(nome: string, end: string) {
  Armazenamento.gravar('pedido_cliente', JSON.stringify({ nome, end }));
}

/** Texto do pedido para o WhatsApp (o mesmo formato do site, sem emoji). */
export function mensagemPedido(p: Pedido, o: { entrega: boolean; end: string; pagamento: string; troco: string; nome: string; obs: string }) {
  const c = contas(p);
  const l = ['Olá! Quero fazer um pedido pelo *Localiza Fácil*:', ''];
  for (const it of Object.values(p.itens)) l.push(`- ${it.n}x ${it.nome}` + (it.preco === null ? ' (preço a confirmar)' : ` - ${reais(it.n * it.preco)}`));
  l.push('', `*Total: ${reais(c.total)}${c.semPreco ? ' + itens a confirmar' : ''}*` + (o.entrega ? ' (sem a taxa de entrega)' : ''));
  l.push(o.entrega ? `Entregar em: ${o.end}` : 'Vou buscar no local');
  l.push(`Pagamento: ${o.pagamento}` + (o.pagamento === 'Dinheiro' && o.troco ? ` (troco para ${o.troco})` : ''));
  if (o.nome) l.push(`Nome: ${o.nome}`);
  if (o.obs) l.push(`Obs.: ${o.obs}`);
  return l.join('\n');
}
