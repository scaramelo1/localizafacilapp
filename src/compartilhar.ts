// 21/11 (passo 36): compartilhar a ficha com imagem (Stories do Instagram, Status do WhatsApp) ou so com o link.
// A imagem vem do site (story.php, 1080x1920, com foto, nome, estrelas e o site). Com imagem, Instagram e WhatsApp
// nao levam o link junto: o link vai para a area de transferencia e o aviso explica como colar no adesivo "Link".
// Precisa de: npx expo install expo-sharing expo-file-system expo-clipboard
import * as Clipboard from 'expo-clipboard';
import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { Alert, Platform, Share, ToastAndroid } from 'react-native';

import { registrarContato, type Profissional } from './api/cliente';

const URL_SITE = 'https://localizafacilapp.com.br';

function aviso(texto: string) {
  if (Platform.OS === 'android') ToastAndroid.show(texto, ToastAndroid.LONG);
  else Alert.alert('Link copiado', texto);
}

async function soLink(p: Profissional, link: string) {
  try {
    const r = await Share.share({ message: `Olha esse lugar: ${p.nome}${p.tipo ? ` (${p.tipo})` : ''}, em ${p.cidade}\n${link}` });
    if (r.action === Share.sharedAction) registrarContato(p.id, 'compartilhar');
  } catch {
    // cancelou ou o celular nao deixou: nada a fazer
  }
}

async function comImagem(p: Profissional, link: string) {
  try {
    if (!(await Sharing.isAvailableAsync())) {
      await soLink(p, link);
      return;
    }
    const destino = new File(Paths.cache, `localiza-facil-${p.id}.jpg`);
    if (destino.exists) destino.delete();   // a ficha pode ter mudado (nota, foto): baixa de novo
    const arquivo = await File.downloadFileAsync(`${URL_SITE}/story.php?id=${p.id}`, destino);
    await Clipboard.setStringAsync(link);
    aviso('Link copiado! No Stories, toque no adesivo Link e cole. No WhatsApp, cole na legenda.');
    await Sharing.shareAsync(arquivo.uri, { mimeType: 'image/jpeg', UTI: 'public.jpeg', dialogTitle: `Compartilhar ${p.nome}` });
    registrarContato(p.id, 'compartilhar');
  } catch {
    // sem internet ou a imagem nao veio: manda so o link
    await soLink(p, link);
  }
}

/** Pergunta como compartilhar: com imagem (Stories e Status) ou so o link (conversas). */
export function compartilharFicha(p: Profissional) {
  const link = p.linkSite ?? `${URL_SITE}/`;
  Alert.alert('Compartilhar', 'Como você quer compartilhar este lugar?', [
    { text: 'Com imagem (Stories e Status)', onPress: () => { comImagem(p, link); } },
    { text: 'Só o link (conversa)', onPress: () => { soLink(p, link); } },
    { text: 'Cancelar', style: 'cancel' },
  ]);
}
