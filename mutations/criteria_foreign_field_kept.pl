# Keep a field id that is no filter (the option bound still applies to the filters): a listing built without the
# services' allowlist then narrows by a custom field the reader is not shown.
s/\$value <= \(\$counts\[\$fieldid\] \?\? 0\)/(\$counts[\$fieldid] ?? PHP_INT_MAX) >= \$value/;
