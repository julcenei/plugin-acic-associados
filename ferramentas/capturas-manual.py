"""Gera as capturas anotadas e as animações do manual do ACIC Associados.

Precisa de um WordPress de TESTES com o plugin ativo, em português, e com os dados de
ferramentas/dados-demo.php. Uso:

    ACICA_BASE=http://localhost:8099 ACICA_SENHA=... ACICA_PLANILHA=/caminho/planilha.xls \
        python3 ferramentas/capturas-manual.py

Requisitos: pip install playwright pillow && playwright install chromium
"""
import asyncio
import os
import tempfile

from PIL import Image, ImageDraw, ImageFont
from playwright.async_api import async_playwright

BASE = os.environ.get('ACICA_BASE', 'http://localhost:8099')
USUARIO = os.environ.get('ACICA_USUARIO', 'admin')
SENHA = os.environ.get('ACICA_SENHA', 'admin123')
PLANILHA = os.environ.get('ACICA_PLANILHA', '')
CHROME = os.environ.get('ACICA_CHROME')  # opcional: caminho de um Chrome já instalado
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'manual', 'img') + '/'
TMP = tempfile.mkdtemp()
COR = (194, 65, 12)           # laranja das marcações (contrasta com o azul do WP e o verde da ACIC)
FONTE = '/usr/share/fonts/opentype/inter/Inter-Bold.otf'
ESCALA = 2                    # deviceScaleFactor: imagens nítidas
LARG_MAX = 1600               # largura máxima salva

LIMPA_JS = """
(() => {
  const s = document.createElement('style');
  s.textContent = '.update-nag,#wp-admin-bar-updates,#wp-admin-bar-comments,#wp-admin-bar-new-content,.notice-warning.is-dismissible{display:none!important}';
  document.head.appendChild(s);
  document.querySelectorAll('#wp-admin-bar-top-secondary > li, #wpadminbar li').forEach(li => { if (/SQLite/i.test(li.textContent)) li.style.display = 'none'; });
  document.querySelectorAll('.awaiting-mod, .update-plugins').forEach(e => e.style.display = 'none');
})()
"""

CURSOR_JS = """
(() => { if (document.getElementById('acica-cursor')) return;
 const c=document.createElement('div'); c.id='acica-cursor';
 c.style.cssText='position:fixed;z-index:999999;width:22px;height:22px;border-radius:50%;background:rgba(194,65,12,.35);border:2px solid #C2410C;pointer-events:none;transform:translate(-50%,-50%);transition:left .45s ease,top .45s ease;left:-50px;top:-50px';
 document.body.appendChild(c); })()
"""


async def caixa(pg, sel):
    loc = pg.locator(sel).first
    if await loc.count() == 0:
        return None
    bb = await loc.bounding_box()
    if not bb:
        return None
    sx, sy = await pg.evaluate('[scrollX, scrollY]')
    return {'x': bb['x'] + sx, 'y': bb['y'] + sy, 'w': bb['width'], 'h': bb['height']}


def anotar(caminho_png, recorte, marcas, destino, larg=None):
    img = Image.open(caminho_png).convert('RGB')
    x0, y0, x1, y1 = [int(v * ESCALA) for v in recorte]
    d = ImageDraw.Draw(img)
    f = ImageFont.truetype(FONTE, 17 * ESCALA)
    for n, b in marcas:
        if not b:
            print('  marca ausente', destino, n)
            continue
        bx, by, bw, bh = [v * ESCALA for v in (b['x'], b['y'], b['w'], b['h'])]
        pad = 4 * ESCALA
        d.rounded_rectangle([bx - pad, by - pad, bx + bw + pad, by + bh + pad], radius=8 * ESCALA, outline=COR, width=3 * ESCALA)
        r = 15 * ESCALA
        cx, cy = max(bx - pad, x0 + r + 2), max(by - pad, y0 + r + 2)
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=COR, outline=(255, 255, 255), width=2 * ESCALA)
        d.text((cx, cy), n, fill=(255, 255, 255), font=f, anchor='mm')
    img = img.crop((max(0, x0), max(0, y0), min(img.width, x1), min(img.height, y1)))
    alvo = larg or LARG_MAX
    if img.width > alvo:
        img = img.resize((alvo, int(img.height * alvo / img.width)), Image.LANCZOS)
    img.save(OUT + destino + '.webp', 'WEBP', quality=80, method=6)
    print('ok', destino, img.size)


