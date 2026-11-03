# Unchecking the last box writes the empty list, which the server reads as every option.
s/if \(!next\.length\) \{\n        return kept;\n    \}\n//;
