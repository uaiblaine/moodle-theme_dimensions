# Hand a cache a negative lifetime.
s/return max\(0, \(int\) \$stored\);/return (int) \$stored;/;
