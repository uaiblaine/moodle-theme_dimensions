# Lay a whole-day item in the hour column.
s/        if \(entry\.bar \|\| isAllDay\(entry\.item, zone\)\) \{/        if (entry.bar) {/;
