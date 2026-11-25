#!/usr/bin/env python3
# 1ª postagem do Instagram com o app na Google Play (25/11/2026): carrossel de 5 imagens 1080x1350 (4:5).
# Mesmo visual do post-lancamento.py (cores, fonte Nunito, paisagem) + telas reais do app (assets/loja/capturas).
#   python3 -I scripts/divulgacao/post-app.py   -> servidor/publico/assets/divulgacao/app-1..5.png
import os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from PIL import Image, ImageDraw
import importlib.util
spec = importlib.util.spec_from_file_location('base', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'post-lancamento.py'))
# reaproveita as funções do carrossel de lançamento sem gerar as imagens dele de novo
codigo = open(spec.origin, encoding='utf-8').read().split('M = 80  # margem')[0]
base = {'__file__': spec.origin}; exec(compile(codigo, spec.origin, 'exec'), base)
fonte, simbolo, quebrar, texto_bloco, marca, fundo_verde, rodape, seta = (base[k] for k in
    ('fonte', 'simbolo', 'quebrar', 'texto_bloco', 'marca', 'fundo_verde', 'rodape', 'seta'))
W, H, VERDE, VERDE_ESC, AMARELO, CREME, BRANCO, TEXTO = (base[k] for k in ('W', 'H', 'VERDE', 'VERDE_ESC', 'AMARELO', 'CREME', 'BRANCO', 'TEXTO'))
RAIZ, SAIDA = base['RAIZ'], base['SAIDA']
CAPTURAS = os.path.join(RAIZ, 'assets', 'loja', 'capturas')
M = 80

def salvar(img, n):
    img.convert('RGB').save(os.path.join(SAIDA, f'app-{n}.png'), optimize=True)

def celular(nome, altura):
    """Só o aparelho da captura da Play (sem o título de cima), com cantos arredondados e sombra."""
    im = Image.open(os.path.join(CAPTURAS, f'celular-{nome}.png')).convert('RGBA').crop((160, 280, 922, 1842))
    esc = altura / im.height
    im = im.resize((round(im.width * esc), altura), Image.LANCZOS)
    masc = Image.new('L', im.size, 0); ImageDraw.Draw(masc).rounded_rectangle((0, 0, im.width - 1, im.height - 1), round(56 * esc), fill=255)
    im.putalpha(masc)
    sombra = Image.new('RGBA', (im.width + 60, im.height + 60), (0, 0, 0, 0))
    ImageDraw.Draw(sombra).rounded_rectangle((30, 40, im.width + 30, im.height + 40), round(56 * esc), fill=(0, 0, 0, 110))
    from PIL import ImageFilter
    sombra = sombra.filter(ImageFilter.GaussianBlur(18))
    sombra.alpha_composite(im, (30, 30))
    return sombra

def selo_play(img, x, y, escala=1.0, centro=False):
    """Botão preto "Baixe grátis no Google Play" (o mesmo do site)."""
    d = ImageDraw.Draw(img)
    f1, f2 = fonte(int(26 * escala), 800), fonte(int(58 * escala), 900)
    t1, t2 = 'BAIXE GRÁTIS NO', 'Google Play'
    ic = int(74 * escala); pad = int(34 * escala)
    larg = pad + ic + int(22 * escala) + max(d.textlength(t1, font=f1), d.textlength(t2, font=f2)) + pad
    alt = int(130 * escala)
    if centro: x = int((W - larg) / 2)
    d.rounded_rectangle((x, y, x + larg, y + alt), int(28 * escala), fill=(17, 17, 17), outline=(120, 120, 120), width=2)
    ix, iy = x + pad, y + (alt - ic) // 2
    p = lambda a, b: (ix + a * ic / 24, iy + b * ic / 24)
    d.polygon([p(3.6, 2.2), p(13.5, 12), p(3.6, 21.8), p(3, 20.7), p(3, 3.3)], fill=(0, 215, 254))
    d.polygon([p(16.8, 8.7), p(20.1, 10.6), p(20.1, 13.4), p(16.8, 15.3), p(13.5, 12)], fill=(255, 206, 0))
    d.polygon([p(13.5, 12), p(3.6, 21.8), p(5, 21.7), p(16.8, 15.3)], fill=(255, 58, 68))
    d.polygon([p(3.6, 2.2), p(5, 2.3), p(16.8, 8.7), p(13.5, 12)], fill=(0, 240, 118))
    tx = ix + ic + int(22 * escala)
    d.text((tx, y + int(16 * escala)), t1, font=f1, fill=(230, 230, 230))
    d.text((tx, y + int(42 * escala)), t2, font=f2, fill=BRANCO)
    return larg

# 1. capa: chegou o app
img = fundo_verde(); d = ImageDraw.Draw(img)
marca(img, M, 80)
fp = fonte(38); tp = 'NOVO NO GOOGLE PLAY'
d.rounded_rectangle((M, 245, M + d.textlength(tp, font=fp) + 68, 315), 35, fill=AMARELO)
d.text((M + 34, 254), tp, font=fp, fill=VERDE_ESC)
y = texto_bloco(d, (M, 350), 'O comércio da sua cidade no seu celular', fonte(78), BRANCO, 520, 1.08)
y = texto_bloco(d, (M, y + 24), 'Ache, veja o horário e chame no WhatsApp. Grátis.', fonte(40, 700), (225, 240, 230), 500, 1.25)
selo_play(img, M, y + 40, 0.95)
cel = celular('1-inicio', 900); img.alpha_composite(cel, (W - cel.width + 40, 300))
seta(img); rodape(img); salvar(img, 1)

