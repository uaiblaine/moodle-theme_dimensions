# The categories a person last saved are never found.
s/(function get_contexts_for_userid\(int \$userid\): contextlist \{\n\s+\$contextlist = new contextlist\(\);\n)/$1        return \$contextlist;\n/;
