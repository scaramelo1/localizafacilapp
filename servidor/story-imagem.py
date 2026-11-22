# Imagem da ficha no formato Stories/Status (1080x1920), para o botão "Compartilhar" do site (21/11, passo 35).
# Com imagem, o menu de compartilhar do celular mostra também Status do WhatsApp e Stories do Instagram (só link não dá).
# Chamado por publico/story.php; à mão:
#   python3 servidor/story-imagem.py saida.jpg "Nome" "Tipo" "Cidade - UF" "4,8" "12" [foto.jpg]
# Precisa do Pillow. Fonte Nunito (OFL) em servidor/fontes/.
import os, sys
from PIL import Image, ImageDraw, ImageFont, ImageOps

AQUI = os.path.dirname(os.path.abspath(__file__))
FUNDO = os.path.join(AQUI, '..', 'assets', 'ilustracoes', 'cabecalho.png')
SIMBOLO = os.path.join(AQUI, 'publico', 'assets', 'logo.png')
FONTE = os.path.join(AQUI, 'fontes', 'Nunito.ttf')
W, H = 1080, 1920
BRANCO, AMARELO, VERDE, VERDE_ESC, CREME, TEXTO = (255, 255, 255), (247, 198, 0), (0, 92, 56), (0, 60, 37), (255, 251, 234), (16, 38, 27)


def fonte(tam, peso=900):
    f = ImageFont.truetype(FONTE, tam)
    try: f.set_variation_by_axes([peso])
    except Exception: pass
    return f


def quebrar(d, texto, f, largura, max_linhas):
    """Quebra em linhas que cabem na largura; corta com reticências se passar de max_linhas."""
    palavras, linhas, atual = texto.split(), [], ''
    for p in palavras:
        t = (atual + ' ' + p).strip()
        if d.textlength(t, font=f) <= largura: atual = t
        else:
            if atual: linhas.append(atual)
            atual = p
    if atual: linhas.append(atual)
    if len(linhas) > max_linhas:
        linhas = linhas[:max_linhas]
        while linhas[-1] and d.textlength(linhas[-1] + '…', font=f) > largura: linhas[-1] = linhas[-1][:-1]
        linhas[-1] = linhas[-1].rstrip() + '…'
    return linhas


def caber(d, texto, tam, minimo, peso, largura, max_linhas):
    while tam > minimo:
        f = fonte(tam, peso)
        l = quebrar(d, texto, f, largura, 99)
        if len(l) <= max_linhas: return f, l
        tam -= 4
    f = fonte(minimo, peso)
    return f, quebrar(d, texto, f, largura, max_linhas)


def estrela(d, cx, cy, r, cor):
    import math
    pts = []
    for i in range(10):
        ang = -math.pi / 2 + i * math.pi / 5
        rr = r if i % 2 == 0 else r * 0.45
        pts.append((cx + rr * math.cos(ang), cy + rr * math.sin(ang)))
    d.polygon(pts, fill=cor)


