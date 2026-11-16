# Mask a value whose length is not the pattern's: slots are filled from the wrong characters.
s/if \(count\(\$characters\) !== \$slots\)/if (false)/;
