# Leave the grid able to hold every panel open at once, by emptying the loop that closes the
# others. The disclosure still opens, every card still works - what is gone
# is the whole rule, that one panel is on screen at a time.
s/        if \(details !== keep\) \{\n            details\.open = false;\n        \}\n//;
