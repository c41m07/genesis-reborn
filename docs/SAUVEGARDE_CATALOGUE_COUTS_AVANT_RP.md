# Sauvegarde du catalogue avant les recettes RP

Cette sauvegarde fige les 156 profils matériaux (52 fiches, trois rangs) avant le changement RP. Référence : dev 14f41b900869d803a7c842133cfb4e60a3f330e5. Les quantités ci-dessous sont les profils bruts, pas le devis après prérequis, niveau, réduction ou quantité. L’électricité n’est pas un matériau de ce bloc : elle reste calculée par la règle existante.

## Revenir à l’ancienne grille

1. Sur une branche issue de dev à jour, restaurer uniquement les clés cost_profiles depuis le JSON de cette page, pour les fichiers app/config/game/<groupe>/<slug>.yaml. Si la PR RP n’a été suivie d’aucune modification sur ces profils, son revert sur une branche dédiée est une autre solution. Ne pas réinitialiser les fichiers entiers : il faut conserver les autres changements intervenus depuis.
2. Vérifier le diff et les devis en local, puis utiliser le parcours normal de PR/CI vers dev. Les surcharges d’univers existantes restent prioritaires ; inspecter séparément celles que l’exploitant a créées, ne pas les écraser.
3. Les commandes déjà prises conservent le prix payé. Restaurer la grille ne rembourse, ne recalcule et ne supprime aucune commande ni donnée de joueur.

## Profils précédents (M, H, A et N)

