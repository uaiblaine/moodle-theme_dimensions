# Hand a cache a fixed lifetime instead of the half's own.
s/\. ', s-maxage=' \. \$shared\)/. ', s-maxage=86400')/;
