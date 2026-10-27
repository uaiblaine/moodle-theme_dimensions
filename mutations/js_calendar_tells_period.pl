# Stop telling the calendar component the period the state holds: a quick option, Back or a removed
# chip would move the listing while the boxes kept naming the old period.
s/^    tellCalendar\(root\.querySelector\(SELECTORS\.daterange\), state\);\n//m;
