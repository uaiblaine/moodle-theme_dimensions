# Widths are stored as posted.
s/max\(self::MIN_WIDTH, min\(self::MAX_WIDTH, \(int\) round\(\(float\) \$width\)\)\)/(int) round((float) \$width)/;
