# A second store would add a row beside the first instead of rewriting it.
s/\$id\n            \? data_controller::create\(\$id, null, \$field\)\n            :/false\n            ? data_controller::create(\$id, null, \$field)\n            :/;
