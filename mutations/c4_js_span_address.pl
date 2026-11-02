# Leave the month in a week's address.
s/\.filter\(\(\[name\]\) => name !== 'month' && name !== VIEW_PARAM && name !== WEEK_PARAM\);/.filter(([name]) => name !== VIEW_PARAM && name !== WEEK_PARAM);/;