async def foto(pg, destino, recorte, marcas, larg=None, pagina_inteira=True):
    png = os.path.join(TMP, destino + '.png')
    if pagina_inteira:
        # Só até o fim do recorte: páginas longas (675 associados) passam do limite de pixels.
        vw = (pg.viewport_size or {'width': 1280})['width']
        await pg.screenshot(path=png, full_page=True, clip={'x': 0, 'y': 0, 'width': vw, 'height': recorte[3]})
    else:
        await pg.screenshot(path=png)
    anotar(png, recorte, marcas, destino, larg)


class Animacao:
    def __init__(self, pg, recorte, nome, larg=1100):
        self.pg, self.recorte, self.nome, self.larg, self.quadros = pg, recorte, nome, larg, []

    async def quadro(self, ms=120):
        png = os.path.join(TMP, 'q.png')
        await self.pg.screenshot(path=png)
        img = Image.open(png).convert('RGB')
        x0, y0, x1, y1 = [int(v * ESCALA) for v in self.recorte]
        img = img.crop((x0, y0, x1, y1))
        img = img.resize((self.larg, int(img.height * self.larg / img.width)), Image.LANCZOS)
        self.quadros.append((img, ms))

    async def pausa(self, quadros=6, ms=160):
        for _ in range(quadros):
            await self.pg.wait_for_timeout(ms)
            await self.quadro(ms)

    async def cursor_para(self, sel, quadros=4):
        await self.pg.evaluate(CURSOR_JS)
        loc = self.pg.locator(sel).first
        await loc.scroll_into_view_if_needed()
        bb = await loc.bounding_box()
        await self.pg.evaluate(
            "([x,y])=>{const c=document.getElementById('acica-cursor'); c.style.left=x+'px'; c.style.top=y+'px';}",
            [bb['x'] + bb['width'] / 2, bb['y'] + bb['height'] / 2])
        for _ in range(quadros):
            await self.pg.wait_for_timeout(110)
            await self.quadro(110)

    def salvar(self):
        imgs = [q[0] for q in self.quadros]
        dur = [q[1] for q in self.quadros]
        dur[-1] = 2200  # Segura o último quadro antes de recomeçar.
        imgs[0].save(OUT + self.nome + '.webp', 'WEBP', save_all=True, append_images=imgs[1:], duration=dur, loop=0, quality=72, method=4)
        print('anim', self.nome, len(imgs), 'quadros')


async def abrir(pg, url):
    await pg.goto(BASE + url)
    await pg.wait_for_load_state('networkidle')
    await pg.evaluate(LIMPA_JS)


async def admin(ctx):
    pg = await ctx.new_page()
    await pg.goto(BASE + '/wp-login.php')
    await pg.fill('#user_login', USUARIO)
    await pg.fill('#user_pass', SENHA)
    await pg.click('#wp-submit')
    await pg.wait_for_load_state('networkidle')
    return pg


LISTA = '/wp-admin/edit.php?post_type=acic_associado'
IMPORTAR = LISTA + '&page=acica-importar'


