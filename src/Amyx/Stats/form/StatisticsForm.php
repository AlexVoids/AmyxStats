<?php
declare(strict_types=1);
namespace Amyx\Stats\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class StatisticsForm implements Form{
    public function __construct(private string $viewerUuid,private string $title,private string $content,private array $buttons,private \Closure $callback){}
    public function jsonSerialize(): array{ return ['type'=>'form','title'=>$this->title,'content'=>$this->content,'buttons'=>array_map(static fn(string $text): array=>['text'=>$text],$this->buttons)]; }
    public function handleResponse(Player $player,mixed $data): void{
        if($player->getUniqueId()->toString()!==$this->viewerUuid || !is_int($data) || !array_key_exists($data,$this->buttons)){ return; }
        ($this->callback)($player,$data);
    }
}
