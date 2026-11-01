# Serve the calendar page to a visitor instead of sending them to log in.
s/        if \(!isloggedin\(\)\) \{\n            \$SESSION->wantsurl/        if (false) {\n            \$SESSION->wantsurl/;
