# The renderer stops handing core's context to the month block: the range fetches the month's events again.
s/blocks::month\(\$now, \$context\)/blocks::month(\$now)/;
