# A long choice would overflow the field column.
s/            if \(\\core_text::strlen\(\$next\) > self::MAX_LENGTH\) \{\n                break;\n            \}\n//;
