# A day view asks for its day's month grid.
s/if \(span\.view === 'day'\) \{\n        return \[span\.day\];/if (span.view === 'day') {\n        return gridWeeks(span.day.slice(0, 7), firstday).flat();/;