def gerar(saida, nome, tipo, cidade, nota, total, foto=''):
    img = Image.new('RGB', (W, H), VERDE)
    # paisagem no topo, escurecida embaixo para emendar no verde
    fundo = Image.open(FUNDO).convert('RGB')
    fundo = ImageOps.fit(fundo, (W, 720), Image.LANCZOS, centering=(0.5, 0.4))
    img.paste(fundo, (0, 0))
    grad = Image.new('L', (W, 720), 0)
    dg = ImageDraw.Draw(grad)
    for y in range(720):
        dg.line([(0, y), (W, y)], fill=int(255 * max(0, (y - 300) / 420) ** 1.3))
    img.paste(Image.new('RGB', (W, 720), VERDE), (0, 0), grad)
    d = ImageDraw.Draw(img)
    m = 80
    # marca no topo
    s = Image.open(SIMBOLO).convert('RGBA').resize((120, 120), Image.LANCZOS)
    faixa = Image.new('RGBA', (W, 200), (0, 40, 24, 150))
    img.paste(faixa, (0, 0), faixa)
    img.paste(s, (m - 14, 40), s)
    f = fonte(68, 900)
    d.text((m + 120, 64), 'Localiza ', font=f, fill=BRANCO)
    d.text((m + 120 + d.textlength('Localiza ', font=f), 64), 'Fácil', font=f, fill=AMARELO)

    # cartão branco com foto, nome, tipo, cidade e nota
    cx0, cx1 = m - 20, W - m + 20
    foto_img = None
    if foto and os.path.isfile(foto):
        try: foto_img = ImageOps.fit(Image.open(foto).convert('RGB'), (cx1 - cx0 - 80, 620), Image.LANCZOS)
        except Exception: foto_img = None
    fn, linhas_nome = caber(d, nome, 96, 56, 900, cx1 - cx0 - 80, 3)
    alt_nome = sum(fn.getbbox(l)[3] + 10 for l in linhas_nome)
    ft = fonte(46, 800)
    linhas_tipo = quebrar(d, tipo, ft, cx1 - cx0 - 80, 2) if tipo else []
    alt = 40 + (foto_img.height + 40 if foto_img else 0) + alt_nome + 20 + len(linhas_tipo) * 60 + 70 + (90 if int(total or 0) > 0 else 0) + 40
    cy0 = 300 if foto_img else max(320, int((H - 420 - alt) / 2) + 60)   # sem foto: cartão no meio
    cy1 = cy0 + alt
    y = cy0 + 40
    d.rounded_rectangle([cx0, cy0, cx1, cy1], radius=48, fill=CREME)
    if foto_img:
        masc = Image.new('L', foto_img.size, 0)
        ImageDraw.Draw(masc).rounded_rectangle([0, 0, foto_img.width, foto_img.height], radius=32, fill=255)
        img.paste(foto_img, (cx0 + 40, y), masc)
        y += foto_img.height + 40
    for l in linhas_nome:
        d.text((cx0 + 40, y), l, font=fn, fill=VERDE)
        y += fn.getbbox(l)[3] + 10
    y += 20
    for l in linhas_tipo:
        d.text((cx0 + 40, y), l, font=ft, fill=TEXTO)
        y += 60
    fc = fonte(44, 700)
    d.text((cx0 + 40, y), cidade, font=fc, fill=(75, 90, 82))
    y += 70
    if int(total or 0) > 0:
        n = float(str(nota).replace(',', '.'))
        for i in range(5):
            cor = AMARELO if i < round(n) else (220, 214, 190)
            estrela(d, cx0 + 72 + i * 70, y + 34, 32, cor)
        fe = fonte(50, 900)
        txt = f'{nota}  ({total} {"avaliação" if int(total) == 1 else "avaliações"})'
        d.text((cx0 + 72 + 5 * 70 - 10, y + 6), txt, font=fe, fill=TEXTO)
        y += 90

    # chamada e endereço do site
    yb = max(cy1 + 80, H - 420)
    fch = fonte(64, 900)
    for l in quebrar(d, 'Endereço, horário e WhatsApp', fch, W - 2 * m, 2):
        d.text(((W - d.textlength(l, font=fch)) / 2, yb), l, font=fch, fill=BRANCO)
        yb += 80
    fs = fonte(54, 900)
    site = 'localizafacilapp.com.br'
    lw = d.textlength(site, font=fs)
    d.rounded_rectangle([(W - lw) / 2 - 40, yb + 30, (W + lw) / 2 + 40, yb + 140], radius=55, fill=AMARELO)
    d.text(((W - lw) / 2, yb + 50), site, font=fs, fill=VERDE_ESC)
    tmp = saida + '.tmp.jpg'
    img.save(tmp, 'JPEG', quality=86, optimize=True, progressive=True)
    os.replace(tmp, saida)


if __name__ == '__main__':
    if len(sys.argv) not in (7, 8):
        sys.exit('uso: story-imagem.py saida.jpg NOME TIPO CIDADE NOTA TOTAL [FOTO]')
    gerar(*sys.argv[1:])
