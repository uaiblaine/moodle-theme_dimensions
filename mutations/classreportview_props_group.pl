# The props always name all participants, not the page's group.
s/'groupid' => self::page_group\(get_course\(\(int\) \$this->context->instanceid\)\),/'groupid' => 0,/;
