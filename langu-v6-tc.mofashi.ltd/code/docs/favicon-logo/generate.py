# -*- coding: utf-8 -*-
"""用图床 logo (public/static/images/logo-icon.png) 生成 favicon 全套。

处理要点:
  - 裁掉 logo 四周透明边距,避免图标在标签栏里显得很小
  - 缩放到目标尺寸前,对 alpha 做 gamma 0.5 提实(线稿缩小会变淡)
  - 同时产出「透明底」和「白底圆」两套:后者在深色标签栏下也能看清
"""
from PIL import Image, ImageDraw, ImageFont
import os

ROOT = r"C:\Users\123\Desktop\github\魔法师图床2.0"
SRC = os.path.join(ROOT, r"public\static\images\logo-icon.png")
OUT = os.path.join(ROOT, r"docs\favicon-logo")

LINE = (17, 17, 20)
GAMMA = 0.5
SIZES = (16, 32, 48, 64, 128, 180, 256, 512)

os.makedirs(OUT, exist_ok=True)
for f in os.listdir(OUT):
    if f.endswith((".png", ".ico")) and not f.startswith("_preview"):
        os.remove(os.path.join(OUT, f))

src = Image.open(SRC).convert("RGBA")
trimmed = src.crop(src.getchannel("A").getbbox())
side = int(max(trimmed.size) * 1.05)
master = Image.new("RGBA", (side, side), (0, 0, 0, 0))
master.paste(trimmed, ((side - trimmed.width) // 2, (side - trimmed.height) // 2), trimmed)
print(f"源 {src.size} -> 去边距 {trimmed.size} -> 画布 {master.size}")


def render(s, bg=None):
    k = 8
    up = master.resize((s * k, s * k), Image.LANCZOS)
    a = up.getchannel("A").point(lambda v: int(255 * ((v / 255) ** GAMMA)) if v else 0)
    layer = Image.new("RGBA", up.size, LINE + (255,))
    layer.putalpha(a)
    canvas = Image.new("RGBA", up.size, (0, 0, 0, 0))
    if bg:
        ImageDraw.Draw(canvas).ellipse([0, 0, up.size[0] - 1, up.size[1] - 1], fill=bg + (255,))
    canvas.alpha_composite(layer)
    return canvas.resize((s, s), Image.LANCZOS)


def emit(prefix, bg):
    for s in SIZES:
        render(s, bg).save(os.path.join(OUT, f"{prefix}-{s}x{s}.png"))
    render(256, bg).save(os.path.join(OUT, f"{prefix}.ico"), format="ICO",
                         sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])


emit("logo-transparent", None)
emit("logo-white", (255, 255, 255))
print("已输出 ico/png")


def preview():
    cells = [("logo-transparent", None, "透明底"), ("logo-white", (255, 255, 255), "白底圆")]
    show = [16, 32, 48, 64, 128, 256]
    CELL, PAD, LBL = 118, 12, 24
    W = PAD + (CELL + PAD) * len(show)
    H = PAD + (CELL + LBL + PAD) * len(cells)
    cv = Image.new("RGB", (W, H), (236, 236, 240))
    dr = ImageDraw.Draw(cv)
    f = ImageFont.load_default(size=15)
    for ri, (_, bg, name) in enumerate(cells):
        y0 = PAD + ri * (CELL + LBL + PAD)
        dr.text((PAD, y0 + 2), name, fill=(40, 40, 45), font=f)
        for ci, s in enumerate(show):
            x0 = PAD + ci * (CELL + PAD)
            im = render(s, bg).resize((CELL, CELL), Image.NEAREST)
            base = Image.new("RGBA", (CELL, CELL), (236, 236, 240, 255))
            base.alpha_composite(im)
            cv.paste(base.convert("RGB"), (x0, y0 + LBL))
            dr.rectangle([x0, y0 + LBL, x0 + CELL - 1, y0 + LBL + CELL - 1], outline=(185, 185, 190))
            dr.text((x0 + CELL // 2 - 16, y0 + LBL + CELL + 2), f"{s}px", fill=(90, 90, 96), font=f)
    cv.save(os.path.join(OUT, "_preview.png"))
    print("预览图 _preview.png", cv.size)


preview()

for f in sorted(os.listdir(OUT)):
    print(f"   {f:34s} {os.path.getsize(os.path.join(OUT, f)):>8d} B")
