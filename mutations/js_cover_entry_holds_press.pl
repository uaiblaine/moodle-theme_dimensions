# Let a press on a cover control that arrives before the film has loaded go through: it reaches a
# button nothing listens to yet, and the reader's first press does nothing.
s/\n    event\.stopImmediatePropagation\(\);//;
