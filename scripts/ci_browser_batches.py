"""Répartir l’inventaire Playwright du commit en lots de durée comparable."""
import json
import math
import os
import statistics
import sys
from collections import Counter, defaultdict
from pathlib import Path

TARGET_SECONDS = 22 * 60
MAX_BATCHES = 4


def count_cases(suite):
    """Les describe imbriqués peuvent contenir plusieurs scénarios du même fichier."""
    counts = Counter(test["projectName"] for spec in suite.get("specs", []) for test in spec["tests"])
    for child in suite.get("suites", []):
        counts.update(count_cases(child))
    return counts


def plan(inventory, categories, weights, *, full_suite=False):
    if inventory.get("errors"):
        raise ValueError("La découverte Playwright a signalé des erreurs.")
    # Un fichier ajouté bénéficie immédiatement de la médiane par scénario.
    # Les durées influencent seulement la répartition, jamais la sélection des tests.
    default_weight = statistics.median(weights.values())
    files = defaultdict(dict)
    for suite in inventory["suites"]:
        path = suite["file"]
        for category, cases in count_cases(suite).items():
            if path in files[category]:
                raise ValueError(f"Fichier en double dans {category} : {path}")
            files[category][path] = cases * weights.get(path, default_weight)

    if full_suite:
        unassigned = files.keys() - {category["category"] for category in categories}
        if unassigned:
            raise ValueError(f"Projets Playwright absents de la matrice : {sorted(unassigned)}")

    jobs = []
    for category in categories:
        name = category["category"]
        costs = files[name]
        if not costs:
            raise ValueError(f"Aucun scénario découvert dans {name}.")
        count = min(MAX_BATCHES, len(costs), max(1, math.ceil(sum(costs.values()) / TARGET_SECONDS)))
        batches = [[] for _ in range(count)]
        loads = [0.0] * count
        # Placer les fichiers longs en premier limite l’attente du dernier runner.
        # Le nom du fichier départage deux durées égales de façon reproductible.
        for path in sorted(costs, key=lambda path: (-costs[path], path)):
            index = min(range(count), key=loads.__getitem__)
            batches[index].append(path)
            loads[index] += costs[path]
        for index, paths in enumerate(batches):
            jobs.append(({
                **category,
                "part": index + 1,
                "total": count,
                "list": f"{name}-{index + 1}.txt",
            }, paths, loads[index]))
    return jobs


def main():
    inventory_path, destination = map(Path, sys.argv[1:])
    weights_path = Path(__file__).resolve().parents[1] / ".github/ci/browser-seconds-per-test.json"
    weights = json.loads(weights_path.read_text())["secondsPerTest"]
    if not weights or any(not math.isfinite(value) or value <= 0 for value in weights.values()):
        raise ValueError("Les durées de référence doivent être positives et finies.")
    inventory = json.loads(inventory_path.read_text())
    categories = json.loads(os.environ["BROWSER_CATEGORIES"])
    jobs = plan(inventory, categories, weights, full_suite=os.environ.get("CI_SCOPE") == "all")
    destination.mkdir()
    for job, paths, seconds in jobs:
        (destination / job["list"]).write_text("\n".join(sorted(paths)) + "\n")
        print(f"{job['label']} {job['part']}/{job['total']} — {len(paths)} fichiers, {seconds / 60:.1f} min estimées")
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
        print("batches=" + json.dumps([job for job, _, _ in jobs]), file=output)


if __name__ == "__main__":
    main()
