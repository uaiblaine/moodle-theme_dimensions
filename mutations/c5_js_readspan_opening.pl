# Fall back to the month's 1st, not its opening day (security read F2).
s/weekStart\(valid \? week : openingOf\(\{view: 'month', month: month\}, today\), firstday\)/weekStart(valid ? week : `\${month}-01`, firstday)/;
