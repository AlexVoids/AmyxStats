<?php
declare(strict_types=1);
namespace AmyxStatsTests;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
/** Attacker lookup replaces the network-connected world-player registry only. */
final class FightDamage extends EntityDamageByEntityEvent{
    public function __construct(private Entity $testAttacker,Entity $victim,float $damage){ parent::__construct($testAttacker,$victim,self::CAUSE_ENTITY_ATTACK,$damage); }
    protected function addAttackerModifiers(Entity $damager): void{}
    public function getDamager(): ?Entity{ return $this->testAttacker; }
}
