<?php
declare(strict_types=1);
namespace Amyx\Stats;

use Amyx\Stats\form\StatisticsMenu;
use Amyx\Stats\integration\ScoreHudBridge;
use Amyx\Stats\listener\StatisticsListener;
use Amyx\Stats\placeholder\PlaceholderResolver;
use Amyx\Stats\stat\Profile;
use Amyx\Stats\stat\StatisticsService;
use Amyx\Stats\storage\StatisticsStore;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

final class Main extends PluginBase{
    private StatisticsService $statistics;
    private StatisticsListener $collector;
    private PlaceholderResolver $resolver;
    private StatisticsMenu $menu;
    private ?ScoreHudBridge $scoreHud=null;
    private Config $messages;
    private Config $labels;
    private float $started;
    public function service(): StatisticsService{ return $this->statistics; }
    public function collector(): StatisticsListener{ return $this->collector; }
    protected function onEnable(): void{
        $this->started=hrtime(true)/1e9-max(0,microtime(true)-$this->getServer()->getStartTime());$this->saveDefaultConfig();$this->saveResource('messages.yml');$this->saveResource('labels.yml');
        $this->messages=new Config($this->getDataFolder().'messages.yml');$this->labels=new Config($this->getDataFolder().'labels.yml');
        $this->statistics=new StatisticsService(new StatisticsStore($this->getDataFolder().'statistics.sqlite'));
        $this->resolver=new PlaceholderResolver($this->statistics,fn(string $key): mixed=>match($key){
            'online_players'=>count($this->getServer()->getOnlinePlayers()),'max_players'=>$this->getServer()->getMaxPlayers(),
            'uptime_seconds'=>hrtime(true)/1e9-$this->started,'tps'=>$this->getServer()->getTicksPerSecond(),default=>null
        });
        $this->menu=new StatisticsMenu($this);$this->collector=new StatisticsListener($this);
        $pm=$this->getServer()->getPluginManager();$pm->registerEvents($this->collector,$this);
        $modern='pocketmine\\event\\player\\PlayerItemEnchantEvent';
        if(class_exists($modern)){ $pm->registerEvent($modern,function(Event $e): void{ $this->collector->nativeEnchant($e); },EventPriority::MONITOR,$this); }
        $pm->registerEvent(PluginEnableEvent::class,function(PluginEnableEvent $event): void{ if($event->getPlugin()->getName()==='ScoreHud'){ $this->setupScoreHud(); } },EventPriority::MONITOR,$this);
        $this->setupScoreHud();foreach($this->getServer()->getOnlinePlayers() as $p){ $this->registerPlayer($p,false); }
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn()=>$this->collector->flushPending()),1);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn()=>$this->statistics->pulse(time())),20);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
            try{ $this->statistics->store->flush(); }catch(\Throwable $e){ $this->getLogger()->error('Не удалось сохранить статистику; данные остаются в памяти для повторной попытки: '.$e->getMessage()); }
        }),20*max(1,min(300,(int)$this->getConfig()->get('save-interval-seconds',10))));
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{ $this->scoreHud?->refresh(); }),20*max(1,min(60,(int)$this->getConfig()->get('scorehud-update-seconds',2))));
        $this->getLogger()->info('Статистика включена. Плейсхолдеров: '.count(PlaceholderResolver::tags()));
    }
    protected function onDisable(): void{
        if(isset($this->statistics)){ $this->collector->flushPending();$this->statistics->shutdown(time()); }
    }
    private function setupScoreHud(): void{
        if($this->scoreHud!==null || !$this->getServer()->getPluginManager()->getPlugin('ScoreHud')?->isEnabled() || !class_exists('Ifera\\ScoreHud\\event\\TagsResolveEvent')){ return; }
        $this->scoreHud=new ScoreHudBridge($this);$this->getServer()->getPluginManager()->registerEvents($this->scoreHud,$this);
        $this->getLogger()->info('Плейсхолдеры ScoreHud подключены.');
    }
    public function registerPlayer(Player $p,bool $join=true): void{
        if(!$p->hasPermission('amyxstats.track')){ return; }
        $this->statistics->enter($p->getUniqueId()->toString(),$p->getName(),time(),$join);
    }
    public function forgetPlayer(Player $p): void{
        $uuid=$p->getUniqueId()->toString();if($this->statistics->online($uuid)){ $this->statistics->leave($uuid,time()); }$this->scoreHud?->forget($uuid);
    }
    public function canTrack(Player $p,bool $game=true): bool{
        if(!$p->hasPermission('amyxstats.track') || !$this->statistics->online($p->getUniqueId()->toString())){ return false; }
        if(in_array($p->getWorld()->getFolderName(),(array)$this->getConfig()->get('excluded-worlds',[]),true)){ return false; }
        return !$game || ((!$p->isCreative() || $this->getConfig()->get('track-creative',false)) && (!$p->isSpectator() || $this->getConfig()->get('track-spectator',false)));
    }
    public function profile(Player|string $player): ?Profile{
        if($player instanceof Player){ return $this->statistics->profile($player->getUniqueId()->toString()) ?? new Profile($player->getUniqueId()->toString(),$player->getName(),time(),time()); }
        return $this->statistics->store->findUuid($player) ?? $this->statistics->store->findName($player);
    }
    public function getStatistic(Player|string $player,string $key): float{ return $this->profile($player)?->get($key) ?? 0; }
    public function getStatistics(Player|string $player): array{ $p=$this->profile($player);if($p===null){ return []; }$out=[];foreach(array_keys(\Amyx\Stats\stat\MetricCatalog::METRICS+\Amyx\Stats\stat\MetricCatalog::DERIVED) as $key){ $out[$key]=$p->get($key); }return $out+$p->values; }
    public function getGlobalStatistic(string $key): float{ return $this->statistics->store->global($key); }
    public function getTop(string $key,int $limit=10): array{ return $this->statistics->store->top($key,$limit); }
    public function resolvePlaceholder(string $tag,Player|string|null $player=null): ?string{ return $this->resolver->resolve($tag,$player===null ? null : $this->profile($player)); }
    public function replacePlaceholders(string $text,Player|string|null $player=null): string{ return $this->resolver->replace($text,$player===null ? null : $this->profile($player)); }
    public function getPlaceholderNames(): array{ return PlaceholderResolver::tags(); }
    public function label(string $key): string{ return (string)$this->labels->get($key,\Amyx\Stats\stat\MetricCatalog::label($key)); }
    public function prefix(): string{ return TextFormat::colorize((string)$this->getConfig()->get('prefix','&bСтатистика &8» &r')); }
    public function message(string $key): string{ return $this->prefix().TextFormat::colorize((string)$this->messages->get($key,$key)); }
    public function onCommand(CommandSender $sender,Command $command,string $label,array $args): bool{
        if(!$sender->hasPermission('amyxstats.command')){ $sender->sendMessage($this->message('denied'));return true; }
        $first=strtolower($args[0] ?? '');
        if($first==='help' || $first==='placeholders'){ $sender->sendMessage($this->message($first));return true; }
        if($first==='top'){ $this->menu->top($sender,$args[1] ?? 'kills');return true; }
        if($first==='global' || $first==='server' || (!$sender instanceof Player && $first==='')){ $this->menu->show($sender,null);return true; }
        $target=$first==='' || $first==='chat' ? ($sender instanceof Player ? $this->profile($sender) : null) : $this->profile($args[0]);
        if($target===null){ $sender->sendMessage($this->message('unknown-player'));return true; }
        $this->menu->show($sender,$target,$first==='chat');return true;
    }
}
