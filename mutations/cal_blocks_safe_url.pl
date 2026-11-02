# Let any address through to an href, a script's included.
s/return preg_match\('~\^https\?:\/\/~i', \$url\) === 1 \? \$url : \$fallback;/return \$url;/;
