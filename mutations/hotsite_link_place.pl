# Stop moving the model select and its description back over the link, which lands the
# link ABOVE the field it belongs to - the placement the QuickForm insert API gives by
# default, and the reason the three-call idiom is there at all.
s/        \$mform->insertElementBefore\(\$mform->removeElement\(\$modeloelement, false\), self::LINK_ELEMENT\);\n        \$descriptionelement = \$modeloelement \. '_static';\n        if \(\$mform->elementExists\(\$descriptionelement\)\) \{\n            \$mform->insertElementBefore\(\$mform->removeElement\(\$descriptionelement, false\), self::LINK_ELEMENT\);\n        \}\n//;
