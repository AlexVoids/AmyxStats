<?php
declare(strict_types=1);
namespace AmyxStatsTests;

use pocketmine\entity\AttributeMap;
use pocketmine\entity\ExperienceManager;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\form\Form;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use pocketmine\world\Position;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/** Replace the transport boundary; retain core inventory, XP and window behaviour. */
final class SyntheticPlayer extends Player{
    public bool $creative=false;
    public bool $spectator=false;
    public array $denied=[];
    public array $messages=[];
    public ?Form $form=null;
    public float $testHealth=20;
    public ?EntityDamageEvent $testCause=null;
    private UuidInterface $testUuid;
    public function __construct(private string $testName,Position $position){
        $this->testUuid=Uuid::uuid5(Uuid::NAMESPACE_DNS,'amyxstats.tests.'.$testName);
        $this->server=\pocketmine\Server::getInstance();$this->logger=$this->server->getLogger();
        $this->id=spl_object_id($this);$this->location=Location::fromObject($position,$position->getWorld());
        $this->attributeMap=new AttributeMap();$this->xpManager=new ExperienceManager($this);
        $this->inventory=new PlayerInventory($this);$this->armorInventory=new ArmorInventory($this);$this->offHandInventory=new PlayerOffHandInventory($this);
        $this->cursorInventory=new PlayerCursorInventory($this);$this->craftingGrid=new PlayerCraftingInventory($this);
        $this->networkSession=new SyntheticSession($this);$this->networkSession->manager=new InventoryManager($this,$this->networkSession);
        $this->addPermanentInventories($this->inventory,$this->armorInventory,$this->offHandInventory,$this->cursorInventory,$this->craftingGrid);
        $this->networkSession->manager->syncAll();$this->xpManager->setXpAndProgressNoEvent(30,0.25);
    }
    public function getName(): string{ return $this->testName; }
    public function getUniqueId(): UuidInterface{ return $this->testUuid; }
    public function isSurvival(bool $literal=false): bool{ return !$this->creative && !$this->spectator; }
    public function isCreative(bool $literal=false): bool{ return $this->creative; }
    public function isAdventure(bool $literal=false): bool{ return false; }
    public function isSpectator(): bool{ return $this->spectator; }
    public function hasFiniteResources(): bool{ return !$this->creative; }
    public function isConnected(): bool{ return true; }
    public function isOnline(): bool{ return true; }
    public function isAlive(): bool{ return true; }
    public function getHealth(): float{ return $this->testHealth; }
    public function getMaxHealth(): int{ return 20; }
    public function getAbsorption(): float{ return 0; }
    public function getLastDamageCause(): ?EntityDamageEvent{ return $this->testCause; }
    public function hasPermission(Permission|string $name): bool{ return !in_array($name,$this->denied,true); }
    public function setUsingItem(bool $value): void{}
    public function sendMessage(Translatable|string $message): void{ $this->messages[]=(string)$message; }
    public function sendForm(Form $form): void{ $this->form=$form; }
    public function __destruct(){}
}
