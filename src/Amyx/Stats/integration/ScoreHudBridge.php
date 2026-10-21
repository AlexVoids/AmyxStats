<?php
declare(strict_types=1);
namespace Amyx\Stats\integration;

use Amyx\Stats\Main;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\event\PlayerTagsUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\event\Listener;
use pocketmine\player\Player;

/** Optional official ScoreHud event API; no bundled copy of ScoreHud. */
final class ScoreHudBridge implements Listener{
    private array $used=[];
    public function __construct(private Main $plugin){}
    public function onResolve(TagsResolveEvent $event): void{
        $p=$event->getPlayer();$name=$event->getTag()->getName();$value=$this->plugin->resolvePlaceholder($name,$p);
        if($value!==null){ $event->getTag()->setValue($value);$this->used[$p->getUniqueId()->toString()][$name]=$value; }
    }
    public function refresh(): void{
        if(!$this->plugin->getServer()->getPluginManager()->getPlugin('ScoreHud')?->isEnabled()){ return; }
        foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
            $this->refreshPlayer($p);
        }
    }
    public function refreshPlayer(Player $p): void{
        $uuid=$p->getUniqueId()->toString();$tags=[];
        foreach($this->used[$uuid] ?? [] as $name=>$last){
            $value=$this->plugin->resolvePlaceholder($name,$p);
            if($value!==null && $value!==$last){ $tags[]=new ScoreTag($name,$value);$this->used[$uuid][$name]=$value; }
        }
        if($tags!==[]){ (new PlayerTagsUpdateEvent($p,$tags))->call(); }
    }
    public function forget(string $uuid): void{ unset($this->used[$uuid]); }
}
