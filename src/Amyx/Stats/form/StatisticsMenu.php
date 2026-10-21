<?php
declare(strict_types=1);
namespace Amyx\Stats\form;

use Amyx\Stats\Main;
use Amyx\Stats\stat\MetricCatalog;
use Amyx\Stats\stat\Profile;
use Amyx\Stats\placeholder\PlaceholderResolver as Format;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class StatisticsMenu{
    public function __construct(private Main $plugin){}
    private function allowed(CommandSender $viewer,?Profile $target): bool{
        if(!$viewer->hasPermission('amyxstats.command')){ return false; }
        return $target===null ? $viewer->hasPermission('amyxstats.global') : (!$viewer instanceof Player || $target->uuid===$viewer->getUniqueId()->toString() || $viewer->hasPermission('amyxstats.others'));
    }
    private function value(?Profile $profile,string $key): float{ return $profile?->get($key) ?? $this->plugin->service()->store->global($key); }
    private function line(?Profile $profile,string $key): string{ return '§f'.$this->plugin->label($key).': §a'.Format::number($this->value($profile,$key),MetricCatalog::decimals($key),true).'§r'; }
    private function summary(?Profile $target): string{
        $lines=['§f'.($target===null ? 'Общая статистика сервера' : 'Игрок: §b'.$target->name),''];
        if($target===null){ $lines[]='§fУникальных игроков: §a'.$this->plugin->service()->store->uniquePlayers(); }
        foreach(['blocks_broken','blocks_placed','kills','deaths','mob_kills','kdr'] as $key){ $lines[]=$this->line($target,$key); }
        $lines[]='§fВремя игры: §a'.Format::duration($this->value($target,'playtime_seconds'));
        return implode("\n",$lines);
    }
    public function show(CommandSender $viewer,?Profile $target,bool $chat=false): void{
        if(!$this->allowed($viewer,$target)){ $viewer->sendMessage($this->plugin->message('denied'));return; }
        if(!$viewer instanceof Player || $chat || !$this->plugin->getConfig()->get('forms',true)){
            $viewer->sendMessage($this->plugin->prefix().$this->summary($target));
            foreach(MetricCatalog::CATEGORIES as $category=>$title){
                $viewer->sendMessage('§b'.$title);
                foreach(array_keys(MetricCatalog::METRICS+MetricCatalog::DERIVED) as $key){ if(MetricCatalog::category($key)===$category){ $viewer->sendMessage($this->line($target,$key)); } }
            }return;
        }
        $uuid=$target?->uuid;$categories=array_keys(MetricCatalog::CATEGORIES);$buttons=[];
        foreach(MetricCatalog::CATEGORIES as $name){ $buttons[]='§b'.$name; }
        $buttons[]='§6Топ игроков';$buttons[]=$target===null ? '§aМоя статистика' : '§eСтатистика сервера';
        $viewer->sendForm(new StatisticsForm($viewer->getUniqueId()->toString(),'§b§lСтатистика',$this->summary($target),$buttons,function(Player $p,int $index) use($uuid,$categories): void{
            $target=$uuid===null ? null : $this->plugin->service()->store->findUuid($uuid);
            if($uuid!==null && $target===null){ return; }
            if($index<count($categories)){ $this->category($p,$target,$categories[$index]); }
            elseif($index===count($categories)){ $this->top($p,'kills'); }
            else{ $this->show($p,$uuid===null ? $this->plugin->profile($p) : null); }
        }));
    }
    private function category(Player $viewer,?Profile $profile,string $category): void{
        if(!$this->allowed($viewer,$profile)){ $viewer->sendMessage($this->plugin->message('denied'));return; }
        $lines=['§f'.($profile?->name ?? 'Весь сервер'),''];
        foreach(array_keys(MetricCatalog::METRICS+MetricCatalog::DERIVED) as $key){ if(MetricCatalog::category($key)===$category){ $lines[]=$this->line($profile,$key); } }
        $uuid=$profile?->uuid;
        $viewer->sendForm(new StatisticsForm($viewer->getUniqueId()->toString(),'§b'.MetricCatalog::CATEGORIES[$category],implode("\n",$lines),['§aОбновить','§7Назад'],function(Player $p,int $index) use($uuid,$category): void{
            $profile=$uuid===null ? null : $this->plugin->service()->store->findUuid($uuid);if($uuid!==null && $profile===null){ return; }
            $index===0 ? $this->category($p,$profile,$category) : $this->show($p,$profile);
        }));
    }
    public function top(CommandSender $viewer,string $metric): void{
        if(!$viewer->hasPermission('amyxstats.top')){ $viewer->sendMessage($this->plugin->message('denied'));return; }
        if(!MetricCatalog::valid($metric)){ $viewer->sendMessage($this->plugin->message('unknown-metric'));return; }
        $rows=$this->plugin->getTop($metric,(int)$this->plugin->getConfig()->get('top-size',10));$lines=[];
        foreach($rows as $i=>$row){ $lines[]='§e'.($i+1).'. §f'.$row['name'].' §8— §a'.Format::number($row['value'],MetricCatalog::decimals($metric),true); }
        if($lines===[]){ $lines[]=$this->plugin->message('empty'); }
        if(!$viewer instanceof Player || !$this->plugin->getConfig()->get('forms',true)){ $viewer->sendMessage($this->plugin->prefix().'Топ: '.$this->plugin->label($metric)."\n".implode("\n",$lines));return; }
        $keys=['kills','blocks_broken','blocks_placed','playtime_seconds','mob_kills','best_kill_streak'];$buttons=array_map(fn(string $key): string=>'§b'.$this->plugin->label($key),$keys);$buttons[]='§7Моя статистика';
        $viewer->sendForm(new StatisticsForm($viewer->getUniqueId()->toString(),'§6Топ: '.$this->plugin->label($metric),implode("\n",$lines),$buttons,function(Player $p,int $index) use($keys): void{
            if(isset($keys[$index])){ $this->top($p,$keys[$index]); }else{ $this->show($p,$this->plugin->profile($p)); }
        }));
    }
}
