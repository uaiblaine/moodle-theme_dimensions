# Spare the vault the users argument whenever the user key is off: the viewer's overrides are lost.
s/'users' => \[self::USER, self::ACTIVITY, self::GROUP, self::OTHER\]/'users' => [self::USER]/;
