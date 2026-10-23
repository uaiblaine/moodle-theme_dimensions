# A deletion for a whole category clears nothing.
s/(        \$DB->set_field\(branding::TABLE, 'usermodified', 0, \['categoryid' => \$context->instanceid\]\);\n)/        return;\n$1/;
