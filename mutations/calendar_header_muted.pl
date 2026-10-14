# Colour the calendar's weekday headers with the subtle grey again: 2.96:1 on a white card, which
# fails AA for text of any size.
s/(th\.header \{\n            color: var\()--text-muted\)/${1}--text-subtle)/;
