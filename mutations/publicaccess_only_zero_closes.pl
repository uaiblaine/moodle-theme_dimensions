# Read the site's switch as "on only when it says 1": a stored 'no' or an empty string then closes the
# public surface, though only an explicit 0 may.
s/return !\(\$stored !== false && \(string\) \$stored === '0'\);/return \$stored === false || (string) \$stored === '1';/;
