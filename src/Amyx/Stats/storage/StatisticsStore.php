<?php
declare(strict_types=1);
namespace Amyx\Stats\storage;

use Amyx\Stats\stat\MetricCatalog;
use Amyx\Stats\stat\Profile;

/** Online profiles are cached. SQLite holds all offline profiles; writes are atomic batches. */
final class StatisticsStore{
    private \SQLite3 $db;
    /** @var Profile[] */private array $loaded=[];
    private array $totals=[];
    private int $unique=0;
    private array $topCache=[];
    public function __construct(string $path){
        $this->db=new \SQLite3($path);$this->db->enableExceptions(true);$this->db->busyTimeout(5000);
        $this->db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA synchronous=NORMAL;');
        $version=(int)$this->db->querySingle('PRAGMA user_version');
        if($version>1){ throw new \RuntimeException('Statistics database is newer than this plugin'); }
        $this->db->exec('CREATE TABLE IF NOT EXISTS players(uuid TEXT PRIMARY KEY,name TEXT NOT NULL,name_key TEXT NOT NULL,first_seen INTEGER NOT NULL,last_seen INTEGER NOT NULL); CREATE INDEX IF NOT EXISTS players_name ON players(name_key); CREATE TABLE IF NOT EXISTS stats(uuid TEXT NOT NULL REFERENCES players(uuid) ON DELETE CASCADE,metric TEXT NOT NULL,value REAL NOT NULL CHECK(value>=0),PRIMARY KEY(uuid,metric)); CREATE INDEX IF NOT EXISTS stats_top ON stats(metric,value DESC); CREATE TABLE IF NOT EXISTS play_days(uuid TEXT NOT NULL REFERENCES players(uuid) ON DELETE CASCADE,day TEXT NOT NULL,PRIMARY KEY(uuid,day)); PRAGMA user_version=1;');
        $rows=$this->query('SELECT metric,SUM(value) AS total,MAX(value) AS best FROM stats GROUP BY metric');
        foreach($rows as $row){ $this->totals[$row['metric']]=(float)$row[MetricCatalog::maximum($row['metric']) ? 'best' : 'total']; }
        $this->unique=(int)$this->db->querySingle('SELECT COUNT(*) FROM players');
    }
    private function statement(string $sql,array $args): \SQLite3Stmt{
        $s=$this->db->prepare($sql);
        foreach(array_values($args) as $i=>$v){ $s->bindValue($i+1,$v,is_int($v) ? SQLITE3_INTEGER : (is_float($v) ? SQLITE3_FLOAT : SQLITE3_TEXT)); }
        return $s;
    }
    private function query(string $sql,array $args=[]): array{
        $s=$this->statement($sql,$args);$r=$s->execute();$rows=[];while($row=$r->fetchArray(SQLITE3_ASSOC)){ $rows[]=$row; }$r->finalize();$s->close();return $rows;
    }
    private function execute(string $sql,array $args=[]): void{ $s=$this->statement($sql,$args);$r=$s->execute();if($r instanceof \SQLite3Result){ $r->finalize(); }$s->close(); }
    public function register(string $uuid,string $name,int $now): Profile{
        if(isset($this->loaded[$uuid])){ $p=$this->loaded[$uuid]; }
        else{
            $p=$this->findUuid($uuid);
            if($p===null){ $p=new Profile($uuid,$name,$now,$now);++$this->unique; }
            $this->loaded[$uuid]=$p;
        }
        $p->name=$name;$p->lastSeen=$now;$p->metadataDirty=true;$this->topCache=[];return $p;
    }
    public function findUuid(string $uuid): ?Profile{
        if(isset($this->loaded[$uuid])){ return $this->loaded[$uuid]; }
        $row=$this->query('SELECT * FROM players WHERE uuid=?',[$uuid])[0] ?? null;
        if($row===null){ return null; }
        $values=[];foreach($this->query('SELECT metric,value FROM stats WHERE uuid=?',[$uuid]) as $s){ $values[$s['metric']]=(float)$s['value']; }
        return new Profile($uuid,$row['name'],(int)$row['first_seen'],(int)$row['last_seen'],$values);
    }
    public function findName(string $name): ?Profile{
        $key=mb_strtolower($name,'UTF-8');
        foreach($this->loaded as $p){ if(mb_strtolower($p->name,'UTF-8')===$key){ return $p; } }
        $row=$this->query('SELECT uuid FROM players WHERE name_key=? ORDER BY last_seen DESC,uuid LIMIT 1',[$key])[0] ?? null;
        return $row===null ? null : $this->findUuid($row['uuid']);
    }
    public function set(Profile $p,string $metric,float $value): void{
        if(!MetricCatalog::valid($metric) || !is_finite($value) || $value<0 || $value>9e15){ throw new \InvalidArgumentException('Invalid statistic or value'); }
        if(($this->loaded[$p->uuid] ?? null)!==$p){ throw new \LogicException('Only registered profiles can be changed'); }
        $old=$p->get($metric);if(abs($old-$value)<1e-9){ return; }
        if(MetricCatalog::maximum($metric) && $value<$old){ throw new \InvalidArgumentException('Records cannot decrease'); }
        $p->values[$metric]=$value;$p->dirty[$metric]=true;
        $this->totals[$metric]=MetricCatalog::maximum($metric) ? max($this->totals[$metric] ?? 0,$value) : max(0,($this->totals[$metric] ?? 0)+$value-$old);
        $this->topCache=[];
    }
    public function add(Profile $p,string $metric,float $delta=1): void{
        if(!is_finite($delta) || $delta<0){ throw new \InvalidArgumentException('Invalid increment'); }
        $this->set($p,$metric,$p->get($metric)+$delta);
    }
    public function markDay(Profile $p,string $day): void{
        if(isset($p->newDays[$day]) || $this->query('SELECT day FROM play_days WHERE uuid=? AND day=?',[$p->uuid,$day])!==[]){ return; }
        $p->newDays[$day]=true;$this->add($p,'days_played');
    }
    public function global(string $metric): float{
        return MetricCatalog::derived($metric,fn(string $k): float=>$this->global($k)) ?? (float)($this->totals[$metric] ?? 0);
    }
    public function uniquePlayers(): int{ return $this->unique; }
    public function flush(): void{
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            foreach($this->loaded as $p){
                if($p->metadataDirty || $p->dirty!==[] || $p->newDays!==[]){
                    $this->execute('INSERT INTO players VALUES(?,?,?,?,?) ON CONFLICT(uuid) DO UPDATE SET name=excluded.name,name_key=excluded.name_key,last_seen=excluded.last_seen',[$p->uuid,$p->name,mb_strtolower($p->name,'UTF-8'),$p->firstSeen,$p->lastSeen]);
                    foreach($p->dirty as $key=>$_){ $this->execute('INSERT INTO stats VALUES(?,?,?) ON CONFLICT(uuid,metric) DO UPDATE SET value=excluded.value',[$p->uuid,$key,$p->values[$key]]); }
                    foreach($p->newDays as $day=>$_){ $this->execute('INSERT OR IGNORE INTO play_days VALUES(?,?)',[$p->uuid,$day]); }
                }
            }
            $this->db->exec('COMMIT');
            foreach($this->loaded as $p){ $p->dirty=[];$p->newDays=[];$p->metadataDirty=false;$p->persisted=$p->values; }
        }catch(\Throwable $e){ $this->db->exec('ROLLBACK');throw $e; }
    }
    public function release(string $uuid): void{ $this->flush();unset($this->loaded[$uuid]); }
    public function top(string $metric,int $limit=10): array{
        if(!MetricCatalog::valid($metric)){ throw new \InvalidArgumentException('Only stored statistics can be ranked'); }
        $limit=max(1,min(50,$limit));$key=$metric.':'.$limit;
        if(isset($this->topCache[$key])){ return $this->topCache[$key]; }
        $rows=$this->query('SELECT p.uuid,p.name,COALESCE(s.value,0) AS value FROM players p LEFT JOIN stats s ON s.uuid=p.uuid AND s.metric=? ORDER BY value DESC,p.name COLLATE NOCASE,p.uuid LIMIT ?',[$metric,$limit+count($this->loaded)]);
        $all=[];foreach($rows as $r){ $all[$r['uuid']]=['uuid'=>$r['uuid'],'name'=>$r['name'],'value'=>(float)$r['value']]; }
        foreach($this->loaded as $p){ $all[$p->uuid]=['uuid'=>$p->uuid,'name'=>$p->name,'value'=>$p->get($metric)]; }
        usort($all,static fn(array $a,array $b): int=>($b['value']<=>$a['value']) ?: (strcasecmp($a['name'],$b['name']) ?: strcmp($a['uuid'],$b['uuid'])));
        return $this->topCache[$key]=array_slice($all,0,$limit);
    }
    public function rank(Profile $p,string $metric): int{
        if(!MetricCatalog::valid($metric)){ throw new \InvalidArgumentException('Invalid ranked metric'); }
        $value=$p->get($metric);$row=$this->query('SELECT COUNT(*) AS n FROM stats WHERE metric=? AND value>?',[$metric,$value])[0];$count=(int)$row['n'];
        foreach($this->loaded as $other){
            $saved=$other->persisted[$metric] ?? 0;
            $count+=(int)($other->get($metric)>$value)-(int)((float)$saved>$value);
        }
        return $count+1;
    }
    public function close(): void{ $this->flush();$this->db->close(); }
}
