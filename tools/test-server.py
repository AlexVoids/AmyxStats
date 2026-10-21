#!/usr/bin/env python3
"""Run real PM5 plus production PHAR in an isolated, disposable server directory."""
import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

p = argparse.ArgumentParser(description=__doc__)
p.add_argument('--php', required=True, type=Path)
p.add_argument('--pmmp', required=True, type=Path)
p.add_argument('--anvil', type=Path)
p.add_argument('--scorehud-source', type=Path)
p.add_argument('--port', type=int, default=19450)
args = p.parse_args()
root = Path(__file__).resolve().parent.parent
php, pmmp = str(args.php.resolve(strict=True)), str(args.pmmp.resolve(strict=True))
subprocess.run([php, '-n', '-d', 'phar.readonly=0', str(root/'tools/build.php')], check=True)
(root/'work').mkdir(exist_ok=True)
testphar=root/'work/AmyxStatsTests.phar'
script = '$p=new Phar($argv[1]);$p->buildFromDirectory($argv[2]);$p->setStub("<?php __HALT_COMPILER();");$p->setSignatureAlgorithm(Phar::SHA256);'
if testphar.exists(): testphar.unlink()
subprocess.run([php,'-n','-d','phar.readonly=0','-r',script,str(testphar),str(root/'tests/server')],check=True)
server=Path(tempfile.mkdtemp(prefix='server-test-',dir=root/'work'))
plugins=server/'plugins';plugins.mkdir()
for source in (root/'build/AmyxStats.phar',testphar): shutil.copy2(source,plugins/source.name)
if args.anvil: shutil.copy2(args.anvil.resolve(strict=True),plugins/'AmyxAnvil.phar')
(server/'server.properties').write_text(f'server-ip=127.0.0.1\nserver-port={args.port}\nserver-portv6={args.port+1}\nenable-query=off\nlevel-type=FLAT\n')
env=os.environ.copy();env['AMYXSTATS_RUN_TESTS']='1'
if args.scorehud_source: env['AMYXSTATS_SCOREHUD_SOURCE']=str(args.scorehud_source.resolve())
with (server/'test.log').open('w') as log:
    run=subprocess.run([php,'-n','-d','zend.assertions=-1',pmmp,'--no-wizard','--disable-ansi',f'--data={server}',f'--plugins={plugins}'],cwd=server,env=env,stdout=log,stderr=subprocess.STDOUT,timeout=90)
result=server/'amyxstats-results.json'
if run.returncode or not result.exists(): raise SystemExit(f'Server failed: {server}/test.log')
results=json.loads(result.read_text());print(json.dumps(results,ensure_ascii=False));print(f'Logs: {server}')
if results['failures'] or results['passed']<20: raise SystemExit(1)
with (server/'restart.log').open('w') as log:
    run=subprocess.run([php,'-n','-d','zend.assertions=-1',pmmp,'--no-wizard','--disable-ansi',f'--data={server}',f'--plugins={plugins}'],cwd=server,env=env,stdout=log,stderr=subprocess.STDOUT,timeout=90)
result=server/'amyxstats-restart-results.json'
if run.returncode or not result.exists(): raise SystemExit(f'Restart failed: {server}/restart.log')
results=json.loads(result.read_text());print(json.dumps(results,ensure_ascii=False))
if results['failures'] or results['passed']<1: raise SystemExit(1)
