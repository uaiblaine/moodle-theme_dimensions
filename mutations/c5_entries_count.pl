# Count every event as a single one, a series included.
s/'count' => \$repeats \? max\(1, \$counts\[\(int\) \$repeats->get_id\(\)\] \?\? 0\) : 1,/'count' => 1,/;
