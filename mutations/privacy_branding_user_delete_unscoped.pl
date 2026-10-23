# A deletion for a person clears every stamp of the approved categories, another person's included.
s/(\$DB->set_field\(branding::TABLE, 'usermodified', 0, \[\n\s+'categoryid' => \$context->instanceid,\n)\s+'usermodified' => \$userid,\n/$1/;
