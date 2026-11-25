# Every viewer's cells carry the grading dialogue's hooks, a viewer who may not rate included.
s/\$hooks = has_capability\(classreport::GRADE, \$context\);/\$hooks = true;/;
