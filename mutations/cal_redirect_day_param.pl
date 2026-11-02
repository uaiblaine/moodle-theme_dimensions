# Send the day view without the day, so the drawer stays closed.
s/        if \(\$view === 'day'\) \{/        if (false) {/;
