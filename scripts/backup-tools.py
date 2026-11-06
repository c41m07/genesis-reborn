#!/usr/bin/env python3
"""Intégrité, conservation et suivi des sauvegardes ; bibliothèque standard uniquement."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import tempfile
import time


def project_name(value):
    if not re.fullmatch(r"[a-z0-9][a-z0-9_-]*", value):
        raise ValueError("Nom de projet invalide")
    return value


def verify(archive):
    archive = Path(archive)
    manifest = Path(str(archive) + ".sha256")
    if archive.is_symlink() or manifest.is_symlink() or not archive.is_file() or not manifest.is_file():
        raise ValueError("Archive et manifeste doivent être des fichiers réguliers, sans lien symbolique")
    if manifest.stat().st_size > 4096:
        raise ValueError("Manifeste trop volumineux")
    match = re.fullmatch(r"([a-fA-F0-9]{64}) [ *]([^\r\n]+)\n?", manifest.read_text())
    if not match or match[2] != archive.name:
        raise ValueError("Le manifeste ne désigne pas exactement cette archive")
    digest = hashlib.sha256()
    with archive.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    if digest.hexdigest() != match[1].lower():
        raise ValueError("Checksum de l’archive invalide : " + archive.name)
    return archive.resolve()


def prune(directory, project, days, keep):
    directory = Path(directory).resolve()
    project_name(project)
    if days < 1:
        raise ValueError("Conservation strictement positive requise")
    keep = verify(keep)
    if keep.parent != directory:
        raise ValueError("L’archive conservée doit appartenir au dossier de sauvegarde")
    pattern = re.compile(r"genesis-" + re.escape(project) + r"-\d{8}T\d{6}Z(?:-[A-Za-z0-9]{12})?\.sql\.gz")
    if not pattern.fullmatch(keep.name):
        raise ValueError("L’archive conservée ne correspond pas au projet")
    cutoff = time.time() - days * 86400
    candidates = []
    for item in directory.iterdir():
        if pattern.fullmatch(item.name) and not item.is_symlink() and item.is_file() and item.resolve() != keep and item.stat().st_mtime < cutoff:
            verify(item)
            candidates.append(item)
    # Tout vérifier avant de retirer uniquement les paires validées de ce projet.
    for item in candidates:
        item.unlink()
        Path(str(item) + ".sha256").unlink()
    return len(candidates)


def record(directory, project, status, archive=None):
    project_name(project)
    directory = Path(directory).resolve()
    path = directory / ("status-" + project + ".json")
    previous = json.loads(path.read_text()) if path.is_file() else {}
    if not isinstance(previous, dict):
        raise ValueError("État de sauvegarde invalide")
    if status == "ok":
        archive = verify(archive)
        if archive.parent != directory or not archive.name.startswith("genesis-" + project + "-"):
            raise ValueError("Archive de succès hors du projet")
    now = int(time.time())
    value = {"status": status, "last_attempt": now, "last_success": previous.get("last_success"), "archive": previous.get("archive")}
    if status == "ok":
        value.update(last_success=now, archive=Path(archive).name)
    handle, name = tempfile.mkstemp(prefix=".genesis-status-", dir=directory)
    try:
        with os.fdopen(handle, "w") as stream:
            json.dump(value, stream)
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def check(directory, project, max_age, max_run=3600):
    project_name(project)
    if max_age < 1 or max_run < 1:
        raise ValueError("Ancienneté maximale invalide")
    path = Path(directory) / ("status-" + project + ".json")
    if not path.is_file():
        raise ValueError("Aucun état de sauvegarde disponible")
    state = json.loads(path.read_text())
    if not isinstance(state, dict) or state.get("status") not in ["started", "ok", "failed"]:
        raise ValueError("État de sauvegarde invalide")
    success = state.get("last_success")
    if state["status"] == "failed":
        print(json.dumps({"healthy": False, "status": "failed", "last_success": success}))
        return 1
    if state["status"] == "started":
        attempt = state.get("last_attempt")
        if not isinstance(attempt, int) or attempt > time.time():
            raise ValueError("Date de début inconnue ou incohérente")
        if time.time()-attempt > max_run:
            print(json.dumps({"healthy": False, "status": "started_timeout", "last_success": success}))
            return 1
        recent = success is None or (isinstance(success, int) and 0 <= time.time()-success <= max_age)
        if time.time()-attempt <= max_run and recent:
            print(json.dumps({"healthy": None, "status": "started", "last_success": success}))
            return 3
    if not isinstance(success, int) or success > time.time():
        raise ValueError("Date du dernier succès inconnue ou incohérente")
    healthy = state.get("status") == "ok" and time.time() - success <= max_age
    if healthy:
        name = state.get("archive")
        if not isinstance(name, str) or Path(name).name != name or not name.startswith("genesis-" + project + "-"):
            raise ValueError("Archive du dernier succès invalide")
        verify(Path(directory) / name)
    print(json.dumps({"healthy": healthy, "status": state.get("status"), "age_seconds": int(time.time() - success)}, ensure_ascii=False))
    return 0 if healthy else 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["verify", "prune", "record", "check"])
    parser.add_argument("--archive")
    parser.add_argument("--directory", default=os.environ.get("BACKUP_DIR", "var/backups"))
    parser.add_argument("--project", default=os.environ.get("GENESIS_PROJECT", "genesis_reborn_v2"))
    parser.add_argument("--days", type=int, default=14)
    parser.add_argument("--max-age", type=int, default=os.environ.get("BACKUP_MAX_AGE_SECONDS", "129600"))
    parser.add_argument("--max-run", type=int, default=os.environ.get("BACKUP_MAX_RUN_SECONDS", "3600"))
    parser.add_argument("--status", choices=["started", "ok", "failed"])
    args = parser.parse_args()
    try:
        if args.action == "verify":
            verify(args.archive)
            print("Intégrité et archive du manifeste vérifiées.")
        elif args.action == "prune":
            print("Paires expirées retirées :", prune(args.directory, args.project, args.days, args.archive))
        elif args.action == "record":
            record(args.directory, args.project, args.status, args.archive)
        else:
            return check(args.directory, args.project, args.max_age, args.max_run)
        return 0
    except (ValueError, TypeError, OSError) as error:
        print("Sauvegarde : " + str(error), file=__import__("sys").stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