```json
{
  "buildings": {
    "fortress": {
      "basic": {
        "metal": 2880,
        "nano": 1560,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 2549,
        "nano": 1350,
        "antimatter": 563,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2625,
        "nano": 1453,
        "antimatter": 727,
        "hydrogen": 1876
      }
    },
    "lab": {
      "basic": {
        "metal": 2400,
        "nano": 1300,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 2127,
        "nano": 1126,
        "antimatter": 469,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2187,
        "nano": 1211,
        "antimatter": 606,
        "hydrogen": 1562
      }
    },
    "mine_antimatter": {
      "basic": {
        "metal": 288,
        "nano": 156,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 256,
        "nano": 135,
        "antimatter": 56,
        "hydrogen": 0
      },
      "superior": {
        "metal": 262,
        "nano": 145,
        "antimatter": 73,
        "hydrogen": 187
      }
    },
    "mine_hydrogen": {
      "basic": {
        "metal": 384,
        "nano": 208,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 340,
        "nano": 180,
        "antimatter": 75,
        "hydrogen": 0
      },
      "superior": {
        "metal": 350,
        "nano": 194,
        "antimatter": 97,
        "hydrogen": 250
      }
    },
    "mine_metal": {
      "basic": {
        "metal": 384,
        "nano": 208,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 340,
        "nano": 180,
        "antimatter": 75,
        "hydrogen": 0
      },
      "superior": {
        "metal": 350,
        "nano": 194,
        "antimatter": 97,
        "hydrogen": 248
      }
    },
    "mine_nano": {
      "basic": {
        "metal": 384,
        "nano": 208,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 340,
        "nano": 180,
        "antimatter": 75,
        "hydrogen": 0
      },
      "superior": {
        "metal": 350,
        "nano": 194,
        "antimatter": 97,
        "hydrogen": 250
      }
    },
    "power_plant": {
      "basic": {
        "metal": 1152,
        "nano": 624,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1020,
        "nano": 540,
        "antimatter": 225,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1049,
        "nano": 581,
        "antimatter": 291,
        "hydrogen": 750
      }
    },
    "shipyard": {
      "basic": {
        "metal": 4800,
        "nano": 2600,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 4251,
        "nano": 2250,
        "antimatter": 938,
        "hydrogen": 0
      },
      "superior": {
        "metal": 4375,
        "nano": 2422,
        "antimatter": 1211,
        "hydrogen": 3126
      }
    },
    "storage_antimatter": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1064,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 782
      }
    },
    "storage_electricity": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1064,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 782
      }
    },
    "storage_hydrogen": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1064,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 782
      }
    },
    "storage_metal": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1064,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 782
      }
    },
    "storage_nano": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1064,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 782
      }
    }
  },
  "technologies": {
    "automated_defense_repair": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "colonial_expansion": {
      "basic": {
        "metal": 5280,
        "nano": 2860,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 4676,
        "nano": 2476,
        "antimatter": 1031,
        "hydrogen": 0
      },
      "superior": {
        "metal": 4814,
        "nano": 2665,
        "antimatter": 1332,
        "hydrogen": 3438
      }
    },
    "cryogenic_reserves": {
      "basic": {
        "metal": 2400,
        "nano": 1300,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 2126,
        "nano": 1126,
        "antimatter": 469,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2188,
        "nano": 1211,
        "antimatter": 606,
        "hydrogen": 1562
      }
    },
    "engineering_foundations": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "evasive_navigation": {
      "basic": {
        "metal": 1440,
        "nano": 780,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1276,
        "nano": 676,
        "antimatter": 281,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1313,
        "nano": 727,
        "antimatter": 364,
        "hydrogen": 936
      }
    },
    "intelligence_countermeasures": {
      "basic": {
        "metal": 1440,
        "nano": 780,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1276,
        "nano": 676,
        "antimatter": 281,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1313,
        "nano": 727,
        "antimatter": 364,
        "hydrogen": 936
      }
    },
    "modular_cargo_bays": {
      "basic": {
        "metal": 2400,
        "nano": 1300,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 2126,
        "nano": 1126,
        "antimatter": 469,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2188,
        "nano": 1211,
        "antimatter": 606,
        "hydrogen": 1562
      }
    },
    "nanomaterial_synthesis": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "naval_engineering": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "rapid_fire_cycle": {
      "basic": {
        "metal": 5760,
        "nano": 3120,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5100,
        "nano": 2700,
        "antimatter": 1125,
        "hydrogen": 0
      },
      "superior": {
        "metal": 5251,
        "nano": 2907,
        "antimatter": 1453,
        "hydrogen": 3750
      }
    },
    "shield_harmonics": {
      "basic": {
        "metal": 3840,
        "nano": 2080,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 3400,
        "nano": 1800,
        "antimatter": 750,
        "hydrogen": 0
      },
      "superior": {
        "metal": 3500,
        "nano": 1937,
        "antimatter": 969,
        "hydrogen": 2500
      }
    },
    "signature_masking": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "signature_reduction": {
      "basic": {
        "metal": 1440,
        "nano": 780,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1276,
        "nano": 676,
        "antimatter": 281,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1313,
        "nano": 727,
        "antimatter": 364,
        "hydrogen": 936
      }
    },
    "structural_armor": {
      "basic": {
        "metal": 3840,
        "nano": 2080,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 3400,
        "nano": 1800,
        "antimatter": 750,
        "hydrogen": 0
      },
      "superior": {
        "metal": 3500,
        "nano": 1937,
        "antimatter": 969,
        "hydrogen": 2500
      }
    },
    "tactical_intelligence": {
      "basic": {
        "metal": 1201,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1063,
        "nano": 563,
        "antimatter": 235,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1095,
        "nano": 606,
        "antimatter": 303,
        "hydrogen": 784
      }
    },
    "target_acquisition": {
      "basic": {
        "metal": 960,
        "nano": 520,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 850,
        "nano": 450,
        "antimatter": 188,
        "hydrogen": 0
      },
      "superior": {
        "metal": 877,
        "nano": 485,
        "antimatter": 242,
        "hydrogen": 626
      }
    },
    "vector_propulsion": {
      "basic": {
        "metal": 2400,
        "nano": 1300,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 2126,
        "nano": 1126,
        "antimatter": 469,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2188,
        "nano": 1211,
        "antimatter": 606,
        "hydrogen": 1562
      }
    },
    "weapon_calibration": {
      "basic": {
        "metal": 5760,
        "nano": 3120,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5100,
        "nano": 2700,
        "antimatter": 1125,
        "hydrogen": 0
      },
      "superior": {
        "metal": 5251,
        "nano": 2907,
        "antimatter": 1453,
        "hydrogen": 3750
      }
    },
    "weapons_engineering": {
      "basic": {
        "metal": 5760,
        "nano": 3120,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5100,
        "nano": 2700,
        "antimatter": 1125,
        "hydrogen": 0
      },
      "superior": {
        "metal": 5251,
        "nano": 2907,
        "antimatter": 1453,
        "hydrogen": 3750
      }
    }
  },
  "ships": {
    "battleship": {
      "basic": {
        "metal": 52921,
        "nano": 28665,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 46857,
        "nano": 24807,
        "antimatter": 10336,
        "hydrogen": 0
      },
      "superior": {
        "metal": 48236,
        "nano": 26702,
        "antimatter": 13351,
        "hydrogen": 34456
      }
    },
    "bomber": {
      "basic": {
        "metal": 5161,
        "nano": 2795,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 4571,
        "nano": 2420,
        "antimatter": 1008,
        "hydrogen": 0
      },
      "superior": {
        "metal": 4705,
        "nano": 2605,
        "antimatter": 1302,
        "hydrogen": 3362
      }
    },
    "capital": {
      "basic": {
        "metal": 90612,
        "nano": 49082,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 80230,
        "nano": 42475,
        "antimatter": 17698,
        "hydrogen": 0
      },
      "superior": {
        "metal": 82591,
        "nano": 45720,
        "antimatter": 22860,
        "hydrogen": 58992
      }
    },
    "colony_ship": {
      "basic": {
        "metal": 9600,
        "nano": 5200,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 8500,
        "nano": 4501,
        "antimatter": 1875,
        "hydrogen": 0
      },
      "superior": {
        "metal": 8750,
        "nano": 4844,
        "antimatter": 2422,
        "hydrogen": 6250
      }
    },
    "corvette": {
      "basic": {
        "metal": 6720,
        "nano": 3640,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5949,
        "nano": 3150,
        "antimatter": 1313,
        "hydrogen": 0
      },
      "superior": {
        "metal": 6125,
        "nano": 3391,
        "antimatter": 1696,
        "hydrogen": 4374
      }
    },
    "cruiser": {
      "basic": {
        "metal": 29215,
        "nano": 15825,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 25870,
        "nano": 13696,
        "antimatter": 5707,
        "hydrogen": 0
      },
      "superior": {
        "metal": 26629,
        "nano": 14741,
        "antimatter": 7371,
        "hydrogen": 19020
      }
    },
    "destroyer": {
      "basic": {
        "metal": 20213,
        "nano": 10949,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 17898,
        "nano": 9476,
        "antimatter": 3948,
        "hydrogen": 0
      },
      "superior": {
        "metal": 18424,
        "nano": 10199,
        "antimatter": 5100,
        "hydrogen": 13158
      }
    },
    "fighter": {
      "basic": {
        "metal": 1440,
        "nano": 780,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1276,
        "nano": 676,
        "antimatter": 281,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1314,
        "nano": 727,
        "antimatter": 363,
        "hydrogen": 940
      }
    },
    "frigate": {
      "basic": {
        "metal": 10934,
        "nano": 5923,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 9684,
        "nano": 5126,
        "antimatter": 2136,
        "hydrogen": 0
      },
      "superior": {
        "metal": 9967,
        "nano": 5518,
        "antimatter": 2759,
        "hydrogen": 7120
      }
    },
    "heavy_fighter": {
      "basic": {
        "metal": 3552,
        "nano": 1924,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 3145,
        "nano": 1666,
        "antimatter": 694,
        "hydrogen": 0
      },
      "superior": {
        "metal": 3239,
        "nano": 1793,
        "antimatter": 897,
        "hydrogen": 2314
      }
    },
    "hydrogen_collector": {
      "basic": {
        "metal": 384,
        "nano": 208,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 340,
        "nano": 180,
        "antimatter": 75,
        "hydrogen": 0
      },
      "superior": {
        "metal": 350,
        "nano": 194,
        "antimatter": 97,
        "hydrogen": 248
      }
    },
    "interceptor": {
      "basic": {
        "metal": 1764,
        "nano": 956,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 1563,
        "nano": 828,
        "antimatter": 345,
        "hydrogen": 0
      },
      "superior": {
        "metal": 1610,
        "nano": 891,
        "antimatter": 446,
        "hydrogen": 1150
      }
    },
    "probe": {
      "basic": {
        "metal": 240,
        "nano": 130,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 213,
        "nano": 113,
        "antimatter": 47,
        "hydrogen": 0
      },
      "superior": {
        "metal": 220,
        "nano": 122,
        "antimatter": 61,
        "hydrogen": 156
      }
    },
    "solar_satellite": {
      "basic": {
        "metal": 384,
        "nano": 208,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 340,
        "nano": 180,
        "antimatter": 75,
        "hydrogen": 0
      },
      "superior": {
        "metal": 350,
        "nano": 194,
        "antimatter": 97,
        "hydrogen": 248
      }
    },
    "stealth_fighter": {
      "basic": {
        "metal": 5856,
        "nano": 3172,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5185,
        "nano": 2746,
        "antimatter": 1144,
        "hydrogen": 0
      },
      "superior": {
        "metal": 5337,
        "nano": 2955,
        "antimatter": 1478,
        "hydrogen": 3812
      }
    },
    "transport": {
      "basic": {
        "metal": 5760,
        "nano": 3120,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 5100,
        "nano": 2701,
        "antimatter": 1125,
        "hydrogen": 0
      },
      "superior": {
        "metal": 5252,
        "nano": 2907,
        "antimatter": 1453,
        "hydrogen": 3752
      }
    }
  },
  "defenses": {
    "ground_turret_anti_light": {
      "basic": {
        "metal": 1200,
        "nano": 650,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 851,
        "nano": 450,
        "antimatter": 187,
        "hydrogen": 0
      },
      "superior": {
        "metal": 700,
        "nano": 388,
        "antimatter": 194,
        "hydrogen": 498
      }
    },
    "ground_turret_anti_escort": {
      "basic": {
        "metal": 4320,
        "nano": 2340,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 3060,
        "nano": 1621,
        "antimatter": 675,
        "hydrogen": 0
      },
      "superior": {
        "metal": 2520,
        "nano": 1395,
        "antimatter": 698,
        "hydrogen": 1800
      }
    },
    "ground_turret_anti_heavy": {
      "basic": {
        "metal": 11280,
        "nano": 6110,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 7989,
        "nano": 4230,
        "antimatter": 1763,
        "hydrogen": 0
      },
      "superior": {
        "metal": 6580,
        "nano": 3642,
        "antimatter": 1821,
        "hydrogen": 4702
      }
    },
    "ground_turret_anti_capital": {
      "basic": {
        "metal": 30720,
        "nano": 16640,
        "antimatter": 0,
        "hydrogen": 0
      },
      "intermediate": {
        "metal": 21760,
        "nano": 11520,
        "antimatter": 4800,
        "hydrogen": 0
      },
      "superior": {
        "metal": 17920,
        "nano": 9920,
        "antimatter": 4960,
        "hydrogen": 12800
      }
    }
  }
}
```
