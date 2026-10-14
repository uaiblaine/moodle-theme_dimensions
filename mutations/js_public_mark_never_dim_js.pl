# Mark the body with the logged-in client's dim-js. The visitor's page would then claim a module it
# does not run, and every rule written for that module would apply to a visitor.
s/^const BODY_CLASS = 'dim-public-js';$/const BODY_CLASS = 'dim-js';/m;
