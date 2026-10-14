# Spell a panel's call to action and its "see all as a list" link with the logged-in shell,
# whichever shell is drawing: on the anonymous page both then lead a visitor to the login form.
s/return links::showcase\(\$categoryid, \$params, \$anonymous\)->out\(false\);/return links::showcase(\$categoryid, \$params, false)->out(false);/;