# 2. o que dá para fazer no app
img = Image.new('RGBA', (W, H), CREME); d = ImageDraw.Draw(img)
d.text((M, 100), 'NO APP VOCÊ', font=fonte(44), fill=(190, 140, 0))
y = texto_bloco(d, (M, 160), 'Acha tudo em segundos', fonte(80), VERDE, W - 2 * M, 1.1)
cel = celular('3-perto', 800); img.alpha_composite(cel, (W - cel.width + 30, 400))
itens = [('Procura', 'por nome ou serviço'), ('Vê quem está aberto', 'agora, com o horário'), ('Chama no WhatsApp', 'liga ou vê o caminho no mapa'),
         ('Perto de você', 'o que tem no seu bairro'), ('Favoritos', 'para achar de novo rapidinho')]
y = 400
for titulo, sub in itens:
    d.ellipse((M, y, M + 64, y + 64), fill=VERDE)
    d.line((M + 17, y + 34, M + 29, y + 46, M + 48, y + 21), fill=AMARELO, width=9, joint='curve')
    yy = texto_bloco(d, (M + 86, y - 6), titulo, fonte(42), VERDE, 440, 1.05)
    texto_bloco(d, (M + 86, yy), sub, fonte(31, 600), TEXTO, 440, 1.15)
    y += 165
rodape(img); salvar(img, 2)

# 3. cidades (números de 25/11)
cidades = [('Poços de Caldas', 'MG', '8.213'), ('São João da Boa Vista', 'SP', '4.419'), ('Vargem Grande do Sul', 'SP', '2.116'),
           ('Santa Rita do Sapucaí', 'MG', '1.911'), ('Aguaí', 'SP', '1.441')]
img = fundo_verde(); d = ImageDraw.Draw(img)
d.text((M, 110), '+18 MIL COMÉRCIOS EM', font=fonte(46), fill=AMARELO)
d.text((M, 170), '5 cidades', font=fonte(110), fill=BRANCO)
y = 340
for nome, uf, n in cidades:
    d.rounded_rectangle((M, y, W - M, y + 130), 30, fill=(255, 255, 255, 235))
    d.text((M + 36, y + 18), nome, font=fonte(48), fill=VERDE)
    d.text((M + 36, y + 76), uf, font=fonte(30, 700), fill=(90, 110, 100))
    fn = fonte(44); t = n + ' lugares'
    d.text((W - M - 36 - d.textlength(t, font=fn), y + 40), t, font=fn, fill=VERDE_ESC)
    y += 150
d.text((M, y + 10), 'E vem mais cidade por aí!', font=fonte(44, 800), fill=AMARELO)
rodape(img); salvar(img, 3)

# 4. para o comerciante
img = Image.new('RGBA', (W, H), AMARELO); d = ImageDraw.Draw(img)
d.text((M, 120), 'TEM UM NEGÓCIO?', font=fonte(50), fill=VERDE)
y = texto_bloco(d, (M, 200), 'Seu comércio pode já estar no app', fonte(92), VERDE_ESC, W - 2 * M, 1.08)
y = texto_bloco(d, (M, y + 40), 'Procure o seu negócio no site, confira se os dados estão certos e informe o seu horário. É grátis.', fonte(46, 700), TEXTO, W - 2 * M, 1.3)
fq = fonte(50); lq = quebrar(d, 'Quer fotos, descrição e aparecer em Destaque? Conheça os planos.', fq, W - 2 * M - 100)
alt = 70 + len(lq) * int(fq.size * 1.2)
d.rounded_rectangle((M, y + 50, W - M, y + 50 + alt), 36, fill=VERDE)
texto_bloco(d, (M + 50, y + 85), ' '.join(lq), fq, BRANCO, W - 2 * M - 100, 1.2)
marca(img, M, H - 270, 110, VERDE_ESC, BRANCO)
rodape(img); salvar(img, 4)

# 5. chamada final: baixe
img = fundo_verde(); d = ImageDraw.Draw(img)
s = simbolo(300); img.alpha_composite(s, ((W - 300) // 2, 70))
f = fonte(96); t1, t2 = 'Localiza ', 'Fácil'; lt = d.textlength(t1 + t2, font=f); x = (W - lt) / 2
d.text((x, 390), t1, font=f, fill=BRANCO); d.text((x + d.textlength(t1, font=f), 390), t2, font=f, fill=AMARELO)
texto_bloco(d, (M, 515), 'O comércio da sua cidade, pertinho de você.', fonte(42, 700), BRANCO, W - 2 * M, 1.2, centro=True)
selo_play(img, 0, 640, 1.25, centro=True)
texto_bloco(d, (M, 835), 'Ou acesse pelo site:', fonte(40, 700), BRANCO, W - 2 * M, 1.3, centro=True)
d.rounded_rectangle((M + 80, 905, W - M - 80, 1005), 50, fill=AMARELO)
f = fonte(48); t = 'localizafacilapp.com.br'; d.text(((W - d.textlength(t, font=f)) / 2, 927), t, font=f, fill=VERDE_ESC)
d.rounded_rectangle((M + 60, 1050, W - M - 60, 1130), 40, fill=(0, 40, 24, 220))
texto_bloco(d, (M, 1068), 'Link na bio  •  Compartilhe com quem precisa!', fonte(36, 800), BRANCO, W - 2 * M, 1.3, centro=True)
salvar(img, 5)
print('ok:', SAIDA)
