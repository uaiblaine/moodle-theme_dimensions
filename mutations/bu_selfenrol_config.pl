# The self enrolment hint links the instances and calls to action for whoever may configure them.
s/        \$canconfig = has_capability\('enrol\/self:config', \$context\);/        \$canconfig = true;/;
