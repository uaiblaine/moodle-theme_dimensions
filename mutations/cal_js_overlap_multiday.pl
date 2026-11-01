# Lay a multi-day item or an event course in the hour column.
s/        if \(entry\.bar \|\| isAllDay\(entry\.item, zone\)\) \{/        if (isAllDay(entry.item, zone)) {/;
