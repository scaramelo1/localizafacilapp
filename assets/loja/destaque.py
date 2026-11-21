# Recurso gráfico (destaque) 1024x500 da Play Store do Localiza Fácil (21/11; o antigo era do Acha Fácil).
# Fundo = paisagem do cabeçalho do app; símbolo com lupa (assets/localiza/simbolo-lupa.svg, convertido em PNG antes).
# Uso: python3 assets/loja/destaque.py <pasta com fontes (roboto-*) e simbolo.png> [saída]
#   convert -background none -density 200 assets/localiza/simbolo-lupa.svg -resize 600x600 <pasta>/simbolo.png
import os
import sys

from PIL import Image, ImageDraw, ImageFont

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ENT = sys.argv[1]
SAI = sys.argv[2] if len(sys.argv) > 2 else os.path.join(RAIZ, 'assets/loja/destaque-1024x500.png')
W, H = 1024, 500

fundo = Image.open(os.path.join(RAIZ, 'assets/ilustracoes/cabecalho.png')).convert('RGBA')
esc = max(W / fundo.width, H / fundo.height)
fundo = fundo.resize((round(fundo.width * esc), round(fundo.height * esc)), Image.LANCZOS)
fundo = fundo.crop((fundo.width - W, (fundo.height - H) // 2, fundo.width, (fundo.height - H) // 2 + H))

simb = Image.open(os.path.join(ENT, 'simbolo.png')).convert('RGBA')
simb = simb.crop(simb.getbbox())
alt = 230
simb = simb.resize((round(simb.width * alt / simb.height), alt), Image.LANCZOS)
fundo.alpha_composite(simb, (48, (H - alt) // 2 - 10))

d = ImageDraw.Draw(fundo)
x = 48 + simb.width + 26
f_nome = ImageFont.truetype(os.path.join(ENT, 'roboto-900i.woff'), 68)
f_sub = ImageFont.truetype(os.path.join(ENT, 'roboto-500.ttf'), 27)
y = 168
d.text((x + 2, y + 3), 'Localiza Fácil', font=f_nome, fill=(0, 0, 0, 90))
d.text((x, y), 'Localiza Fácil', font=f_nome, fill='#F7C600')
traco = Image.open(os.path.join(RAIZ, 'assets/ilustracoes/traco.png')).convert('RGBA')
tw = 200
fundo.alpha_composite(traco.resize((tw, tw // 10), Image.LANCZOS), (x + 4, y + 84))
for i, l in enumerate(('O comércio da sua cidade,', 'pertinho de você.')):
    d.text((x + 2, y + 116 + i * 36), l, font=f_sub, fill='#FFFFFF')
fundo.convert('RGB').save(SAI, optimize=True)
print(SAI)
