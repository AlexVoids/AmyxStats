<?php
declare(strict_types=1);
namespace Amyx\Stats\listener;

use pocketmine\math\Vector3;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkListenerNoOpTrait;
use pocketmine\world\World;

/** Observe actual writes during an accepted block action, including transient blocks.
 * Registered for at most one tick; no change to PocketMine's block implementation.
 */
final class BlockObservation implements ChunkListener{
    use ChunkListenerNoOpTrait;
    private array $goals=[];
    private array $seen=[];
    private array $chunks=[];
    /** @param array<array{int,int,int,int,bool}> $goals x,y,z,state,expectEquality */
    public function __construct(private World $world,array $goals){
        foreach($goals as [$x,$y,$z,$state,$equal]){
            $this->goals[$x.':'.$y.':'.$z]=[$state,$equal];$this->chunks[($x>>4).':'.($z>>4)]=[$x>>4,$z>>4];
        }
        foreach($this->chunks as [$x,$z]){ $world->registerChunkListener($this,$x,$z); }
    }
    public function onBlockChanged(Vector3 $block): void{
        $key=$block->getFloorX().':'.$block->getFloorY().':'.$block->getFloorZ();
        if(!isset($this->goals[$key]) || isset($this->seen[$key])){ return; }
        [$state,$equal]=$this->goals[$key];
        if(($this->world->getBlock($block)->getStateId()===$state)===$equal){
            $this->seen[$key]=true;if(count($this->seen)===count($this->goals)){ $this->close(); }
        }
    }
    public function seen(int $x,int $y,int $z): bool{ return isset($this->seen[$x.':'.$y.':'.$z]); }
    public function close(): void{
        foreach($this->chunks as [$x,$z]){ $this->world->unregisterChunkListener($this,$x,$z); }$this->chunks=[];
    }
}
