# Give every reader of the layer the edit switch, whatever core lets them edit.
s/return \$page->user_allowed_editing\(\);/return true;/;
