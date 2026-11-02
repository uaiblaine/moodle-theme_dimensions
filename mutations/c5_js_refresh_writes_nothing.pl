# Write the address on a refresh.
s/return request\(showing\.current, state, 'none', selected, false\);/return request(showing.current, state, 'replace', selected, false);/;
