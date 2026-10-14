# Draw the tree in the grouped organisation too. The accordion below it already names every child and
# holds its courses, so the page asks one question twice -
# and the tree's own links carry a criterion that organisation drops, so pressing one narrows
# nothing and marks nothing. Anchored on the condition inside the method's opening line, so that
# neither the other readings of is_grouped() in this file nor anything later inside tree() is touched.
s/(private function tree\(\): array \{\n[ \t]*if \()\$this->criteria->is_grouped\(\)(\) \{)/$1false$2/;
