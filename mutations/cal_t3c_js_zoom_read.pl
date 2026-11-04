# Read any zoom the address names, not only week or month.
s/\(value === 'week' \|\| value === 'month' \? value : 'day'\)/(value as Zoom)/;
