# Keep a parent current beside its active child: two destinations carry aria-current on Assign roles (D96).
s/\$node->isactive && !self::holds_active\(\$node\)/(bool) \$node->isactive/;
