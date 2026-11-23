# Start the closing without the bound: a motion whose animationend never comes holds Behat for good.
s/(layer\.addEventListener\('animationend', ended\);)\n\s+window\.setTimeout\(finish, SETTLE\);/$1/;
