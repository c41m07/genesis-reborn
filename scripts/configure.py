#!/usr/bin/env python3
from pathlib import Path
import secrets
p=Path(__file__).resolve().parents[1]/'.env.local'
if p.exists():
    print('.env.local existe, conservé.')
else:
    db,root,rabbit,secret=(secrets.token_hex(24) for _ in range(4))
    p.write_text(f"APP_SECRET={secret}\nDB_PASSWORD={db}\nDB_ROOT_PASSWORD={root}\nRABBITMQ_PASSWORD={rabbit}\nDATABASE_URL=mysql://genesis:{db}@database:3306/genesis_v2?serverVersion=11.8.0-MariaDB&charset=utf8mb4\nMESSENGER_TRANSPORT_DSN=amqp://genesis:{rabbit}@rabbitmq:5672/%2f/genesis_v2\n")
    p.chmod(0o600)
    print('.env.local créé avec secrets aléatoires.')
if 'MERCURE_JWT_SECRET=' not in p.read_text():
    with p.open('a') as f: f.write('MERCURE_JWT_SECRET='+secrets.token_hex(32)+'\n')
