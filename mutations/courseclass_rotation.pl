# Keep a value for ever when no event announces a write.
s/\n            && \$now - \(int\) \(\$stored\[\x27built\x27\] \?\? 0\) < self::ROTATION//;