async def main():
    if not PLANILHA or not os.path.exists(PLANILHA):
        raise SystemExit('Defina ACICA_PLANILHA com o caminho da planilha de testes.')
    os.makedirs(OUT, exist_ok=True)
    async with async_playwright() as p:
        nav = await p.chromium.launch(executable_path=CHROME, args=['--no-sandbox', '--lang=pt-BR']) if CHROME else await p.chromium.launch(args=['--lang=pt-BR'])
        ctx = await nav.new_context(viewport={'width': 1280, 'height': 860}, device_scale_factor=ESCALA, locale='pt-BR')
        pg = await admin(ctx)

        # 1) Onde fica tudo.
        await abrir(pg, LISTA)
        await foto(pg, 'painel-inicio', (0, 0, 1280, 640), [
            ('A', await caixa(pg, '#menu-posts-acic_associado')),
            ('B', await caixa(pg, '.acica-pp')),
            ('C', await caixa(pg, 'ul.subsubsub')),
            ('D', await caixa(pg, 'p.search-box')),
        ], pagina_inteira=False)

        # 2) Importar: envio, conferência e pronto (com a animação do caminho todo).
        await abrir(pg, IMPORTAR)
        await pg.add_style_tag(content='.acica-pp{display:none!important}')
        card = await caixa(pg, '.acica-card')
        await foto(pg, 'importar-envio', (160, card['y'] - 70, 1280, card['y'] + card['h'] + 16), [
            ('A', await caixa(pg, '#acica-planilha')),
            ('B', await caixa(pg, '.acica-modo')),
            ('C', await caixa(pg, '.acica-card button[type=submit]')),
        ])

        anim = Animacao(pg, (160, 32, 1280, 760), 'anim-importar')
        await pg.evaluate('scrollTo(0,0)')
        await anim.pausa(4)
        await anim.cursor_para('#acica-planilha')
        await pg.set_input_files('#acica-planilha', PLANILHA)
        await anim.pausa(5)
        await anim.cursor_para('.acica-opcao:first-of-type')
        await anim.pausa(2)
        await anim.cursor_para('.acica-card button[type=submit]')
        await pg.click('.acica-card button[type=submit]')
        await pg.wait_for_load_state('networkidle')
        await pg.evaluate(LIMPA_JS)
        await pg.add_style_tag(content='.acica-pp{display:none!important}')
        await anim.pausa(8)

        # Conferência (estática, com a lista de atualizados aberta).
        await pg.evaluate("document.querySelectorAll('details.acica-det')[1] && (document.querySelectorAll('details.acica-det')[1].open = true)")
        card = await caixa(pg, '.acica-card')
        await foto(pg, 'importar-previa', (160, card['y'] - 70, 1280, card['y'] + card['h'] + 16), [
            ('A', await caixa(pg, '.acica-arquivo')),
            ('B', await caixa(pg, '.acica-numeros')),
            ('C', await caixa(pg, 'details.acica-det >> nth=1')),
            ('D', await caixa(pg, '.acica-form-aplicar button')),
        ])
        await pg.evaluate("document.querySelectorAll('details.acica-det').forEach(d => d.open = false)")

        await anim.cursor_para('.acica-num--novo')
        await anim.pausa(3)
        await anim.cursor_para('details.acica-det >> nth=0')
        await pg.evaluate("document.querySelector('details.acica-det').open = true")
        await anim.pausa(8)
        await pg.evaluate("document.querySelector('details.acica-det').open = false")
        await anim.cursor_para('.acica-form-aplicar button')
        await pg.click('.acica-form-aplicar button')
        await pg.wait_for_load_state('networkidle')
        await pg.evaluate(LIMPA_JS)
        await pg.add_style_tag(content='.acica-pp{display:none!important}')
        await anim.pausa(6)
        anim.salvar()

        card = await caixa(pg, '.acica-card')
        await foto(pg, 'importar-pronto', (160, card['y'] - 70, 1280, card['y'] + card['h'] + 16), [
            ('A', await caixa(pg, '.acica-numeros')),
            ('B', await caixa(pg, '.acica-card a.button-primary')),
            ('C', await caixa(pg, '.acica-desfazer button')),
        ])

        # 3) Histórico e desfazer.
        await abrir(pg, IMPORTAR)
        await pg.add_style_tag(content='.acica-pp{display:none!important}')
        hist = await caixa(pg, '.acica-card >> nth=1')
        await foto(pg, 'historico', (160, hist['y'] - 16, 1280, hist['y'] + hist['h'] + 16), [
            ('A', await caixa(pg, '.acica-card >> nth=1 >> table')),
            ('B', await caixa(pg, '.acica-card >> nth=1 >> .acica-desfazer button')),
        ])

        # 4) Lista: inativar, reativar, ações em massa.
        await abrir(pg, LISTA)
        await pg.add_style_tag(content='.acica-pp{display:none!important} #the-list tr:nth-child(3) .row-actions{left:0!important;position:static!important}')
        linha = '#the-list tr:nth-child(3)'
        await pg.hover(linha + ' .row-title')
        await foto(pg, 'lista-acoes', (160, 60, 1280, 560), [
            ('A', await caixa(pg, linha + ' .row-actions')),
            ('B', await caixa(pg, linha + ' .column-acica_situacao .acica-tag')),
            ('C', await caixa(pg, '#bulk-action-selector-top')),
            ('D', await caixa(pg, 'ul.subsubsub')),
        ], pagina_inteira=False)

        anim = Animacao(pg, (160, 60, 1280, 560), 'anim-inativar')
        await pg.evaluate(CURSOR_JS)
        await anim.pausa(3)
        await anim.cursor_para(linha + ' .acica_situacao a')
        await pg.click(linha + ' .acica_situacao a')
        await pg.wait_for_load_state('networkidle')
        await pg.evaluate(LIMPA_JS)
        await pg.add_style_tag(content='.acica-pp{display:none!important}')
        await anim.pausa(10)
        anim.salvar()
        # Desfaz o "Inativar" da animação.
        await pg.hover(linha + ' .row-title')
        await pg.click(linha + ' .acica_situacao a')
        await pg.wait_for_load_state('networkidle')

        # 5) Cadastro/edição.
        await abrir(pg, LISTA + '&s=10+PASTEIS')
        await pg.click('#the-list a.row-title')
        await pg.wait_for_load_state('networkidle')
        await pg.evaluate(LIMPA_JS)
        await pg.click('#acica_dados .acica-dica__btn >> nth=3')
        await foto(pg, 'editar', (160, 50, 1280, 600), [
            ('A', await caixa(pg, '#titlewrap')),
            ('B', await caixa(pg, '#acica_cnpj')),
            ('C', await caixa(pg, '.acica-dica__balao:visible')),
            ('D', await caixa(pg, '#acica_situacao .inside')),
            ('E', await caixa(pg, '#publish')),
        ], pagina_inteira=False)

        # 6) O que o visitante vê.
        site = await ctx.new_page()
        await site.set_viewport_size({'width': 1280, 'height': 900})
        await site.goto(BASE + '/?pagename=associados')
        await site.wait_for_load_state('networkidle')
        topo = await caixa(site, 'h1')
        ini = topo['y'] - 30
        await foto(site, 'site-lista', (60, ini, 1220, ini + 860), [
            ('A', await caixa(site, '.acica-busca')),
            ('B', await caixa(site, '.acica-letras')),
            ('C', await caixa(site, '.acica-grupo__topo')),
            ('D', await caixa(site, '.acica-item__cnpj')),
            ('E', await caixa(site, '.acica-item__contato')),
        ])

        anim = Animacao(site, (60, 0, 1220, 760), 'anim-busca', larg=1000)
        await site.evaluate(f'scrollTo(0, {int(topo["y"] - 30)})')
        await site.evaluate(CURSOR_JS)
        await anim.pausa(3)
        await anim.cursor_para('.acica-busca__campo')
        await site.click('.acica-busca__campo')
        for letra in 'farm':
            await site.keyboard.type(letra)
            await site.wait_for_timeout(260)
            await anim.quadro(260)
        await anim.pausa(8)
        await site.fill('.acica-busca__campo', '')
        await site.dispatch_event('.acica-busca__campo', 'input')
        await anim.pausa(2)
        await anim.cursor_para('button.acica-letra[data-letra="P"]')
        await site.click('button.acica-letra[data-letra="P"]')
        await anim.pausa(8)
        anim.salvar()

        cel = await ctx.new_page()
        await cel.set_viewport_size({'width': 390, 'height': 844})
        await cel.goto(BASE + '/?pagename=associados')
        await cel.wait_for_load_state('networkidle')
        topo = await caixa(cel, 'h1')
        await foto(cel, 'site-celular', (0, topo['y'] - 20, 390, topo['y'] + 780), [
            ('A', await caixa(cel, '.acica-letras')),
            ('B', await caixa(cel, '.acica-btn--wa')),
        ], larg=700)

        await nav.close()


asyncio.run(main())
