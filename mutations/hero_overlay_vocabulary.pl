# Read the overlay with a plain cast instead of comparing it with the vocabulary first.
# Every value that is not an overlay - the empty string a select hands back for an unknown choice,
# a word, a decimal - then becomes 0, which is the VALID "Transparent": a category is stored
# with no colour over its photograph instead of the submission being refused.
s/        \$submitted = \$data->overlay \?\? self::DEFAULT_OVERLAY;\n        \$overlay = self::overlay\(\$submitted\);\n        if \(\$overlay === null\) \{/        \$submitted = \$data->overlay ?? self::DEFAULT_OVERLAY;\n        \$overlay = (int) \$submitted;\n        if (!in_array(\$overlay, self::OVERLAYS, true)) {/;
