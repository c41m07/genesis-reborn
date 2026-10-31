#!/usr/bin/env python3
"""Générateur reproductible de cartes locales. Ne modifie jamais la base."""
import json,random,argparse
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--slug',required=True);p.add_argument('--seed',type=int,default=42);p.add_argument('--systems',type=int,default=12);p.add_argument('--output',required=True);a=p.parse_args()
if not 1 <= a.systems <= 1000: p.error('systems doit être entre 1 et 1000')
r=random.Random(a.seed)
systems=[]
for i in range(a.systems):
    systems.append({'name':f'Système {i+1:02d}','coord_x':i%20*3,'coord_y':i//20*3,'planets':[{'position':j} for j in sorted(r.sample(range(1,10),r.randint(3,9)))]})
Path(a.output).write_text(json.dumps({'slug':a.slug,'name':a.slug,'galaxies':[{'name':'Alpha','solar_systems':systems}]},ensure_ascii=False,indent=2))
