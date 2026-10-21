<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class): void{ $file=dirname(__DIR__).'/src/'.str_replace('\\','/',$class).'.php';if(is_file($file)){ require $file; } });
use Amyx\Stats\storage\StatisticsStore as Store;
use Amyx\Stats\stat\StatisticsService as Service;
use Amyx\Stats\stat\MetricCatalog as Catalog;
use Amyx\Stats\placeholder\PlaceholderResolver as Resolver;
function ok(bool $v,string $why='Assertion failed'): void{ if(!$v){ throw new RuntimeException($why); } }
function near(float $a,float $b): void{ ok(abs($a-$b)<0.00001,"$a != $b"); }
function rejected(Closure $test): void{ try{ $test(); }catch(Exception $e){ return; }throw new Exception('Expected rejection'); }
$tests=[];
$tests['live personal values, totals and derived ratios before flush']=function(): void{
    $s=new Store(':memory:');$a=$s->register('a','Alice',100);$b=$s->register('b','Bob',100);$s->add($a,'kills',8);$s->add($a,'deaths',2);$s->add($b,'deaths',6);
    near(4,$a->get('kdr'));near(1,$s->global('kdr'));near(8,$s->global('kills'));ok($s->uniquePlayers()===2);$s->close();
};
$tests['records aggregate MAX while ordinary counters aggregate SUM']=function(): void{
    $s=new Store(':memory:');$a=$s->register('a','A',1);$b=$s->register('b','B',1);$s->set($a,'best_kill_streak',5);$s->set($b,'best_kill_streak',7);near(7,$s->global('best_kill_streak'));
    $s->set($a,'kill_streak',5);$s->set($b,'kill_streak',3);near(8,$s->global('kill_streak'));$s->set($a,'kill_streak',0);near(3,$s->global('kill_streak'));rejected(fn()=>$s->set($b,'best_kill_streak',6));$s->close();
};
$tests['disk reopen restores counters, names, first seen, dimensions and global maxima']=function(): void{
    $path=tempnam(sys_get_temp_dir(),'amyxstats');$s=new Store($path);$a=$s->register('a','Alice',100);$s->add($a,'block_break:diamond_ore',7);$s->set($a,'best_kill_streak',9);$s->register('a','ALICE2',200);$s->close();$s=new Store($path);
    ok($s->findName('alice2')?->uuid==='a');ok($s->findName('Alice')===null);ok($s->findUuid('a')->firstSeen===100);near(7,$s->global('block_break:diamond_ore'));near(9,$s->global('best_kill_streak'));ok($s->uniquePlayers()===1);$s->close();unlink($path);
};
$tests['SQLite transaction failure rolls back every player and retains dirty memory']=function(): void{
    $path=tempnam(sys_get_temp_dir(),'amyxstats');$s=new Store($path);$a=$s->register('a','A',1);$b=$s->register('b','B',1);$s->add($a,'kills',2);$s->add($b,'kills',3);$db=new SQLite3($path);$db->exec("CREATE TRIGGER fail BEFORE INSERT ON stats WHEN NEW.uuid='b' BEGIN SELECT RAISE(ABORT,'injected'); END");
    rejected(fn()=>$s->flush());ok((int)$db->querySingle('SELECT COUNT(*) FROM players')===0);near(5,$s->global('kills'));ok($a->dirty!==[] && $b->dirty!==[]);$db->exec('DROP TRIGGER fail');$db->close();$s->flush();$s->close();$s=new Store($path);near(5,$s->global('kills'));$s->close();unlink($path);
};
$tests['invalid metrics, negative numbers, non-finite values and injection are rejected']=function(): void{
    $s=new Store(':memory:');$a=$s->register('a',"O'Reilly",1);
    foreach(['oops','kills; DROP TABLE stats','block_break:../x\n','block_break:','block_break:'.str_repeat('x',121)] as $key){ rejected(fn()=>$s->add($a,$key)); }
    foreach([-1,NAN,INF,1e20] as $n){ rejected(fn()=>$s->set($a,'kills',$n)); }
    ok($s->findName("O'Reilly")?->uuid==='a');ok($s->findName("' OR 1=1--")===null);ok(Catalog::valid('item_pickup:custom:gem'));$s->close();
};
$tests['top includes unsaved changes, stable ties and reductions']=function(): void{
    $s=new Store(':memory:');$a=$s->register('a','A',1);$b=$s->register('b','B',1);$c=$s->register('c','C',1);$s->set($a,'kill_streak',10);$s->set($b,'kill_streak',8);$s->set($c,'kill_streak',8);$s->flush();
    ok($s->rank($a,'kill_streak')===1);ok($s->rank($b,'kill_streak')===2);$s->set($a,'kill_streak',0);ok($s->rank($b,'kill_streak')===1);ok($s->rank($c,'kill_streak')===1);ok($s->rank($a,'kill_streak')===3);ok(array_column($s->top('kill_streak',2),'name')===['B','C']);$s->close();
};
$tests['top overlay cannot hide offline leaders when cached players decrease']=function(): void{
    $path=tempnam(sys_get_temp_dir(),'amyxstats');$s=new Store($path);for($i=1;$i<=12;++$i){ $a=$s->register((string)$i,'P'.$i,1);$s->set($a,'kill_streak',100-$i);$s->release((string)$i); }
    for($i=1;$i<=8;++$i){ $a=$s->register((string)$i,'P'.$i,1);$s->set($a,'kill_streak',0); }
    ok(array_column($s->top('kill_streak',3),'uuid')===['9','10','11']);$s->close();unlink($path);
};
$tests['monotonic playtime ignores offline time and survives reconnect']=function(): void{
    $time=10.0;$s=new Store(':memory:');$service=new Service($s,function() use(&$time): float{ return $time; });$p=$service->enter('a','A',1000);$time=15.5;$service->pulse(1005);near(5.5,$p->get('playtime_seconds'));$service->leave('a',1005);$time=1000;$p=$service->enter('a','A',2000);$time=1003;$service->pulse(2003);near(8.5,$p->get('playtime_seconds'));near(5.5,$p->get('longest_session_seconds'));near(2,$p->get('joins'));$service->shutdown(2003);
};
$tests['session calls are idempotent and per-day counting handles UTC midnight']=function(): void{
    $time=1.0;$s=new Store(':memory:');$service=new Service($s,function() use(&$time): float{ return $time; });$now=strtotime('2026-10-21 23:59:59 UTC');$p=$service->enter('a','A',$now);$service->enter('a','A',$now);$time=3;$service->pulse($now+2);$service->pulse($now+2);near(1,$p->get('joins'));near(2,$p->get('days_played'));near(2,$p->get('playtime_seconds'));$service->shutdown($now+2);
};
$tests['kill streak resets on death while personal and global records remain']=function(): void{
    $s=new Store(':memory:');$service=new Service($s);$p=$service->enter('a','A',100);for($i=0;$i<4;++$i){ $service->streakKill('a'); }$service->death('a');near(0,$p->get('kill_streak'));near(4,$p->get('best_kill_streak'));near(4,$s->global('best_kill_streak'));near(1,$p->get('deaths'));$service->shutdown(100);
};
$tests['every advertised placeholder resolves for a registered player']=function(): void{
    $s=new Store(':memory:');$service=new Service($s);$p=$service->enter('a','A',100);$r=new Resolver($service,static fn(string $key): float=>20.0);
    foreach(Resolver::tags() as $tag){ ok($r->resolve($tag,$p)!==null,'Missing '.$tag); }ok(count(array_unique(Resolver::tags()))===count(Resolver::tags()));ok(count(Resolver::tags())===531);$service->shutdown(100);
};
$tests['percent, brace and ScoreHud forms have identical resolution and preserve unknown tags']=function(): void{
    $s=new Store(':memory:');$service=new Service($s);$p=$service->enter('a','Алекс',100);$service->add('a','blocks_broken',1200);$service->add('a','block_break:stone',23);$r=new Resolver($service,static fn()=>0);
    ok($r->replace('{amyxstats.my.blocks_broken} %amyxstats_my_blocks_broken% {amyxstats_my_blocks_broken}',$p)==='1200 1200 1200');ok($r->resolve('amyxstats.global.blocks_broken_formatted')==='1 200');ok($r->resolve('amyxstats.my.blocks_broken_compact',$p)==='1.2K');ok($r->resolve('amyxstats.my.block_break:stone',$p)==='23');ok($r->resolve('amyxstats.my.kills_rank',$p)==='1');ok($r->replace('x {amyxstats.my.nope} y',$p)==='x {amyxstats.my.nope} y');ok($r->resolve('amyxstats.my.kills')===null);$service->shutdown(100);
};
$tests['duration formatting handles days, zero and negative values']=function(): void{ ok(Resolver::duration(90061)==='1 д 1 ч 1 мин 1 с');ok(Resolver::duration(-10)==='0 ч 0 мин 0 с'); };
$tests['unseen metrics read as zero without growing stored statistics']=function(): void{
    $s=new Store(':memory:');$a=$s->register('a','A',1);near(0,$a->get('block_break:unknown'));near(0,$s->global('block_break:unknown'));ok($a->values===[]);$s->close();
};
$tests['empty rankings and zero denominators are well-defined']=function(): void{
    $s=new Store(':memory:');ok($s->top('kills')===[]);near(0,$s->global('kdr'));near(0,$s->global('blocks_per_hour'));$s->close();
};
$failed=0;foreach($tests as $name=>$test){ try{ $test();echo 'PASS '.$name.PHP_EOL; }catch(Throwable $e){ ++$failed;fwrite(STDERR,'FAIL '.$name.': '.$e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL); } }echo (count($tests)-$failed).'/'.count($tests)." core tests passed\n";exit($failed ? 1 : 0);
