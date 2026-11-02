# Count only Sunday as the weekend.
s/return weekday === 0 \|\| weekday === 6;/return weekday === 0;/;
