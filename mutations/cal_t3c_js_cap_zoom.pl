# One cap for every zoom: the month zoom's is the days' and weeks'.
s/\(zoom === 'month' \? MONTH_CAP : LOADED_CAP\)/LOADED_CAP/;
