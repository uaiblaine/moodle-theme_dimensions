# Read the category flag alone: a flavour flagged with no category, which Boost Union applies nowhere, is built.
s/\$bycategory = !empty\(\$flavour->applytocategories\) && !empty\(json_decode\(\(string\) \$flavour->applytocategories_ids\)\);/\$bycategory = !empty(\$flavour->applytocategories);/;
