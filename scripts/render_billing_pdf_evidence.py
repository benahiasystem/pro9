#!/usr/bin/env python3
"""Render only immutable, fictitious PDFs from a completed private PG test run."""
import argparse
import concurrent.futures
import hashlib
import json
from pathlib import Path
import re
import subprocess

from PIL import Image, ImageDraw, ImageOps


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run", required=True)
    parser.add_argument("--matrix", type=int, choices=[1, 2], default=2)
    args = parser.parse_args()
    if not re.fullmatch(r"sab-pgtest-[a-f0-9]{12}", args.run):
        parser.error("La ejecución no es una instancia privada reconocida.")
    root = Path(__file__).resolve().parents[1]
    evidence = root / "informes/evidencias/postgresql_17_implementacion/resultados"
    completed = json.loads((evidence / (args.run + ".json")).read_text())
    if completed["exit_code"] != 0 or not completed["source_unchanged"] or not completed["cleanup_ok"]:
        raise RuntimeError("La prueba no terminó correctamente con fuente fija y limpieza.")
    source = evidence / ("billing-pdf-" + args.run)
    files = []
    cases = [("VES", 48), ("USD", 24)]
    if args.matrix == 2:
        cases += [("integral-VES", 8), ("integral-USD", 8), ("subprecision-VES", 16), ("subprecision-USD", 8)]
    elif (source / "integral-VES").exists() or (source / "integral-USD").exists():
        cases += [("integral-VES", 8), ("integral-USD", 8)]
    for currency, count in cases:
        manifest = json.loads((source / currency / "manifest.json").read_text())
        if not manifest["fictitious"] or manifest["run_id"] != args.run or len(manifest["files"]) != count:
            raise RuntimeError("La matriz ficticia de facturación está incompleta.")
        for entry in manifest["files"]:
            if Path(entry["file"]).name != entry["file"]:
                raise RuntimeError("Nombre de comprobante inválido.")
            path = source / currency / entry["file"]
            if sha256(path) != entry["sha256"]:
                raise RuntimeError("Cambió el PDF después de la prueba nativa.")
            files.append((path, entry))
    output = evidence / ("billing-pdf-visual-" + args.run)
    output.mkdir()  # Never overwrite an earlier inspection.

    def render(pair):
        pdf, entry = pair
        prefix = output / (pdf.parent.name + "-" + pdf.stem)
        subprocess.run(["pdftoppm", "-r", "150", "-png", str(pdf), str(prefix)],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        pages = sorted(output.glob(prefix.name + "-*.png"))
        if len(pages) != entry["pages"]:
            raise RuntimeError("El render no conserva el número de páginas validado.")
        return {"pdf": str(pdf), "pdf_sha256": entry["sha256"],
                "pages": [{"path": str(page), "sha256": sha256(page)} for page in pages]}

    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        results = list(pool.map(render, files))
    for theme in ["default", "modern-2027", "marca_de_agua", "Plantilla_personalizable"]:
        pages = [(Path(row["pdf"]).parent.name + "/" + Path(row["pdf"]).name, page["path"])
                 for row in results if "-" + theme + "-" in row["pdf"] for page in row["pages"]]
        for offset in range(0, len(pages), 6):
            cards = []
            for label, path in pages[offset:offset + 6]:
                with Image.open(path) as image:
                    thumb = ImageOps.contain(image.convert("RGB"), (700, 1500))
                    card = Image.new("RGB", (720, thumb.height + 38), "#ddd")
                    card.paste(thumb, ((720 - thumb.width) // 2, 35))
                    ImageDraw.Draw(card).text((8, 8), label, fill="black")
                    cards.append(card)
            heights = [max(card.height for card in cards[index:index + 2]) for index in range(0, len(cards), 2)]
            sheet = Image.new("RGB", (1440, sum(heights) + 10 * (len(heights) - 1)), "#aaa")
            y = 0
            for index, height in zip(range(0, len(cards), 2), heights):
                for column, card in enumerate(cards[index:index + 2]):
                    sheet.paste(card, (column * 720, y))
                y += height + 10
            sheet.save(output / f"{theme}-contact-{offset // 6 + 1:02}.png")
    report = {"run_id": args.run, "source_sha256_at_generation": completed["source_sha256"],
              "matrix_version": args.matrix,
              "pdf_count": len(results), "page_count": sum(len(row["pages"]) for row in results),
              "visual_inspection": "pending", "files": results}
    (output / "manifest.json").write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps({"pdfs": report["pdf_count"], "pages": report["page_count"], "output": str(output)}))


if __name__ == "__main__":
    main()
