# The entry ignores a site that closed guest login.
s/        if \(empty\(\$CFG->guestloginbutton\) && empty\(\$CFG->autologinguests\)\) \{\n            return false;\n        \}\n//;
