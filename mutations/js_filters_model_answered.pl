# Ignore the answer when it says the panel has no group.
s/        next\.filters = Object\.assign\(\{\}, next\.filters, \{available: answer\.filtergroups\}\);/        next.filters = next.filters;/;
