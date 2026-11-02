# Send every view of core's calendar, the default upcoming one included.
s/        if \(\$view !== .month. && \$view !== .day.\) \{/        if (false) {/;
