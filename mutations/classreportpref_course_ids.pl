# Any positive id is kept, a forged one and another course's included.
s/return \$kept === \[\] \? null : \$kept;/return \$ids === [] ? null : \$ids;/;
