# Add the picker's options even where the picker is not installed, so core's file manager gets an array it was not given.
s/        if \(!self::available\(\$element\)\) \{\n            return \$options;\n        \}\n//;
