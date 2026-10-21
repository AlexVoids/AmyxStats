# ScoreHud

Установите ScoreHud отдельно. AmyxStats использует API `Ifera\ScoreHud\event\TagsResolveEvent`, `PlayerTagsUpdateEvent` и `ScoreTag`. По умолчанию изменившиеся значения уже запрошенных тегов обновляются каждые 2 секунды; все 531 тега каждому игроку постоянно не отправляются.

В `plugin_data/ScoreHud/scorehud.yml` замените строки нужной доски. Пример `default-board` для стандартного конфига ScoreHud 8:

```yaml
default-board:
  - "§bСтатистика §f{amyxstats.my.name}"
  - "§fУбийств: §a{amyxstats.my.kills}"
  - "§fСмертей: §c{amyxstats.my.deaths}"
  - "§fK/D: §e{amyxstats.my.kdr}"
  - "§fСломано: §a{amyxstats.my.blocks_broken_compact}"
  - "§fПоставлено: §a{amyxstats.my.blocks_placed_compact}"
  - "§fОнлайн: §b{amyxstats.my.playtime_formatted}"
  - "§fМесто по убийствам: §6{amyxstats.my.kills_rank}"
  - "§fВсего блоков: §a{amyxstats.global.blocks_broken_compact}"
  - "§fВсего игроков: §b{amyxstats.global.unique_players}"
```

Если в ScoreHud включены отдельные доски для миров, аналогичные строки нужно добавить в доску соответствующего мира. Ограничение числа строк определяется ScoreHud и Minecraft. После настройки полностью перезапустите сервер.

`{amyxstats.my.*}` относится к игроку, которому показывается доска. `{amyxstats.global.*}` одинаков для всех игроков. API не подставляет статистику произвольного другого игрока в личный тег; для отдельного офлайн-ника используйте PHP API.

В тестах используются официальные классы событий и тегов ScoreHud 8 из репозитория Ifera/ScoreHud. Проверяются разрешение тега, отправка изменённого значения, отсутствие повторной отправки неизменившегося значения и очистка подписок. Полный рендер доски в Bedrock-клиенте отдельно не проверялся.
