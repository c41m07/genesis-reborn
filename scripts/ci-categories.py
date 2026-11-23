"""Construire les matrices complètes ou ciblées à partir du choix GitHub Actions."""
import json
import os
from pathlib import Path

php = {
    "Unit": "Unitaires",
    "Service": "Services",
    "Controller": "Contrôleurs",
    "Integration": "Intégration et schéma MariaDB",
    "Twig": "Rendu Twig",
}
browser = {
    "audits": "Audits d’interface",
    "administration": "Administration",
    "empire": "Empire et progression",
    "interactions": "Flottes et interactions",
}
labels = {"all": "Suite complète", "quality": "Qualité et assets", "backup": "Sauvegarde et restauration", **php, **browser}
selection = os.environ["CATEGORY"]
scope = next((key for key, label in labels.items() if label == selection), selection)
if scope not in ["all", "development", "quality", "backup", *php, *browser]:
    raise SystemExit("Catégorie inconnue")

# PHPUnit découvre normalement tous les dossiers. Une nouvelle famille doit
# aussi rejoindre la matrice avant que le contrôle de fusion puisse réussir.
for test in Path("app/tests").rglob("*Test.php"):
    if test.relative_to("app/tests").parts[0] not in php:
        raise SystemExit(f"Test PHP sans catégorie : {test}")

with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
    print(f"scope={scope}", file=output)
    for key, categories in (("php", php), ("browser", browser)):
        jobs = [{"category": name, "label": label} for name, label in categories.items() if scope in ("all", name)]
        print(f"{key}={json.dumps(jobs)}", file=output)
