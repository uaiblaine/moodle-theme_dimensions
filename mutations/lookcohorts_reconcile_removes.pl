# The reconcile task leaves a member added behind the theme's back.
s/array_merge\(array_diff\(\$chose, \$members\), array_diff\(\$members, \$chose\)\)/array_diff(\$chose, \$members)/;
