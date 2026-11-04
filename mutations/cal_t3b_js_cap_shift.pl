# Drop the first month past the cap without telling the scroller the first day moved.
s/            shift -= monthDays\(String\(months\.shift\(\)\)\)\.length;/            months.shift();/;
