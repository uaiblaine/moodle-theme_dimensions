# Count only the builder's exception as a failure: a child killed by a fatal error (255) is retried every minute.
s/if \(!in_array\(\$code, self::ANSWERS, true\)\)/if (\$code === 1)/;
