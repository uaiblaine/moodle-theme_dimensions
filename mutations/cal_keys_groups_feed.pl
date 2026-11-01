# Spare the vault the groups argument whenever the group key is off: a group override takes its activity's date away.
s/'groups' => \[self::GROUP, self::ACTIVITY, self::OTHER\]/'groups' => [self::GROUP]/;
