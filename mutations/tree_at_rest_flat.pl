# Draw a visitor's flat tree from the narrowed population again: choosing one child, or loading
# its address, leaves the tree naming that child alone, so its sibling is no longer a choice and
# the only way to it is clearing the filter first. Anchored on the flat half of the condition.
s/ \|\| \$criteria->withposters\)/)/;
