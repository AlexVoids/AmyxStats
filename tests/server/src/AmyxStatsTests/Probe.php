<?php
declare(strict_types=1);
namespace AmyxStatsTests;

use Amyx\Stats\Main;
use pocketmine\block\VanillaBlocks as B;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSprintEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\VanillaItems as I;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\BlockTransaction;
use pocketmine\world\Position;

final class Probe extends PluginBase{
    private int $passed=0;
    private array $failures=[];
    private Main $stats;
    protected function onEnable(): void{
        if(getenv('AMYXSTATS_RUN_TESTS')!=='1'){ $this->getServer()->getPluginManager()->disablePlugin($this);return; }
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn()=>$this->run()),30);
    }
    private static function ok(bool $v,string $why='Assertion failed'): void{ if(!$v){ throw new \RuntimeException($why); } }
    private static function near(float $a,float $b): void{ self::ok(abs($a-$b)<0.0001,"$a != $b"); }
    private function test(string $name,\Closure $fn): void{
        try{ $fn();++$this->passed;$this->getLogger()->info('PASS '.$name); }catch(\Throwable $e){ $this->failures[$name]=$e->getMessage();$this->getLogger()->logException($e); }
    }
    private function drain(): void{ $this->stats->collector()->flushPending(); }
    private function value(SyntheticPlayer $p,string $key): float{ return $this->stats->getStatistic($p,$key); }
    private function run(): void{
        $this->stats=$this->getServer()->getPluginManager()->getPlugin('AmyxStats');$path=$this->getServer()->getDataPath();
        if(is_file($path.'restart-manifest.json')){
            $this->test('real process restart restores profiles, counters, dimensions and totals',function() use($path): void{
                $m=json_decode(file_get_contents($path.'restart-manifest.json'),true,512,JSON_THROW_ON_ERROR);
                self::near($m['broken'],$this->stats->getStatistic('Alice','blocks_broken'));self::near($m['global'],$this->stats->getGlobalStatistic('blocks_broken'));self::near($m['ore'],$this->stats->getStatistic('Alice','block_break:diamond_ore'));self::near($m['kills'],$this->stats->getStatistic('Alice','kills'));self::ok($this->stats->resolvePlaceholder('amyxstats.my.online','Alice')==='0');self::ok($this->stats->getTop('blocks_broken')[0]['name']===$m['top']);
            });
            file_put_contents($path.'amyxstats-restart-results.json',json_encode(['passed'=>$this->passed,'failures'=>$this->failures]));$this->getServer()->shutdown();return;
        }
        try{
            $world=$this->getServer()->getWorldManager()->getDefaultWorld();$world->setChunk(0,0,new \pocketmine\world\format\Chunk([],true));
            $a=new SyntheticPlayer('Alice',new Position(4,100,4,$world));$b=new SyntheticPlayer('Bob',new Position(6,100,4,$world));(new PlayerJoinEvent($a,'test'))->call();(new PlayerJoinEvent($b,'test'))->call();
            $this->test('plugin loads without dependencies and advertises all placeholders',function() use($a): void{ self::ok($this->stats->isEnabled());self::ok(count($this->stats->getPlaceholderNames())===531);self::near(1,$this->value($a,'joins')); });
            $this->test('all three commands resolve and open the Russian statistics form',function() use($a): void{
                foreach(['stat','stats','mystat'] as $name){ $cmd=$this->getServer()->getCommandMap()->getCommand($name);self::ok($cmd!==null);$cmd->execute($a,$name,[]);self::ok($a->form!==null);$body=$a->form->jsonSerialize();self::ok(count($body['buttons'])===9);self::ok(str_contains($body['content'],'Alice')); }
            });
            $this->test('category form refresh and back navigation work',function() use($a): void{ $a->form->handleResponse($a,0);$body=$a->form->jsonSerialize();self::ok(str_contains($body['content'],'Сломано блоков'));$a->form->handleResponse($a,0);$a->form->handleResponse($a,1);self::ok(count($a->form->jsonSerialize()['buttons'])===9); });
            $this->test('form rejects foreign player and malformed button selections',function() use($a,$b): void{ $form=$a->form;$old=$b->form;$form->handleResponse($b,0);self::ok($b->form===$old);foreach([null,true,-1,99,'0',0.5] as $bad){ $form->handleResponse($a,$bad);self::ok($a->form===$form); } });
            $this->test('successful block break is counted only after world change',function() use($a,$world): void{
                $world->setBlockAt(4,100,0,B::DIAMOND_ORE());$e=new BlockBreakEvent($a,$world->getBlockAt(4,100,0),I::DIAMOND_PICKAXE());$e->call();self::near(0,$this->value($a,'blocks_broken'));$world->setBlockAt(4,100,0,B::AIR());$this->drain();self::near(1,$this->value($a,'blocks_broken'));self::near(1,$this->value($a,'ores_mined'));self::near(1,$this->value($a,'block_break:diamond_ore'));
            });
            $this->test('cancelled or failed block breaks never count',function() use($a,$world): void{
                $world->setBlockAt(4,100,0,B::STONE());$e=new BlockBreakEvent($a,$world->getBlockAt(4,100,0),I::DIAMOND_PICKAXE());$e->call();$this->drain();self::near(1,$this->value($a,'blocks_broken'));
                $e=new BlockBreakEvent($a,$world->getBlockAt(4,100,0),I::DIAMOND_PICKAXE());$e->call();$e->cancel();$world->setBlockAt(4,100,0,B::AIR());$this->drain();self::near(1,$this->value($a,'blocks_broken'));
            });
            $this->test('multi-block placement counts each confirmed physical block',function() use($a,$world): void{
                $tx=new BlockTransaction($world);$tx->addBlockAt(7,100,0,B::STONE());$tx->addBlockAt(7,101,0,B::STONE());$e=new BlockPlaceEvent($a,$tx,$world->getBlockAt(7,99,0),B::STONE()->asItem());$e->call();$tx->apply();$this->drain();self::near(2,$this->value($a,'blocks_placed'));self::near(2,$this->value($a,'block_place:stone'));
            });
            $this->test('creative and excluded worlds are filtered',function() use($a,$world): void{
                $before=$this->value($a,'blocks_broken');$a->creative=true;$world->setBlockAt(4,100,0,B::STONE());$e=new BlockBreakEvent($a,$world->getBlockAt(4,100,0),I::DIAMOND_PICKAXE());$e->call();$world->setBlockAt(4,100,0,B::AIR());$this->drain();$a->creative=false;self::near($before,$this->value($a,'blocks_broken'));
                $this->stats->getConfig()->set('excluded-worlds',[$world->getFolderName()]);(new PlayerJumpEvent($a))->call();self::near(0,$this->value($a,'jumps'));$this->stats->getConfig()->set('excluded-worlds',[]);
            });
            $this->test('walk distance counts three-dimensional movement but not large jumps',function() use($a,$world): void{
                $from=new Location(4,100,4,$world,0,0);$to=new Location(7,104,4,$world,0,0);(new PlayerMoveEvent($a,$from,$to))->call();$this->drain();self::near(5,$this->value($a,'distance_travelled'));self::near(5,$this->value($a,'distance_walked'));
                (new PlayerMoveEvent($a,$from,new Location(200,100,4,$world,0,0)))->call();$this->drain();self::near(5,$this->value($a,'distance_travelled'));
            });
            $this->test('cancelled movement and angle-only changes do not count',function() use($a,$world): void{
                $from=new Location(4,100,4,$world,0,0);$e=new PlayerMoveEvent($a,$from,new Location(5,100,4,$world,0,0));$e->call();$e->cancel();$this->drain();(new PlayerMoveEvent($a,$from,new Location(4,100,4,$world,90,0)))->call();$this->drain();self::near(5,$this->value($a,'distance_travelled'));
            });
            $this->test('teleports are recorded separately from walked distance',function() use($a,$world): void{ (new EntityTeleportEvent($a,new Position(4,100,4,$world),new Position(104,100,4,$world)))->call();$this->drain();self::near(1,$this->value($a,'teleports'));self::near(100,$this->value($a,'distance_teleported'));self::near(5,$this->value($a,'distance_travelled')); });
            $this->test('jump, sprint and sneak counters reject cancelled toggles',function() use($a): void{
                (new PlayerJumpEvent($a))->call();(new PlayerToggleSprintEvent($a,true))->call();(new PlayerToggleSneakEvent($a,true,true))->call();$e=new PlayerToggleSprintEvent($a,true);$e->call();$e->cancel();$this->drain();self::near(1,$this->value($a,'jumps'));self::near(1,$this->value($a,'sprint_starts'));self::near(1,$this->value($a,'sneak_starts'));
            });
            $this->test('PvP damage is clamped to available health and cancelled attacks are ignored',function() use($a,$b): void{
                $e=new FightDamage($a,$b,100);$e->call();$this->drain();self::near(20,$this->value($a,'damage_pvp_dealt'));self::near(20,$this->value($b,'damage_pvp_taken'));$e=new FightDamage($a,$b,2);$e->call();$e->cancel();$this->drain();self::near(20,$this->value($a,'damage_dealt'));self::near(1,$this->value($a,'hits_dealt'));
            });
            $this->test('player deaths credit killer, cause and streak once',function() use($a,$b): void{
                $b->testCause=new FightDamage($a,$b,20);(new PlayerDeathEvent($b,[],0,'test'))->call();self::near(1,$this->value($a,'kills'));self::near(1,$this->value($a,'kill_streak'));self::near(1,$this->value($a,'best_kill_streak'));self::near(1,$this->value($b,'deaths'));self::near(1,$this->value($b,'deaths_pvp'));
            });
            $this->test('environmental death resets streak and is not a player kill',function() use($a): void{
                $a->testCause=new EntityDamageEvent($a,EntityDamageEvent::CAUSE_FALL,20);(new PlayerDeathEvent($a,[],0,'test'))->call();self::near(1,$this->value($a,'deaths_fall'));self::near(0,$this->value($a,'kill_streak'));self::near(1,$this->value($a,'best_kill_streak'));self::near(1,$this->value($a,'kills'));
            });
            $this->test('mob kills are distinct from PvP and expose per-type totals',function() use($a,$world): void{
                \pocketmine\entity\EntityFactory::getInstance()->register(TestMob::class,static fn(\pocketmine\world\World $w,\pocketmine\nbt\tag\CompoundTag $nbt): TestMob=>new TestMob(\pocketmine\entity\EntityDataHelper::parseLocation($nbt,$w),$nbt),['AmyxStatsTestMob']);
                $mob=new TestMob(new Location(10,100,10,$world,0,0));$mob->setLastDamageCause(new FightDamage($a,$mob,20));(new EntityDeathEvent($mob))->call();self::near(1,$this->value($a,'mob_kills'));self::near(1,$this->value($a,'mob_kill:zombie'));self::near(1,$this->value($a,'kills'));$mob->close();
            });
            $this->test('health regeneration cannot exceed missing health',function() use($a): void{ $a->testHealth=18;(new EntityRegainHealthEvent($a,10,EntityRegainHealthEvent::CAUSE_CUSTOM))->call();$this->drain();self::near(2,$this->value($a,'health_regenerated'));$a->testHealth=20; });
            $this->test('pickup and drop count item amounts and exact item identifiers',function() use($a): void{
                (new EntityItemPickupEvent($a,$a,I::DIAMOND()->setCount(12),$a->getInventory()))->call();(new PlayerDropItemEvent($a,I::DIAMOND()->setCount(3)))->call();$this->drain();self::near(12,$this->value($a,'items_picked_up'));self::near(3,$this->value($a,'items_dropped'));self::near(12,$this->value($a,'item_pickup:diamond'));
            });
            $this->test('cancelled pickup and drop do not change counters',function() use($a): void{ $e=new EntityItemPickupEvent($a,$a,I::DIAMOND(),$a->getInventory());$e->cancel();$e->call();$e=new PlayerDropItemEvent($a,I::DIAMOND());$e->cancel();$e->call();$this->drain();self::near(12,$this->value($a,'items_picked_up'));self::near(3,$this->value($a,'items_dropped')); });
            $this->test('consumption counts food once per action rather than whole held stack',function() use($a): void{ (new PlayerItemConsumeEvent($a,I::APPLE()->setCount(64)))->call();$this->drain();self::near(1,$this->value($a,'food_eaten'));self::near(1,$this->value($a,'items_consumed'));self::near(1,$this->value($a,'item_consume:apple')); });
            $this->test('accepted chat, command and item use events update activity',function() use($a): void{
                (new PlayerChatEvent($a,'hello',[],new \pocketmine\player\chat\StandardChatFormatter()))->call();(new CommandEvent($a,'stat'))->call();(new PlayerItemUseEvent($a,I::SNOWBALL(),new Vector3(0,0,1)))->call();$this->drain();self::near(1,$this->value($a,'chat_messages'));self::near(1,$this->value($a,'commands_used'));self::near(1,$this->value($a,'items_used'));
            });
            $this->test('real crafting transaction counts already multiplied output and recipe repetitions',function() use($a): void{
                $plank=B::OAK_PLANKS()->asItem();$recipe=new \pocketmine\crafting\ShapelessRecipe([new \pocketmine\crafting\ExactRecipeIngredient($plank),new \pocketmine\crafting\ExactRecipeIngredient($plank)],[I::STICK()->setCount(4)],\pocketmine\crafting\ShapelessRecipeType::CRAFTING());
                $manager=$this->getServer()->getCraftingManager();$manager->registerShapelessRecipe($recipe);$inventory=$a->getInventory();$inventory->setItem(0,(clone $plank)->setCount(4));$inventory->clear(1);
                $actions=[new \pocketmine\inventory\transaction\action\SlotChangeAction($inventory,0,$inventory->getItem(0),I::AIR()),new \pocketmine\inventory\transaction\action\SlotChangeAction($inventory,1,I::AIR(),I::STICK()->setCount(8))];
                $tx=new \pocketmine\inventory\transaction\CraftingTransaction($a,$manager,$actions,$recipe,2);$tx->execute();$this->drain();self::near(8,$this->value($a,'items_crafted'));self::near(2,$this->value($a,'crafts'));self::near(8,$this->value($a,'item_craft:stick'));
            });
            $this->test('cancelled crafting event never adds statistics',function() use($a): void{
                $before=$this->value($a,'items_crafted');$plank=B::OAK_PLANKS()->asItem();$recipe=new \pocketmine\crafting\ShapelessRecipe([new \pocketmine\crafting\ExactRecipeIngredient($plank)],[I::STICK()],\pocketmine\crafting\ShapelessRecipeType::CRAFTING());
                $tx=new \pocketmine\inventory\transaction\CraftingTransaction($a,$this->getServer()->getCraftingManager());$event=new \pocketmine\event\inventory\CraftItemEvent($tx,$recipe,1,[$plank],[I::STICK()]);$event->call();$event->cancel();$this->drain();self::near($before,$this->value($a,'items_crafted'));
            });
            if($this->getServer()->getPluginManager()->getPlugin('AmyxAnvil')!==null){
                $this->test('real AmyxAnvil repair and rename transaction updates both statistics',function() use($a,$world): void{
                    $world->setBlockAt(8,99,4,B::STONE());$world->setBlockAt(8,100,4,B::ANVIL());$w=new \pocketmine\block\inventory\AnvilInventory(new Position(8,100,4,$world));$a->setCurrentWindow($w);
                    $input=I::IRON_SWORD()->setDamage(100);$right=I::IRON_INGOT()->setCount(2);$w->setItem(0,$input);$w->setItem(1,$right);$a->getInventory()->clear(3);
                    $plan=(new \Amyx\Anvil\rules\AnvilCalculator())->calculate($input,$right,'Исправленный');
                    $tx=new \Amyx\Anvil\transaction\AnvilTransaction($a,$w,$plan,[new \pocketmine\inventory\transaction\action\SlotChangeAction($w,0,$input,I::AIR()),new \pocketmine\inventory\transaction\action\SlotChangeAction($w,1,$right,I::AIR()),new \pocketmine\inventory\transaction\action\SlotChangeAction($a->getInventory(),3,I::AIR(),$plan->result())]);
                    $tx->execute();$this->drain();self::near(1,$this->value($a,'items_repaired'));self::near(1,$this->value($a,'items_renamed'));$a->removeCurrentWindow();
                });
            }
            $this->test('tracking permission stops gathering while viewing remains possible',function() use($a): void{ $a->denied=['amyxstats.track'];(new PlayerJumpEvent($a))->call();self::near(1,$this->value($a,'jumps'));$a->denied=[]; });
            $this->test('viewing another profile and global statistics enforces permissions',function() use($a): void{
                $cmd=$this->getServer()->getCommandMap()->getCommand('stat');$old=$a->form;$a->denied=['amyxstats.others','amyxstats.global'];$cmd->execute($a,'stat',['Bob']);self::ok($a->form===$old);$cmd->execute($a,'stat',['global']);self::ok($a->form===$old);self::ok(count($a->messages)>=2);$a->denied=[];
            });
            $this->test('global placeholders and KDR use whole-server inputs',function() use($a): void{ self::ok($this->stats->resolvePlaceholder('amyxstats.global.blocks_broken')==='1');self::ok($this->stats->resolvePlaceholder('amyxstats.global.kdr')==='0.50');self::ok($this->stats->replacePlaceholders('K:%amyxstats_my_kills% / {amyxstats.global.deaths}',$a)==='K:1 / 2'); });
            $this->test('top command, chat output and unknown-name handling work',function() use($a): void{ $cmd=$this->getServer()->getCommandMap()->getCommand('stat');$cmd->execute($a,'stat',['top','blocks_broken']);self::ok(str_contains($a->form->jsonSerialize()['content'],'Alice'));$cmd->execute($a,'stat',['chat']);self::ok(count($a->messages)>70);$before=count($a->messages);$cmd->execute($a,'stat',['Missing']);self::ok(count($a->messages)===$before+1); });
            $this->scoreHud($a);
            $this->test('unchanged or failed placement does not count and observers are removed',function() use($a,$world): void{
                $before=$this->value($a,'blocks_placed');$listeners=count($world->getChunkListeners(0,0));$world->setBlockAt(11,100,1,B::STONE());$tx=new BlockTransaction($world);$tx->addBlockAt(11,100,1,B::STONE());(new BlockPlaceEvent($a,$tx,$world->getBlockAt(11,99,1),B::STONE()->asItem()))->call();$tx->apply();$this->drain();self::near($before,$this->value($a,'blocks_placed'));
                $tx=new BlockTransaction($world);$tx->addBlockAt(11,101,1,B::STONE());(new BlockPlaceEvent($a,$tx,$world->getBlockAt(11,100,1),B::STONE()->asItem()))->call();$this->drain();self::near($before,$this->value($a,'blocks_placed'));self::ok(count($world->getChunkListeners(0,0))===$listeners);
            });
            $this->test('transient placed block remains counted even when removed in the same tick',function() use($a,$world): void{
                $before=$this->value($a,'blocks_placed');$tx=new BlockTransaction($world);$tx->addBlockAt(11,102,1,B::GRAVEL());(new BlockPlaceEvent($a,$tx,$world->getBlockAt(11,101,1),B::GRAVEL()->asItem()))->call();$tx->apply();$world->setBlockAt(11,102,1,B::AIR());$this->drain();self::near($before+1,$this->value($a,'blocks_placed'));
            });
            $this->test('breaking and replacing the same block in one tick still counts the break',function() use($a,$world): void{
                $before=$this->value($a,'blocks_broken');$world->setBlockAt(11,100,1,B::STONE());(new BlockBreakEvent($a,$world->getBlockAt(11,100,1),I::DIAMOND_PICKAXE()))->call();$world->setBlockAt(11,100,1,B::AIR());$world->setBlockAt(11,100,1,B::STONE());$this->drain();self::near($before+1,$this->value($a,'blocks_broken'));
            });
            $this->test('quit flushes offline profile without creating a new identity',function() use($a): void{ $before=$this->value($a,'blocks_broken');(new PlayerQuitEvent($a,'test','test'))->call();self::ok(!$this->stats->service()->online($a->getUniqueId()->toString()));self::near($before,$this->stats->getStatistic('aLiCe','blocks_broken'));self::ok($this->stats->service()->store->uniquePlayers()===2); });
            $manifest=['broken'=>$this->stats->getStatistic('Alice','blocks_broken'),'ore'=>$this->stats->getStatistic('Alice','block_break:diamond_ore'),'kills'=>$this->stats->getStatistic('Alice','kills'),'global'=>$this->stats->getGlobalStatistic('blocks_broken'),'top'=>$this->stats->getTop('blocks_broken')[0]['name']];
            file_put_contents($path.'restart-manifest.json',json_encode($manifest));
        }catch(\Throwable $e){ $this->failures['harness']=$e->getMessage();$this->getLogger()->logException($e); }
        file_put_contents($path.'amyxstats-results.json',json_encode(['passed'=>$this->passed,'failures'=>$this->failures],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));$this->getServer()->shutdown();
    }
    private function scoreHud(SyntheticPlayer $a): void{
        $source=getenv('AMYXSTATS_SCOREHUD_SOURCE');if(!$source){ return; }
        spl_autoload_register(static function(string $class) use($source): void{ if(str_starts_with($class,'Ifera\\ScoreHud\\')){ $file=$source.'/src/'.str_replace('\\','/',$class).'.php';if(is_file($file)){ require $file; } } });
        $this->test('official ScoreHud 8 tag API resolves and refreshes only changed requested tags',function() use($a): void{
            $bridge=new \Amyx\Stats\integration\ScoreHudBridge($this->stats);$tag=new \Ifera\ScoreHud\scoreboard\ScoreTag('amyxstats.my.blocks_placed');$event=new \Ifera\ScoreHud\event\TagsResolveEvent($a,$tag);$bridge->onResolve($event);self::ok($tag->getValue()==='2');
            $updates=[];$this->getServer()->getPluginManager()->registerEvent(\Ifera\ScoreHud\event\PlayerTagsUpdateEvent::class,function(\Ifera\ScoreHud\event\PlayerTagsUpdateEvent $event) use(&$updates): void{ $updates[]=$event->getTags(); },\pocketmine\event\EventPriority::NORMAL,$this);
            $bridge->refreshPlayer($a);self::ok($updates===[]);$this->stats->service()->add($a->getUniqueId()->toString(),'blocks_placed');$bridge->refreshPlayer($a);self::ok(count($updates)===1);self::ok($updates[0][0]->getValue()==='3');$bridge->refreshPlayer($a);self::ok(count($updates)===1);$bridge->forget($a->getUniqueId()->toString());
        });
    }
}
