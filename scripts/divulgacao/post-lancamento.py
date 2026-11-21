#!/usr/bin/env python3
# Carrossel de lançamento do Localiza Fácil para o Instagram (21/11/2026): 5 imagens 1080x1350 (4:5).
#   python3 -I scripts/divulgacao/post-lancamento.py   -> servidor/publico/assets/divulgacao/lancamento-1..5.png
import os, subprocess
from PIL import Image, ImageDraw, ImageFont, ImageFilter

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
FONTE = os.path.join(RAIZ, 'servidor', 'fontes', 'Nunito.ttf')
FUNDO = os.path.join(RAIZ, 'assets', 'ilustracoes', 'cabecalho.png')
SIMBOLO_SVG = os.path.join(RAIZ, 'assets', 'localiza', 'simbolo-lupa.svg')
SAIDA = os.path.join(RAIZ, 'servidor', 'publico', 'assets', 'divulgacao')
W, H = 1080, 1350
VERDE, VERDE_ESC, AMARELO, CREME, BRANCO = (0, 92, 56), (0, 62, 38), (247, 198, 0), (255, 248, 231), (255, 255, 255)
TEXTO = (16, 38, 27)

def fonte(tam, peso=900):
    f = ImageFont.truetype(FONTE, tam)
    try: f.set_variation_by_axes([peso])
    except Exception: pass
    return f

def simbolo(lado):
    arq = f'/tmp/claude-0/simbolo-{lado}.png'
    if not os.path.exists(arq):
        subprocess.run(['convert', '-background', 'none', '-density', '192', SIMBOLO_SVG, '-resize', f'{lado}x{lado}', arq], check=True)
    return Image.open(arq).convert('RGBA')

def quebrar(d, texto, f, largura):
    linhas, atual = [], ''
    for p in texto.split():
        t = (atual + ' ' + p).strip()
        if d.textlength(t, font=f) <= largura: atual = t
        else: linhas.append(atual); atual = p
    if atual: linhas.append(atual)
    return linhas

def texto_bloco(d, xy, texto, f, cor, largura, entre=1.18, centro=False):
    x, y = xy
    for l in quebrar(d, texto, f, largura):
        lx = x + (largura - d.textlength(l, font=f)) / 2 if centro else x
        d.text((lx, y), l, font=f, fill=cor)
        y += int(f.size * entre)
    return y

def marca(img, x, y, lado=96, cor=BRANCO, cor2=AMARELO):
    img.alpha_composite(simbolo(lado), (x, y))
    d = ImageDraw.Draw(img); f = fonte(int(lado * 0.52))
    ty = y + (lado - f.size) // 2 - 4
    d.text((x + lado + 18, ty), 'Localiza ', font=f, fill=cor)
    d.text((x + lado + 18 + d.textlength('Localiza ', font=f), ty), 'Fácil', font=f, fill=cor2)

def fundo_verde():
    img = Image.new('RGBA', (W, H), VERDE)
    pais = Image.open(FUNDO).convert('RGBA').resize((W, int(W * 640 / 1200)))
    img.alpha_composite(pais, (0, H - pais.height))
    escurece = Image.new('RGBA', (W, H), (0, 40, 24, 120)); img.alpha_composite(escurece)
    return img

def rodape(img, texto='localizafacilapp.com.br'):
    d = ImageDraw.Draw(img)
    d.rectangle((0, H - 110, W, H), fill=VERDE_ESC); d.rectangle((0, H - 114, W, H - 110), fill=AMARELO)
    f = fonte(40, 800); d.text(((W - d.textlength(texto, font=f)) / 2, H - 82), texto, font=f, fill=AMARELO)

def seta(img, texto='Arraste para o lado'):
    d = ImageDraw.Draw(img); f = fonte(34, 800)
    lt = d.textlength(texto, font=f); x1 = W - 70 - lt - 70; y = H - 200
    d.rounded_rectangle((x1 - 28, y - 14, W - 50, y + 56), 35, fill=(0, 40, 24, 230))
    d.text((x1, y), texto, font=f, fill=BRANCO)
    ax = W - 70 - 44; ay = y + 22   # seta desenhada (a fonte não tem o caractere da seta)
    d.line((ax, ay, ax + 40, ay), fill=AMARELO, width=7)
    d.polygon([(ax + 46, ay), (ax + 28, ay - 14), (ax + 28, ay + 14)], fill=AMARELO)

def salvar(img, n):
    img.convert('RGB').save(os.path.join(SAIDA, f'lancamento-{n}.png'), optimize=True)

M = 80  # margem

