<?php
declare(strict_types=1);
namespace Amyx\Stats\stat;

final class Profile{
    public array $dirty=[];
    public array $newDays=[];
    public bool $metadataDirty=false;
    public array $persisted;
    public function __construct(public readonly string $uuid,public string $name,public int $firstSeen,public int $lastSeen,public array $values=[]){ $this->persisted=$values; }
    public function get(string $key): float{
        return MetricCatalog::derived($key,fn(string $k): float=>$this->get($k)) ?? (float)($this->values[$key] ?? 0);
    }
}
