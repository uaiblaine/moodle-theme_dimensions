# Serve the calendar page to the guest account instead of core's calendar.
s/        if \(isguestuser\(\)\) \{\n            return new self\(self::core_calendar\(\), null\);/        if (false) {\n            return new self(self::core_calendar(), null);/;