# 1. capa
img = fundo_verde(); d = ImageDraw.Draw(img)
marca(img, M, 90)
d.text((M, 300), 'CHEGOU NA SUA CIDADE', font=fonte(46, 900), fill=AMARELO)
y = texto_bloco(d, (M, 370), 'O guia do comércio da sua cidade no seu celular', fonte(104), BRANCO, W - 2 * M, 1.08)
d.rounded_rectangle((M, y + 40, M + 620, y + 160), 60, fill=AMARELO)
f = fonte(58); d.text((M + 40, y + 66), '+18 mil comércios', font=f, fill=VERDE_ESC)
seta(img); rodape(img); salvar(img, 1)

# 2. como funciona
img = Image.new('RGBA', (W, H), CREME); d = ImageDraw.Draw(img)
y = texto_bloco(d, (M, 120), 'Precisa de um eletricista? Uma pizzaria aberta agora? Um dentista perto de casa?', fonte(66), VERDE, W - 2 * M, 1.15)
d.text((M, y + 40), 'Ache em segundos:', font=fonte(54), fill=TEXTO)
itens = [('Endereço', 'e como chegar pelo mapa'), ('Horário', 'e quem está aberto agora'), ('WhatsApp', 'para chamar direto, sem intermediário'), ('Grátis', 'sem cadastro para procurar')]
y += 150
for titulo, sub in itens:
    d.ellipse((M, y, M + 76, y + 76), fill=VERDE)
    d.line((M + 20, y + 40, M + 34, y + 54, M + 58, y + 24), fill=AMARELO, width=10, joint='curve')
    d.text((M + 104, y - 4), titulo, font=fonte(52), fill=VERDE)
    texto_bloco(d, (M + 104, y + 58), sub, fonte(38, 600), TEXTO, W - 2 * M - 104)
    y += 175
rodape(img); salvar(img, 2)

# 3. cidades
cidades = [('Poços de Caldas', 'MG', '8.372'), ('São João da Boa Vista', 'SP', '4.468'), ('Vargem Grande do Sul', 'SP', '2.118'),
           ('Santa Rita do Sapucaí', 'MG', '1.915'), ('Aguaí', 'SP', '1.442')]
img = fundo_verde(); d = ImageDraw.Draw(img)
d.text((M, 110), 'JÁ ESTAMOS EM', font=fonte(46), fill=AMARELO)
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
y = texto_bloco(d, (M, 200), 'Seu comércio pode já estar no Localiza Fácil', fonte(92), VERDE_ESC, W - 2 * M, 1.08)
y = texto_bloco(d, (M, y + 40), 'Procure o seu negócio no site, confira se os dados estão certos e informe o seu horário. É grátis.', fonte(46, 700), TEXTO, W - 2 * M, 1.3)
fq = fonte(50); lq = quebrar(d, 'Quer fotos, descrição e aparecer em Destaque? Conheça os planos.', fq, W - 2 * M - 100)
alt = 70 + len(lq) * int(fq.size * 1.2)
d.rounded_rectangle((M, y + 50, W - M, y + 50 + alt), 36, fill=VERDE)
texto_bloco(d, (M + 50, y + 85), ' '.join(lq), fq, BRANCO, W - 2 * M - 100, 1.2)
marca(img, M, H - 270, 110, VERDE_ESC, BRANCO)
rodape(img); salvar(img, 4)

# 5. chamada final
img = fundo_verde(); d = ImageDraw.Draw(img)
s = simbolo(360); img.alpha_composite(s, ((W - 360) // 2, 70))
f = fonte(96); t1, t2 = 'Localiza ', 'Fácil'; lt = d.textlength(t1 + t2, font=f); x = (W - lt) / 2
d.text((x, 440), t1, font=f, fill=BRANCO); d.text((x + d.textlength(t1, font=f), 440), t2, font=f, fill=AMARELO)
texto_bloco(d, (M, 570), 'O comércio da sua cidade, pertinho de você.', fonte(50, 700), BRANCO, W - 2 * M, 1.2, centro=True)
d.rounded_rectangle((M + 40, 720, W - M - 40, 850), 65, fill=AMARELO)
f = fonte(52); t = 'localizafacilapp.com.br'; d.text(((W - d.textlength(t, font=f)) / 2, 752), t, font=f, fill=VERDE_ESC)
texto_bloco(d, (M, 880), 'Link na bio  •  Siga @localizafacilapp e compartilhe com quem precisa!', fonte(40, 800), BRANCO, W - 2 * M, 1.3, centro=True)
salvar(img, 5)
print('ok:', SAIDA)
