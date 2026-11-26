# A rating reads the rows again and leaves the band stale.
s/run\(\{\}, true\);/run({}, false);/;
