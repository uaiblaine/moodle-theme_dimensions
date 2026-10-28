# Count the three adopted frames as the component's: More statuses, the custom fields, the sort and
# "Show as" stop being painted on the logged-in shell, with nothing drawing them instead.
s/ && !element\.closest\(SELECTORS\.adopted\);/;/;
