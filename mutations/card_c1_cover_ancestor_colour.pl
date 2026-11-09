# Read no category as holding a pair (own or inherited): every cover is painted in the primary.
s/\(int\) \(\$payload\[.colourfrom.\] \?\? 0\) > 0 \?/(int) (\$payload['colourfrom'] ?? 0) < 0 ?/;
