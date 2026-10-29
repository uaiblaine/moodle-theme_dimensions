# The reconcile task leaves out a person whose recorded choice is the look.
s/array_merge\(array_diff\(\$chose, \$members\), array_diff\(\$members, \$chose\)\)/array_diff(\$members, \$chose)/;
