# Let a box press close the open popover: after a start the calendar is in the end's mode, and a
# reader pressing the end box to say so would lose the start they had just described.
s/if \(isOpen\(\)\) \{\n([ \t]+)setMode\(bound\);/if (isOpen()) {\n$1close(true);/;
