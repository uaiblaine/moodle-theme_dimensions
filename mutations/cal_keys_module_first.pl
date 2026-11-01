# Classify by component before module: every activity event reads "other" (its component is mod_*).
s/        if \(\$event->get_course_module\(\)\) \{\n            return self::ACTIVITY;/        if (false) {\n            return self::ACTIVITY;/;
