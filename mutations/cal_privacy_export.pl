# Leave the calendar's stored state out of an export.
s/        if \(\$calendar !== null\) \{/        if (false) {/;
