<?php
declare(strict_types=1);
namespace Amyx\Stats\stat;

use Amyx\Stats\storage\StatisticsStore;

final class StatisticsService{
    private array $sessions=[];
    private \Closure $clock;
    public function __construct(public readonly StatisticsStore $store,?\Closure $clock=null){ $this->clock=$clock ?? static fn(): float=>hrtime(true)/1e9; }
    public function enter(string $uuid,string $name,int $now,bool $countJoin=true): Profile{
        if(isset($this->sessions[$uuid])){ return $this->store->register($uuid,$name,$now); }
        $p=$this->store->register($uuid,$name,$now);$time=($this->clock)();$this->sessions[$uuid]=['start'=>$time,'last'=>$time,'day'=>gmdate('Y-m-d',$now)];
        if($countJoin){ $this->store->add($p,'joins'); }
        $this->store->markDay($p,gmdate('Y-m-d',$now));return $p;
    }
    public function pulse(int $now): void{
        $time=($this->clock)();
        foreach($this->sessions as $uuid=>&$s){
            $p=$this->store->findUuid($uuid);$elapsed=max(0,$time-$s['last']);$s['last']=$time;
            $this->store->add($p,'playtime_seconds',$elapsed);
            $this->store->set($p,'longest_session_seconds',max($p->get('longest_session_seconds'),$time-$s['start']));
            $p->lastSeen=$now;$p->metadataDirty=true;
            $day=gmdate('Y-m-d',$now);if($day!==$s['day']){ $this->store->markDay($p,$day);$s['day']=$day; }
        }unset($s);
    }
    public function leave(string $uuid,int $now): void{ $this->pulse($now);unset($this->sessions[$uuid]);$this->store->release($uuid); }
    public function profile(string $uuid): ?Profile{ return $this->store->findUuid($uuid); }
    public function online(string $uuid): bool{ return isset($this->sessions[$uuid]); }
    public function sessionSeconds(string $uuid): float{ return isset($this->sessions[$uuid]) ? max(0,($this->clock)()-$this->sessions[$uuid]['start']) : 0; }
    public function add(string $uuid,string $metric,float $amount=1): void{ if($this->online($uuid) && ($p=$this->profile($uuid))!==null){ $this->store->add($p,$metric,$amount); } }
    public function streakKill(string $uuid): void{
        $p=$this->profile($uuid);if(!$this->online($uuid) || $p===null){ return; }
        $this->store->add($p,'kill_streak');$this->store->set($p,'best_kill_streak',max($p->get('best_kill_streak'),$p->get('kill_streak')));
    }
    public function death(string $uuid): void{ $p=$this->profile($uuid);if($this->online($uuid) && $p!==null){ $this->store->add($p,'deaths');$this->store->set($p,'kill_streak',0); } }
    public function shutdown(int $now): void{ $this->pulse($now);$this->sessions=[];$this->store->close(); }
}
