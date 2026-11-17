# Never arm the row's client: the menus keep no keys and nothing moves into More.
s/^[ \t]*\$this->arm_navpills\(\$context->navpills\);\n//m;
