# Any "mine" value but the empty one is read as on.
s/return self::scalar_param\(self::PARAM_MINE\) === .1.;/return self::scalar_param(self::PARAM_MINE) !== '';/;
