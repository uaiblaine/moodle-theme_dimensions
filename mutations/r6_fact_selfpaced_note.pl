# Drop "at your own pace" from under a self-paced course's format.
s/\$note = \$selfpaced \? get_string\('hotsite_selfpacednote', self::COMPONENT\) : null;/\$note = null;/;
