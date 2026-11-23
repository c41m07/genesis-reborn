import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[3] / "scripts"))
from ci_browser_batches import plan


class BrowserBatchTest(unittest.TestCase):
    def setUp(self):
        self.categories = [{"category": "audits", "label": "Audits"}]

    def inventory(self, paths):
        return {"suites": [
            {"file": path, "suites": [{"specs": [{"tests": [{"projectName": "audits"}]}]}]}
            for path in paths
        ]}

    def test_new_file_uses_median_and_every_file_belongs_to_one_batch(self):
        costs = {"long": 1200, "medium": 600, "short": 100}
        inventory = self.inventory(["short", "new", "long", "medium"])
        jobs = plan(inventory, self.categories, costs)
        self.assertEqual(2, len(jobs))
        self.assertCountEqual([path for _, paths, _ in jobs for path in paths], ["short", "new", "long", "medium"])
        self.assertEqual([1200, 1300], sorted(seconds for _, _, seconds in jobs))
        inventory["suites"].reverse()
        self.assertEqual(jobs, plan(inventory, self.categories, costs))

    def test_cap_and_small_category(self):
        costs = {str(index): 2000 for index in range(10)}
        self.assertEqual(4, len(plan(self.inventory(costs), self.categories, costs)))
        self.assertEqual(1, len(plan(self.inventory(["0"]), self.categories, costs)))

    def test_full_matrix_requires_every_discovered_project(self):
        inventory = self.inventory(["first", "second"])
        inventory["suites"][1]["suites"][0]["specs"][0]["tests"][0]["projectName"] = "extra"
        with self.assertRaisesRegex(ValueError, "extra"):
            plan(inventory, self.categories, {"first": 1}, full_suite=True)
        self.assertEqual(["first"], plan(inventory, self.categories, {"first": 1})[0][1])

    def test_empty_or_duplicate_selection_fails(self):
        for paths in ([], ["a", "a"]):
            with self.subTest(paths=paths), self.assertRaises(ValueError):
                plan(self.inventory(paths), self.categories, {"a": 1})
