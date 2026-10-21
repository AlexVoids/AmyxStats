# Плейсхолдеры AmyxStats

Готовых имён: **531**. Показателей: **66 сохраняемых + 9 вычисляемых**. Полный машинный список: [placeholders.json](placeholders.json).

ScoreHud: `{amyxstats.my.blocks_broken}`, `{amyxstats.global.kills}`. Префикс `my` означает игрока, для которого строится строка; `global` — весь сервер.

Для API замены текста также принимаются `%amyxstats_my_blocks_broken%` и `{amyxstats_my_blocks_broken}`. Сторонний плагин должен вызывать API AmyxStats или иметь совместимый адаптер; произвольные плагины автоматически не изменяются.

| Ключ | Русское название | Личный плейсхолдер | Общий плейсхолдер | Общий расчёт |
| --- | --- | --- | --- | --- |
| `blocks_broken` | Сломано блоков | `{amyxstats.my.blocks_broken}` | `{amyxstats.global.blocks_broken}` | Сумма по игрокам |
| `blocks_placed` | Поставлено блоков | `{amyxstats.my.blocks_placed}` | `{amyxstats.global.blocks_placed}` | Сумма по игрокам |
| `ores_mined` | Добыто руд | `{amyxstats.my.ores_mined}` | `{amyxstats.global.ores_mined}` | Сумма по игрокам |
| `logs_broken` | Срублено брёвен | `{amyxstats.my.logs_broken}` | `{amyxstats.global.logs_broken}` | Сумма по игрокам |
| `leaves_broken` | Сломано листвы | `{amyxstats.my.leaves_broken}` | `{amyxstats.global.leaves_broken}` | Сумма по игрокам |
| `crops_harvested` | Собрано зрелых культур | `{amyxstats.my.crops_harvested}` | `{amyxstats.global.crops_harvested}` | Сумма по игрокам |
| `kills` | Убийств игроков | `{amyxstats.my.kills}` | `{amyxstats.global.kills}` | Сумма по игрокам |
| `deaths` | Смертей | `{amyxstats.my.deaths}` | `{amyxstats.global.deaths}` | Сумма по игрокам |
| `mob_kills` | Убийств мобов | `{amyxstats.my.mob_kills}` | `{amyxstats.global.mob_kills}` | Сумма по игрокам |
| `kills_melee` | Убийств в ближнем бою | `{amyxstats.my.kills_melee}` | `{amyxstats.global.kills_melee}` | Сумма по игрокам |
| `kills_projectile` | Убийств снарядами | `{amyxstats.my.kills_projectile}` | `{amyxstats.global.kills_projectile}` | Сумма по игрокам |
| `kill_streak` | Текущая серия убийств | `{amyxstats.my.kill_streak}` | `{amyxstats.global.kill_streak}` | Сумма по игрокам |
| `best_kill_streak` | Лучшая серия убийств | `{amyxstats.my.best_kill_streak}` | `{amyxstats.global.best_kill_streak}` | Максимум среди игроков |
| `deaths_pvp` | Смертей от игроков | `{amyxstats.my.deaths_pvp}` | `{amyxstats.global.deaths_pvp}` | Сумма по игрокам |
| `deaths_fall` | Смертей от падения | `{amyxstats.my.deaths_fall}` | `{amyxstats.global.deaths_fall}` | Сумма по игрокам |
| `deaths_fire` | Смертей от огня | `{amyxstats.my.deaths_fire}` | `{amyxstats.global.deaths_fire}` | Сумма по игрокам |
| `deaths_lava` | Смертей от лавы | `{amyxstats.my.deaths_lava}` | `{amyxstats.global.deaths_lava}` | Сумма по игрокам |
| `deaths_drowning` | Смертей от утопления | `{amyxstats.my.deaths_drowning}` | `{amyxstats.global.deaths_drowning}` | Сумма по игрокам |
| `deaths_void` | Смертей в пустоте | `{amyxstats.my.deaths_void}` | `{amyxstats.global.deaths_void}` | Сумма по игрокам |
| `deaths_explosion` | Смертей от взрыва | `{amyxstats.my.deaths_explosion}` | `{amyxstats.global.deaths_explosion}` | Сумма по игрокам |
| `deaths_other` | Прочих смертей | `{amyxstats.my.deaths_other}` | `{amyxstats.global.deaths_other}` | Сумма по игрокам |
| `damage_dealt` | Нанесено урона | `{amyxstats.my.damage_dealt}` | `{amyxstats.global.damage_dealt}` | Сумма по игрокам |
| `damage_taken` | Получено урона | `{amyxstats.my.damage_taken}` | `{amyxstats.global.damage_taken}` | Сумма по игрокам |
| `damage_pvp_dealt` | Нанесено урона игрокам | `{amyxstats.my.damage_pvp_dealt}` | `{amyxstats.global.damage_pvp_dealt}` | Сумма по игрокам |
| `damage_pvp_taken` | Получено урона от игроков | `{amyxstats.my.damage_pvp_taken}` | `{amyxstats.global.damage_pvp_taken}` | Сумма по игрокам |
| `hits_dealt` | Успешных атак | `{amyxstats.my.hits_dealt}` | `{amyxstats.global.hits_dealt}` | Сумма по игрокам |
| `hits_taken` | Получено ударов | `{amyxstats.my.hits_taken}` | `{amyxstats.global.hits_taken}` | Сумма по игрокам |
| `projectiles_shot` | Выпущено снарядов | `{amyxstats.my.projectiles_shot}` | `{amyxstats.global.projectiles_shot}` | Сумма по игрокам |
| `arrows_shot` | Выпущено стрел | `{amyxstats.my.arrows_shot}` | `{amyxstats.global.arrows_shot}` | Сумма по игрокам |
| `projectile_hits` | Попаданий снарядами | `{amyxstats.my.projectile_hits}` | `{amyxstats.global.projectile_hits}` | Сумма по игрокам |
| `health_regenerated` | Восстановлено здоровья | `{amyxstats.my.health_regenerated}` | `{amyxstats.global.health_regenerated}` | Сумма по игрокам |
| `items_crafted` | Создано предметов | `{amyxstats.my.items_crafted}` | `{amyxstats.global.items_crafted}` | Сумма по игрокам |
| `crafts` | Выполнено крафтов | `{amyxstats.my.crafts}` | `{amyxstats.global.crafts}` | Сумма по игрокам |
| `items_picked_up` | Подобрано предметов | `{amyxstats.my.items_picked_up}` | `{amyxstats.global.items_picked_up}` | Сумма по игрокам |
| `items_dropped` | Выброшено предметов | `{amyxstats.my.items_dropped}` | `{amyxstats.global.items_dropped}` | Сумма по игрокам |
| `items_consumed` | Использовано расходников | `{amyxstats.my.items_consumed}` | `{amyxstats.global.items_consumed}` | Сумма по игрокам |
| `food_eaten` | Съедено еды | `{amyxstats.my.food_eaten}` | `{amyxstats.global.food_eaten}` | Сумма по игрокам |
| `potions_drunk` | Выпито зелий | `{amyxstats.my.potions_drunk}` | `{amyxstats.global.potions_drunk}` | Сумма по игрокам |
| `items_enchanted` | Зачаровано предметов | `{amyxstats.my.items_enchanted}` | `{amyxstats.global.items_enchanted}` | Сумма по игрокам |
| `enchantments_applied` | Улучшено зачарований | `{amyxstats.my.enchantments_applied}` | `{amyxstats.global.enchantments_applied}` | Сумма по игрокам |
| `items_repaired` | Починено на наковальне | `{amyxstats.my.items_repaired}` | `{amyxstats.global.items_repaired}` | Сумма по игрокам |
| `items_renamed` | Переименовано предметов | `{amyxstats.my.items_renamed}` | `{amyxstats.global.items_renamed}` | Сумма по игрокам |
| `distance_travelled` | Пройдено метров | `{amyxstats.my.distance_travelled}` | `{amyxstats.global.distance_travelled}` | Сумма по игрокам |
| `distance_walked` | Пройдено шагом, м | `{amyxstats.my.distance_walked}` | `{amyxstats.global.distance_walked}` | Сумма по игрокам |
| `distance_sprinted` | Пройдено бегом, м | `{amyxstats.my.distance_sprinted}` | `{amyxstats.global.distance_sprinted}` | Сумма по игрокам |
| `distance_sneaked` | Пройдено крадучись, м | `{amyxstats.my.distance_sneaked}` | `{amyxstats.global.distance_sneaked}` | Сумма по игрокам |
| `distance_swum` | Проплыто, м | `{amyxstats.my.distance_swum}` | `{amyxstats.global.distance_swum}` | Сумма по игрокам |
| `distance_flown` | Пролетено, м | `{amyxstats.my.distance_flown}` | `{amyxstats.global.distance_flown}` | Сумма по игрокам |
| `distance_glided` | Пройдено на элитрах, м | `{amyxstats.my.distance_glided}` | `{amyxstats.global.distance_glided}` | Сумма по игрокам |
| `distance_teleported` | Перемещено телепортами, м | `{amyxstats.my.distance_teleported}` | `{amyxstats.global.distance_teleported}` | Сумма по игрокам |
| `jumps` | Прыжков | `{amyxstats.my.jumps}` | `{amyxstats.global.jumps}` | Сумма по игрокам |
| `teleports` | Телепортаций | `{amyxstats.my.teleports}` | `{amyxstats.global.teleports}` | Сумма по игрокам |
| `world_changes` | Переходов между мирами | `{amyxstats.my.world_changes}` | `{amyxstats.global.world_changes}` | Сумма по игрокам |
| `sprint_starts` | Начато пробежек | `{amyxstats.my.sprint_starts}` | `{amyxstats.global.sprint_starts}` | Сумма по игрокам |
| `sneak_starts` | Начато приседаний | `{amyxstats.my.sneak_starts}` | `{amyxstats.global.sneak_starts}` | Сумма по игрокам |
| `joins` | Входов на сервер | `{amyxstats.my.joins}` | `{amyxstats.global.joins}` | Сумма по игрокам |
| `days_played` | Игровых дней UTC | `{amyxstats.my.days_played}` | `{amyxstats.global.days_played}` | Сумма по игрокам |
| `playtime_seconds` | Время игры, секунд | `{amyxstats.my.playtime_seconds}` | `{amyxstats.global.playtime_seconds}` | Сумма по игрокам |
| `longest_session_seconds` | Самая долгая сессия, с | `{amyxstats.my.longest_session_seconds}` | `{amyxstats.global.longest_session_seconds}` | Максимум среди игроков |
| `chat_messages` | Сообщений в чате | `{amyxstats.my.chat_messages}` | `{amyxstats.global.chat_messages}` | Сумма по игрокам |
| `commands_used` | Введено команд | `{amyxstats.my.commands_used}` | `{amyxstats.global.commands_used}` | Сумма по игрокам |
| `beds_entered` | Использовано кроватей | `{amyxstats.my.beds_entered}` | `{amyxstats.global.beds_entered}` | Сумма по игрокам |
| `buckets_filled` | Наполнено вёдер | `{amyxstats.my.buckets_filled}` | `{amyxstats.global.buckets_filled}` | Сумма по игрокам |
| `buckets_emptied` | Опустошено вёдер | `{amyxstats.my.buckets_emptied}` | `{amyxstats.global.buckets_emptied}` | Сумма по игрокам |
| `containers_opened` | Открыто контейнеров | `{amyxstats.my.containers_opened}` | `{amyxstats.global.containers_opened}` | Сумма по игрокам |
| `items_used` | Использований предмета | `{amyxstats.my.items_used}` | `{amyxstats.global.items_used}` | Сумма по игрокам |
| `kdr` | Убийств / смертей | `{amyxstats.my.kdr}` | `{amyxstats.global.kdr}` | Из общих исходных счётчиков |
| `total_kills` | Всего убийств | `{amyxstats.my.total_kills}` | `{amyxstats.global.total_kills}` | Из общих исходных счётчиков |
| `blocks_changed` | Всего изменено блоков | `{amyxstats.my.blocks_changed}` | `{amyxstats.global.blocks_changed}` | Из общих исходных счётчиков |
| `distance_km` | Пройдено километров | `{amyxstats.my.distance_km}` | `{amyxstats.global.distance_km}` | Из общих исходных счётчиков |
| `playtime_minutes` | Время игры, минут | `{amyxstats.my.playtime_minutes}` | `{amyxstats.global.playtime_minutes}` | Из общих исходных счётчиков |
| `playtime_hours` | Время игры, часов | `{amyxstats.my.playtime_hours}` | `{amyxstats.global.playtime_hours}` | Из общих исходных счётчиков |
| `projectile_accuracy` | Попадания снарядами, % | `{amyxstats.my.projectile_accuracy}` | `{amyxstats.global.projectile_accuracy}` | Из общих исходных счётчиков |
| `blocks_per_hour` | Сломано блоков за час | `{amyxstats.my.blocks_per_hour}` | `{amyxstats.global.blocks_per_hour}` | Из общих исходных счётчиков |
| `kills_per_hour` | Убийств за час | `{amyxstats.my.kills_per_hour}` | `{amyxstats.global.kills_per_hour}` | Из общих исходных счётчиков |

