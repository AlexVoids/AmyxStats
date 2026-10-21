<?php
declare(strict_types=1);
namespace Amyx\Stats\stat;

final class MetricCatalog{
    /** key => [Russian label, category, aggregate, decimal places] */
    public const METRICS=[
        'blocks_broken'=>['Сломано блоков','building','sum',0],
        'blocks_placed'=>['Поставлено блоков','building','sum',0],
        'ores_mined'=>['Добыто руд','building','sum',0],
        'logs_broken'=>['Срублено брёвен','building','sum',0],
        'leaves_broken'=>['Сломано листвы','building','sum',0],
        'crops_harvested'=>['Собрано зрелых культур','building','sum',0],
        'kills'=>['Убийств игроков','combat','sum',0],
        'deaths'=>['Смертей','combat','sum',0],
        'mob_kills'=>['Убийств мобов','combat','sum',0],
        'kills_melee'=>['Убийств в ближнем бою','combat','sum',0],
        'kills_projectile'=>['Убийств снарядами','combat','sum',0],
        'kill_streak'=>['Текущая серия убийств','combat','sum',0],
        'best_kill_streak'=>['Лучшая серия убийств','records','max',0],
        'deaths_pvp'=>['Смертей от игроков','deaths','sum',0],
        'deaths_fall'=>['Смертей от падения','deaths','sum',0],
        'deaths_fire'=>['Смертей от огня','deaths','sum',0],
        'deaths_lava'=>['Смертей от лавы','deaths','sum',0],
        'deaths_drowning'=>['Смертей от утопления','deaths','sum',0],
        'deaths_void'=>['Смертей в пустоте','deaths','sum',0],
        'deaths_explosion'=>['Смертей от взрыва','deaths','sum',0],
        'deaths_other'=>['Прочих смертей','deaths','sum',0],
        'damage_dealt'=>['Нанесено урона','combat','sum',2],
        'damage_taken'=>['Получено урона','combat','sum',2],
        'damage_pvp_dealt'=>['Нанесено урона игрокам','combat','sum',2],
        'damage_pvp_taken'=>['Получено урона от игроков','combat','sum',2],
        'hits_dealt'=>['Успешных атак','combat','sum',0],
        'hits_taken'=>['Получено ударов','combat','sum',0],
        'projectiles_shot'=>['Выпущено снарядов','combat','sum',0],
        'arrows_shot'=>['Выпущено стрел','combat','sum',0],
        'projectile_hits'=>['Попаданий снарядами','combat','sum',0],
        'health_regenerated'=>['Восстановлено здоровья','combat','sum',2],
        'items_crafted'=>['Создано предметов','items','sum',0],
        'crafts'=>['Выполнено крафтов','items','sum',0],
        'items_picked_up'=>['Подобрано предметов','items','sum',0],
        'items_dropped'=>['Выброшено предметов','items','sum',0],
        'items_consumed'=>['Использовано расходников','items','sum',0],
        'food_eaten'=>['Съедено еды','items','sum',0],
        'potions_drunk'=>['Выпито зелий','items','sum',0],
        'items_enchanted'=>['Зачаровано предметов','items','sum',0],
        'enchantments_applied'=>['Улучшено зачарований','items','sum',0],
        'items_repaired'=>['Починено на наковальне','items','sum',0],
        'items_renamed'=>['Переименовано предметов','items','sum',0],
        'distance_travelled'=>['Пройдено метров','movement','sum',2],
        'distance_walked'=>['Пройдено шагом, м','movement','sum',2],
        'distance_sprinted'=>['Пройдено бегом, м','movement','sum',2],
        'distance_sneaked'=>['Пройдено крадучись, м','movement','sum',2],
        'distance_swum'=>['Проплыто, м','movement','sum',2],
        'distance_flown'=>['Пролетено, м','movement','sum',2],
        'distance_glided'=>['Пройдено на элитрах, м','movement','sum',2],
        'distance_teleported'=>['Перемещено телепортами, м','movement','sum',2],
        'jumps'=>['Прыжков','movement','sum',0],
        'teleports'=>['Телепортаций','movement','sum',0],
        'world_changes'=>['Переходов между мирами','movement','sum',0],
        'sprint_starts'=>['Начато пробежек','movement','sum',0],
        'sneak_starts'=>['Начато приседаний','movement','sum',0],
        'joins'=>['Входов на сервер','activity','sum',0],
        'days_played'=>['Игровых дней UTC','activity','sum',0],
        'playtime_seconds'=>['Время игры, секунд','activity','sum',0],
        'longest_session_seconds'=>['Самая долгая сессия, с','records','max',0],
        'chat_messages'=>['Сообщений в чате','activity','sum',0],
        'commands_used'=>['Введено команд','activity','sum',0],
        'beds_entered'=>['Использовано кроватей','activity','sum',0],
        'buckets_filled'=>['Наполнено вёдер','activity','sum',0],
        'buckets_emptied'=>['Опустошено вёдер','activity','sum',0],
        'containers_opened'=>['Открыто контейнеров','activity','sum',0],
        'items_used'=>['Использований предмета','activity','sum',0]
    ];
    public const DERIVED=[
        'kdr'=>['Убийств / смертей','combat',2],
        'total_kills'=>['Всего убийств','combat',0],
        'blocks_changed'=>['Всего изменено блоков','building',0],
        'distance_km'=>['Пройдено километров','movement',3],
        'playtime_minutes'=>['Время игры, минут','activity',2],
        'playtime_hours'=>['Время игры, часов','activity',2],
        'projectile_accuracy'=>['Попадания снарядами, %','combat',2],
        'blocks_per_hour'=>['Сломано блоков за час','building',2],
        'kills_per_hour'=>['Убийств за час','combat',2]
    ];
    public const CATEGORIES=['building'=>'Строительство и добыча','combat'=>'Бои','deaths'=>'Причины смерти','items'=>'Предметы','movement'=>'Перемещение','activity'=>'Активность','records'=>'Рекорды'];
    public const DIMENSIONS=['block_break','block_place','item_pickup','item_drop','item_craft','item_consume','mob_kill'];
    public static function valid(string $key): bool{
        if(isset(self::METRICS[$key])){ return true; }
        $parts=explode(':',$key,2);
        return count($parts)===2 && in_array($parts[0],self::DIMENSIONS,true) && preg_match('~^[a-z0-9_][a-z0-9_:/.\-]{0,119}$~D',$parts[1])===1;
    }
    public static function label(string $key): string{ return self::METRICS[$key][0] ?? self::DERIVED[$key][0] ?? $key; }
    public static function category(string $key): string{ return self::METRICS[$key][1] ?? self::DERIVED[$key][1] ?? 'activity'; }
    public static function maximum(string $key): bool{ return (self::METRICS[$key][2] ?? 'sum')==='max'; }
    public static function decimals(string $key): int{ return self::METRICS[$key][3] ?? self::DERIVED[$key][2] ?? 0; }
    public static function derived(string $key,\Closure $get): ?float{
        return match($key){
            'kdr'=>$get('kills')/max(1,$get('deaths')),
            'total_kills'=>$get('kills')+$get('mob_kills'),
            'blocks_changed'=>$get('blocks_broken')+$get('blocks_placed'),
            'distance_km'=>$get('distance_travelled')/1000,
            'playtime_minutes'=>$get('playtime_seconds')/60,
            'playtime_hours'=>$get('playtime_seconds')/3600,
            'projectile_accuracy'=>min(100,100*$get('projectile_hits')/max(1,$get('projectiles_shot'))),
            'blocks_per_hour'=>$get('playtime_seconds')>0 ? $get('blocks_broken')*3600/$get('playtime_seconds') : 0.0,
            'kills_per_hour'=>$get('playtime_seconds')>0 ? $get('kills')*3600/$get('playtime_seconds') : 0.0,
            default=>null
        };
    }
}
