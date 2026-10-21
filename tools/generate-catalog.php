<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Amyx/Stats/stat/MetricCatalog.php';
require dirname(__DIR__).'/src/Amyx/Stats/placeholder/PlaceholderResolver.php';
use Amyx\Stats\stat\MetricCatalog as C;
use Amyx\Stats\placeholder\PlaceholderResolver as P;
$root=dirname(__DIR__);@mkdir($root.'/docs',0777,true);
$labels="# Названия показателей в меню. Можно менять без пересборки плагина.\n";
$doc="# Плейсхолдеры AmyxStats\n\nГотовых имён: **".count(P::tags())."**. Показателей: **".count(C::METRICS)." сохраняемых + ".count(C::DERIVED)." вычисляемых**. Полный машинный список: [placeholders.json](placeholders.json).\n\nScoreHud: `{amyxstats.my.blocks_broken}`, `{amyxstats.global.kills}`. Префикс `my` означает игрока, для которого строится строка; `global` — весь сервер.\n\nДля API замены текста также принимаются `%amyxstats_my_blocks_broken%` и `{amyxstats_my_blocks_broken}`. Сторонний плагин должен вызывать API AmyxStats или иметь совместимый адаптер; произвольные плагины автоматически не изменяются.\n\n| Ключ | Русское название | Личный плейсхолдер | Общий плейсхолдер | Общий расчёт |\n| --- | --- | --- | --- | --- |\n";
foreach(C::METRICS+C::DERIVED as $key=>$info){
    $labels.=$key.': '.json_encode($info[0],JSON_UNESCAPED_UNICODE)."\n";
    $aggregation=isset(C::DERIVED[$key]) ? 'Из общих исходных счётчиков' : (C::maximum($key) ? 'Максимум среди игроков' : 'Сумма по игрокам');
    $doc.='| `'.$key.'` | '.$info[0].' | `{amyxstats.my.'.$key.'}` | `{amyxstats.global.'.$key.'}` | '.$aggregation." |\n";
}
$doc.="\nДля каждого ключа в таблице доступны суффиксы `_formatted` (разделение тысяч пробелом) и `_compact` (K/M/B/T). Для каждого сохраняемого показателя есть личный суффикс `_rank`, например `{amyxstats.my.blocks_broken_rank}`. Ранг при равных значениях одинаковый: 1, 1, 3. В меню топа равные значения упорядочены по нику, затем UUID. Вычисляемые коэффициенты в топ не входят.\n\n## Игрок и сервер\n\n";
foreach(['my'=>['name','uuid','first_seen','last_seen','online','session_seconds','session_formatted','playtime_formatted'],'global'=>['unique_players','online_players','max_players','uptime_seconds','uptime_formatted','playtime_formatted','tps']] as $scope=>$keys){ foreach($keys as $key){ $doc.='- `{amyxstats.'.$scope.'.'.$key."}`\n"; } }
$doc.="\n## По отдельным блокам, предметам и мобам\n\nЗамените идентификатор на имя Minecraft без `minecraft:`. Например, `diamond_ore`, `oak_log`, `diamond`, `zombie`. Точное имя зависит от версии ядра. Пространства имён сторонних предметов сохраняются. Каждая запись имеет личную и общую форму:\n\n";
foreach(C::DIMENSIONS as $dimension){ $doc.='- `{amyxstats.my.'.$dimension.':stone}` / `{amyxstats.global.'.$dimension.":stone}`\n"; }
$doc.="\nДля измерений также работают `_formatted`, `_compact`, личный `_rank` и `/stat top block_break:diamond_ore`. Счётчики не создаются от просмотра несуществующего идентификатора: он даёт 0. Неизвестные плейсхолдеры API возвращает как `null`, а замена текста сохраняет исходный текст.\n\nK/D = убийства игроков / max(1, смерти). Общий K/D вычисляется из общих убийств и смертей. Точность = попадания снарядами / выпущенные снаряды × 100, максимум 100%. `days_played` — разные даты UTC; общее значение — сумма игровых дней всех игроков. Время игры включает AFK; текущая сессия не включает время отключённого сервера. `kill_streak` на сервере — сумма текущих серий, `best_kill_streak` — лучший результат одного игрока.\n";
file_put_contents($root.'/resources/labels.yml',$labels);
file_put_contents($root.'/docs/PLACEHOLDERS.md',$doc);
file_put_contents($root.'/docs/placeholders.json',json_encode(P::tags(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo count(C::METRICS).' stored, '.count(C::DERIVED).' derived, '.count(P::tags())." canonical placeholders\n";