Для каждого ключа в таблице доступны суффиксы `_formatted` (разделение тысяч пробелом) и `_compact` (K/M/B/T). Для каждого сохраняемого показателя есть личный суффикс `_rank`, например `{amyxstats.my.blocks_broken_rank}`. Ранг при равных значениях одинаковый: 1, 1, 3. В меню топа равные значения упорядочены по нику, затем UUID. Вычисляемые коэффициенты в топ не входят.

## Игрок и сервер

- `{amyxstats.my.name}`
- `{amyxstats.my.uuid}`
- `{amyxstats.my.first_seen}`
- `{amyxstats.my.last_seen}`
- `{amyxstats.my.online}`
- `{amyxstats.my.session_seconds}`
- `{amyxstats.my.session_formatted}`
- `{amyxstats.my.playtime_formatted}`
- `{amyxstats.global.unique_players}`
- `{amyxstats.global.online_players}`
- `{amyxstats.global.max_players}`
- `{amyxstats.global.uptime_seconds}`
- `{amyxstats.global.uptime_formatted}`
- `{amyxstats.global.playtime_formatted}`
- `{amyxstats.global.tps}`

## По отдельным блокам, предметам и мобам

Замените идентификатор на имя Minecraft без `minecraft:`. Например, `diamond_ore`, `oak_log`, `diamond`, `zombie`. Точное имя зависит от версии ядра. Пространства имён сторонних предметов сохраняются. Каждая запись имеет личную и общую форму:

