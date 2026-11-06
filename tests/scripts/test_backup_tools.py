import fcntl
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("backup_tools", ROOT / "scripts/backup-tools.py")
tools = importlib.util.module_from_spec(spec)
spec.loader.exec_module(tools)


class BackupTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="genesis-backup-unit-")
        self.directory = Path(self.temporary.name)
        self.archives = self.directory / "archives"
        self.archives.mkdir()
        self.bin = self.directory / "bin"
        self.bin.mkdir()
        docker = self.bin / "docker"
        docker.write_text('#!/bin/sh\n[ "${FAIL_DOCKER:-0}" = 0 ] || exit 9\nif [ -n "${IMPORT_MARKER:-}" ]; then cat > "$IMPORT_MARKER"; else printf "%s\\n" "${FAKE_SQL:-SELECT 1;}"; fi\n')
        docker.chmod(0o700)
        date = self.bin / "date"
        date.write_text('#!/bin/sh\nprintf "%s\\n" 20261106T120000Z\n')
        date.chmod(0o700)
        self.env = dict(os.environ, PATH=str(self.bin)+":"+os.environ["PATH"], GENESIS_PROJECT="genesis-backup-unit", BACKUP_DIR=str(self.archives))

    def tearDown(self):
        self.temporary.cleanup()

    def archive(self, name, content=b"SELECT 1;\n"):
        path = self.archives / name
        path.write_bytes(gzip.compress(content))
        Path(str(path)+".sha256").write_text(hashlib.sha256(path.read_bytes()).hexdigest()+"  "+path.name+"\n")
        return path

    def run_script(self, name, **env):
        return subprocess.run(["bash", "scripts/"+name], cwd=ROOT, env=dict(self.env, **env), text=True, capture_output=True)

    def test_two_backups_same_second_are_distinct_private_and_valid(self):
        for sql in ["SELECT 1;", "SELECT 2;"]:
            result = self.run_script("db-backup.sh", FAKE_SQL=sql)
            self.assertEqual(result.returncode, 0, result.stderr)
        archives = list(self.archives.glob("*.sql.gz"))
        self.assertEqual(len(archives), 2)
        self.assertEqual({gzip.decompress(path.read_bytes()).strip() for path in archives}, {b"SELECT 1;", b"SELECT 2;"})
        for archive in archives:
            self.assertEqual(archive.stat().st_mode & 0o777, 0o600)
            self.assertEqual(tools.verify(archive), archive.resolve())
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 0)

    def test_restore_rejects_manifest_for_different_archive_before_docker(self):
        archive = self.archive("unchecked.sql.gz", b"SELECT 999;")
        decoy = self.archive("other.sql.gz")
        Path(str(archive)+".sha256").write_text(hashlib.sha256(decoy.read_bytes()).hexdigest()+"  "+decoy.name+"\n")
        marker = self.directory / "called"
        result = self.run_script("db-restore.sh", BACKUP_FILE=str(archive), RESTORE_CONFIRM="genesis-backup-unit", IMPORT_MARKER=str(marker))
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(marker.exists())
        self.assertIn("exactement", result.stderr)

    def test_restore_confirmation_is_required(self):
        archive = self.archive("valid.sql.gz")
        marker = self.directory / "called"
        result = self.run_script("db-restore.sh", BACKUP_FILE=str(archive), RESTORE_CONFIRM="different-project", IMPORT_MARKER=str(marker))
        self.assertEqual(result.returncode, 3)
        self.assertFalse(marker.exists())

    def test_failure_preserves_last_success_and_removes_temporary_archive(self):
        self.assertEqual(self.run_script("db-backup.sh").returncode, 0)
        status = self.archives / "status-genesis-backup-unit.json"
        previous = json.loads(status.read_text())
        self.assertEqual(self.run_script("db-backup.sh", FAIL_DOCKER="1").returncode, 9)
        failed = json.loads(status.read_text())
        self.assertEqual(failed["status"], "failed")
        self.assertEqual(failed["last_success"], previous["last_success"])
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 1)
        self.assertEqual(list(self.archives.glob(".genesis-*.????????????")), [])

    def test_lock_prevents_second_job(self):
        with (self.archives / ".genesis-genesis-backup-unit.lock").open("w") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            self.assertEqual(self.run_script("db-backup.sh").returncode, 4)
        self.assertEqual(list(self.archives.glob("*.sql.gz")), [])

    def test_retention_keeps_latest_foreign_and_symlink_files(self):
        keep = self.archive("genesis-genesis-backup-unit-20261106T120000Z-abcdefghijkl.sql.gz")
        old = self.archive("genesis-genesis-backup-unit-20261001T120000Z.sql.gz")
        foreign = self.archive("genesis-other-20261001T120000Z.sql.gz")
        link = self.archives / "genesis-genesis-backup-unit-20260901T120000Z.sql.gz"
        link.symlink_to(foreign)
        for path in [keep, old, foreign]:
            os.utime(path, (time.time()-30*86400, time.time()-30*86400))
        self.assertEqual(tools.prune(self.archives, "genesis-backup-unit", 14, keep), 1)
        self.assertFalse(old.exists())
        self.assertTrue(keep.exists())
        self.assertTrue(foreign.exists())
        self.assertTrue(link.is_symlink())

    def test_bad_old_checksum_prevents_any_retention_removal(self):
        keep = self.archive("genesis-genesis-backup-unit-20261106T120000Z.sql.gz")
        bad = self.archive("genesis-genesis-backup-unit-20261001T120000Z.sql.gz")
        Path(str(bad)+".sha256").write_text("0"*64+"  "+bad.name+"\n")
        os.utime(bad, (0, 0))
        with self.assertRaises(ValueError):
            tools.prune(self.archives, "genesis-backup-unit", 14, keep)
        self.assertTrue(bad.exists())

    def test_stale_success_is_detected(self):
        tools.record(self.archives, "genesis-backup-unit", "ok", self.archive("genesis-genesis-backup-unit-20261106T120000Z.sql.gz"))
        path = self.archives / "status-genesis-backup-unit.json"
        state = json.loads(path.read_text())
        state["last_success"] = int(time.time())-129601
        path.write_text(json.dumps(state))
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 1)

    def test_running_backup_does_not_report_failure_or_recovery(self):
        archive = self.archive("genesis-genesis-backup-unit-20261106T120000Z.sql.gz")
        tools.record(self.archives, "genesis-backup-unit", "ok", archive)
        tools.record(self.archives, "genesis-backup-unit", "started")
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 3)

    def test_first_job_stuck_beyond_limit_is_detected(self):
        tools.record(self.archives, "genesis-backup-unit", "started")
        path = self.archives / "status-genesis-backup-unit.json"
        state = json.loads(path.read_text())
        state["last_attempt"] = int(time.time())-3601
        path.write_text(json.dumps(state))
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 1)

    def test_first_failure_is_detected_without_previous_success(self):
        tools.record(self.archives, "genesis-backup-unit", "failed")
        self.assertEqual(tools.check(self.archives, "genesis-backup-unit", 129600), 1)

    def test_recent_status_does_not_hide_missing_archive(self):
        archive = self.archive("genesis-genesis-backup-unit-20261106T120000Z.sql.gz")
        tools.record(self.archives, "genesis-backup-unit", "ok", archive)
        archive.unlink()
        with self.assertRaises(ValueError):
            tools.check(self.archives, "genesis-backup-unit", 129600)

    def test_invalid_status_is_not_assumed_healthy(self):
        (self.archives / "status-genesis-backup-unit.json").write_text("[]")
        with self.assertRaises(ValueError):
            tools.check(self.archives, "genesis-backup-unit", 129600)


if __name__ == "__main__":
    unittest.main()
