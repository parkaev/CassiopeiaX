#!/usr/bin/env python3
import os
import time
import random
from datetime import datetime
import subprocess

def get_env_def(name, default):
    return os.getenv(name, default)

def rand_float(min_v, max_v):
    return min_v + random.random() * (max_v - min_v)

def generate_and_copy():
    out_dir = get_env_def('CSV_OUT_DIR', '/data/csv')
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
    fn = f'telemetry_{ts}.csv'
    fullpath = os.path.join(out_dir, fn)
    
    # Write CSV
    with open(fullpath, 'w') as f:
        f.write('recorded_at,voltage,temp,source_file\n')
        recorded_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        voltage = f'{rand_float(3.2, 12.6):.2f}'
        temp = f'{rand_float(-50.0, 80.0):.2f}'
        f.write(f'{recorded_at},{voltage},{temp},{fn}\n')
    
    # COPY into Postgres
    pghost = get_env_def('PGHOST', 'db')
    pgport = get_env_def('PGPORT', '5432')
    pguser = get_env_def('PGUSER', 'monouser')
    pgpass = get_env_def('PGPASSWORD', 'monopass')
    pgdb = get_env_def('PGDATABASE', 'monolith')
    
    # Fixed: Use proper escaping for psql command
    copy_sql = f"\\copy telemetry_legacy(recorded_at, voltage, temp, source_file) FROM '{fullpath}' WITH (FORMAT csv, HEADER true)"
    
    env = os.environ.copy()
    env['PGPASSWORD'] = pgpass
    
    subprocess.run([
        'psql',
        f'host={pghost} port={pgport} user={pguser} dbname={pgdb}',
        '-c', copy_sql
    ], env=env, check=True)

if __name__ == '__main__':
    random.seed()
    period = int(get_env_def('GEN_PERIOD_SEC', '300'))
    
    while True:
        try:
            generate_and_copy()
        except Exception as e:
            print(f'Legacy error: {e}')
        time.sleep(period)
