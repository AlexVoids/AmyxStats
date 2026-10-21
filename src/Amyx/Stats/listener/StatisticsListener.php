<?php
declare(strict_types=1);
namespace Amyx\Stats\listener;

use Amyx\Stats\Main;
use pocketmine\block\Crops;
use pocketmine\block\inventory\AnvilInventory;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\Cancellable;
use pocketmine\event\Event;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\player\PlayerToggleSprintEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\inventory\BlockInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Durable;
use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class StatisticsListener implements Listener{
    /** @var \Closure[] */private array $pending=[];
    public function __construct(private Main $plugin){}
    public function flushPending(): void{
        $pending=$this->pending;$this->pending=[];
        foreach($pending as $job){ try{ $job(); }catch(\Throwable $e){ $this->plugin->getLogger()->logException($e); } }
    }
    private function later(Event $event,\Closure $job,?\Closure $cleanup=null): void{
        $this->pending[]=static function() use($event,$job,$cleanup): void{
            try{ if(!$event instanceof Cancellable || !$event->isCancelled()){ $job(); } }finally{ if($cleanup!==null){ $cleanup(); } }
        };
    }
    private function count(Player $p,string $key,float $amount=1): void{ $this->plugin->service()->add($p->getUniqueId()->toString(),$key,$amount); }
    private function allowed(Player $p,bool $game=true): bool{ return $this->plugin->canTrack($p,$game); }
    public static function itemId(Item $item): string{
        return $item->isNull() ? 'air' : preg_replace('/^minecraft:/','',$item->nbtSerialize()->getString('Name'));
    }
    private function blockId(\pocketmine\block\Block $block): string{
        return preg_replace('/^minecraft:/','',GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId())->getName());
    }
    /** @priority MONITOR */
    public function onJoin(PlayerJoinEvent $event): void{ $this->plugin->registerPlayer($event->getPlayer()); }
    /** @priority MONITOR */
    public function onQuit(PlayerQuitEvent $event): void{
        $this->flushPending();$this->plugin->forgetPlayer($event->getPlayer());
    }
    /** @priority MONITOR */
    public function onBreak(BlockBreakEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }
        $b=$event->getBlock();$world=$b->getPosition()->getWorld();$position=$b->getPosition();$old=$b->getStateId();$id=$this->blockId($b);
        $observation=new BlockObservation($world,[[$position->getFloorX(),$position->getFloorY(),$position->getFloorZ(),$old,false]]);
        $mature=$b instanceof Crops && $b->getAge()===$b::MAX_AGE;
        $this->later($event,function() use($p,$world,$position,$observation,$id,$mature): void{
            if(!$world->isLoaded() || !$observation->seen($position->getFloorX(),$position->getFloorY(),$position->getFloorZ())){ return; }
            $this->count($p,'blocks_broken');$this->count($p,'block_break:'.$id);
            if(str_ends_with($id,'_ore') || $id==='ancient_debris'){ $this->count($p,'ores_mined'); }
            if(preg_match('/(_log|_wood|_stem|_hyphae)$/D',$id) || in_array($id,['log','log2','wood'],true)){ $this->count($p,'logs_broken'); }
            if(str_ends_with($id,'_leaves') || in_array($id,['leaves','leaves2'],true)){ $this->count($p,'leaves_broken'); }
            if($mature){ $this->count($p,'crops_harvested'); }
        },fn()=>$observation->close());
    }
    /** @priority MONITOR */
    public function onPlace(BlockPlaceEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }
        $world=$p->getWorld();$blocks=[];
        foreach($event->getTransaction()->getBlocks() as [$x,$y,$z,$block]){
            if(!$block->asItem()->isNull() && $world->getBlockAt($x,$y,$z)->getStateId()!==$block->getStateId()){ $blocks[]=[$x,$y,$z,$block->getStateId(),$this->blockId($block)]; }
        }
        $observation=new BlockObservation($world,array_map(static fn(array $b): array=>[$b[0],$b[1],$b[2],$b[3],true],$blocks));
        $this->later($event,function() use($p,$world,$blocks,$observation): void{
            if(!$world->isLoaded()){ return; }
            foreach($blocks as [$x,$y,$z,$state,$id]){
                if($observation->seen($x,$y,$z)){ $this->count($p,'blocks_placed');$this->count($p,'block_place:'.$id); }
            }
        },fn()=>$observation->close());
    }
    /** @priority MONITOR */
    public function onMove(PlayerMoveEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }
        $from=$event->getFrom();$to=$event->getTo();if($from->getWorld()!==$to->getWorld()){ return; }
        $distance=$from->distance($to);if($distance<0.001 || $distance>(float)$this->plugin->getConfig()->get('maximum-move-distance',16)){ return; }
        $kind=$p->isGliding() ? 'glided' : ($p->isFlying() ? 'flown' : ($p->isSwimming() ? 'swum' : ($p->isSprinting() ? 'sprinted' : ($p->isSneaking() ? 'sneaked' : 'walked'))));
        $this->later($event,function() use($p,$distance,$kind): void{ $this->count($p,'distance_travelled',$distance);$this->count($p,'distance_'.$kind,$distance); });
    }
    /** @priority MONITOR */
    public function onTeleport(EntityTeleportEvent $event): void{
        $p=$event->getEntity();if(!$p instanceof Player || !$this->allowed($p)){ return; }
        $from=$event->getFrom();$to=$event->getTo();$same=$from->getWorld()===$to->getWorld();$distance=$same ? $from->distance($to) : 0;
        if($same && $distance<0.001){ return; }
        $this->later($event,function() use($p,$same,$distance): void{ $this->count($p,'teleports');if(!$same){ $this->count($p,'world_changes'); }else{ $this->count($p,'distance_teleported',$distance); } });
    }
    /** @priority MONITOR */
    public function onDamage(EntityDamageEvent $event): void{
        $victim=$event->getEntity();if(!$victim instanceof Living){ return; }
        $damage=max(0,min($event->getFinalDamage(),$victim->getHealth()+$victim->getAbsorption()));if($damage<=0){ return; }
        $attacker=$event instanceof EntityDamageByEntityEvent ? $event->getDamager() : null;
        if($attacker instanceof Projectile){ $attacker=$attacker->getOwningEntity(); }
        $projectile=$event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile;
        $take=$victim instanceof Player && $this->allowed($victim);$deal=$attacker instanceof Player && $attacker!==$victim && $this->allowed($attacker);
        $this->later($event,function() use($victim,$attacker,$damage,$projectile,$take,$deal): void{
            if($take){ $this->count($victim,'damage_taken',$damage);if($attacker!==null){ $this->count($victim,'hits_taken'); } }
            if($deal){
                $this->count($attacker,'damage_dealt',$damage);$this->count($attacker,'hits_dealt');if($projectile){ $this->count($attacker,'projectile_hits'); }
                if($victim instanceof Player){ $this->count($attacker,'damage_pvp_dealt',$damage); }
            }
            if($take && $attacker instanceof Player && $attacker!==$victim){ $this->count($victim,'damage_pvp_taken',$damage); }
        });
    }
    /** @priority MONITOR */
    public function onDeath(EntityDeathEvent $event): void{
        $victim=$event->getEntity();$cause=$victim->getLastDamageCause();$killer=$cause instanceof EntityDamageByEntityEvent ? $cause->getDamager() : null;
        if($killer instanceof Projectile){ $killer=$killer->getOwningEntity(); }
        if($victim instanceof Player){
            if(!$this->allowed($victim)){ return; }
            $this->plugin->service()->death($victim->getUniqueId()->toString());
            $reason=$killer instanceof Player && $killer!==$victim ? 'pvp' : match($cause?->getCause()){
                EntityDamageEvent::CAUSE_FALL=>'fall',EntityDamageEvent::CAUSE_FIRE,EntityDamageEvent::CAUSE_FIRE_TICK=>'fire',
                EntityDamageEvent::CAUSE_LAVA=>'lava',EntityDamageEvent::CAUSE_DROWNING=>'drowning',EntityDamageEvent::CAUSE_VOID=>'void',
                EntityDamageEvent::CAUSE_BLOCK_EXPLOSION,EntityDamageEvent::CAUSE_ENTITY_EXPLOSION=>'explosion',default=>'other'
            };
            $this->count($victim,'deaths_'.$reason);
        }
        if($killer instanceof Player && $killer!==$victim && $this->allowed($killer)){
            if($victim instanceof Player){
                $this->count($killer,'kills');$this->plugin->service()->streakKill($killer->getUniqueId()->toString());
                $this->count($killer,$cause instanceof EntityDamageByChildEntityEvent && $cause->getChild() instanceof Projectile ? 'kills_projectile' : 'kills_melee');
            }else{ $this->count($killer,'mob_kills');$this->count($killer,'mob_kill:'.preg_replace('/^minecraft:/','',$victim::getNetworkTypeId())); }
        }
    }
    /** @priority MONITOR */
    public function onProjectile(ProjectileLaunchEvent $event): void{
        $projectile=$event->getEntity();$p=$projectile->getOwningEntity();if(!$p instanceof Player || !$this->allowed($p)){ return; }
        $this->later($event,function() use($p,$projectile): void{ $this->count($p,'projectiles_shot');if($projectile instanceof Arrow){ $this->count($p,'arrows_shot'); } });
    }
    /** @priority MONITOR */
    public function onRegain(EntityRegainHealthEvent $event): void{
        $p=$event->getEntity();if(!$p instanceof Player || !$this->allowed($p)){ return; }
        $amount=max(0,min($event->getAmount(),$p->getMaxHealth()-$p->getHealth()));$this->later($event,fn()=>$this->count($p,'health_regenerated',$amount));
    }
    /** @priority MONITOR */
    public function onCraft(CraftItemEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }
        $outputs=$event->getOutputs();$repetitions=$event->getRepetitions();
        $this->later($event,function() use($event,$p,$outputs,$repetitions): void{
            if(!$event->getTransaction()->hasExecuted()){ return; }
            $this->count($p,'crafts',$repetitions);
            foreach($outputs as $item){ $this->count($p,'items_crafted',$item->getCount());$this->count($p,'item_craft:'.self::itemId($item),$item->getCount()); }
        });
    }
    /** @priority MONITOR */
    public function onPickup(EntityItemPickupEvent $event): void{
        $p=$event->getEntity();if(!$p instanceof Player || !$this->allowed($p)){ return; }
        $item=clone $event->getItem();$this->later($event,function() use($p,$item): void{ $this->count($p,'items_picked_up',$item->getCount());$this->count($p,'item_pickup:'.self::itemId($item),$item->getCount()); });
    }
    /** @priority MONITOR */
    public function onDrop(PlayerDropItemEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }$item=clone $event->getItem();
        $this->later($event,function() use($p,$item): void{ $this->count($p,'items_dropped',$item->getCount());$this->count($p,'item_drop:'.self::itemId($item),$item->getCount()); });
    }
    /** @priority MONITOR */
    public function onConsume(PlayerItemConsumeEvent $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }$item=clone $event->getItem();$id=self::itemId($item);
        $this->later($event,function() use($p,$item,$id): void{ $this->count($p,'items_consumed');$this->count($p,'item_consume:'.$id);if($item instanceof Food){ $this->count($p,'food_eaten'); }if($id==='potion'){ $this->count($p,'potions_drunk'); } });
    }
    /** @priority MONITOR */
    public function onAnvil(InventoryTransactionEvent $event): void{
        $tx=$event->getTransaction();if(!is_a($tx,'Amyx\\Anvil\\transaction\\AnvilTransaction')){ return; }
        $p=$tx->getSource();if(!$this->allowed($p)){ return; }$input=null;$output=null;
        foreach($tx->getActions() as $action){
            if(!$action instanceof SlotChangeAction){ continue; }
            if($action->getInventory() instanceof AnvilInventory && $action->getSlot()===0){ $input=$action->getSourceItem(); }
            elseif(($action->getInventory()===$p->getInventory() || $action->getInventory()===$p->getCursorInventory()) && $action->getTargetItem()->getCount()>$action->getSourceItem()->getCount()){
                $output=$action->getTargetItem();
            }
        }
        if($input===null || $output===null){ return; }
        $this->later($event,function() use($tx,$p,$input,$output): void{
            if(!$tx->hasExecuted()){ return; }
            if($input->getCustomName()!==$output->getCustomName()){ $this->count($p,'items_renamed',$input->getCount()); }
            if($input instanceof Durable && $output instanceof Durable && $output->getDamage()<$input->getDamage()){ $this->count($p,'items_repaired'); }
            $this->countEnchantments($p,$input,$output);
        });
    }
    public function countEnchantments(Player $p,Item $input,Item $output): void{
        $changes=0;foreach($output->getEnchantments() as $enchantment){ if($enchantment->getLevel()>$input->getEnchantmentLevel($enchantment->getType())){ ++$changes; } }
        if($changes>0){ $this->count($p,'items_enchanted');$this->count($p,'enchantments_applied',$changes); }
    }
    /** @priority MONITOR */
    public function onOpen(InventoryOpenEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p) && $event->getInventory() instanceof BlockInventory){ $this->later($event,fn()=>$this->count($p,'containers_opened')); } }
    /** @priority MONITOR */
    public function onJump(PlayerJumpEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p)){ $this->count($p,'jumps'); } }
    /** @priority MONITOR */
    public function onSprint(PlayerToggleSprintEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p) && $event->isSprinting()){ $this->later($event,fn()=>$this->count($p,'sprint_starts')); } }
    /** @priority MONITOR */
    public function onSneak(PlayerToggleSneakEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p) && $event->isSneaking()){ $this->later($event,fn()=>$this->count($p,'sneak_starts')); } }
    /** @priority MONITOR */
    public function onChat(PlayerChatEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p,false)){ $this->later($event,fn()=>$this->count($p,'chat_messages')); } }
    /** @priority MONITOR */
    public function onCommand(CommandEvent $event): void{ $p=$event->getSender();if($p instanceof Player && $this->allowed($p,false)){ $this->later($event,fn()=>$this->count($p,'commands_used')); } }
    /** @priority MONITOR */
    public function onBed(PlayerBedEnterEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p)){ $this->later($event,fn()=>$this->count($p,'beds_entered')); } }
    /** @priority MONITOR */
    public function onBucketFill(PlayerBucketFillEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p)){ $this->later($event,fn()=>$this->count($p,'buckets_filled')); } }
    /** @priority MONITOR */
    public function onBucketEmpty(PlayerBucketEmptyEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p)){ $this->later($event,fn()=>$this->count($p,'buckets_emptied')); } }
    /** @priority MONITOR */
    public function onUse(PlayerItemUseEvent $event): void{ $p=$event->getPlayer();if($this->allowed($p)){ $this->later($event,fn()=>$this->count($p,'items_used')); } }
    public function nativeEnchant(Event $event): void{
        $p=$event->getPlayer();if(!$this->allowed($p)){ return; }
        $this->later($event,function() use($p,$event): void{ if($event->getTransaction()->hasExecuted()){ $this->countEnchantments($p,$event->getInputItem(),$event->getOutputItem()); } });
    }
}
