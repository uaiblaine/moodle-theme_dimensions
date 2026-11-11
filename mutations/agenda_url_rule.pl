# The agenda's address bypasses the blocks' http(s) rule.
s/return blocks::item_url\(\$item, \$courses\);/return \$item['url'] !== '' ? \$item['url'] : blocks::item_url(\$item, \$courses);/;
