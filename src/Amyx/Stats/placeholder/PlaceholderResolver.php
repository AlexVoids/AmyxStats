<?php
declare(strict_types=1);
namespace Amyx\Stats\placeholder;

use Amyx\Stats\stat\MetricCatalog;
use Amyx\Stats\stat\Profile;
use Amyx\Stats\stat\StatisticsService;

final class PlaceholderResolver{
    public function __construct(private StatisticsService $stats,private \Closure $serverValue){}
    public static function number(float $value,int $decimals=0,bool $group=false): string{
        return number_format($value,$decimals,'.',$group ? ' ' : '');
    }
    public static function duration(float $seconds): string{
        $seconds=max(0,(int)$seconds);$days=intdiv($seconds,86400);$hours=intdiv($seconds%86400,3600);$minutes=intdiv($seconds%3600,60);$sec=$seconds%60;
        return ($days>0 ? $days.' д ' : '').$hours.' ч '.$minutes.' мин '.$sec.' с';
    }
    public function resolve(string $tag,?Profile $player=null): ?string{
        $tag=trim($tag,'{}%');
        if(preg_match('/^amyxstats_(my|global)_(.+)$/D',$tag,$match)===1){ $tag='amyxstats.'.$match[1].'.'.$match[2]; }
        if(preg_match('/^amyxstats\.(my|global)\.(.+)$/D',$tag,$match)!==1){ return null; }
        [, $scope,$key]=$match;
        if($scope==='my'){
            if($player===null){ return null; }
            $metadata=match($key){
                'name'=>$player->name,'uuid'=>$player->uuid,
                'first_seen'=>gmdate('d.m.Y H:i:s',$player->firstSeen),
                'last_seen'=>gmdate('d.m.Y H:i:s',$player->lastSeen),
                'online'=>$this->stats->online($player->uuid) ? '1' : '0',
                'session_seconds'=>(string)(int)$this->stats->sessionSeconds($player->uuid),
                'session_formatted'=>self::duration($this->stats->sessionSeconds($player->uuid)),
                'playtime_formatted'=>self::duration($player->get('playtime_seconds')),
                default=>null
            };
            if($metadata!==null){ return $metadata; }
        }else{
            if($key==='unique_players'){ return (string)$this->stats->store->uniquePlayers(); }
            if($key==='playtime_formatted'){ return self::duration($this->stats->store->global('playtime_seconds')); }
            if($key==='uptime_formatted'){ return self::duration((float)($this->serverValue)('uptime_seconds')); }
            if(in_array($key,['online_players','max_players','uptime_seconds','tps'],true)){
                $value=($this->serverValue)($key);return $value===null ? null : (is_numeric($value) ? self::number((float)$value,$key==='tps' ? 2 : 0) : (string)$value);
            }
        }
        if($scope==='my' && str_ends_with($key,'_rank')){
            $metric=substr($key,0,-5);return MetricCatalog::valid($metric) ? (string)$this->stats->store->rank($player,$metric) : null;
        }
        $style='raw';
        foreach(['formatted','compact'] as $suffix){ if(str_ends_with($key,'_'.$suffix)){ $key=substr($key,0,-strlen($suffix)-1);$style=$suffix;break; } }
        if(!MetricCatalog::valid($key) && !isset(MetricCatalog::DERIVED[$key])){ return null; }
        $value=$scope==='my' ? $player->get($key) : $this->stats->store->global($key);
        if($style==='compact'){
            foreach([1e12=>'T',1e9=>'B',1e6=>'M',1e3=>'K'] as $div=>$suffix){ if($value>=$div){ return rtrim(rtrim(self::number($value/$div,2),'0'),'.').$suffix; } }
        }
        return self::number($value,MetricCatalog::decimals($key),$style==='formatted');
    }
    public function replace(string $text,?Profile $player=null): string{
        return preg_replace_callback('/\{(amyxstats[._][a-z0-9_.:\/-]+)\}|%(amyxstats[._][a-z0-9_.:\/-]+)%/',fn(array $m): string=>$this->resolve($m[1]!=='' ? $m[1] : $m[2],$player) ?? $m[0],$text) ?? $text;
    }
    /** Canonical finite catalog; per-block/item/entity expansions are documented separately. */
    public static function tags(): array{
        $tags=[];
        foreach(['my','global'] as $scope){
            foreach(array_keys(MetricCatalog::METRICS+MetricCatalog::DERIVED) as $key){
                foreach(['','_formatted','_compact'] as $suffix){ $tags[]='amyxstats.'.$scope.'.'.$key.$suffix; }
                if($scope==='my' && isset(MetricCatalog::METRICS[$key])){ $tags[]='amyxstats.my.'.$key.'_rank'; }
            }
        }
        foreach(['name','uuid','first_seen','last_seen','online','session_seconds','session_formatted','playtime_formatted'] as $key){ $tags[]='amyxstats.my.'.$key; }
        foreach(['unique_players','online_players','max_players','uptime_seconds','uptime_formatted','playtime_formatted','tps'] as $key){ $tags[]='amyxstats.global.'.$key; }
        return $tags;
    }
}
