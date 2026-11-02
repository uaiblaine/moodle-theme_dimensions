# Draw the blocks whatever the setting says.
s/        if \(!\$enabled\) \{\n            return false;/        if (false) {\n            return false;/;
