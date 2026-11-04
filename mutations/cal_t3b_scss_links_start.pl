# Place a connector's piece from the left, so a right-to-left page does not mirror it.
s/        inset-inline-start: var\(--dim-cal-link-start, 0\);\n        background: var\(--dim-cal-trail-link\);/        left: var(--dim-cal-link-start, 0);\n        background: var(--dim-cal-trail-link);/;
