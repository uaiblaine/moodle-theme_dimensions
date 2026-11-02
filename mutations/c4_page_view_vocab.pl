# Read any view the address names as the week.
s/return self::scalar_param\(self::PARAM_VIEW\) === self::VIEW_WEEK \? self::VIEW_WEEK : self::VIEW_MONTH;/return self::scalar_param(self::PARAM_VIEW) !== '' ? self::VIEW_WEEK : self::VIEW_MONTH;/;
