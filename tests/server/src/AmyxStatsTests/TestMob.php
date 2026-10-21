<?php
declare(strict_types=1);
namespace AmyxStatsTests;
final class TestMob extends \pocketmine\entity\Living{
    protected function getInitialSizeInfo(): \pocketmine\entity\EntitySizeInfo{ return new \pocketmine\entity\EntitySizeInfo(1.8,0.6); }
    public static function getNetworkTypeId(): string{ return 'minecraft:zombie'; }
    public function getName(): string{ return 'Zombie'; }
}