- `{amyxstats.my.block_break:stone}` / `{amyxstats.global.block_break:stone}`
- `{amyxstats.my.block_place:stone}` / `{amyxstats.global.block_place:stone}`
- `{amyxstats.my.item_pickup:stone}` / `{amyxstats.global.item_pickup:stone}`
- `{amyxstats.my.item_drop:stone}` / `{amyxstats.global.item_drop:stone}`
- `{amyxstats.my.item_craft:stone}` / `{amyxstats.global.item_craft:stone}`
- `{amyxstats.my.item_consume:stone}` / `{amyxstats.global.item_consume:stone}`
- `{amyxstats.my.mob_kill:stone}` / `{amyxstats.global.mob_kill:stone}`

Для измерений также работают `_formatted`, `_compact`, личный `_rank` и `/stat top block_break:diamond_ore`. Счётчики не создаются от просмотра несуществующего идентификатора: он даёт 0. Неизвестные плейсхолдеры API возвращает как `null`, а замена текста сохраняет исходный текст.

K/D = убийства игроков / max(1, смерти). Общий K/D вычисляется из общих убийств и смертей. Точность = попадания снарядами / выпущенные снаряды × 100, максимум 100%. `days_played` — разные даты UTC; общее значение — сумма игровых дней всех игроков. Время игры включает AFK; текущая сессия не включает время отключённого сервера. `kill_streak` на сервере — сумма текущих серий, `best_kill_streak` — лучший результат одного игрока.
