# Store the map whatever its length: past 1,333 characters core's column refuses the write.
s/while \(\$map && strlen\(self::json\(\$map\)\) > self::MAX_LENGTH\) \{/while (false) {/;
