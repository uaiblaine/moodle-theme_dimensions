# Leave the grouped A-Z order out of every address: a reload, the pager or "Clear" opens the panels newest first.
s/return \$this->psort === self::SORT_NAME \? \[self::PARAM_PSORT => self::SORT_NAME\] : \[\];/return [];/;
