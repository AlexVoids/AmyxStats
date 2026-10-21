# API AmyxStats

В `plugin.yml` использующего плагина добавьте `depend: [AmyxStats]` либо `softdepend: [AmyxStats]` и проверяйте наличие и состояние плагина. Пример:

```php
use Amyx\Stats\Main;

$stats = $this->getServer()->getPluginManager()->getPlugin('AmyxStats');
if($stats instanceof Main && $stats->isEnabled()){
    $kills = $stats->getStatistic($player, 'kills'); // float
    $offline = $stats->getStatistic('AlexVoids', 'blocks_broken');
    $total = $stats->getGlobalStatistic('blocks_broken');
    $all = $stats->getStatistics($player); // массив ключ => float
    $top = $stats->getTop('kills', 10); // uuid, name, value
    $value = $stats->resolvePlaceholder('amyxstats.my.kdr', $player); // ?string
    $text = $stats->replacePlaceholders(
        'Убийств: {amyxstats.my.kills}; всего блоков: %amyxstats_global_blocks_broken%',
        $player
    );
    $tags = $stats->getPlaceholderNames(); // 531 каноническое имя
}
```

Аргумент игрока — объект `Player`, UUID или известный ник, в том числе офлайн. Отсутствующий показатель равен 0; `getStatistics()` для неизвестного офлайн-игрока возвращает пустой массив. `resolvePlaceholder()` для неизвестного тега возвращает `null`; `replacePlaceholders()` оставляет неизвестный тег в тексте.

API предназначен для главного потока PocketMine. Не обращайтесь к SQLite и объектам сервера из AsyncTask; если нужен асинхронный запрос наружу, сначала получите обычный массив или строку в главном потоке. Методы чтения не создают новую запись игрока и не увеличивают статистику.

Программное изменение внутренних `Profile` или массива сумм не является публичным контрактом. Сбор ведётся слушателями штатных событий. При интеграции других систем используйте их нормальные события игрока либо добавьте отдельный адаптер к источникам событий.
