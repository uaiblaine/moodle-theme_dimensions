# Ask core to move to a day outside its limits.
s/        if \(refusal\) \{\n/        if (refusal \&\& !refusal) {\n/;
